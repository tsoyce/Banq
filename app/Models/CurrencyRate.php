<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A40 · Официальный курс валюты на дату (App\Services\Currency\CurrencyRates). */
class CurrencyRate extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'nominal' => 'integer', 'rate' => 'float', 'change' => 'float'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** Курс за одну единицу валюты. */
    public function perUnit(): float
    {
        return $this->rate / max(1, $this->nominal);
    }
}
