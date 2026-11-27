<?php

namespace App\Services\Cards;

use App\Enums\CardKind;
use App\Enums\RatingKind;
use App\Models\Bank;
use App\Models\Card;
use App\Models\PaymentSystem;
use App\Services\Ratings\RatingBoard;
use Illuminate\Support\Collection;

/**
 * A33 · Витрина карт текущей страны: подбор по фильтрам, «Оценка Banq», счётчики фильтров, похожие.
 * Карт у страны — сотни, поэтому выборка одна на запрос (scoped), фильтры и сортировка — в памяти.
 */
class CardCatalog
{
    private ?Collection $cards = null;

    private ?CardScore $scorer = null;

    /** @var array<int, CardOffer> */
    private array $offers = [];

    public function __construct(private RatingBoard $board) {}

    /** @return Collection<int, Card> активные карты активных банков страны */
    public function all(): Collection
    {
        return $this->cards ??= Card::query()->active()->inCountry()
            ->with(['bank.country', 'paymentSystems', 'tiers'])->orderBy('sort')->orderBy('name')->get();
    }

    public function offer(Card $card): CardOffer
    {
        if (isset($this->offers[$card->id])) {
            return $this->offers[$card->id];
        }
        $this->scorer ??= new CardScore($this->all()->isEmpty() ? collect([$card]) : $this->all());
        ['score' => $score, 'parts' => $parts] = $this->scorer->for($card);

        return $this->offers[$card->id] = new CardOffer($card, $score, $parts, $card->bank ? $this->board->for($card->bank) : null);
    }

    /** @return Collection<int, CardOffer> */
    public function search(CardFilters $f): Collection
    {
        $q = $f->q ? mb_strtolower($f->q) : null;
        $list = $this->all()->filter(function (Card $c) use ($f, $q) {
            $systems = $c->paymentSystems->pluck('slug')->all();

            return (! $f->kinds || in_array($c->kind, $f->kinds, true))
                && (! $f->systems || array_intersect($f->systems, $systems))
                && (! $f->banks || in_array($c->bank->slug, $f->banks, true))
                && (! $f->features || ! array_diff($f->features, $c->features ?? []))
                && (! $f->currency || in_array($f->currency, $c->currencies ?? [], true))
                && (! $f->segment || $c->segment === $f->segment)
                && (! $f->free || $c->yearlyFee() === 0.0)
                && (! $f->cashback || (float) $c->cashback_max > 0)
                && (! $f->online || $c->online_opening)
                && (! $q || str_contains(mb_strtolower($c->name.' '.$c->bank->name.' '.$c->bank->short_name.' '.$c->paymentSystems->pluck('name')->implode(' ')), $q));
        })->map(fn (Card $c) => $this->offer($c));

        return $this->sort($list, $f->sort)->values();
    }

    /** @param Collection<int, CardOffer> $list */
    public function sort(Collection $list, string $sort): Collection
    {
        return match ($sort) {
            'cashback' => $list->sortByDesc(fn (CardOffer $o) => [(float) $o->card->cashback_max, $o->score]),
            'fee' => $list->sortBy(fn (CardOffer $o) => [$o->card->yearlyFee() ?? PHP_INT_MAX, -$o->score]),
            'grace' => $list->sortByDesc(fn (CardOffer $o) => [(int) $o->card->grace_days, $o->score]),
            'balance' => $list->sortByDesc(fn (CardOffer $o) => [(float) $o->card->balance_rate, $o->score]),
            'bank' => $list->sortByDesc(fn (CardOffer $o) => [$o->bankScore?->score(RatingKind::Objective) ?? 0, $o->score]),
            default => $list->sortByDesc(fn (CardOffer $o) => [$o->score, (float) $o->card->cashback_max]),
        };
    }

    /** @return array<string, int> вид → число карт */
    public function kindCounts(): array
    {
        return $this->all()->countBy(fn (Card $c) => $c->kind->value)->all();
    }

    /** @return Collection<int, PaymentSystem> системы с картами в стране (cards_here — сколько) */
    public function systems(): Collection
    {
        $counts = $this->all()->flatMap(fn (Card $c) => $c->paymentSystems->pluck('id')->unique())->countBy();

        return PaymentSystem::query()->active()->ordered()->whereIn('id', $counts->keys())->get()
            ->each(fn (PaymentSystem $p) => $p->setAttribute('cards_here', $counts[$p->id] ?? 0));
    }

    /** @return Collection<int, Bank> банки с картами (cards_here) */
    public function banks(): Collection
    {
        return $this->all()->groupBy('bank_id')->map(function (Collection $cards) {
            $b = $cards->first()->bank;
            $b->setAttribute('cards_here', $cards->count());

            return $b;
        })->sortBy(fn (Bank $b) => [$b->sort, $b->name])->values();
    }

    public function currencies(): array
    {
        $country = $this->all()->first()?->bank?->country;
        $all = $this->all()->flatMap(fn (Card $c) => $c->currencies ?? [])->unique()->values()->all();

        return array_values(array_unique([...array_intersect($country?->currencyList() ?? [], $all), ...$all]));
    }

    /** Крупные цифры для шапки раздела. */
    public function stats(): array
    {
        $all = $this->all();

        return [
            'count' => $all->count(),
            'banks' => $all->pluck('bank_id')->unique()->count(),
            'free' => $all->filter(fn (Card $c) => $c->yearlyFee() === 0.0)->count(),
            'cashback' => $all->sortByDesc(fn (Card $c) => (float) $c->cashback_max)->first(),
            'grace' => $all->where('kind', CardKind::Credit)->sortByDesc('grace_days')->first(),
            'balance' => $all->sortByDesc(fn (Card $c) => (float) $c->balance_rate)->first(),
        ];
    }

    /** @return Collection<int, CardOffer> того же вида в других банках, лучшие по оценке */
    public function similar(Card $card, int $limit = 4): Collection
    {
        return $this->all()->filter(fn (Card $c) => $c->kind === $card->kind && $c->bank_id !== $card->bank_id)
            ->map(fn (Card $c) => $this->offer($c))->sortByDesc(fn (CardOffer $o) => $o->score)->take($limit)->values();
    }

    /** Место карты среди карт того же вида (1 — лучшая). */
    public function rank(Card $card): ?int
    {
        $ids = $this->all()->filter(fn (Card $c) => $c->kind === $card->kind)
            ->map(fn (Card $c) => $this->offer($c))->sortByDesc(fn (CardOffer $o) => $o->score)->pluck('card.id')->values();
        $i = $ids->search($card->id);

        return $i === false ? null : $i + 1;
    }
}
