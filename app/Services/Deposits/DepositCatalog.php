<?php

namespace App\Services\Deposits;

use App\Enums\DepositType;
use App\Models\Bank;
use App\Models\DepositProduct;
use App\Models\DepositRate;
use App\Support\CurrentCountry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A14 · Каталог вкладов: подбор по фильтрам, лучшая ставка вклада под условия, фасеты.
 * Используется DepositController (каталог, карточка, сравнение).
 *
 * Ставки без срока (term_months = null) или без порога суммы (amount_from = null) считаются
 * подходящими под любой срок/сумму — так приходят данные из Excel, где срок не указан.
 */
class DepositCatalog
{
    /** @return Collection<int, DepositOffer> */
    public function search(DepositFilters $f): Collection
    {
        $products = $this->productQuery($f)
            ->with(['bank', 'currentRates' => fn ($q) => $this->rateConditions($q, $f)])
            ->get();

        $offers = $products->map(function (DepositProduct $p) use ($f) {
            $best = $p->currentRates->sortByDesc(fn (DepositRate $r) => [(float) $r->rate, (float) $r->effective_rate])->first();
            if (! $best) {
                return null;
            }
            // Без суммы в фильтре доход считаем «с 1 млн ₸ за год» — чтобы сортировка по доходу работала всегда
            $income = Income::calc($f->amount ?? 1_000_000, $f->term ?? 12, (float) $best->rate, $best->effective_rate === null ? null : (float) $best->effective_rate);

            return new DepositOffer($p, $best, $p->currentRates->count(), $income);
        })->filter();

        return $this->sort($offers, $f->sort)->values();
    }

    /** Лучшая ставка конкретного вклада под сумму/срок/валюту (для сравнения). */
    public function bestRate(DepositProduct $product, DepositFilters $f): ?DepositRate
    {
        return $this->rateConditions($product->currentRates()->getQuery(), $f)
            ->orderByDesc('rate')->orderByDesc('effective_rate')->first();
    }

    /** Банки, у которых есть активные вклады с действующими ставками. */
    public function banks(): Collection
    {
        return Bank::query()->active()->inCountry()
            ->whereHas('depositProducts', fn ($q) => $q->active()->whereHas('currentRates'))
            ->withCount(['depositProducts as offers_count' => fn ($q) => $q->active()->whereHas('currentRates')])
            ->orderBy('sort')->orderBy('name')->get();
    }

    /** Валюты страны, в которых есть действующие ставки (основная валюта страны — всегда). A21 */
    public function currencies(): array
    {
        $country = app(CurrentCountry::class);
        $have = DepositRate::query()->current()->whereHas('product.bank', fn ($b) => $b->inCountry())->distinct()->pluck('currency')->all();
        $order = $country->get()?->currencyList() ?? config('banq.currencies');

        return array_values(array_unique([$country->currency(), ...array_intersect($order, $have), ...$have]));
    }

    /** Сколько вкладов каждого типа — для подписей в фильтре. */
    public function typeCounts(): array
    {
        $counts = DepositProduct::query()->active()->whereHas('currentRates')->whereHas('bank', fn ($b) => $b->inCountry())
            ->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');

        return collect(DepositType::cases())->mapWithKeys(fn ($t) => [$t->value => (int) ($counts[$t->value] ?? 0)])->all();
    }

    /**
     * Сетка ставок вклада: валюта → строки (порог суммы) × колонки (срок).
     *
     * @return array<string, array{terms: list<?int>, amounts: list<?float>, cells: array<string, DepositRate>, best: ?DepositRate}>
     */
    public function matrix(DepositProduct $product): array
    {
        return $product->currentRates->groupBy('currency')
            ->sortBy(fn ($_, $cur) => array_search($cur, $product->bank?->country?->currencyList() ?? config('banq.currencies'), true))
            ->map(function (Collection $rates) {
                $terms = $rates->pluck('term_months')->unique()->sort()->values()->all();
                $amounts = $rates->map(fn ($r) => $r->amount_from === null ? null : (float) $r->amount_from)->unique()->sort()->values()->all();

                return [
                    'terms' => $terms,
                    'amounts' => $amounts,
                    'cells' => $rates->keyBy(fn ($r) => ($r->amount_from === null ? '' : (float) $r->amount_from).'|'.($r->term_months ?? ''))->all(),
                    'best' => $rates->sortByDesc(fn ($r) => (float) $r->rate)->first(),
                ];
            })->all();
    }

