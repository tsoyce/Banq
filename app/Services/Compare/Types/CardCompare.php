<?php

namespace App\Services\Compare\Types;

use App\Models\Card;
use App\Services\Compare\CompareType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** A53 · Карты: страница сравнения — своя (A33, cards.compare: пластик, кредитные строки). */
class CardCompare extends CompareType
{
    public function key(): string { return 'cards'; }

    public function label(): string { return 'Карты'; }

    public function genitive(): string { return 'карт'; }

    public function forms(): array { return ['карта', 'карты', 'карт']; }

    public function icon(): string { return 'card'; }

    public function store(): string { return 'cardCompare'; }

    public function storageKey(): string { return 'banq.cards.compare'; }

    public function catalogUrl(): string { return route('cards.index'); }

    public function hint(): string { return 'Обслуживание, кэшбэк, процент на остаток, льготный период, платёжные системы'; }

    public function generic(): bool { return false; }

    public function limit(): int { return (int) config('banq.cards.compare_limit', 4); }

    public function items(Collection $ids): Collection
    {
        return $this->ordered(Card::query()->active()->whereIn('id', $ids)->with('bank')->get(), $ids);
    }

    public function stored(Collection $ids): Collection
    {
        return $this->ordered(Card::query()->whereIn('id', $ids)->with('bank')->get(), $ids);
    }

    /** @param Card $item */
    public function head(Model $item): array
    {
        return [
            'id' => $item->id, 'code' => $item->bank->mark(), 'color' => $item->color ?? $item->bank->brand_color ?? '#8b5cf6', 'name' => $item->name,
            'title' => $item->name, 'sub' => $item->bank->displayName(), 'url' => route('cards.show', $item), 'active' => (bool) $item->is_active,
        ];
    }

    public function url(array $ids = [], array $query = []): string
    {
        return route('cards.compare', array_filter(array_merge(['ids' => implode(',', $ids)], $query), fn ($v) => $v !== null && $v !== ''));
    }
}
