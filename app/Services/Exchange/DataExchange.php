<?php

namespace App\Services\Exchange;

use App\Models\Import;
use App\Services\Exchange\Formats\CsvFormat;
use App\Services\Exchange\Formats\Format;
use App\Services\Exchange\Formats\JsonFormat;
use App\Services\Exchange\Formats\XlsxFormat;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * A22 · Универсальный обмен данными: один движок для админки, CLI и API.
 *
 *   parse(content, format?, entity?)  → пакет ['banks' => [...], ...] (вложенные списки раскрыты)
 *   import(bundle, ImportOptions)      → ImportReport (+ запись в imports, если не dry-run)
 *   export(entities, filters)          → пакет, render(bundle, format) → строка файла
 *
 * Импорт идёт в транзакции; dry-run — та же транзакция с откатом, поэтому предпросмотр точен
 * (вложенные банк → вклад → ставки находятся друг у друга ещё до записи).
 */
class DataExchange
{
    public function __construct(private EntityRegistry $registry) {}

    /** @return array<string, Format> */
    public function formats(): array
    {
        return ['json' => new JsonFormat, 'csv' => new CsvFormat, 'xlsx' => new XlsxFormat];
    }

    public function format(string $key): Format
    {
        $key = strtolower($key);
        $key = ['jsonl' => 'json', 'ndjson' => 'json', 'xls' => 'xlsx', 'ods' => 'xlsx', 'tsv' => 'csv', 'txt' => 'csv'][$key] ?? $key;

        return $this->formats()[$key] ?? throw new InvalidArgumentException("Формат «{$key}» не поддерживается. Есть: json, csv, xlsx");
    }

