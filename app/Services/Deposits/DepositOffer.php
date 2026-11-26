<?php

namespace App\Services\Deposits;

use App\Models\DepositProduct;
use App\Models\DepositRate;

/** A14 · Строка каталога: вклад + лучшая из его действующих ставок под условия фильтра. */
final class DepositOffer
{
    public function __construct(
        public readonly DepositProduct $product,
        public readonly DepositRate $rate,
        public readonly int $ratesCount,
        public readonly ?float $income = null,
    ) {}

    public function bank()
    {
        return $this->product->bank;
    }
}
