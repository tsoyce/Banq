<?php

namespace App\Services\Ratings;

use App\Models\Review;
use Illuminate\Support\Collection;

/**
 * A18 · Народный рейтинг — из одобренных модератором отзывов (оценка 1–5).
 * Итог — байесовское среднее: (C × m + Σоценок) / (C + n), где C = people_prior_weight, m = people_prior_mean.
 * Так банк с одним отзывом «5» не обгоняет банк с сотней отзывов «4,6».
 */
class PeopleRating
{
    private ?Collection $result = null;

    /**
     * @return Collection<int, array{score: float, avg: float, count: int, dist: array<int, int>}> по bank_id
     */
    public function all(): Collection
    {
        if ($this->result !== null) {
            return $this->result;
        }

        $rows = Review::query()->approved()->selectRaw('bank_id, score, count(*) as c')->groupBy('bank_id', 'score')->get();
        $C = (float) config('banq.ratings.people_prior_weight', 5);
        $m = (float) config('banq.ratings.people_prior_mean', 3.5);

        return $this->result = $rows->groupBy('bank_id')->map(function (Collection $g) use ($C, $m) {
            $dist = array_fill_keys([5, 4, 3, 2, 1], 0);
            foreach ($g as $r) {
                $dist[(int) $r->score] = (int) $r->c;
            }
            $n = array_sum($dist);
            $sum = array_sum(array_map(fn ($s, $c) => $s * $c, array_keys($dist), $dist));

            return ['score' => round(($C * $m + $sum) / ($C + $n), 2), 'avg' => round($sum / $n, 2), 'count' => $n, 'dist' => $dist];
        });
    }

    public function forget(): void
    {
        $this->result = null;
    }
}
