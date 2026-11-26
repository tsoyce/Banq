<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A18 · Отзыв о банке. Виден на сайте и входит в народный рейтинг только после одобрения модератором. */
class Review extends Model
{
    public const STATUSES = ['pending' => 'на модерации', 'approved' => 'опубликован', 'rejected' => 'отклонён'];

    protected $guarded = ['id'];

    protected $casts = [
        'score' => 'integer',
        'helpful' => 'integer',
        'moderated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DepositProduct::class, 'deposit_product_id');
    }

    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'review_votes')->withPivot('created_at');
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
