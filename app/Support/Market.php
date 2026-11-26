<?php

namespace App\Support;

use App\Enums\DepositType;
use App\Models\DepositRate;
use Illuminate\Support\Collection;
use Throwable;

/**
 * A13 · Срез рынка вкладов: действующие ставки, лидеры по типам.
 * Используется главной (HomeController) и бегущей строкой в шапке (layouts.app).
 * Кешируется на время запроса.
 */
class Market
{
    private ?Collection $rates = null;

    /** Действующие ставки активных продуктов, по убыванию. */
    public function rates(): Collection
    {
        if ($this->rates !== null) {
            return $this->rates;
        }
        if (! Installation::isInstalled()) {
            return $this->rates = collect();
        }

        try {
            return $this->rates = DepositRate::query()->current()
                ->with('product.bank')
                ->whereHas('product', fn ($q) => $q->active())
                ->orderByDesc('rate')
                ->get();
        } catch (Throwable) {
            return $this->rates = collect();
        }
    }

    /** Лучшая ставка по каждому типу вклада. */
    public function leaders(): Collection
    {
        return collect(DepositType::cases())->map(fn (DepositType $type) => [
            'type' => $type,
            'rate' => $this->rates()->first(fn ($r) => $r->product->type === $type),
            'count' => $this->rates()->filter(fn ($r) => $r->product->type === $type)->count(),
        ]);
    }

    /** Топ: по одной ставке на пару «банк + тип». */
    public function top(int $limit = 8): Collection
    {
        return $this->rates()->unique(fn ($r) => $r->product->bank_id.'-'.$r->product->type->value)->take($limit)->values();
    }
}
