<?php

namespace App\Services\Compare\Types;

use App\Enums\RatingKind;
use App\Models\ServicePackage;
use App\Services\Compare\CompareType;
use App\Services\Ratings\RatingBoard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** A53 · Пакеты услуг: цена в месяц и год, условие бесплатности, привилегии, карты в пакете, рейтинг банка. */
class PackageCompare extends CompareType
{
    public function __construct(private RatingBoard $board) {}

    public function key(): string { return 'packages'; }

    public function label(): string { return 'Пакеты услуг'; }

    public function genitive(): string { return 'пакетов услуг'; }

    public function forms(): array { return ['пакет', 'пакета', 'пакетов']; }

    public function icon(): string { return 'gift'; }

    public function store(): string { return 'packageCompare'; }

    public function storageKey(): string { return 'banq.packages.compare'; }

    public function catalogUrl(): string { return route('packages.index'); }

    public function hint(): string { return 'Стоимость, условие бесплатности, привилегии, карты в пакете, рейтинг банка'; }

    public function items(Collection $ids): Collection
    {
        return $this->ordered(ServicePackage::query()->active()->whereIn('id', $ids)->with(['bank.country', 'cards' => fn ($q) => $q->where('is_active', true)])->get(), $ids);
    }

    public function stored(Collection $ids): Collection
    {
        return $this->ordered(ServicePackage::query()->whereIn('id', $ids)->with('bank')->get(), $ids);
    }

    /** @param ServicePackage $item */
    public function head(Model $item): array
    {
        return [
            'id' => $item->id, 'code' => $item->bank->mark(), 'color' => $item->color ?? $item->bank->brand_color ?? '#8b5cf6', 'name' => $item->name,
            'title' => $item->name, 'sub' => $item->bank->displayName(), 'url' => route('packages.show', $item), 'active' => (bool) $item->is_active,
        ];
    }

    public function rows(Collection $items): array
    {
        $sign = fn (ServicePackage $p) => currency_symbol($p->bank->country?->currency);
        $fee = fn (?float $v, ServicePackage $p) => $v === null ? '—' : ($v == 0 ? 'бесплатно' : money_format($v, $sign($p)));
        $monthly = fn (ServicePackage $p) => $p->monthly_fee !== null ? (float) $p->monthly_fee : ($p->annual_fee !== null ? (float) $p->annual_fee / 12 : null);
        // Все привилегии всех пакетов — строка на каждую: сразу видно, чего у кого нет
        $perks = $items->flatMap(fn (ServicePackage $p) => $p->perks ?? [])->map(fn ($s) => trim((string) $s))->filter()->unique(fn ($s) => mb_strtolower($s))->values();
        $has = fn (ServicePackage $p, string $perk) => collect($p->perks ?? [])->contains(fn ($s) => mb_strtolower(trim((string) $s)) === mb_strtolower($perk));
        $score = fn (ServicePackage $p, RatingKind $k) => $this->board->for($p->bank)?->score($k);

        return [
            ['group' => 'Стоимость'],
            ['label' => 'В месяц', 'value' => fn (ServicePackage $p) => $fee($monthly($p), $p), 'num' => $monthly, 'dir' => 'min', 'big' => true],
            ['label' => 'В год', 'value' => fn (ServicePackage $p) => $fee($p->yearlyFee(), $p), 'num' => fn (ServicePackage $p) => $p->yearlyFee(), 'dir' => 'min'],
            ['label' => 'Бесплатно, если', 'value' => fn (ServicePackage $p) => $p->fee_note ?: '—'],
            ['label' => 'Сегмент', 'value' => fn (ServicePackage $p) => $p->segmentLabel()],

            ['group' => 'Что входит'],
            ['label' => 'Привилегий', 'value' => fn (ServicePackage $p) => (string) count($p->perks ?? []), 'num' => fn (ServicePackage $p) => count($p->perks ?? []) ?: null, 'dir' => 'max'],
            ...$perks->map(fn ($perk) => ['label' => $perk, 'value' => fn (ServicePackage $p) => $has($p, $perk) ? '✓' : '—', 'num' => fn (ServicePackage $p) => $has($p, $perk) ? 1 : 0, 'dir' => 'max', 'small' => true])->all(),
            ['label' => 'Карты в пакете', 'value' => fn (ServicePackage $p) => $p->cards->pluck('name')->join(', ') ?: '—', 'num' => fn (ServicePackage $p) => $p->cards->count() ?: null, 'dir' => 'max'],

            ['group' => 'Банк'],
            ['label' => 'Объективный рейтинг', 'value' => fn (ServicePackage $p) => score_format($score($p, RatingKind::Objective)), 'num' => fn (ServicePackage $p) => $score($p, RatingKind::Objective), 'dir' => 'max'],
            ['label' => 'Народный рейтинг', 'value' => fn (ServicePackage $p) => score_format($score($p, RatingKind::People)), 'num' => fn (ServicePackage $p) => $score($p, RatingKind::People), 'dir' => 'max'],
        ];
    }

    public function note(): ?string
    {
        return 'Цены — из опубликованных тарифов банков. Если банк указывает только годовую цену, в месяц — её двенадцатая часть.';
    }
}
