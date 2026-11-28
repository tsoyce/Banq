<?php

namespace App\Models;

use App\Services\Ads\AdServer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A51 · Объявление кампании: баннер (image + image_mobile), нативная карточка (заголовок, текст, картинка, кнопка)
 * или HTML (показывается в iframe sandbox — чужой код не трогает страницу). Метка «Реклама» + sponsor + disclosure.
 */
class AdCreative extends Model
{
    public const TYPES = ['native' => 'Нативная карточка', 'image' => 'Баннер-картинка', 'html' => 'HTML (только администратор)'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'weight' => 'integer', 'impressions' => 'integer', 'clicks' => 'integer'];

    protected static function booted(): void
    {
        static::saved(fn () => AdServer::flush());
        static::deleted(fn () => AdServer::flush());
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }

    public function ctr(): ?float
    {
        return $this->impressions ? round($this->clicks / $this->impressions * 100, 2) : null;
    }
}
