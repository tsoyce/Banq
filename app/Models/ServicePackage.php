<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A30 · Пакет услуг банка (тариф/подписка): стоимость, условие бесплатности, привилегии, входящие карты. */
class ServicePackage extends Model
{
    public const SEGMENTS = ['mass' => 'Базовый', 'premium' => 'Премиальный', 'private' => 'Private banking', 'business' => 'Для бизнеса'];

    protected $guarded = ['id'];

    protected $casts = ['perks' => 'array', 'conditions' => 'array', 'is_active' => 'boolean', 'monthly_fee' => 'decimal:2', 'annual_fee' => 'decimal:2', 'sort' => 'integer'];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->whereHas('bank', fn ($b) => $b->where('status', 'active'));
    }

    public function scopeInCountry(Builder $q, Country|int|null $country = null): Builder
    {
        return $q->whereHas('bank', fn ($b) => $b->inCountry($country));
    }

    public function segmentLabel(): string
    {
        return self::SEGMENTS[$this->segment] ?? $this->segment;
    }

    public function yearlyFee(): ?float
    {
        if ($this->annual_fee !== null) {
            return (float) $this->annual_fee;
        }

        return $this->monthly_fee !== null ? (float) $this->monthly_fee * 12 : null;
    }
}
