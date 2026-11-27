<?php

namespace App\Services\Cards;

use App\Enums\CardKind;
use Illuminate\Http\Request;

/** A33 · Фильтры каталога карт — в URL: ?kind[]=credit&system[]=visa&feature[]=apple_pay&free=1&sort=cashback. */
final class CardFilters
{
    public const SORTS = [
        'score' => 'По оценке Banq',
        'cashback' => 'По кэшбэку',
        'fee' => 'Сначала дешёвые',
        'grace' => 'По льготному периоду',
        'balance' => 'По проценту на остаток',
        'bank' => 'По рейтингу банка',
    ];

    /**
     * @param  list<CardKind>  $kinds
     * @param  list<string>  $systems  slug платёжных систем
     * @param  list<string>  $banks  slug банков
     * @param  list<string>  $features  ключи config('banq.cards.features')
     */
    public function __construct(
        public readonly array $kinds = [],
        public readonly array $systems = [],
        public readonly array $banks = [],
        public readonly array $features = [],
        public readonly ?string $currency = null,
        public readonly ?string $segment = null,
        public readonly bool $free = false,
        public readonly bool $cashback = false,
        public readonly bool $online = false,
        public readonly string $sort = 'score',
        public readonly ?string $q = null,
    ) {}

    public static function fromRequest(Request $r): self
    {
        $list = fn (string $k) => array_values(array_unique(array_filter(array_map('strval', (array) $r->query($k, [])))));
        $sort = (string) $r->query('sort', 'score');
        $segment = (string) $r->query('segment');

        return new self(
            kinds: collect($list('kind'))->map(fn ($k) => CardKind::tryFrom($k))->filter()->values()->all(),
            systems: $list('system'),
            banks: $list('bank'),
            features: array_values(array_intersect($list('feature'), array_keys(config('banq.cards.features')))),
            currency: preg_match('/^[A-Z]{3}$/', $c = strtoupper((string) $r->query('currency'))) ? $c : null,
            segment: array_key_exists($segment, \App\Models\Card::SEGMENTS) ? $segment : null,
            free: $r->boolean('free'),
            cashback: $r->boolean('cashback'),
            online: $r->boolean('online'),
            sort: array_key_exists($sort, self::SORTS) ? $sort : 'score',
            q: trim((string) $r->query('q')) ?: null,
        );
    }

    public function hasKind(CardKind $k): bool
    {
        return in_array($k, $this->kinds, true);
    }

    /** Только кредитные — тогда в карточке на первом плане льготный период и ставка. */
    public function creditOnly(): bool
    {
        return $this->kinds === [CardKind::Credit];
    }

    /** Параметры URL текущей подборки (+ замены): для ссылок сортировки и «сбросить одно». */
    public function query(array $override = []): array
    {
        $q = array_filter([
            'kind' => array_map(fn ($k) => $k->value, $this->kinds), 'system' => $this->systems, 'bank' => $this->banks, 'feature' => $this->features,
            'currency' => $this->currency, 'segment' => $this->segment, 'free' => $this->free ?: null, 'cashback' => $this->cashback ?: null,
            'online' => $this->online ?: null, 'sort' => $this->sort === 'score' ? null : $this->sort, 'q' => $this->q,
        ]);

        return array_filter(array_replace($q, $override), fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    public function activeCount(): int
    {
        return count($this->kinds) + count($this->systems) + count($this->banks) + count($this->features)
            + ($this->currency ? 1 : 0) + ($this->segment ? 1 : 0) + (int) $this->free + (int) $this->cashback + (int) $this->online + ($this->q ? 1 : 0);
    }
}
