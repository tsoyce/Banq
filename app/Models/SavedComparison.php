<?php

namespace App\Models;

use App\Services\Compare\CompareRegistry;
use App\Services\Compare\CompareType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A28 · Сохранённое сравнение: набор id + (для вкладов) сумма и срок.
 * A53 · type — вид продукта из CompareRegistry (deposits, cards, packages, banks, payment-systems).
 */
class SavedComparison extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['type' => 'deposits'];

    protected $casts = ['product_ids' => 'array', 'amount' => 'integer', 'term_months' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kind(): CompareType
    {
        $registry = app(CompareRegistry::class);

        return $registry->get((string) $this->type) ?? $registry->get('deposits');
    }

    /** Записи в исходном порядке (удалённые пропускаются, снятые — остаются с пометкой). */
    public function products(): Collection
    {
        return $this->kind()->stored(collect($this->product_ids ?? [])->map(fn ($id) => (int) $id));
    }

    /** Шапки для списка в кабинете: title, sub, code, color, url, active. */
    public function heads(): Collection
    {
        return $this->products()->map(fn ($m) => $this->kind()->head($m));
    }

    public function url(): string
    {
        return $this->kind()->url(array_map('intval', $this->product_ids ?? []), array_filter([
            'amount' => $this->amount,
            'term' => $this->term_months,
        ]));
    }
}
