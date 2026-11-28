<?php

namespace App\Models;

use App\Services\Ads\AdServer;
use Illuminate\Database\Eloquent\Model;

/**
 * A51 · Рекламное место. Ключ (home-wide, sidebar…) стоит в шаблоне: @include('ads.slot', ['slot' => 'sidebar']).
 * Размеры — пропорции резерва (место не «прыгает» при загрузке). Если своих объявлений нет — fallback_code (сеть).
 */
class AdSlot extends Model
{
    public const FORMATS = ['image' => 'Баннер-картинка', 'native' => 'Нативная карточка', 'html' => 'HTML (в изолированном окне)'];

    protected $guarded = ['id'];

    protected $casts = ['formats' => 'array', 'is_active' => 'boolean', 'width' => 'integer', 'height' => 'integer', 'm_width' => 'integer', 'm_height' => 'integer'];

    protected static function booted(): void
    {
        static::saved(fn () => AdServer::flush());
        static::deleted(fn () => AdServer::flush());
    }

    public function accepts(string $format): bool
    {
        return in_array($format, $this->formats ?: array_keys(self::FORMATS), true);
    }
}
