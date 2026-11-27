<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Services\Exchange\DataExchange;
use App\Services\Exchange\EntityRegistry;
use App\Services\Exchange\ImportOptions;
use App\Services\Exchange\OpenApi;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A23 · API обмена данными /api/v1 — чтобы собрать данные где угодно (скрипт, Claude) и залить одной командой.
 * Формат — тот же, что у админки и banq:import; схема — GET /api/v1/schema.
 * A39 · Для ИИ-агентов: GET /api/v1 (руководство без токена), /openapi.json, постраничные строки с updated_since,
 * POST/DELETE /api/v1/{entity}, право delete, Idempotency-Key, форматы XML/YAML/NDJSON/TSV.
 */
class ExchangeController extends Controller
{
    public function __construct(private DataExchange $exchange, private EntityRegistry $registry) {}

    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

    /** A39 · GET /api/v1 — без токена: что это, как авторизоваться, порядок работы агента, все адреса. */
    public function about(): JsonResponse
    {
        $base = url('/api/v1');

        return response()->json([
            'name' => settings('site.name', 'TSOY.IN Banq').' API',
            'version' => '1.1',
            'docs' => ['openapi' => "{$base}/openapi.json", 'schema' => "{$base}/schema", 'guide' => 'docs/API.md в репозитории'],
            'auth' => 'Authorization: Bearer banq_… (или X-Api-Token). Права: read — чтение, write — запись, delete — удаление. Токен выдаёт администратор: /admin/exchange/tokens',
            'entities' => array_map(fn ($e) => ['label' => $e->label(), 'description' => $e->description(), 'url' => "{$base}/{$e->key()}"], $this->registry->all()),
            'formats' => array_keys($this->exchange->formats()),
            'agent_workflow' => [
                '1. GET /schema — поля, типы, обязательные, примеры и алиасы каждой сущности.',
                '2. GET /{entity}?country=KZ&ids=1 — что уже есть (постранично: limit, offset → next_offset; updated_since — только изменённое).',
                '3. Собери пакет {"banks": [...], "deposit_rates": [...]} — ссылки по естественным ключам (код банка, slug вклада), не по id. Вложенность разрешена: банк → deposit_products → rates.',
                '4. POST /import?dry_run=1 — проверка: отчёт created/updated/unchanged/error с диффом по полям, ничего не пишется.',
                '5. Исправь строки с action=error (message — причина), повтори dry_run до нуля ошибок.',
                '6. POST /import с заголовком Idempotency-Key — запись. Повторная заливка того же пакета ничего не меняет.',
                '7. Ставки не перезаписываются: новая ставка закрывает старую (valid_to), история сохраняется.',
            ],
            'endpoints' => [
                'GET /schema', 'GET /openapi.json', 'GET /export?entities=&format=&country=&bank=&current=&updated_since=&ids=',
                'GET /{entity}?country=&bank=&current=&updated_since=&limit=&offset=&ids=', 'POST /{entity}?dry_run=&mode=&country=&allow_delete=',
                'DELETE /{entity}?dry_run=', 'POST /import?dry_run=&mode=&country=&entity=&format=&allow_delete=', 'GET /imports', 'GET /imports/{id}',
            ],
            'limits' => ['requests_per_minute' => 120, 'page_max' => 5000, 'body_max_mb' => 20],
        ], options: self::JSON);
    }

    /** A39 · GET /api/v1/openapi.json — без токена. */
    public function openapi(OpenApi $spec): JsonResponse
    {
        return response()->json($spec->build(), options: self::JSON);
    }

    public function schema(): JsonResponse
    {
        return response()->json($this->registry->schema(), options: JSON_UNESCAPED_UNICODE);
    }

