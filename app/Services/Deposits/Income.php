<?php

namespace App\Services\Deposits;

/**
 * A14 · Доход по вкладу. Если известна ГЭСВ — считаем по ней (она уже учитывает капитализацию),
 * иначе по номинальной ставке с ежемесячной капитализацией. Тот же расчёт — в resources/js/app.js (depositIncome).
 */
final class Income
{
    public static function calc(float $amount, int $months, float $rate, ?float $effective = null, bool $capitalize = true): float
    {
        if ($amount <= 0 || $months <= 0) {
            return 0.0;
        }
        if ($effective !== null && $effective > 0) {
            return $amount * ((1 + $effective / 100) ** ($months / 12) - 1);
        }

        return $capitalize
            ? $amount * ((1 + $rate / 100 / 12) ** $months - 1)
            : $amount * $rate / 100 * $months / 12;
    }
}
