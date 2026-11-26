<?php

namespace App\Services\Ratings;

use App\Enums\RatingKind;
use App\Models\Bank;
use App\Models\Rating;

/** A18 · Три оценки одного банка + места в рейтингах и изменение к прошлому снимку. */
final class BankScore
{
    /** @var array<string, int|null> место по виду рейтинга */
    public array $ranks = [];

    /** @var array<string, float|null> изменение к прошлой оценке, баллы */
    public array $deltas = [];

    public function __construct(
        public readonly Bank $bank,
        public readonly ?Rating $author,
        public readonly array $objective,
        public readonly ?array $people,
    ) {}

    public function score(RatingKind $kind): ?float
    {
        return match ($kind) {
            RatingKind::Author => $this->author ? (float) $this->author->score : null,
            RatingKind::Objective => $this->objective['score'] ?? null,
            RatingKind::People => $this->people['score'] ?? null,
        };
    }

    public function rank(RatingKind $kind): ?int
    {
        return $this->ranks[$kind->value] ?? null;
    }

    public function delta(RatingKind $kind): ?float
    {
        return $this->deltas[$kind->value] ?? null;
    }
}
