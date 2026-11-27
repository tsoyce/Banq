<?php

namespace App\Services\Ratings;

use App\Enums\DepositType;
use App\Models\Bank;
use App\Support\Market;
use Illuminate\Support\Collection;

/**
 * A18 · Объективный рейтинг банка — считается только из данных портала, без мнений.
 *
 * Четыре критерия, каждый 0…1, итог = 5 × Σ(вес × критерий), веса — config('banq.ratings.objective_weights'):
 *  - rates       ставки: лучшая ставка банка в ₸ по каждому типу вклада относительно рынка (мин → 0, макс → 1);
 *                тип, которого у банка нет, даёт 0. Среднее по всем типам, которые есть на рынке;
 *  - reliability надёжность: участие в КФГД (0.4), размер активов в лог-шкале к лидеру (0.4), возраст до 30 лет (0.2).
 *                Неизвестные части (нет активов/года) не штрафуют — веса остальных частей нормируются;
 *  - range       широта линейки: доля типов вкладов (несрочный/срочный/сберегательный), которые есть у банка;
 *  - terms       удобство: доля вкладов с онлайн-открытием (0.6) + есть вклад со снятием (0.4).
 *
 * Тот же текст — в методике на /ratings (ratings/index.blade.php #method).
 */
class ObjectiveRating
{
    public const CRITERIA = [
        'rates' => ['Ставки', 'Лучшие ставки в ₸ по каждому типу вклада относительно рынка'],
        'reliability' => ['Надёжность', 'КФГД, размер активов, возраст банка'],
        'range' => ['Линейка', 'Сколько типов вкладов предлагает банк'],
        'terms' => ['Удобство', 'Онлайн-открытие и вклады со снятием'],
    ];

    private ?Collection $result = null;

    public function __construct(private Market $market) {}

    /**
     * @return Collection<int, array{score: float, criteria: array<string, float>, has_data: bool}> по bank_id
     */
    public function all(Collection $banks): Collection
    {
        if ($this->result !== null) {
            return $this->result;
        }

        $rates = $this->market->rates()->filter(fn ($r) => $r->currency === ($r->product->bank?->country?->currency ?? 'KZT') && $r->product->bank?->status === 'active');
        // лучшая ставка: [тип][bank_id] => ставка
        $best = [];
        foreach ($rates as $r) {
            $t = $r->product->type->value;
            $best[$t][$r->product->bank_id] = max($best[$t][$r->product->bank_id] ?? 0, (float) $r->rate);
        }
        $marketTypes = array_keys($best);
        $products = $rates->pluck('product')->unique('id')->groupBy('bank_id');
        $maxAssets = (float) $banks->max(fn (Bank $b) => (float) $b->assets_bn);
        $weights = config('banq.ratings.objective_weights');

        return $this->result = $banks->mapWithKeys(function (Bank $bank) use ($best, $marketTypes, $products, $maxAssets, $weights) {
            $own = $products->get($bank->id, collect());

            $rateParts = array_map(function ($t) use ($best, $bank) {
                if (! isset($best[$t][$bank->id])) {
                    return 0.0;
                }
                [$lo, $hi] = [min($best[$t]), max($best[$t])];

                return $hi > $lo ? ($best[$t][$bank->id] - $lo) / ($hi - $lo) : 1.0;
            }, $marketTypes);

            $types = $own->pluck('type')->unique(fn ($t) => $t->value);
            $criteria = [
                'rates' => $rateParts ? array_sum($rateParts) / count($rateParts) : 0.0,
                'reliability' => self::weighted([
                    [$bank->deposit_insured ? 1.0 : 0.0, 0.4],
                    [$maxAssets > 0 && $bank->assets_bn > 0 ? log1p((float) $bank->assets_bn) / log1p($maxAssets) : null, 0.4],
                    [$bank->founded_year ? min(30, max(0, (int) date('Y') - $bank->founded_year)) / 30 : null, 0.2],
                ]),
                'range' => $types->count() / count(DepositType::cases()),
                'terms' => $own->isEmpty() ? 0.0 : self::weighted([
                    [$own->filter(fn ($p) => $p->online_opening)->count() / $own->count(), 0.6],
                    [$own->contains(fn ($p) => $p->can_withdraw) ? 1.0 : 0.0, 0.4],
                ]),
            ];
            $criteria = array_map(fn ($v) => round($v, 3), $criteria);
            $score = 5 * array_sum(array_map(fn ($k) => ($weights[$k] ?? 0) * $criteria[$k], array_keys($criteria)));

            return [$bank->id => ['score' => round($score, 2), 'criteria' => $criteria, 'has_data' => $own->isNotEmpty()]];
        });
    }

    /** Взвешенное среднее; части со значением null пропускаются, веса остальных нормируются. */
    private static function weighted(array $parts): float
    {
        $parts = array_filter($parts, fn ($p) => $p[0] !== null);
        $w = array_sum(array_column($parts, 1));

        return $w > 0 ? array_sum(array_map(fn ($p) => $p[0] * $p[1], $parts)) / $w : 0.0;
    }
}
