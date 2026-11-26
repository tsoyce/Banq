<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A28 · Сохранённое сравнение вкладов: набор id + сумма и срок, открывается ссылкой /deposits/compare. */
class SavedComparison extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['product_ids' => 'array', 'amount' => 'integer', 'term_months' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Вклады в исходном порядке (неактивные и удалённые пропускаются). */
    public function products(): Collection
    {
        $ids = array_map('intval', $this->product_ids ?? []);

        return DepositProduct::query()->whereIn('id', $ids)->with('bank')->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids, true))->values();
    }

    public function url(): string
    {
        return route('deposits.compare', array_filter([
            'ids' => implode(',', $this->product_ids ?? []),
            'amount' => $this->amount,
            'term' => $this->term_months,
        ]));
    }
}
