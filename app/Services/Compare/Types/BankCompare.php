<?php

namespace App\Services\Compare\Types;

use App\Enums\RatingKind;
use App\Models\Bank;
use App\Services\Banks\BankDirectory;
use App\Services\Compare\CompareType;
use App\Services\Ratings\RatingBoard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** A53 · Банки: три рейтинга, лучшие ставки, линейка продуктов, надёжность и отзывы. */
class BankCompare extends CompareType
{
    public function __construct(private RatingBoard $board, private BankDirectory $directory) {}

    public function key(): string { return 'banks'; }

    public function label(): string { return 'Банки'; }

    public function genitive(): string { return 'банков'; }

    public function forms(): array { return ['банк', 'банка', 'банков']; }

    public function icon(): string { return 'bank'; }

    public function store(): string { return 'bankCompare'; }

    public function storageKey(): string { return 'banq.banks.compare'; }

    public function catalogUrl(): string { return route('banks.index'); }

    public function hint(): string { return 'Три рейтинга, лучшие ставки, сколько карт и пакетов, активы, гарантия, отзывы'; }

    public function items(Collection $ids): Collection
    {
        $found = Bank::query()->active()->whereIn('id', $ids)->with('country')
            ->withCount(['cards' => fn ($q) => $q->where('is_active', true), 'servicePackages' => fn ($q) => $q->where('is_active', true), 'reviews' => fn ($q) => $q->approved()])
            ->get();

        return $this->ordered($found, $ids);
    }

    public function stored(Collection $ids): Collection
    {
        return $this->ordered(Bank::query()->whereIn('id', $ids)->get(), $ids);
    }

    /** @param Bank $item */
    public function head(Model $item): array
    {
        return [
            'id' => $item->id, 'code' => $item->mark(), 'color' => $item->brand_color ?? '#8b5cf6', 'name' => $item->displayName(),
            'title' => $item->displayName(), 'sub' => $item->name !== $item->displayName() ? $item->name : ($item->legal_name ?: ''), 'url' => route('banks.show', $item),
            'active' => $item->status === 'active',
        ];
    }

    public function rows(Collection $items): array
    {
        $score = fn (Bank $b, RatingKind $k) => $this->board->for($b)?->score($k);
        $stat = fn (Bank $b) => $this->directory->for($b);
        $sign = fn (Bank $b) => currency_symbol($b->country?->currency);
        $bn = fn ($v, Bank $b) => $v === null ? '—' : number_format((float) $v, 1, ',', "\u{202F}").' млрд '.$sign($b);

        return [
            ['group' => 'Рейтинги'],
            ['label' => RatingKind::Objective->label(), 'value' => fn (Bank $b) => score_format($score($b, RatingKind::Objective)), 'num' => fn (Bank $b) => $score($b, RatingKind::Objective), 'dir' => 'max', 'big' => true],
            ['label' => RatingKind::People->label(), 'value' => fn (Bank $b) => score_format($score($b, RatingKind::People)), 'num' => fn (Bank $b) => $score($b, RatingKind::People), 'dir' => 'max', 'big' => true],
            ['label' => RatingKind::Author->label(), 'value' => fn (Bank $b) => score_format($score($b, RatingKind::Author)), 'num' => fn (Bank $b) => $score($b, RatingKind::Author), 'dir' => 'max'],
            ['label' => 'Место (объективный)', 'value' => fn (Bank $b) => ($r = $this->board->for($b)?->rank(RatingKind::Objective)) ? '#'.$r : '—', 'num' => fn (Bank $b) => $this->board->for($b)?->rank(RatingKind::Objective), 'dir' => 'min'],
            ['label' => 'Отзывов', 'value' => fn (Bank $b) => (string) (int) $b->reviews_count, 'num' => fn (Bank $b) => (int) $b->reviews_count ?: null, 'dir' => 'max'],

            ['group' => 'Продукты'],
            ['label' => 'Лучшая ставка по вкладу', 'value' => fn (Bank $b) => rate_format($stat($b)['best']?->rate), 'num' => fn (Bank $b) => $stat($b)['best'] ? (float) $stat($b)['best']->rate : null, 'dir' => 'max', 'big' => true],
            ['label' => 'Вкладов со ставками', 'value' => fn (Bank $b) => (string) $stat($b)['offers'], 'num' => fn (Bank $b) => $stat($b)['offers'] ?: null, 'dir' => 'max'],
            ['label' => 'Типы вкладов', 'value' => fn (Bank $b) => collect($stat($b)['types'])->map(fn ($t) => mb_strtolower($t->label()))->join(', ') ?: '—', 'num' => fn (Bank $b) => count($stat($b)['types']) ?: null, 'dir' => 'max'],
            ['label' => 'Карт', 'value' => fn (Bank $b) => (string) (int) $b->cards_count, 'num' => fn (Bank $b) => (int) $b->cards_count ?: null, 'dir' => 'max'],
            ['label' => 'Пакетов услуг', 'value' => fn (Bank $b) => (string) (int) $b->service_packages_count, 'num' => fn (Bank $b) => (int) $b->service_packages_count ?: null, 'dir' => 'max'],

            ['group' => 'Надёжность'],
            ['label' => 'Гарантия вкладов', 'value' => fn (Bank $b) => $b->deposit_insured ? '✓ '.($b->country?->guaranteeName() ?? '') : '—', 'num' => fn (Bank $b) => $b->deposit_insured ? 1 : 0, 'dir' => 'max'],
            ['label' => 'Активы', 'value' => fn (Bank $b) => $bn($b->assets_bn, $b), 'num' => fn (Bank $b) => $b->assets_bn === null ? null : (float) $b->assets_bn, 'dir' => 'max'],
            ['label' => 'Капитал', 'value' => fn (Bank $b) => $bn($b->capital_bn, $b), 'num' => fn (Bank $b) => $b->capital_bn === null ? null : (float) $b->capital_bn, 'dir' => 'max'],
            ['label' => 'Основан', 'value' => fn (Bank $b) => $b->founded_year ? $b->founded_year.' г.' : '—', 'num' => fn (Bank $b) => $b->founded_year, 'dir' => 'min'],
            ['label' => 'Лицензия', 'value' => fn (Bank $b) => $b->license ?: '—'],
            ['label' => 'Сайт', 'value' => fn (Bank $b) => $b->website ? (parse_url($b->website, PHP_URL_HOST) ?: $b->website) : '—', 'href' => fn (Bank $b) => $b->website],
        ];
    }

    public function note(): ?string
    {
        return 'Рейтинги пересчитываются при обновлении ставок и отзывов. «Основан» — раньше значит дольше на рынке. Лучшая ставка — в национальной валюте.';
    }
}
