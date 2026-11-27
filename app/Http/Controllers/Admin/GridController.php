<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Country;
use App\Models\DepositProduct;
use App\Models\PaymentSystem;
use App\Services\Exchange\DataExchange;
use App\Services\Exchange\Entity;
use App\Services\Exchange\EntityRegistry;
use App\Services\Exchange\ImportOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * A39 · Табличный редактор («как Excel») для любой сущности обмена данными.
 * Страница — /admin/exchange/grid/{entity}; строки грузятся JSON (экспорт с _id), сохранение — тем же движком
 * DataExchange (dry-run → запись): строки с _id ищутся по id (можно сменить код/slug), _delete — удаление
 * (ставка — закрывается). Клиент — resources/js/sheet.js.
 */
class GridController extends Controller
{
    public function __construct(private DataExchange $exchange, private EntityRegistry $registry) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.exchange.grid', 'deposit_rates');
    }

    public function show(Request $request, string $entity): View
    {
        $e = $this->entity($entity);

        return view('admin.exchange.grid', [
            'entity' => $e,
            'entities' => $this->registry->all(),
            'countries' => Country::query()->ordered()->get(),
            'country' => $request->query('country', country()?->code),
            'config' => [
                'entity' => $e->key(),
                'label' => $e->label(),
                'columns' => $this->columns($e),
                'rowsUrl' => route('admin.exchange.grid.rows', $e->key()),
                'saveUrl' => route('admin.exchange.grid.save', $e->key()),
                'exportUrl' => route('admin.exchange.export'),
                'hasRates' => $e->key() === 'deposit_rates',
            ],
        ]);
    }

    /** GET …/rows?country=KZ&bank=BCC&current=1 — строки с _id, подсказки для ссылок. */
    public function rows(Request $request, string $entity): JsonResponse
    {
        $e = $this->entity($entity);
        $country = $request->query('country') ?: null;
        $filters = ['country' => $country, 'bank' => array_filter(explode(',', (string) $request->query('bank', ''))) ?: null, 'current' => $request->boolean('current')];
        $rows = $this->exchange->export([$e->key()], $filters, true)[$e->key()];

        return response()->json(['rows' => $rows, 'options' => $this->options($e, $country), 'banks' => $this->banks($country)], options: JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /** POST — {rows: [...], dry_run: bool, country: "KZ"}; строки — только изменённые, с _id / _delete. */
    public function save(Request $request, string $entity): JsonResponse
    {
        $e = $this->entity($entity);
        $data = $request->validate(['rows' => ['present', 'array', 'max:20000'], 'rows.*' => ['array'], 'dry_run' => ['boolean'], 'country' => ['nullable', 'string', 'max:2']]);
        $dry = (bool) ($data['dry_run'] ?? false);
        try {
            $report = $this->exchange->import([$e->key() => array_values($data['rows'])], new ImportOptions(
                dryRun: $dry, country: $data['country'] ?? null, source: 'admin', userId: $request->user()->id,
                filename: 'таблица · '.$e->label(), format: 'grid', allowDelete: true,
            ));
        } catch (Throwable $ex) {
            return response()->json(['message' => $ex->getMessage()], 422);
        }

        return response()->json(['dry_run' => $dry] + $report->toArray(), options: JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function entity(string $key): Entity
    {
        return $this->registry->find($key) ?? abort(404, 'Неизвестная сущность');
    }

    /** Колонки из полей сущности: тип, обязательность, варианты, подсказка, русское имя (первый кириллический алиас). */
    private function columns(Entity $e): array
    {
        $out = [];
        foreach ($e->fields() as $name => $f) {
            $ru = collect($f['aliases'] ?? [])->first(fn ($a) => preg_match('/\p{Cyrillic}/u', $a));
            $out[] = array_filter([
                'key' => $name, 'type' => $f[0], 'required' => $f['required'] ?? false, 'values' => $f['values'] ?? null,
                'hint' => $f['desc'] ?? null, 'ru' => $ru, 'aliases' => array_values(array_filter([...($f['aliases'] ?? [])], 'is_string')),
                'example' => isset($f['example']) ? (is_array($f['example']) ? (array_is_list($f['example']) ? implode('|', $f['example']) : json_encode($f['example'], JSON_UNESCAPED_UNICODE)) : (string) $f['example']) : null,
            ], fn ($v) => $v !== null && $v !== [] && $v !== false);
        }

        return $out;
    }

    /** Подсказки для ячеек-ссылок: страна, банк, вклад, платёжная система. */
    private function options(Entity $e, ?string $country): array
    {
        $fields = $e->fields();
        $opts = [];
        if (isset($fields['country'])) {
            $opts['country'] = Country::query()->ordered()->pluck('code')->all();
        }
        if (isset($fields['bank'])) {
            $opts['bank'] = array_column($this->banks($country), 'code');
        }
        if (isset($fields['product'])) {
            $opts['product'] = DepositProduct::query()->when($country, fn ($q) => $q->whereHas('bank.country', fn ($w) => $w->where('code', strtoupper($country))))->orderBy('slug')->pluck('slug')->all();
        }
        if (isset($fields['payment_system'])) {
            $opts['payment_system'] = PaymentSystem::query()->ordered()->pluck('code')->all();
        }
        foreach ($fields as $name => $f) {
            if ($f[0] === 'enum') {
                $opts[$name] = $f['values'] ?? [];
            } elseif ($f[0] === 'bool') {
                $opts[$name] = ['да', 'нет'];
            } elseif ($f[0] === 'ref' && $name === 'currency') {
                $opts[$name] = Country::query()->pluck('currency')->unique()->values()->all();
            }
        }

        return $opts;
    }

    private function banks(?string $country): array
    {
        return Bank::query()->when($country, fn ($q) => $q->whereHas('country', fn ($w) => $w->where('code', strtoupper($country))))
            ->orderBy('sort')->orderBy('name')->get(['code', 'name', 'short_name'])->map(fn ($b) => ['code' => $b->code, 'name' => $b->short_name ?: $b->name])->all();
    }
}
