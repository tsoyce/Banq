<?php

namespace App\Services\Ratings;

use App\Enums\RatingKind;
use App\Models\Bank;
use App\Models\Rating;
use Illuminate\Support\Collection;

/**
 * A18 · Табло рейтингов: собирает три вида оценок по всем активным банкам.
 * Авторская — последняя запись ratings(kind=author); объективная и народная — считаются вживую,
 * а изменение (▲▼) берётся из последнего снимка до сегодняшнего дня (snapshot(), команда banq:ratings-snapshot).
 * Регистрируется как scoped — один расчёт на запрос.
 */
class RatingBoard
{
    private ?Collection $scores = null;

    public function __construct(private ObjectiveRating $objective, private PeopleRating $people) {}

    /** @return Collection<int, BankScore> по bank_id, порядок — справочник */
    public function all(): Collection
    {
        if ($this->scores !== null) {
            return $this->scores;
        }

        $banks = Bank::query()->active()->orderBy('sort')->orderBy('name')->get();
        $objective = $this->objective->all($banks);
        $people = $this->people->all();
        $authors = $this->latest(RatingKind::Author);
        $before = collect(RatingKind::cases())->mapWithKeys(fn ($k) => [$k->value => $this->latest($k, beforeToday: true)]);

        $scores = $banks->mapWithKeys(fn (Bank $b) => [$b->id => new BankScore($b, $authors->get($b->id), $objective[$b->id], $people->get($b->id))]);

        foreach (RatingKind::cases() as $kind) {
            $ranked = $scores->filter(fn (BankScore $s) => $s->score($kind) !== null && ($kind !== RatingKind::Objective || $s->objective['has_data']))
                ->sortByDesc(fn (BankScore $s) => [$s->score($kind), $kind === RatingKind::People ? $s->people['count'] : 0])->values();
            foreach ($ranked as $i => $s) {
                $s->ranks[$kind->value] = $i + 1;
                $prev = $kind === RatingKind::Author
                    ? $this->previousAuthor($s->author)
                    : $before[$kind->value]->get($s->bank->id)?->score;
                $s->deltas[$kind->value] = $prev === null ? null : round($s->score($kind) - (float) $prev, 2);
            }
        }

        return $this->scores = $scores;
    }

    public function for(Bank $bank): ?BankScore
    {
        return $this->all()->get($bank->id);
    }

    /** @return Collection<int, BankScore> лидеры вида рейтинга */
    public function top(RatingKind $kind, ?int $limit = null): Collection
    {
        $list = $this->all()->filter(fn (BankScore $s) => $s->rank($kind) !== null)->sortBy(fn (BankScore $s) => $s->rank($kind))->values();

        return $limit ? $list->take($limit) : $list;
    }

    /** Сохранить сегодняшний снимок объективного и народного рейтинга (история для ▲▼ и графиков). */
    public function snapshot(): int
    {
        $n = 0;
        foreach ($this->all() as $s) {
            if ($s->objective['has_data']) {
                Rating::updateOrCreate(
                    ['bank_id' => $s->bank->id, 'kind' => RatingKind::Objective, 'period' => today()->toDateString()],
                    ['score' => $s->objective['score'], 'breakdown' => $s->objective['criteria']],
                );
                $n++;
            }
            if ($s->people) {
                Rating::updateOrCreate(
                    ['bank_id' => $s->bank->id, 'kind' => RatingKind::People, 'period' => today()->toDateString()],
                    ['score' => $s->people['score'], 'votes' => $s->people['count'], 'breakdown' => $s->people['dist']],
                );
                $n++;
            }
        }

        return $n;
    }

    public function forget(): void
    {
        $this->scores = null;
        $this->people->forget();
    }

    /** Последняя запись вида по каждому банку (по дате). */
    private function latest(RatingKind $kind, bool $beforeToday = false): Collection
    {
        return Rating::query()->where('kind', $kind)
            ->when($beforeToday, fn ($q) => $q->where('period', '<', today()->toDateString()))
            ->orderBy('period')->orderBy('id')->get()->keyBy('bank_id');
    }

    private function previousAuthor(?Rating $current): ?float
    {
        if (! $current) {
            return null;
        }
        $prev = Rating::query()->where('kind', RatingKind::Author)->where('bank_id', $current->bank_id)
            ->where('period', '<', $current->period->toDateString())->orderByDesc('period')->value('score');

        return $prev === null ? null : (float) $prev;
    }
}
