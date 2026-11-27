<?php

namespace App\Services\Cards;

use App\Models\Card;
use App\Services\Ratings\BankScore;

/** A33 · Карта в каталоге: модель + «Оценка Banq» (с разбивкой) + оценки банка. */
final class CardOffer
{
    public function __construct(
        public readonly Card $card,
        public readonly float $score,
        public readonly array $parts,
        public readonly ?BankScore $bankScore,
    ) {}
}
