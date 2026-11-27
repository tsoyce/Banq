<?php

namespace App\Http\Controllers;

use App\Models\DepositProduct;
use App\Services\Deposits\DepositCatalog;
use App\Services\Deposits\DepositFilters;
use App\Support\CurrentCountry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * A14 · Витрина вкладов: каталог с фильтрами, карточка вклада, сравнение до 4 вкладов.
 * Данные — App\Services\Deposits\DepositCatalog. Состояние фильтров — в URL.
 */
class DepositController extends Controller
{
    public const COMPARE_LIMIT = 4;

    public function __construct(private DepositCatalog $catalog) {}

    public function index(Request $request): View|Response
    {
        $filters = DepositFilters::fromRequest($request);
        $offers = $this->catalog->search($filters);

        $data = [
            'filters' => $filters,
            'offers' => $offers,
            'banks' => $this->catalog->banks(),
            'currencies' => $this->catalog->currencies(),
            'typeCounts' => $this->catalog->typeCounts(),
            'terms' => config('banq.terms'),
        ];

        // Частичное обновление списка без перезагрузки (fetch из resources/js/app.js → depositFilters)
        if ($request->header('X-Partial') === 'results') {
            return response()->view('deposits._results', $data);
        }

        return view('deposits.index', $data);
    }

    public function show(DepositProduct $product): View
    {
        abort_unless($product->is_active && $product->bank?->status === 'active', 404);
        $product->load(['bank.country', 'currentRates']);
        app(CurrentCountry::class)->set($product->bank->country); // A21 · страница живёт в стране банка
        $matrix = $this->catalog->matrix($product);
        $currency = array_key_first($matrix) ?? $product->bank->country?->currency ?? 'KZT';

        return view('deposits.show', [
            'product' => $product,
            'matrix' => $matrix,
            'best' => $matrix[$currency]['best'] ?? null,
            'history' => $this->catalog->history($product, $currency),
            'similar' => $this->catalog->similar($product),
            'siblings' => $product->bank->depositProducts()->active()->whereKeyNot($product->id)
                ->whereHas('currentRates')->with('currentRates')->get(),
            'kdifLimit' => $product->bank->deposit_insured ? $product->bank->country?->guaranteeLimit($product->type->value, $currency) : null,
        ]);
    }

    public function compare(Request $request): View
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)->filter()->unique()->take(self::COMPARE_LIMIT)->values();
        $filters = DepositFilters::fromRequest($request);

        $products = DepositProduct::query()->active()->whereIn('id', $ids)->with('bank.country')->get()
            ->sortBy(fn ($p) => $ids->search($p->id))->values();
        $columns = $products->map(fn (DepositProduct $p) => ['product' => $p, 'rate' => $this->catalog->bestRate($p, $filters)]);

        return view('deposits.compare', [
            'columns' => $columns,
            'filters' => $filters,
            'limit' => self::COMPARE_LIMIT,
            'bestRate' => $columns->max(fn ($c) => (float) $c['rate']?->rate),
            'bestEffective' => $columns->max(fn ($c) => (float) $c['rate']?->effective_rate),
        ]);
    }
}