    /** GET /api/v1/export?entities=banks,deposit_rates&format=json|csv|xlsx&country=KZ&bank=BCC&current=1 */
    public function export(Request $request): Response|JsonResponse
    {
        try {
            $entities = array_filter(explode(',', (string) $request->query('entities', '')));
            $bundle = $this->exchange->export($entities, $this->filters($request), $request->boolean('ids'));
            $format = $this->exchange->format((string) $request->query('format', 'json'));
        } catch (\InvalidArgumentException|InvalidFormatException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response($format->write($bundle), 200, [
            'Content-Type' => $format->mime().($format->key() === 'xlsx' ? '' : '; charset=utf-8'),
            'Content-Disposition' => 'inline; filename="banq-'.now()->format('Ymd-His').'.'.$format->extension().'"',
        ]);
    }

    /** GET /api/v1/{entity} — строки одной сущности JSON: {entity, count, data}. */
    public function index(Request $request, string $entity): JsonResponse
    {
        try {
            $key = $this->registry->get($entity)->key();
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
        $limit = max(1, min(5000, (int) $request->query('limit', 500)));
        $offset = max(0, (int) $request->query('offset', 0));
        try {
            $page = $this->exchange->page($key, $this->filters($request), $limit, $offset, $request->boolean('ids'));
        } catch (InvalidFormatException $e) {
            return response()->json(['message' => 'updated_since: ожидалась дата ISO 8601'], 422);
        }
        $next = $offset + count($page['data']) < $page['total'] ? $offset + count($page['data']) : null;

        return response()->json(['entity' => $key, 'count' => count($page['data']), 'total' => $page['total'], 'limit' => $limit, 'offset' => $offset, 'next_offset' => $next, 'data' => $page['data']], options: self::JSON);
    }

    /** A39 · POST /api/v1/{entity} — строки одной сущности: JSON-массив, {rows: [...]}, CSV/TSV/XML/YAML/NDJSON. */
    public function upsert(Request $request, string $entity): JsonResponse
    {
        return $this->run($request, $entity, false);
    }

    /** A39 · DELETE /api/v1/{entity} — тело как у POST, достаточно ключей или _id. Ставки закрываются, а не удаляются. */
    public function destroy(Request $request, string $entity): JsonResponse
    {
        return $this->run($request, $entity, true);
    }

    /**
     * POST /api/v1/import?dry_run=1&mode=upsert&country=KZ&entity=banks&format=csv
     * Тело: JSON-пакет (application/json), файл (multipart, поле file) или сырой CSV/JSON.
     */
    public function import(Request $request): JsonResponse
    {
        return $this->run($request, $request->query('entity') ?: null, false);
    }

    /** Общий прогон импорта для POST /import, POST и DELETE /{entity}; с Idempotency-Key ответ запоминается на сутки. */
    private function run(Request $request, ?string $entity, bool $deleteAll): JsonResponse
    {
        $token = $request->attributes->get('api_token');
        $dryRun = $request->boolean('dry_run');
        $allowDelete = $deleteAll || $request->boolean('allow_delete');
        if ($allowDelete && ! $token?->can('delete')) {
            return response()->json(['message' => 'Удаление: нужен токен с правом delete'], 403);
        }
        if ($entity !== null && ! $this->registry->find($entity)) {
            return response()->json(['message' => "Неизвестная сущность «{$entity}». Есть: ".implode(', ', $this->registry->keys())], 404);
        }

        $file = $request->file('file');
        $content = $file ? (string) file_get_contents($file->getRealPath()) : $request->getContent();
        if (trim($content) === '') {
            return response()->json(['message' => 'Пустое тело: пришли JSON-пакет, CSV, XML, YAML или файл (поле file). Схема — GET /api/v1/schema'], 422);
        }

        $idem = $request->header('Idempotency-Key');
        $cacheKey = $idem && ! $dryRun ? 'api.idem.'.($token?->id ?? 0).'.'.sha1($idem) : null;
        $hash = sha1($request->method().$request->fullUrl().$content);
        if ($cacheKey && ($saved = Cache::get($cacheKey))) {
            if ($saved['hash'] !== $hash) {
                return response()->json(['message' => 'Idempotency-Key уже использован с другим запросом'], 409);
            }

            return response()->json($saved['body'], $saved['status'], ['Idempotent-Replayed' => 'true'], self::JSON);
        }

        $filename = $file?->getClientOriginalName();
        try {
            $format = $request->query('format') ?: $this->exchange->detect($content, $filename);
            $bundle = $this->exchange->parse($content, $format, $entity, $filename);
            if ($deleteAll) {
                $bundle = array_map(fn ($rows) => array_map(fn ($r) => ['_delete' => true] + (array) $r, $rows), $bundle);
            }
            $report = $this->exchange->import($bundle, ImportOptions::fromArray([
                'mode' => $request->query('mode', 'upsert'), 'dry_run' => $dryRun, 'country' => $request->query('country'),
                'source' => 'api', 'token_id' => $token?->id, 'filename' => $filename ?? 'api.'.$format, 'format' => $format, 'allow_delete' => $allowDelete,
            ]));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $body = ['dry_run' => $dryRun] + $report->toArray();
        $status = $report->total('error') && ! $report->hasChanges() ? 422 : 200;
        if ($cacheKey) {
            Cache::put($cacheKey, ['hash' => $hash, 'status' => $status, 'body' => $body], now()->addDay());
        }

        return response()->json($body, $status, options: self::JSON);
    }

    public function imports(): JsonResponse
    {
        return response()->json(Import::query()->latest('id')->limit(50)->get(['id', 'kind', 'filename', 'format', 'source', 'mode', 'status', 'rows_total', 'rows_created', 'rows_updated', 'rows_skipped', 'rows_failed', 'created_at']));
    }

    public function showImport(Import $import): JsonResponse
    {
        return response()->json($import->only(['id', 'kind', 'filename', 'format', 'source', 'mode', 'status', 'rows_total', 'rows_created', 'rows_updated', 'rows_skipped', 'rows_failed', 'stats', 'log', 'created_at']), options: JSON_UNESCAPED_UNICODE);
    }

    private function filters(Request $request): array
    {
        return [
            'country' => $request->query('country'),
            'bank' => array_filter(explode(',', (string) $request->query('bank', ''))) ?: null,
            'current' => $request->boolean('current'),
            // «+» часового пояса без URL-кодирования приходит пробелом
            'updated_since' => $request->query('updated_since') ? str_replace(' ', '+', (string) $request->query('updated_since')) : null,
        ];
    }
}
