<?php

namespace App\Models;

use App\Enums\DepositType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepositProduct extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'type' => DepositType::class,
        'currencies' => 'array',
        'conditions' => 'array',
        'can_replenish' => 'boolean',
        'can_withdraw' => 'boolean',
        'online_opening' => 'boolean',
        'is_active' => 'boolean',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(DepositRate::class);
    }

    public function currentRates(): HasMany
    {
        return $this->rates()->whereNull('valid_to');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
