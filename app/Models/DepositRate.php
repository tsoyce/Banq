<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ставка — запись в истории: старая закрывается valid_to, новая добавляется. */
class DepositRate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'rate' => 'decimal:3',
        'effective_rate' => 'decimal:3',
        'amount_from' => 'decimal:2',
        'amount_to' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(DepositProduct::class, 'deposit_product_id');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function scopeCurrent(Builder $q): Builder
    {
        return $q->whereNull('valid_to');
    }
}
