<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A05 · Настройки сайта из таблицы settings, с кешем.
 * До установки (или без БД) отдаёт значения по умолчанию из defaults().
 * Хелперы: settings('site.name'), project_years().
 */
class Settings
{
    public const CACHE_KEY = 'banq.settings';

    private ?array $loaded = null;

    public static function defaults(): array
    {
        return [
            'site.name' => 'TSOY.IN',
            'site.tagline' => 'Banq of Bank — всё о банках',
            'site.description' => 'Ставки по депозитам, карты, кредиты, рейтинги и новости банков.',
            'site.logo' => null,
            'site.favicon' => null,
            'site.founded_year' => (int) date('Y'),
            'site.copyright' => 'TSOY.IN',
            'site.footer_text' => 'Информация носит справочный характер. Точные условия уточняйте в банке.',
            'site.contact_email' => null,
            'site.social' => [],
            'site.counters' => null,
            'theme.default' => 'light',
            // A40 · Бегущая строка и курсы валют
            'ticker.enabled' => true,
            'ticker.sources' => ['currency', 'deposit_rates'],
            'ticker.text' => null,
            'ticker.speed' => 5,
            'fx.currencies' => [],       // {"KZ": ["USD","EUR","RUB","CNY"]} — пусто: config('banq.fx.defaults')
            'fx.home_card' => true,
            // A55 · Автор, соцсети, донаты (админка → Сайт → «Автор и соцсети»)
            'author.user_id' => null,
            'author.headline' => 'Основатель TSOY.IN',
            'author.slogan' => 'My Unlimited Story',
            'author.about' => null,
            'social.enabled' => true,
            'social.networks' => [
                ['network' => 'youtube', 'url' => 'https://www.youtube.com/channel/UC0l5mO3-_SB6x9jOFkwTQtQ', 'followers' => 1000, 'visible' => true],
                ['network' => 'telegram', 'url' => 'https://t.me/tsoyin', 'followers' => null, 'visible' => true],
                ['network' => 'x', 'url' => 'https://x.com/tsoyce', 'followers' => null, 'visible' => true],
                ['network' => 'vk', 'url' => 'https://vk.com/tsoyin', 'followers' => null, 'visible' => true],
                ['network' => 'instagram', 'url' => 'https://www.instagram.com/tsoyce', 'followers' => null, 'visible' => true],
                ['network' => 'facebook', 'url' => 'https://facebook.com/tsoyce', 'followers' => null, 'visible' => true],
            ],
            'social.counts' => [],       // {network: {count, at, source, error?}} — SocialStats::refresh
            'social.keys' => [],         // {youtube, telegram, vk} — ключи API (иначе .env)
            'social.refreshed_at' => null,
            'donate.enabled' => true,
            'donate.title' => 'Поддержать проект',
            'donate.text' => 'TSOY.IN делается без рекламы банков и платных мест в рейтингах. Если материалы помогли выбрать вклад или карту — поддержите автора: это время на новые обзоры и проверку данных.',
            'donate.items' => [
                ['label' => 'Т-Банк', 'currency' => 'RUB', 'url' => 'https://tbank.ru/cf/LVYLwfut21', 'details' => null, 'note' => 'Картой любого банка России'],
                ['label' => 'ForteBank', 'currency' => 'KZT', 'url' => 'https://p2p.forte.kz/operations/transfer/card/9013648101', 'details' => null, 'note' => 'Перевод на карту ForteBank'],
            ],
        ];
    }

    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $stored = [];
        if (Installation::isInstalled()) {
            try {
                $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());
            } catch (Throwable) {
                $stored = [];
            }
        }

        return $this->loaded = array_replace(self::defaults(), $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default) ?? $default;
    }

    public function set(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    /** «Годы жизни проекта»: 2026 или 2026–2031. */
    public function years(): string
    {
        $from = (int) $this->get('site.founded_year', date('Y'));
        $now = (int) date('Y');

        return $from >= $now ? (string) $now : $from.'–'.$now;
    }
}
