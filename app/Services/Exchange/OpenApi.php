<?php

namespace App\Services\Exchange;

/**
 * A39 · Спецификация OpenAPI 3.1 для /api/v1 — строится из реестра сущностей, поэтому
 * новая сущность (кредиты и т.п.) сразу появляется в описании для ИИ-агентов и генераторов клиентов.
 */
class OpenApi
{
    public function __construct(private EntityRegistry $registry, private DataExchange $exchange) {}

    public function build(): array
    {
        $keys = $this->registry->keys();
        $schemas = [];
        foreach ($this->registry->all() as $key => $e) {
            $props = ['_id' => ['type' => 'integer', 'description' => 'id записи (из выгрузки с ids=1): поиск по нему вместо естественного ключа — так можно сменить код/slug'],
                '_delete' => ['type' => 'boolean', 'description' => 'удалить запись (нужно право delete и allow_delete=1; ставки не удаляются, а закрываются)']];
            $required = [];
            foreach ($e->fields() as $name => $f) {
                $props[$name] = self::type($f);
                if ($f['required'] ?? false) {
                    $required[] = $name;
                }
            }
            foreach ($e->nested() as $childKey => $names) {
                $props[$names[0]] = ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/{$childKey}"], 'description' => 'вложенные записи: '.$this->registry->get($childKey)->label()];
            }
            $schemas[$key] = array_filter(['type' => 'object', 'title' => $e->label(), 'description' => $e->description(), 'properties' => $props, 'required' => $required ?: null]);
        }
        $schemas['Bundle'] = ['type' => 'object', 'description' => 'Пакет: ключи — сущности, значения — списки строк. Порядок записи: '.implode(' → ', $keys),
            'properties' => array_map(fn ($k) => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/{$k}"]], array_combine($keys, $keys))];
        $schemas['Report'] = ['type' => 'object', 'properties' => [
            'dry_run' => ['type' => 'boolean'],
            'totals' => ['type' => 'object', 'additionalProperties' => ['type' => 'integer'], 'description' => 'created, updated, deleted, unchanged, skipped, error'],
            'stats' => ['type' => 'object', 'additionalProperties' => ['type' => 'object']],
            'rows' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'entity' => ['type' => 'string'], 'row' => ['type' => 'integer', 'description' => 'номер строки в своей сущности, с 1'],
                'action' => ['type' => 'string', 'enum' => ImportReport::ACTIONS], 'key' => ['type' => 'string'],
                'changes' => ['type' => 'object', 'description' => 'поле → [было, стало]'], 'message' => ['type' => 'string'],
            ]]],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            'import_id' => ['type' => ['integer', 'null']],
        ]];
        $schemas['Error'] = ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]];

        $p = fn (string $name, string $in, string $desc, array $schema = ['type' => 'string'], bool $req = false) => ['name' => $name, 'in' => $in, 'description' => $desc, 'required' => $req, 'schema' => $schema];
        $entity = $p('entity', 'path', 'сущность: '.implode(', ', $keys), ['type' => 'string', 'enum' => $keys], true);
        $filters = [
            $p('country', 'query', 'код страны ISO-2 (KZ)'), $p('bank', 'query', 'коды банков через запятую'),
            $p('current', 'query', 'только действующие ставки', ['type' => 'boolean']),
            $p('updated_since', 'query', 'только изменённые с даты/времени ISO 8601 — для инкрементальной синхронизации', ['type' => 'string', 'format' => 'date-time']),
        ];
        $write = [
            $p('dry_run', 'query', 'проверить без записи: тот же отчёт с диффом, транзакция откатывается', ['type' => 'boolean']),
            $p('mode', 'query', 'upsert — создать и обновить, create — только новые, update — только существующие', ['type' => 'string', 'enum' => array_keys(ImportOptions::MODES), 'default' => 'upsert']),
            $p('country', 'query', 'страна по умолчанию для строк без country'),
            $p('allow_delete', 'query', 'выполнять строки с _delete (нужно право delete)', ['type' => 'boolean']),
            $p('Idempotency-Key', 'header', 'повтор запроса с тем же ключом (24 ч) вернёт сохранённый ответ, ничего не записав повторно'),
        ];
        $report = ['200' => ['description' => 'отчёт', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Report']]]],
            '422' => ['description' => 'не разобрался файл или все строки с ошибками', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Report']]]]];
        $rowsBody = ['required' => true, 'content' => [
            'application/json' => ['schema' => ['oneOf' => [['type' => 'array', 'items' => ['type' => 'object']], ['type' => 'object', 'properties' => ['rows' => ['type' => 'array', 'items' => ['type' => 'object']]]]]]],
            'text/csv' => ['schema' => ['type' => 'string']], 'application/xml' => ['schema' => ['type' => 'string']],
        ]];
        $formats = array_keys($this->exchange->formats());

        return [
            'openapi' => '3.1.0',
            'info' => ['title' => settings('site.name', 'TSOY.IN Banq').' — API данных', 'version' => '1.1',
                'description' => 'Сбор и выгрузка данных о банках и их продуктах (страны, банки, вклады, ставки, платёжные системы, карты, пакеты). '
                    .'Строки ищутся по естественным ключам — повторная заливка не плодит дублей (идемпотентный upsert). Перед записью — dry_run. Ставки не перезаписываются: новая закрывает старую. '
                    .'Руководство для агентов — GET /api/v1 (без токена).'],
            'servers' => [['url' => url('/api/v1')]],
            'security' => [['bearer' => []]],
            'paths' => [
                '/' => ['get' => ['summary' => 'Описание API и порядок работы для агентов', 'security' => [], 'responses' => ['200' => ['description' => 'JSON']]]],
                '/openapi.json' => ['get' => ['summary' => 'Эта спецификация', 'security' => [], 'responses' => ['200' => ['description' => 'OpenAPI']]]],
                '/schema' => ['get' => ['summary' => 'Схема формата: поля, типы, примеры, алиасы', 'responses' => ['200' => ['description' => 'JSON']]]],
                '/export' => ['get' => ['summary' => 'Выгрузка пакетом в любом формате', 'parameters' => [
                    $p('entities', 'query', 'сущности через запятую (по умолчанию все)'), $p('format', 'query', 'формат', ['type' => 'string', 'enum' => $formats, 'default' => 'json']),
                    $p('ids', 'query', 'добавить _id', ['type' => 'boolean']), ...$filters], 'responses' => ['200' => ['description' => 'файл']]]],
                '/import' => ['post' => ['summary' => 'Залить пакет (JSON, NDJSON, XML, YAML, CSV, TSV, XLSX)', 'parameters' => [
                    $p('entity', 'query', 'сущность для плоских форматов (CSV, массив строк)'), $p('format', 'query', 'формат (по умолчанию — по содержимому)', ['type' => 'string', 'enum' => $formats]), ...$write],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Bundle']], 'multipart/form-data' => ['schema' => ['type' => 'object', 'properties' => ['file' => ['type' => 'string', 'format' => 'binary']]]],
                        'application/xml' => ['schema' => ['type' => 'string']], 'application/yaml' => ['schema' => ['type' => 'string']], 'application/x-ndjson' => ['schema' => ['type' => 'string']], 'text/csv' => ['schema' => ['type' => 'string']]]],
                    'responses' => $report]],
                '/imports' => ['get' => ['summary' => 'Последние 50 импортов', 'responses' => ['200' => ['description' => 'JSON']]]],
                '/imports/{id}' => ['get' => ['summary' => 'Журнал импорта', 'parameters' => [$p('id', 'path', 'id импорта', ['type' => 'integer'], true)], 'responses' => ['200' => ['description' => 'JSON']]]],
                '/{entity}' => [
                    'get' => ['summary' => 'Строки сущности постранично', 'parameters' => [$entity, ...$filters,
                        $p('limit', 'query', 'сколько строк (до 5000)', ['type' => 'integer', 'default' => 500]), $p('offset', 'query', 'сдвиг', ['type' => 'integer', 'default' => 0]),
                        $p('ids', 'query', 'добавить _id', ['type' => 'boolean'])],
                        'responses' => ['200' => ['description' => '{entity, count, total, limit, offset, next_offset, data}']]],
                    'post' => ['summary' => 'Создать/обновить строки одной сущности (upsert)', 'parameters' => [$entity, ...$write], 'requestBody' => $rowsBody, 'responses' => $report],
                    'delete' => ['summary' => 'Удалить строки по ключам или _id (право delete; ставки закрываются)', 'parameters' => [$entity, ...array_slice($write, 0, 1), $write[4]], 'requestBody' => $rowsBody, 'responses' => $report],
                ],
            ],
            'components' => [
                'securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Токен banq_… — админка → «Токены API» или php artisan banq:token. Права: read, write, delete']],
                'schemas' => $schemas,
            ],
        ];
    }

    private static function type(array $f): array
    {
        $t = match ($f[0]) {
            'int' => ['type' => 'integer'],
            'decimal' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'date' => ['type' => 'string', 'format' => 'date'],
            'enum' => ['type' => 'string', 'enum' => $f['values'] ?? []],
            'list' => ['type' => 'array', 'items' => ['type' => 'string']],
            'json' => ['type' => 'object'],
            'ref' => ['type' => 'string', 'description' => 'ссылка по естественному ключу (код, slug, название)'],
            default => ['type' => 'string'],
        };
        $desc = trim(($t['description'] ?? '').' '.($f['desc'] ?? ''));
        if (! empty($f['aliases'])) {
            $desc .= ($desc ? '. ' : '').'Другие имена: '.implode(', ', $f['aliases']);
        }

        return array_filter($t + ['description' => $desc ?: null, 'examples' => isset($f['example']) ? [$f['example']] : null]);
    }
}
