<?php

namespace App\Services\Ticker\Sources;

use App\Models\Country;
use App\Services\Ticker\TickerSource;
use App\Support\Market;

/** A40 · Лента: лучшие ставки вкладов (как было в A13). */
class DepositRatesSource implements TickerSource
{
    public function __construct(private Market $market) {}

    public function label(): string { return 'Ставки вкладов'; }

    public function hint(): string { return 'Лучшие ставки банков по типам вкладов — по одной на банк и тип'; }

    public function group(?Country $country): ?array
    {
        $top = $this->market->top(14);
        if ($top->isEmpty()) {
            return null;
        }

        return [
            'title' => 'Вклады',
            'href' => route('deposits.index'),
            'items' => $top->map(fn ($r) => [
                'label' => $r->product->bank->code.' '.mb_strtolower($r->product->type->label()),
                'value' => rate_format($r->rate),
                'href' => route('deposits.show', $r->product),
            ])->all(),
        ];
    }
}
