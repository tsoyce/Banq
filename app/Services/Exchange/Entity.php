<?php

namespace App\Services\Exchange;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

/**
 * A22 · Описание сущности для универсального обмена данными (импорт/экспорт/схема).
 *
 * Поле: name => [type, required?, desc, example?, values?, rules?, aliases?]
 *   type: string | text | int | decimal | bool | date | enum | list (массив строк) | json (объект) | ref (ссылка по естественному ключу)
 * Строка импорта ищется по естественному ключу (find), а не по id — так данные можно
 * собирать где угодно (скрипт, Claude, таблица) и заливать повторно без дублей.
 */
abstract class Entity
{
    /** Ключ в пакете: countries, banks… */
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /** @return array<string, array> */
    abstract public function fields(): array;

    /** Существующая запись по естественному ключу или null. */
    abstract public function find(array $row, ImportContext $ctx): ?Model;

    /** Атрибуты модели из нормализованной строки (ссылки → id). Бросает RowError. */
    abstract public function attributes(array $row, ImportContext $ctx, ?Model $existing): array;

    abstract public function newModel(): Model;

    /** Подпись строки для отчёта: «KZ · BCC», «bcc-term». */
    abstract public function rowLabel(array $row): string;

    /** Запрос для экспорта; фильтры: country (код), bank (код), current (только действующие). */
    abstract public function exportQuery(array $filters): Builder;

    /** Строка экспорта — в том же формате, что принимает импорт. */
    abstract public function toRow(Model $model): array;

    /** Другие имена сущности в пакете/листе Excel. */
    public function aliases(): array
    {
        return [];
    }

    /**
     * Вложенные списки: ['deposit_products' => ['products', 'deposits']] — дети, которые можно положить внутрь строки.
     * childContext() даёт поля, которые родитель передаёт детям (ссылка на себя).
     */
    public function nested(): array
    {
        return [];
    }

    public function childContext(array $row): array
    {
        return [];
    }

    /** Приводит строку к полям сущности: имена (алиасы), типы, проверка. Бросает RowError. */
    public function normalize(array $raw): array
    {
        $byName = [];
        foreach ($this->fields() as $name => $f) {
            foreach ([$name, ...($f['aliases'] ?? [])] as $alias) {
                $byName[self::headerKey($alias)] = $name;
            }
        }

        $row = [];
        $canonical = [];
        foreach (self::undot($raw) as $k => $v) {
            $name = $byName[self::headerKey((string) $k)] ?? null;
            if ($name === null) {
                continue;
            }
            $isCanonical = self::headerKey((string) $k) === self::headerKey($name);
            $empty = $v === null || $v === '';
            // пустое не затирает заполненное; алиас не перебивает поле с точным именем
            if (array_key_exists($name, $row) && ($empty || (isset($canonical[$name]) && ! $isCanonical))) {
                continue;
            }
            $row[$name] = $v;
            $canonical[$name] = $isCanonical ?: ($canonical[$name] ?? false);
            if (! $canonical[$name]) {
                unset($canonical[$name]);
            }
        }

        $errors = [];
        foreach ($this->fields() as $name => $f) {
            if (! array_key_exists($name, $row)) {
                continue;
            }
            try {
                $row[$name] = self::cast($f, $row[$name]);
            } catch (Throwable $e) {
                $errors[] = "{$name}: {$e->getMessage()}";
            }
        }
        foreach ($this->fields() as $name => $f) {
            if (($f['required'] ?? false) && ($row[$name] ?? null) === null) {
                $errors[] = "{$name}: обязательное поле";
            }
        }
        if (! $errors) {
            $rules = array_filter(array_map(fn ($f) => $f['rules'] ?? null, $this->fields()));
            $v = Validator::make(array_filter($row, fn ($x) => $x !== null), $rules);
            foreach ($v->errors()->messages() as $field => $messages) {
                $errors[] = "{$field}: ".implode(', ', $messages);
            }
        }
        if ($errors) {
            throw new RowError(implode('; ', $errors));
        }

        return $row;
    }

    /** Запись строки: upsert с учётом режима. Возвращает [action, changes]. */
    public function persist(array $row, ImportContext $ctx): array
    {
        $existing = $this->find($row, $ctx);
        $mode = $ctx->options->mode;

        if ($existing && $mode === 'create') {
            return ['skipped', [], 'уже есть (режим «только новые»)'];
        }
        if (! $existing && $mode === 'update') {
            return ['skipped', [], 'нет в базе (режим «только обновить»)'];
        }

        $attrs = $this->attributes($row, $ctx, $existing);
        if (! $existing) {
            $model = $this->newModel()->fill($attrs);
            $model->save();

            return ['created', [], null];
        }

        $existing->fill($attrs);
        $changes = self::diff($existing);
        if (! $changes) {
            return ['unchanged', [], null];
        }
        $existing->save();

        return ['updated', $changes, null];
    }

