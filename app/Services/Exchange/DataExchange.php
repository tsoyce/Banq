<?php

namespace App\Services\Exchange;

use App\Models\Import;
use App\Services\Exchange\Formats\CsvFormat;
use App\Services\Exchange\Formats\Format;
use App\Services\Exchange\Formats\JsonFormat;
use App\Services\Exchange\Formats\NdjsonFormat;
use App\Services\Exchange\Formats\XlsxFormat;
use App\Services\Exchange\Formats\XmlFormat;
use App\Services\Exchange\Formats\YamlFormat;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * A22 · Универсальный обмен данными: один движок для админки, CLI и API.
 *
 * A39 · Строки могут нести служебные _id (поиск по id) и _delete (удаление, если разрешено); форматы + XML, YAML, NDJSON, TSV.
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
        // A39 · + XML, YAML, NDJSON (по объекту на строку), TSV (как копирует Excel)
        return ['json' => new JsonFormat, 'ndjson' => new NdjsonFormat, 'xml' => new XmlFormat, 'yaml' => new YamlFormat,
            'csv' => new CsvFormat, 'tsv' => new CsvFormat("\t"), 'xlsx' => new XlsxFormat];
    }

    /** A39 · Подписи форматов для админки. */
    public const FORMAT_LABELS = ['json' => 'JSON', 'xml' => 'XML', 'yaml' => 'YAML', 'ndjson' => 'NDJSON', 'csv' => 'CSV', 'tsv' => 'TSV', 'xlsx' => 'XLSX'];

    public function format(string $key): Format
    {
        $key = strtolower($key);
        $key = ['jsonl' => 'ndjson', 'yml' => 'yaml', 'xls' => 'xlsx', 'ods' => 'xlsx', 'tab' => 'tsv', 'txt' => 'csv'][$key] ?? $key;

        return $this->formats()[$key] ?? throw new InvalidArgumentException("Формат «{$key}» не поддерживается. Есть: ".implode(', ', array_keys($this->formats())));
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
        $first = strtok($t, "\n") ?: '';

        return match (true) {
            str_starts_with($t, '<') => 'xml',
            str_starts_with($t, '{') && ! json_validate($t) && json_validate(trim($first)) => 'ndjson',
            str_starts_with($t, '{') || str_starts_with($t, '[') => 'json',
            str_starts_with($t, '---') || (bool) preg_match('/^[a-z_]+:\s*$/m', $first) => 'yaml',
            substr_count($first, "\t") > 0 && substr_count($first, "\t") >= substr_count($first, ',') && substr_count($first, "\t") >= substr_count($first, ';') => 'tsv',
            default => 'csv',
        };
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

            // A39 · сначала создание/обновление (родители раньше детей), потом удаление (дети раньше родителей)
            $deletes = [];
            foreach ($this->registry->all() as $key => $entity) {
                foreach ($bundle[$key] ?? [] as $i => $raw) {
                    [$raw, $id, $delete] = self::meta((array) $raw);
                    if ($delete) {
                        $deletes[$key][$i] = [$raw, $id];

                        continue;
                    }
                    $this->runRow($entity, $key, $i + 1, $raw, $id, $ctx, false);
                }
            }
            foreach (array_reverse($this->registry->all()) as $key => $entity) {
                foreach ($deletes[$key] ?? [] as $i => [$raw, $id]) {
                    $this->runRow($entity, $key, $i + 1, $raw, $id, $ctx, true);
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
                    'rows_total' => $r->total(), 'rows_created' => $r->total('created'), 'rows_updated' => $r->total('updated') + $r->total('deleted'),
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

    /** A39 · Одна строка импорта: нормализация → запись/удаление в своей точке сохранения; ошибка строки не валит весь импорт. */
    private function runRow(Entity $entity, string $key, int $n, array $raw, ?int $id, ImportContext $ctx, bool $delete): void
    {
        $label = null;
        try {
            if ($delete && ! $ctx->options->allowDelete) {
                throw new RowError('удаление не разрешено: в админке — табличный редактор, в API — токен с правом delete и allow_delete=1, в консоли — --allow-delete');
            }
            $row = $entity->normalize($raw, $delete);
            $label = ($id ? "#{$id} " : '').$entity->rowLabel($row);
            $ctx->rowId = $id;
            DB::transaction(function () use ($entity, $row, $ctx, $key, $n, $label, $delete) {
                [$action, $changes, $message] = ($delete ? $entity->remove($row, $ctx) : $entity->persist($row, $ctx)) + [null, [], null];
                $ctx->report->add($key, $n, $action, $label, $changes, $message);
            });
        } catch (RowError $e) {
            $ctx->report->add($key, $n, 'error', $label ?? $entity->rowLabel($raw), [], $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $ctx->report->add($key, $n, 'error', $label ?? $entity->rowLabel($raw), [], 'ошибка записи: '.$e->getMessage());
        } finally {
            $ctx->rowId = null;
        }
    }

    /**
     * A39 · Служебные поля строки: _id (id записи — поиск по нему, можно сменить ключ) и _delete
     * (да/1/true или _action: delete — удалить). Возвращает [строка без них, id, удалить?].
     */
    public static function meta(array $raw): array
    {
        $id = $raw['_id'] ?? null;
        $delete = $raw['_delete'] ?? null;
        $action = strtolower((string) ($raw['_action'] ?? ''));
        unset($raw['_id'], $raw['_delete'], $raw['_action']);
        $id = is_numeric($id) && (int) $id > 0 ? (int) $id : null;
        $delete = $action === 'delete' || in_array(mb_strtolower(trim((string) (is_bool($delete) ? (int) $delete : $delete))), ['1', 'true', 'yes', 'да', 'delete', 'x', '✓'], true);

        return [$raw, $id, $delete];
    }

    private static function logLine(array $r): string
    {
        $changes = collect($r['changes'] ?? [])->map(fn ($c, $f) => "{$f}: ".json_encode($c[0], JSON_UNESCAPED_UNICODE).' → '.json_encode($c[1], JSON_UNESCAPED_UNICODE))->implode('; ');

        return "{$r['entity']} #{$r['row']} {$r['action']} {$r['key']}".($changes ? " ({$changes})" : '').(isset($r['message']) ? " — {$r['message']}" : '');
    }

    /**
     * @param  list<string>  $entities  пусто — все
     * @param  array{country?: ?string, bank?: string|list<string>|null, current?: bool, updated_since?: ?string}  $filters
     * @param  bool  $withIds  A39 · первой колонкой _id — для табличного редактора и синхронизации агентов
     */
    public function export(array $entities = [], array $filters = [], bool $withIds = false): array
    {
        $keys = $entities ? array_map(fn ($e) => $this->registry->get($e)->key(), $entities) : $this->registry->keys();
        $bundle = [];
        foreach ($this->registry->all() as $key => $entity) {
            if (in_array($key, $keys, true)) {
                $bundle[$key] = $this->query($entity, $filters)->get()->map(fn ($m) => $this->row($entity, $m, $withIds))->all();
            }
        }

        return $bundle;
    }

    /**
     * A39 · Страница строк одной сущности (API): ['data', 'total', 'limit', 'offset'].
     */
    public function page(string $entity, array $filters, int $limit, int $offset, bool $withIds = false): array
    {
        $e = $this->registry->get($entity);
        $q = $this->query($e, $filters);
        $total = (clone $q)->toBase()->getCountForPagination();
        $rows = $q->skip($offset)->take($limit)->get()->map(fn ($m) => $this->row($e, $m, $withIds))->all();

        return ['data' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** A39 · Запрос экспорта + общий фильтр updated_since (дата/время ISO 8601) — для инкрементальной выгрузки. */
    private function query(Entity $entity, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $q = $entity->exportQuery($filters);
        if (! empty($filters['updated_since'])) {
            $since = \Illuminate\Support\Carbon::parse($filters['updated_since']);
            $q->where($q->getModel()->qualifyColumn('updated_at'), '>=', $since);
        }

        return $q;
    }

    private function row(Entity $entity, \Illuminate\Database\Eloquent\Model $m, bool $withIds): array
    {
        return $withIds ? ['_id' => $m->getKey()] + $entity->toRow($m) : $entity->toRow($m);
    }

    public function render(array $bundle, string $format): string
    {
        return $this->format($format)->write($bundle);
    }
}
