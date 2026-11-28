<?php

namespace App\Models;

use App\Services\Ads\Snippets;
use Illuminate\Database\Eloquent\Model;

/**
 * A50 · Вставка кода стороннего сервиса: счётчик, метрика, тег рекламной сети или свой HTML.
 * Для готовых сервисов (gtag, gtm, metrika, adsense) хранится только идентификатор — код собирается
 * App\Services\Ads\Snippets из проверенного шаблона; «Свой код» вставляется как есть (правит только admin).
 */
class CodeSnippet extends Model
{
    public const PROVIDERS = [
        'gtag' => 'Google Analytics 4 / Google Ads (gtag.js)',
        'gtm' => 'Google Tag Manager',
        'metrika' => 'Яндекс Метрика',
        'adsense' => 'Google AdSense',
        'custom' => 'Свой код (HTML)',
    ];

    /** Формат идентификатора у готовых сервисов. */
    public const ID_PATTERNS = [
        'gtag' => '/^(G|AW|GT|DC)-[A-Z0-9]{4,16}$/',
        'gtm' => '/^GTM-[A-Z0-9]{4,12}$/',
        'metrika' => '/^\d{5,12}$/',
        'adsense' => '/^ca-pub-\d{10,20}$/',
    ];

    public const PLACEMENTS = ['head' => 'В <head>', 'body_start' => 'Сразу после <body>', 'body_end' => 'Перед </body>'];

    protected $guarded = ['id'];

    protected $casts = ['countries' => 'array', 'skip_staff' => 'boolean', 'is_active' => 'boolean', 'sort' => 'integer'];

    protected static function booted(): void
    {
        static::saving(function (CodeSnippet $s) {
            $s->tracking_id = $s->tracking_id !== null ? trim($s->tracking_id) : null;
            if ($s->provider !== 'custom') {
                $s->code = null;
            }
        });
        static::saved(fn () => Snippets::flush());
        static::deleted(fn () => Snippets::flush());
    }
}
