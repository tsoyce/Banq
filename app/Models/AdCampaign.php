<?php

namespace App\Models;

use App\Services\Ads\AdServer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A51 · Рекламная кампания: период, страны, места, приоритет, частота показа одному посетителю, бюджет показов/кликов.
 * Счётчики impressions/clicks — итог (по дням — ad_stats).
 */
class AdCampaign extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'countries' => 'array', 'slots' => 'array',
        'priority' => 'integer', 'freq_cap' => 'integer', 'max_impressions' => 'integer', 'max_clicks' => 'integer',
        'impressions' => 'integer', 'clicks' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => AdServer::flush());
        static::deleted(fn () => AdServer::flush());
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(AdCreative::class, 'campaign_id');
    }

    /** Состояние для админки: идёт, запланирована, завершена, на паузе, бюджет исчерпан. */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'paused',
            $this->starts_at && $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at && $this->ends_at->isPast() => 'ended',
            $this->max_impressions && $this->impressions >= $this->max_impressions,
            $this->max_clicks && $this->clicks >= $this->max_clicks => 'spent',
            default => 'running',
        };
    }

    public function stateLabel(): string
    {
        return ['paused' => 'на паузе', 'scheduled' => 'запланирована', 'ended' => 'завершена', 'spent' => 'бюджет исчерпан', 'running' => 'идёт'][$this->state()];
    }

    public function ctr(): ?float
    {
        return $this->impressions ? round($this->clicks / $this->impressions * 100, 2) : null;
    }
}
