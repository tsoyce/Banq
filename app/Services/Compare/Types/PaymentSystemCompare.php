<?php

namespace App\Services\Compare\Types;

use App\Models\PaymentSystem;
use App\Services\Cards\CardCatalog;
use App\Services\Compare\CompareType;
use App\Support\Countries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** A53 · Платёжные системы: охват, уровни карт, кошельки, сколько карт и банков в выбранной стране. */
class PaymentSystemCompare extends CompareType
{
    public function __construct(private CardCatalog $catalog) {}

    public function key(): string { return 'payment-systems'; }

    public function label(): string { return 'Платёжные системы'; }

    public function genitive(): string { return 'платёжных систем'; }

    public function forms(): array { return ['система', 'системы', 'систем']; }

    public function icon(): string { return 'globe'; }

    public function store(): string { return 'systemCompare'; }

    public function storageKey(): string { return 'banq.systems.compare'; }

    public function catalogUrl(): string { return route('payment-systems.index'); }

    public function hint(): string { return 'Охват, уровни карт от Classic до топового, кошельки, карты и банки в вашей стране'; }

    public function items(Collection $ids): Collection
    {
        return $this->ordered(PaymentSystem::query()->active()->whereIn('id', $ids)->with('tiers')->get(), $ids);
    }

    public function stored(Collection $ids): Collection
    {
        return $this->ordered(PaymentSystem::query()->whereIn('id', $ids)->get(), $ids);
    }

    /** @param PaymentSystem $item */
    public function head(Model $item): array
    {
        return [
            'id' => $item->id, 'code' => $item->mark(), 'color' => $item->color ?? '#6d28d9', 'name' => $item->displayName(),
            'title' => $item->name, 'sub' => $item->scopeLabel(), 'url' => route('payment-systems.show', $item), 'active' => (bool) $item->is_active,
        ];
    }

    public function rows(Collection $items): array
    {
        // Карты и банки в каталоге текущей страны — по каждой системе
        $cards = $this->catalog->all();
        $here = fn (PaymentSystem $ps) => $cards->filter(fn ($c) => $c->paymentSystems->contains('id', $ps->id));
        $top = fn (PaymentSystem $ps) => $ps->tiers->sortByDesc('level')->first();
        $country = country();

        return [
            ['group' => 'Система'],
            ['label' => 'Охват', 'value' => fn (PaymentSystem $ps) => $ps->scopeLabel(), 'num' => fn (PaymentSystem $ps) => ['national' => 1, 'regional' => 2, 'international' => 3][$ps->scope] ?? null, 'dir' => 'max'],
            ['label' => 'Страна происхождения', 'value' => fn (PaymentSystem $ps) => Countries::name($ps->home_country) ?? '—'],
            ['label' => 'Национальная в', 'value' => fn (PaymentSystem $ps) => collect($ps->countries ?? [])->map(fn ($c) => Countries::name($c))->join(', ') ?: '—'],
            ['label' => 'Основана', 'value' => fn (PaymentSystem $ps) => $ps->founded_year ? $ps->founded_year.' г.' : '—', 'num' => fn (PaymentSystem $ps) => $ps->founded_year, 'dir' => 'min'],
            ['label' => 'Штаб-квартира', 'value' => fn (PaymentSystem $ps) => $ps->headquarters ?: '—'],

            ['group' => 'Уровни карт'],
            ['label' => 'Уровней', 'value' => fn (PaymentSystem $ps) => (string) $ps->tiers->count(), 'num' => fn (PaymentSystem $ps) => $ps->tiers->count() ?: null, 'dir' => 'max', 'big' => true],
            ['label' => 'Топовый уровень', 'value' => fn (PaymentSystem $ps) => $top($ps)?->name ?? '—'],
            ['label' => 'Все уровни', 'value' => fn (PaymentSystem $ps) => $ps->tiers->sortBy('level')->map(fn ($t) => trim(\Illuminate\Support\Str::after($t->name, $ps->name), ' -') ?: $t->name)->join(', ') ?: '—'],
            ['label' => 'Кошельки', 'value' => fn (PaymentSystem $ps) => collect($ps->wallets ?? [])->map(fn ($w) => config('banq.cards.wallets.'.$w, $w))->join(', ') ?: '—', 'num' => fn (PaymentSystem $ps) => count($ps->wallets ?? []) ?: null, 'dir' => 'max'],

            ['group' => $country ? 'Карты '.$country->locative() : 'На портале'],
            ['label' => 'Карт в каталоге', 'value' => fn (PaymentSystem $ps) => (string) $here($ps)->count(), 'num' => fn (PaymentSystem $ps) => $here($ps)->count() ?: null, 'dir' => 'max', 'big' => true],
            ['label' => 'Банков выпускают', 'value' => fn (PaymentSystem $ps) => (string) $here($ps)->pluck('bank_id')->unique()->count(), 'num' => fn (PaymentSystem $ps) => $here($ps)->pluck('bank_id')->unique()->count() ?: null, 'dir' => 'max'],
            ['label' => 'Сайт', 'value' => fn (PaymentSystem $ps) => $ps->website ? (parse_url($ps->website, PHP_URL_HOST) ?: $ps->website) : '—', 'href' => fn (PaymentSystem $ps) => $ps->website],
        ];
    }

    public function note(): ?string
    {
        return 'Карты и банки — из каталога выбранной страны. «Основана» — раньше значит дольше на рынке.';
    }
}
