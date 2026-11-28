<?php

namespace App\Services\Compare\Types;

use App\Models\DepositProduct;
use App\Services\Compare\CompareType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** A53 · Вклады: страница сравнения — своя (A14, deposits.compare: калькулятор дохода, гарантия). */
class DepositCompare extends CompareType
{
    public function key(): string { return 'deposits'; }

    public function label(): string { return 'Вклады'; }

    public function genitive(): string { return 'вкладов'; }

    public function forms(): array { return ['вклад', 'вклада', 'вкладов']; }

    public function icon(): string { return 'piggy'; }

    public function store(): string { return 'compare'; }

    public function storageKey(): string { return 'banq.compare'; }

    public function catalogUrl(): string { return route('deposits.index'); }

    public function hint(): string { return 'Ставка, ГЭСВ, доход под вашу сумму и срок, пополнение и снятие, гарантия'; }

    public function generic(): bool { return false; }

    public function items(Collection $ids): Collection
    {
        return $this->ordered(DepositProduct::query()->active()->whereIn('id', $ids)->with('bank.country')->get(), $ids);
    }

    public function stored(Collection $ids): Collection
    {
        return $this->ordered(DepositProduct::query()->whereIn('id', $ids)->with('bank')->get(), $ids);
    }

    /** @param DepositProduct $item */
    public function head(Model $item): array
    {
        return [
            'id' => $item->id, 'code' => Str::limit($item->bank->code, 4, ''), 'color' => $item->bank->brand_color ?? '#8b5cf6', 'name' => $item->bank->displayName(),
            'title' => $item->name, 'sub' => $item->bank->displayName(), 'url' => route('deposits.show', $item), 'active' => (bool) $item->is_active,
        ];
    }

    public function defaultTitle(Collection $items): string
    {
        return $items->map(fn ($p) => $p->bank->code)->unique()->join(' · ');
    }

    public function url(array $ids = [], array $query = []): string
    {
        return route('deposits.compare', array_filter(array_merge(['ids' => implode(',', $ids)], $query), fn ($v) => $v !== null && $v !== ''));
    }
}
