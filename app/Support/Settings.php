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