    /** Формат по расширению имени файла или по содержимому. */
    public function detect(string $content, ?string $filename = null): string
    {
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        if ($ext !== '') {
            return $this->format($ext)->key();
        }
        if (str_starts_with($content, "PK\x03\x04") || str_starts_with($content, "\xD0\xCF\x11\xE0")) {
            return 'xlsx';
        }
        $t = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $content));

        return str_starts_with($t, '{') || str_starts_with($t, '[') ? 'json' : 'csv';
    }

    /** Разбор файла в пакет: ключи сущностей приводятся к каноническим, вложенные списки раскрываются. */
    public function parse(string $content, ?string $format = null, ?string $entity = null, ?string $filename = null): array
    {
        $format ??= $this->detect($content, $filename);
        $raw = $this->format($format)->read($content, $entity ? $this->registry->get($entity)->key() : null);

        $bundle = array_fill_keys($this->registry->keys(), []);
        foreach ($raw as $key => $rows) {
            $e = $this->registry->find((string) $key) ?? throw new InvalidArgumentException("Неизвестная сущность «{$key}». Есть: ".implode(', ', $this->registry->keys()));
            foreach ($rows as $row) {
                $this->flattenInto($bundle, $e, (array) $row);
            }
        }

        return array_filter($bundle);
    }

    private function flattenInto(array &$bundle, Entity $e, array $row, array $inherit = []): void
    {
        $children = [];
        foreach ($e->nested() as $childKey => $names) {
            foreach ($names as $name) {
                if (isset($row[$name]) && is_array($row[$name]) && array_is_list($row[$name])) {
                    $children[$childKey] = [...($children[$childKey] ?? []), ...$row[$name]];
                    unset($row[$name]);
                }
            }
        }
        // поля родителя (bank, country, product) — если в строке их нет
        foreach ($inherit as $k => $v) {
            $row[$k] ??= $v;
        }
        $bundle[$e->key()][] = $row;

        foreach ($children as $childKey => $list) {
            $child = $this->registry->get($childKey);
            $ctx = array_filter($e->childContext($row) + array_intersect_key($row, array_flip(['country'])), fn ($v) => $v !== null);
            foreach ($list as $childRow) {
                $this->flattenInto($bundle, $child, (array) $childRow, $ctx);
            }
        }
    }

    public function import(array $bundle, ImportOptions $options): ImportReport
    {
        $ctx = new ImportContext($options);
        $import = null;

        DB::beginTransaction();
        try {
            if (! $options->dryRun) {
                $import = Import::create([
                    'user_id' => $options->userId, 'api_token_id' => $options->tokenId, 'kind' => implode(',', array_keys($bundle)) ?: 'empty',
                    'filename' => $options->filename ?? 'data.'.($options->format ?? 'json'), 'format' => $options->format,
                    'source' => $options->source, 'mode' => $options->mode, 'status' => 'running',
                ]);
                $ctx->importId = $import->id;
            }

            foreach ($this->registry->all() as $key => $entity) {
                foreach ($bundle[$key] ?? [] as $i => $raw) {
                    $n = $i + 1;
                    try {
                        $row = $entity->normalize((array) $raw);
                        $label = $entity->rowLabel($row);
                        // ошибка одной строки не валит весь импорт
                        DB::transaction(function () use ($entity, $row, $ctx, $key, $n, $label) {
                            [$action, $changes, $message] = $entity->persist($row, $ctx) + [null, [], null];
                            $ctx->report->add($key, $n, $action, $label, $changes, $message);
                        });
                    } catch (RowError $e) {
                        $ctx->report->add($key, $n, 'error', $label ?? $entity->rowLabel((array) $raw), [], $e->getMessage());
                    } catch (Throwable $e) {
                        report($e);
                        $ctx->report->add($key, $n, 'error', $entity->rowLabel((array) $raw), [], 'ошибка записи: '.$e->getMessage());
                    }
                    unset($label);
                }
            }

            $unknown = array_diff(array_keys($bundle), $this->registry->keys());
            foreach ($unknown as $u) {
                $ctx->report->warn("Сущность «{$u}» пропущена — неизвестна.");
            }

            if ($options->dryRun) {
                DB::rollBack();
            } else {
                $r = $ctx->report;
                $import->update([
                    'rows_total' => $r->total(), 'rows_created' => $r->total('created'), 'rows_updated' => $r->total('updated'),
                    'rows_skipped' => $r->total('unchanged') + $r->total('skipped'), 'rows_failed' => $r->total('error'),
                    'stats' => $r->stats, 'log' => array_slice(array_map(fn ($row) => self::logLine($row), array_filter($r->rows, fn ($x) => $x['action'] !== 'unchanged')), 0, 500),
                    'status' => $r->total('error') ? 'partial' : 'done',
                ]);
                DB::commit();
                $ctx->report->importId = $import->id;
            }
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $ctx->report;
    }

    private static function logLine(array $r): string
    {
        $changes = collect($r['changes'] ?? [])->map(fn ($c, $f) => "{$f}: ".json_encode($c[0], JSON_UNESCAPED_UNICODE).' → '.json_encode($c[1], JSON_UNESCAPED_UNICODE))->implode('; ');

        return "{$r['entity']} #{$r['row']} {$r['action']} {$r['key']}".($changes ? " ({$changes})" : '').(isset($r['message']) ? " — {$r['message']}" : '');
    }

    /**
     * @param  list<string>  $entities  пусто — все
     * @param  array{country?: ?string, bank?: string|list<string>|null, current?: bool}  $filters
     */
    public function export(array $entities = [], array $filters = []): array
    {
        $keys = $entities ? array_map(fn ($e) => $this->registry->get($e)->key(), $entities) : $this->registry->keys();
        $bundle = [];
        foreach ($this->registry->all() as $key => $entity) {
            if (in_array($key, $keys, true)) {
                $bundle[$key] = $entity->exportQuery($filters)->get()->map(fn ($m) => $entity->toRow($m))->all();
            }
        }

        return $bundle;
    }

    public function render(array $bundle, string $format): string
    {
        return $this->format($format)->write($bundle);
    }
}
