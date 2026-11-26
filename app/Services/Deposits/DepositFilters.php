<?php

namespace App\Services\Deposits;

use App\Enums\DepositType;
use Illuminate\Http\Request;

/**
 * A14 · Фильтры каталога вкладов. Состояние живёт в URL (?type[]=term&currency=KZT&term=12&amount=1000000…),
 * чтобы подборку можно было отправить ссылкой и чтобы её видели поисковики.
 */
final class DepositFilters
{
    public const SORTS = [
        'rate' => 'По ставке',
        'effective' => 'По ГЭСВ',
        'income' => 'По доходу',
        'bank' => 'По банку',
    ];

    /**
     * @param  list<DepositType>  $types
     * @param  list<string>  $banks  slug банков
     */
    public function __construct(
        public readonly array $types = [],
        public readonly string $currency = 'KZT',
        public readonly ?int $term = null,
        public readonly ?float $amount = null,
        public readonly array $banks = [],
        public readonly bool $replenish = false,
        public readonly bool $withdraw = false,
        public readonly bool $online = false,
        public readonly string $sort = 'rate',
        public readonly ?string $q = null,
    ) {}

    public static function fromRequest(Request $r): self
    {
        $types = collect((array) $r->query('type', []))
            ->map(fn ($t) => DepositType::tryFrom((string) $t))->filter()->unique()->values()->all();
        $currency = strtoupper((string) $r->query('currency', 'KZT'));
        $term = (int) $r->query('term') ?: null;
        $amount = (float) preg_replace('/[^\d.]/', '', (string) $r->query('amount')) ?: null;
        $sort = (string) $r->query('sort', 'rate');

        return new self(
            types: $types,
            currency: in_array($currency, config('banq.currencies'), true) ? $currency : 'KZT',
            term: $term && $term <= 120 ? $term : null,
            amount: $amount && $amount < 1e13 ? $amount : null,
            banks: array_values(array_filter(array_map('strval', (array) $r->query('bank', [])))),
            replenish: $r->boolean('replenish'),
            withdraw: $r->boolean('withdraw'),
            online: $r->boolean('online'),
            sort: array_key_exists($sort, self::SORTS) ? $sort : 'rate',
            q: trim((string) $r->query('q')) ?: null,
        );
    }

    public function hasType(DepositType $t): bool
    {
        return in_array($t, $this->types, true);
    }

    /** Сколько фильтров включено (валюта KZT и сортировка не считаются). */
    public function activeCount(): int
    {
        return count($this->types) + count($this->banks) + ($this->currency !== 'KZT' ? 1 : 0)
            + ($this->term ? 1 : 0) + ($this->amount ? 1 : 0) + (int) $this->replenish + (int) $this->withdraw
            + (int) $this->online + ($this->q ? 1 : 0);
    }

    /** Параметры для ссылок; $override — что поменять. */
    public function query(array $override = []): array
    {
        return array_filter([
            'type' => array_map(fn (DepositType $t) => $t->value, $this->types),
            'currency' => $this->currency === 'KZT' ? null : $this->currency,
            'term' => $this->term,
            'amount' => $this->amount ? (int) $this->amount : null,
            'bank' => $this->banks,
            'replenish' => $this->replenish ? 1 : null,
            'withdraw' => $this->withdraw ? 1 : null,
            'online' => $this->online ? 1 : null,
            'sort' => $this->sort === 'rate' ? null : $this->sort,
            'q' => $this->q,
            ...$override,
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');
    }
}