    /** Описание для схемы (GET /api/v1/schema, banq:schema, админка). */
    public function schema(): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'description' => $this->description(),
            'aliases' => $this->aliases(),
            'nested' => array_keys($this->nested()),
            'fields' => array_map(fn ($f) => array_filter([
                'type' => $f[0],
                'required' => $f['required'] ?? false,
                'description' => $f['desc'] ?? null,
                'values' => $f['values'] ?? null,
                'example' => $f['example'] ?? null,
                'aliases' => $f['aliases'] ?? null,
            ], fn ($v) => $v !== null), $this->fields()),
        ];
    }

    /** Пример строки из примеров полей. */
    public function example(): array
    {
        return array_filter(array_map(fn ($f) => $f['example'] ?? null, $this->fields()), fn ($v) => $v !== null);
    }

    // ── Помощники ─────────────────────────────────────────────

    /** Поля модели для экспорта: числа — числами, даты — Y-m-d, перечисления — значением. */
    protected function exportFields(Model $m, array $skip = []): array
    {
        $out = [];
        foreach ($this->fields() as $name => $f) {
            if (in_array($name, $skip, true) || $f[0] === 'ref') {
                continue;
            }
            $v = self::plain($m->getAttribute($name));
            $out[$name] = match (true) {
                $v === null => null,
                $f[0] === 'decimal' => (float) $v,
                $f[0] === 'int' => (int) $v,
                default => $v,
            };
        }

        return $out;
    }

    /** Что изменилось у модели (dirty) в виде field => [было, стало]. */
    protected static function diff(Model $m): array
    {
        $out = [];
        foreach (array_keys($m->getDirty()) as $field) {
            $old = $m->getOriginal($field);
            $new = $m->getAttribute($field);
            if (self::same($old, $new)) {
                continue;
            }
            $out[$field] = [self::plain($old), self::plain($new)];
        }

        return $out;
    }

    private static function same(mixed $a, mixed $b): bool
    {
        $a = self::plain($a);
        $b = self::plain($b);
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return $a == $b;
    }

    private static function plain(mixed $v): mixed
    {
        return match (true) {
            $v instanceof \BackedEnum => $v->value,
            $v instanceof \DateTimeInterface => $v->format('Y-m-d'),
            default => $v,
        };
    }

    /** Нормализация заголовка: «Мин. сумма» → «минсумма», min_amount → «minamount». */
    public static function headerKey(string $h): string
    {
        return preg_replace('/[\s\-_.()«»"\']+/u', '', mb_strtolower(trim($h)));
    }

    /** {"limits.term": 1} → {"limits": {"term": 1}}; точки в заголовках CSV/XLSX дают вложенность. */
    public static function undot(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            if (($v === null || $v === '') && is_string($k) && (str_contains($k, '.') || is_array($out[$k] ?? null))) {
                continue; // пустая ячейка вложенного поля — «не указано»
            }
            if (is_string($k) && str_contains($k, '.') && ! str_starts_with($k, '.')) {
                if (! is_array($out[strtok($k, '.')] ?? [])) {
                    unset($out[strtok($k, '.')]);
                }
                data_set($out, $k, $v);
            } else {
                $out[$k] = is_array($v) && isset($out[$k]) && is_array($out[$k]) ? array_replace_recursive($out[$k], $v) : $v;
            }
        }

        return $out;
    }

    /** Приведение значения к типу поля. Пустая строка → null. */
    public static function cast(array $f, mixed $v): mixed
    {
        if (is_string($v)) {
            $v = trim($v);
        }
        if ($v === '' || $v === null) {
            return null;
        }

        return match ($f[0]) {
            'string', 'text' => is_scalar($v) ? (string) $v : throw new \InvalidArgumentException('ожидалась строка'),
            'int' => self::number($v, true),
            'decimal' => self::number($v, false),
            'bool' => self::bool($v),
            'date' => Carbon::parse(is_numeric($v) ? Date::excelToDateTimeObject((float) $v) : $v)->toDateString(),
            'enum' => self::enum($f, $v),
            'list' => self::list($v),
            'json' => self::json($v),
            'ref' => is_scalar($v) ? (string) $v : throw new \InvalidArgumentException('ожидался код/имя'),
            default => $v,
        };
    }

    private static function number(mixed $v, bool $int): int|float
    {
        if (is_string($v)) {
            $v = str_replace(["\u{00A0}", "\u{202F}", ' ', '%', '₸', '$', '€'], '', $v);
            $v = str_replace(',', '.', $v);
        }
        if (! is_numeric($v)) {
            throw new \InvalidArgumentException('ожидалось число');
        }

        return $int ? (int) round((float) $v) : (float) $v;
    }

    private static function bool(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        $s = mb_strtolower(trim((string) $v));

        return match (true) {
            in_array($s, ['1', 'true', 'yes', 'y', 'да', 'д', '+', 'on', 'есть', '✓'], true) => true,
            in_array($s, ['0', 'false', 'no', 'n', 'нет', 'н', '-', '—', 'off'], true) => false,
            default => throw new \InvalidArgumentException('ожидалось да/нет'),
        };
    }

    private static function enum(array $f, mixed $v): string
    {
        $s = (string) $v;
        if (isset($f['parse']) && ($parsed = ($f['parse'])($s)) !== null) {
            return $parsed;
        }
        if (in_array($s, $f['values'] ?? [], true)) {
            return $s;
        }
        if (in_array(mb_strtolower($s), $f['values'] ?? [], true)) {
            return mb_strtolower($s);
        }

        throw new \InvalidArgumentException('допустимо: '.implode(', ', $f['values'] ?? []));
    }

    private static function list(mixed $v): array
    {
        if (is_string($v)) {
            $j = json_decode($v, true);
            $v = is_array($j) ? $j : preg_split('/\s*[|;,\n]\s*/u', $v);
        }

        return array_values(array_filter(array_map(fn ($x) => is_scalar($x) ? trim((string) $x) : null, (array) $v), fn ($x) => $x !== null && $x !== ''));
    }

    private static function json(mixed $v): ?array
    {
        if (is_string($v)) {
            $v = json_decode($v, true);
            if (! is_array($v)) {
                throw new \InvalidArgumentException('ожидался JSON-объект');
            }
        }
        $prune = function (array $a) use (&$prune) {
            foreach ($a as $k => $x) {
                if (is_array($x)) {
                    $a[$k] = $prune($x);
                }
                if ($a[$k] === null || $a[$k] === '' || $a[$k] === []) {
                    unset($a[$k]);
                }
            }

            return $a;
        };

        return $prune((array) $v) ?: null;
    }
}
