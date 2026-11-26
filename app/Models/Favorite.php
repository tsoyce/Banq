<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A28 · Избранное пользователя: вклад (deposit) или банк (bank). Типы — morph map в AppServiceProvider. */
class Favorite extends Model
{
    public const TYPES = ['deposit' => DepositProduct::class, 'bank' => Bank::class];

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function favoritable(): MorphTo
    {
        return $this->morphTo();
    }
}
