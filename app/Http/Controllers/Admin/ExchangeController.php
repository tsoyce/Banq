<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Country;
use App\Models\Import;
use App\Services\Exchange\DataExchange;
use App\Services\Exchange\EntityRegistry;
use App\Services\Exchange\ImportOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * A22/A23 · «Обмен данными» в админке: импорт любого формата (проверка dry-run с диффом → применить),
 * экспорт с фильтрами, схема и шаблоны, токены API, история импортов.
 * Движок — App\Services\Exchange\DataExchange (тот же, что у CLI и API).
 */
class ExchangeController extends Controller
{
    private const DIR = 'exchange';

    public function __construct(private DataExchange $exchange, private EntityRegistry $registry) {}

    public function index(Request $request): View
    {
        return view('admin.exchange.index', [
            'entities' => $this->registry->all(),
            'countries' => Country::query()->ordered()->get(),
            'imports' => Import::query()->with(['user', 'token'])->latest('id')->paginate(15)->withQueryString(),
            'modes' => ImportOptions::MODES,
            'tab' => in_array($request->query('tab'), ['import', 'export', 'format', 'history'], true) ? $request->query('tab') : 'import',
        ]);
    }

    /** Шаг 1: принять файл или вставленный текст, прогнать dry-run и показать дифф. */
    public function check(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'file' => ['nullable', 'file', 'max:20480'],
            'text' => ['nullable', 'string', 'max:5000000'],
            'entity' => ['nullable', 'string'],
            'format' => ['nullable', 'in:json,csv,xlsx'],
            'mode' => ['required', 'in:'.implode(',', array_keys(ImportOptions::MODES))],
            'country' => ['nullable', 'string', 'max:2'],
        ]);
        $file = $request->file('file');
        $content = $file ? (string) file_get_contents($file->getRealPath()) : (string) ($data['text'] ?? '');
        if (trim($content) === '') {
            return back()->withErrors(['file' => 'Загрузи файл или вставь данные (JSON или CSV).'])->withInput();
        }
        $filename = $file?->getClientOriginalName() ?? 'вставка.'.($data['format'] ?? $this->exchange->detect($content));

        try {
            $format = $data['format'] ?? $this->exchange->detect($content, $file?->getClientOriginalName());
            $bundle = $this->exchange->parse($content, $format, $data['entity'] ?? null, $filename);
            $report = $this->exchange->import($bundle, new ImportOptions(mode: $data['mode'], dryRun: true, country: $data['country'] ?? null));
        } catch (Throwable $e) {
            return back()->withErrors(['file' => 'Не удалось разобрать данные: '.$e->getMessage()])->withInput();
        }

        // сохраняем исходник, чтобы «Применить» залило ровно то, что проверили
        $token = Str::random(32);
        Storage::put(self::DIR."/{$token}.{$format}", $content);
        session()->put("exchange.pending.{$token}", ['format' => $format, 'filename' => $filename, 'entity' => $data['entity'] ?? null, 'mode' => $data['mode'], 'country' => $data['country'] ?? null]);

        return view('admin.exchange.report', [
            'report' => $report->toArray(), 'token' => $token, 'filename' => $filename, 'format' => $format, 'mode' => $data['mode'],
            'counts' => array_map('count', $bundle), 'entities' => $this->registry->all(), 'dryRun' => true,
        ]);
    }

    /** Шаг 2: применить проверенное. */
    public function apply(Request $request): RedirectResponse
    {
        $token = (string) $request->validate(['token' => ['required', 'alpha_num', 'size:32']])['token'];
        $pending = session()->pull("exchange.pending.{$token}");
        abort_unless($pending, 419, 'Проверка устарела — загрузи данные ещё раз');
        $path = self::DIR."/{$token}.{$pending['format']}";
        $content = (string) Storage::get($path);

        $bundle = $this->exchange->parse($content, $pending['format'], $pending['entity'], $pending['filename']);
        $report = $this->exchange->import($bundle, new ImportOptions(
            mode: $pending['mode'], country: $pending['country'], source: 'admin', userId: $request->user()->id,
            filename: $pending['filename'], format: $pending['format'],
        ));
        Import::whereKey($report->importId)->update(['path' => $path]);

        return redirect()->route('admin.exchange.show', $report->importId)->with('status',
            "Готово: новых {$report->total('created')}, обновлено {$report->total('updated')}, без изменений {$report->total('unchanged')}".($report->total('error') ? ", ошибок {$report->total('error')}" : '').'.');
    }

    public function export(Request $request): Response
    {
        $data = $request->validate([
            'entities' => ['nullable', 'array'], 'entities.*' => ['string'], 'format' => ['nullable', 'in:json,csv,xlsx'],
            'country' => ['nullable', 'string', 'max:2'], 'current' => ['nullable', 'boolean'],
        ]);
        $format = $this->exchange->format($data['format'] ?? 'json');
        $bundle = $this->exchange->export($data['entities'] ?? [], ['country' => $data['country'] ?? null, 'current' => (bool) ($data['current'] ?? false)]);

        return response($format->write($bundle), 200, [
            'Content-Type' => $format->mime(),
            'Content-Disposition' => 'attachment; filename="banq-'.($data['country'] ?? 'all').'-'.now()->format('Ymd-His').'.'.$format->extension().'"',
        ]);
    }

    public function schema(): JsonResponse
    {
        return response()->json($this->registry->schema(), options: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Disposition', 'inline; filename="banq-schema.json"');
    }

    /** Шаблон для заполнения: все поля сущности + строка-пример. */
    public function template(string $entity, string $format): Response
    {
        $e = $this->registry->get($entity);
        $f = $this->exchange->format($format);
        $row = array_map(fn () => null, $e->fields());
        $bundle = [$e->key() => [array_replace($row, $e->example())]];
        if ($f->key() === 'json') {
            $bundle = $this->registry->schema()['example'];
        }

        return response($f->write($bundle), 200, [
            'Content-Type' => $f->mime(),
            'Content-Disposition' => 'attachment; filename="template-'.$e->key().'.'.$f->extension().'"',
        ]);
    }

    public function show(Import $import): View
    {
        return view('admin.exchange.show', ['import' => $import->load(['user', 'token'])]);
    }

    public function tokens(): View
    {
        return view('admin.exchange.tokens', ['tokens' => ApiToken::query()->with('user')->latest('id')->get(), 'abilities' => ApiToken::ABILITIES]);
    }

    public function issueToken(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403, 'Токены выпускает только администратор');
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'abilities' => ['required', 'array'], 'abilities.*' => ['in:read,write'], 'days' => ['nullable', 'integer', 'min:1', 'max:3650']]);
        [$token, $plain] = ApiToken::issue($data['name'], $data['abilities'], $request->user()->id, isset($data['days']) ? now()->addDays((int) $data['days']) : null);

        return redirect()->route('admin.exchange.tokens')->with('status', "Токен «{$token->name}» выпущен.")->with('plain_token', $plain);
    }

    public function revokeToken(Request $request, ApiToken $token): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $token->delete();

        return back()->with('status', "Токен «{$token->name}» отозван.");
    }
}
