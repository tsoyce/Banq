<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WpImporter;
use App\Services\WordPress\WxrReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * A43 · Админка → Сайт → «Перенос с WordPress» (только admin).
 * 1) «Проверить» — снимок сайта (REST API или файл WXR/JSON), план и проверка безопасности; база не меняется.
 * 2) «Перенести» — браузер вызывает step() по очереди, каждый шаг ≤ 20 с (без очередей и cron), прогресс на странице.
 * 3) Отчёт — на странице и файлом JSON.
 * 4) A52 · «Дотянуть с копии» — записи с пропавшими картинками переносятся заново, файлы — с запасной копии (old.tsoy.in).
 */
class WordPressController extends Controller
{
    public function __construct(private WpImporter $importer) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    public function index(Request $request): View
    {
        $this->guard($request);
        $state = $this->importer->state();

        return view('admin.wordpress.index', [
            'state' => $state, 'report' => $state ? $this->importer->report() : null,
            'progress' => $state ? $this->importer->progress() : null,
        ]);
    }

    public function scan(Request $request, WxrReader $wxr): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'url' => ['nullable', 'url:http,https', 'max:255', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:204800', 'required_without:url'],
            'update' => ['nullable', 'boolean'], 'external_images' => ['nullable', 'boolean'],
            'mirror' => ['nullable', 'url:http,https', 'max:255'], // A52 · запасная копия для пропавших файлов
        ], [], ['url' => 'адрес сайта', 'file' => 'файл', 'mirror' => 'адрес копии']);

        try {
            $snapshot = null;
            if ($file = $request->file('file')) {
                $raw = (string) file_get_contents($file->getRealPath());
                $snapshot = str_starts_with(ltrim($raw), '<') ? $wxr->read($raw) : json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($snapshot) || ! isset($snapshot['posts'])) {
                    throw new \RuntimeException('В JSON нет списка posts — это не снимок WordPress.');
                }
            }
            @set_time_limit(180);
            $this->importer->scan($data['url'] ?? null, $snapshot, [
                'update' => $request->boolean('update'), 'external_images' => $request->boolean('external_images', true),
                'mirror' => $data['mirror'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['url' => $e->getMessage()]);
        }

        return redirect()->route('admin.wordpress.index')->with('status', 'Пробный прогон готов: проверьте план и находки, затем «Перенести».');
    }

    public function step(Request $request): JsonResponse
    {
        $this->guard($request);
        try {
            return response()->json($this->importer->step(20));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** A52 · Дотянуть пропавшие картинки с запасной копии сайта — шаг ≤ 20 с, браузер повторяет до finished. */
    public function fill(Request $request): JsonResponse
    {
        $this->guard($request);
        $data = $request->validate(['mirror' => ['required', 'url:http,https', 'max:255']], [], ['mirror' => 'адрес копии']);
        try {
            return response()->json($this->importer->fillMissing($data['mirror'], 20));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function report(Request $request): Response
    {
        $this->guard($request);

        return response()->json($this->importer->report(), 200, [
            'Content-Disposition' => 'attachment; filename="wordpress-import-'.now()->format('Y-m-d-His').'.json"',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->guard($request);
        $this->importer->reset();

        return redirect()->route('admin.wordpress.index')->with('status', 'План и снимок удалены. Перенесённые записи и файлы остались на месте.');
    }
}
