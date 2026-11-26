<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Services\Exchange\DataExchange;
use App\Services\Exchange\EntityRegistry;
use App\Services\Exchange\ImportOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * A23 · API обмена данными /api/v1 — чтобы собрать данные где угодно (скрипт, Claude) и залить одной командой.
 * Формат — тот же, что у админки и banq:import; схема — GET /api/v1/schema.
 */
class ExchangeController extends Controller
{
    public function __construct(private DataExchange $exchange, private EntityRegistry $registry) {}

    public function schema(): JsonResponse
    {
        return response()->json($this->registry->schema(), options: JSON_UNESCAPED_UNICODE);
    }

    /** GET /api/v1/export?entities=banks,deposit_rates&format=json|csv|xlsx&country=KZ&bank=BCC&current=1 */
    public function export(Request $request): Response|JsonResponse
    {
        try {
            $entities = array_filter(explode(',', (string) $request->query('entities', '')));
            $bundle = $this->exchange->export($entities, $this->filters($request));
            $format = $this->exchange->format((string) $request->query('format', 'json'));
        } catch (\InvalidArgumentException $e) {
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
        $rows = $this->exchange->export([$key], $this->filters($request))[$key];

        return response()->json(['entity' => $key, 'count' => count($rows), 'data' => $rows], options: JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/v1/import?dry_run=1&mode=upsert&country=KZ&entity=banks&format=csv
     * Тело: JSON-пакет (application/json), файл (multipart, поле file) или сырой CSV/JSON.
     */
    public function import(Request $request): JsonResponse
    {
        $file = $request->file('file');
        $content = $file ? (string) file_get_contents($file->getRealPath()) : $request->getContent();
        if (trim($content) === '') {
            return response()->json(['message' => 'Пустое тело: пришли JSON-пакет, CSV или файл (поле file). Схема — GET /api/v1/schema'], 422);
        }
        $filename = $file?->getClientOriginalName();

        try {
            $format = $request->query('format') ?: $this->exchange->detect($content, $filename);
            $bundle = $this->exchange->parse($content, $format, $request->query('entity') ?: null, $filename);
            $report = $this->exchange->import($bundle, ImportOptions::fromArray([
                'mode' => $request->query('mode', 'upsert'), 'dry_run' => $request->boolean('dry_run'), 'country' => $request->query('country'),
                'source' => 'api', 'token_id' => $request->attributes->get('api_token')?->id, 'filename' => $filename ?? 'api.'.$format, 'format' => $format,
            ]));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['dry_run' => $request->boolean('dry_run')] + $report->toArray(), $report->total('error') && ! $report->hasChanges() ? 422 : 200, options: JSON_UNESCAPED_UNICODE);
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
        ];
    }
}