    /**
     * История лучшей ставки вклада в валюте: точки на каждую дату изменения + сегодня.
     *
     * @return list<array{date: Carbon, rate: float}>
     */
    public function history(DepositProduct $product, ?string $currency = null): array
    {
        $currency ??= $product->bank?->country?->currency ?? 'KZT';
        $rates = $product->rates()->where('currency', $currency)->orderBy('valid_from')->get();
        $dates = $rates->pluck('valid_from')->push(today())->unique(fn ($d) => $d->toDateString())->sort()->values();

        return $dates->map(function ($d) use ($rates) {
            $active = $rates->filter(fn ($r) => $r->valid_from->lte($d) && ($r->valid_to === null || $r->valid_to->gt($d)));

            return $active->isEmpty() ? null : ['date' => $d, 'rate' => (float) $active->max('rate')];
        })->filter()->values()->all();
    }

    /** Похожие вклады: тот же тип, другие банки, лучшие ставки. */
    public function similar(DepositProduct $product, int $limit = 4): Collection
    {
        return $this->search(new DepositFilters(types: [$product->type]))
            ->reject(fn (DepositOffer $o) => $o->product->bank_id === $product->bank_id)
            ->unique(fn (DepositOffer $o) => $o->product->bank_id)
            ->take($limit)->values();
    }

    private function productQuery(DepositFilters $f): Builder
    {
        return DepositProduct::query()->active()
            ->whereHas('bank', fn ($q) => $q->active()->inCountry()) // A21
            ->when($f->types, fn ($q) => $q->whereIn('type', array_map(fn ($t) => $t->value, $f->types)))
            ->when($f->banks, fn ($q) => $q->whereHas('bank', fn ($b) => $b->whereIn('slug', $f->banks)))
            ->when($f->replenish, fn ($q) => $q->where('can_replenish', true))
            ->when($f->withdraw, fn ($q) => $q->where('can_withdraw', true))
            ->when($f->online, fn ($q) => $q->where('online_opening', true))
            ->when($f->amount, fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('min_amount')->orWhere('min_amount', '<=', $f->amount))
                ->where(fn ($w) => $w->whereNull('max_amount')->orWhere('max_amount', '>=', $f->amount)))
            ->when($f->term, fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('term_min')->orWhere('term_min', '<=', $f->term))
                ->where(fn ($w) => $w->whereNull('term_max')->orWhere('term_max', '>=', $f->term)))
            ->when($f->q, function ($q) use ($f) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $f->q).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhereHas('bank', fn ($b) => $b
                    ->where('name', 'like', $like)->orWhere('short_name', 'like', $like)->orWhere('code', 'like', $like)));
            })
            ->whereHas('currentRates', fn ($q) => $this->rateConditions($q, $f));
    }

    private function rateConditions($q, DepositFilters $f)
    {
        return $q->where('currency', $f->currency)
            ->when($f->term, fn ($q) => $q->where(fn ($w) => $w->whereNull('term_months')->orWhere('term_months', $f->term)))
            ->when($f->amount, fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('amount_from')->orWhere('amount_from', '<=', $f->amount))
                ->where(fn ($w) => $w->whereNull('amount_to')->orWhere('amount_to', '>=', $f->amount)));
    }

    private function sort(Collection $offers, string $sort): Collection
    {
        return match ($sort) {
            'effective' => $offers->sortByDesc(fn (DepositOffer $o) => [(float) ($o->rate->effective_rate ?? $o->rate->rate), (float) $o->rate->rate]),
            'income' => $offers->sortByDesc(fn (DepositOffer $o) => [$o->income ?? 0, (float) $o->rate->rate]),
            'bank' => $offers->sortBy(fn (DepositOffer $o) => [mb_strtolower($o->bank()->displayName()), $o->product->type->value]),
            default => $offers->sortByDesc(fn (DepositOffer $o) => [(float) $o->rate->rate, (float) $o->rate->effective_rate]),
        };
    }
}
