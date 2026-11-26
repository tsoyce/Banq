<?php

namespace App\Services\Banks;

use App\Models\Bank;
use App\Support\Market;
use Illuminate\Support\Collection;

/**
 * A18 · Справочник банков для витрины: по каждому активному банку — лучшая ставка в ₸,
 * число вкладов с действующими ставками и какие типы вкладов есть. Источник — App\Support\Market.
 */
class BankDirectory
{
    public function __construct(private Market $market) {}

    /** @return Collection<int, array{best: ?\App\Models\DepositRate, offers: int, types: list<\App\Enums\DepositType>}> по bank_id */
    public function stats(): Collection
    {
        return $this->market->rates()
            ->filter(fn ($r) => $r->product->bank?->status === 'active')
            ->groupBy(fn ($r) => $r->product->bank_id)
            ->map(fn (Collection $rates) => [
                'best' => $rates->where('currency', 'KZT')->sortByDesc(fn ($r) => (float) $r->rate)->first(),
                'offers' => $rates->pluck('deposit_product_id')->unique()->count(),
                'types' => $rates->pluck('product.type')->unique(fn ($t) => $t->value)->sortBy(fn ($t) => $t->value)->values()->all(),
            ]);
    }

    public function for(Bank $bank): array
    {
        return $this->stats()->get($bank->id, ['best' => null, 'offers' => 0, 'types' => []]);
    }
}
