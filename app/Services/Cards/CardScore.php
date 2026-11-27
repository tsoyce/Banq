<?php

namespace App\Services\Cards;

use App\Enums\CardKind;
use App\Models\Card;
use Illuminate\Support\Collection;

/**
 * A33 · «Оценка Banq» карты 0–5 — объективная, из данных, относительно рынка страны.
 * Дебетовая: кэшбэк, стоимость, процент на остаток, возможности. Кредитная: льготный период, ставка, стоимость, кэшбэк.
 * Веса — config('banq.cards.score'). Методика показывается на странице карты.
 */
final class CardScore
{
    /** @var array{cashback: float, balance: float, grace: float, fee: float, rate_min: float, rate_max: float} */
    private array $market;

    /** @param Collection<int, Card> $cards — рынок для сравнения (активные карты страны) */
    public function __construct(Collection $cards)
    {
        $fees = $cards->map->yearlyFee()->filter(fn ($v) => $v !== null);
        $rates = $cards->pluck('credit_rate')->filter()->map(fn ($v) => (float) $v);
        $this->market = [
            'cashback' => max(1.0, (float) $cards->max('cashback_max')),
            'balance' => max(1.0, (float) $cards->max('balance_rate')),
            'grace' => max(30.0, (float) $cards->max('grace_days')),
            'fee' => max(1.0, (float) $fees->max()),
            'rate_min' => (float) ($rates->min() ?? 0),
            'rate_max' => (float) ($rates->max() ?? 0),
        ];
    }

    /** @return array{score: float, parts: array<string, array{label: string, value: float, weight: float}>} */
    public function for(Card $c): array
    {
        $fee = $c->yearlyFee();
        $feeScore = $fee === null ? 0.5 : max(0.0, 1 - $fee / $this->market['fee']);
        $features = min(1.0, count($c->features ?? []) / 6);
        $cashback = min(1.0, (float) $c->cashback_max / $this->market['cashback']);

        $parts = $c->kind === CardKind::Credit
            ? [
                'grace' => ['Льготный период', min(1.0, (float) $c->grace_days / $this->market['grace'])],
                'rate' => ['Ставка', $c->credit_rate === null ? 0.4 : ($this->market['rate_max'] > $this->market['rate_min']
                    ? 1 - ((float) $c->credit_rate - $this->market['rate_min']) / ($this->market['rate_max'] - $this->market['rate_min']) : 0.7)],
                'fee' => ['Стоимость', $feeScore],
                'cashback' => ['Кэшбэк', $cashback],
            ]
            : [
                'cashback' => ['Кэшбэк', $cashback],
                'fee' => ['Стоимость', $feeScore],
                'balance' => ['Процент на остаток', min(1.0, (float) $c->balance_rate / $this->market['balance'])],
                'features' => ['Возможности', $features],
            ];

        $weights = config('banq.cards.score.'.($c->kind === CardKind::Credit ? 'credit' : 'debit'));
        $out = [];
        $sum = 0.0;
        foreach ($parts as $k => [$label, $v]) {
            $w = (float) ($weights[$k] ?? 0);
            $out[$k] = ['label' => $label, 'value' => round(max(0.0, min(1.0, $v)), 3), 'weight' => $w];
            $sum += $w * max(0.0, min(1.0, $v));
        }

        return ['score' => round(1 + 4 * $sum, 2), 'parts' => $out];
    }
}
