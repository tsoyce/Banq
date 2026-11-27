<?php

namespace App\Services\Ticker;

use App\Support\CurrentCountry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A40 · Бегущая строка в шапке витрины: какие типы контента и в каком порядке — настройка
 * `ticker.sources` (админка «Бегущая строка»); типы — config('banq.ticker.sources').
 * Ошибка одного типа не роняет страницу: группа просто пропускается.
 */
class Ticker
{
    /** @return array<string, TickerSource> */
    public function sources(): array
    {
        return collect(config('banq.ticker.sources'))->map(fn ($class) => app($class))->all();
    }

    public function enabled(): bool
    {
        return (bool) settings('ticker.enabled', true);
    }

    /** Выбранные типы по порядку (только существующие). */
    public function selected(): array
    {
        return array_values(array_intersect((array) settings('ticker.sources', []), array_keys(config('banq.ticker.sources'))));
    }

    /** Секунд на полный круг: больше элементов — дольше, скорость чтения одинаковая. */
    public function duration(array $groups): int
    {
        $n = array_sum(array_map(fn ($g) => count($g['items']) + ($g['title'] ? 1 : 0), $groups));
        $speed = max(1, min(10, (int) settings('ticker.speed', 5))); // 1 — медленно, 10 — быстро

        return (int) max(15, round($n * (14 - $speed) * 0.55));
    }

    /** @return list<array{key: string, title: ?string, href: ?string, items: list<array>}> */
    public function groups(): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $country = app(CurrentCountry::class)->get();
        $sources = $this->sources();
        $groups = [];
        foreach ($this->selected() as $key) {
            try {
                $g = $sources[$key]->group($country);
            } catch (Throwable $e) {
                Log::warning('A40 · лента: тип '.$key.' — '.$e->getMessage());
                $g = null;
            }
            if ($g && ! empty($g['items'])) {
                $groups[] = ['key' => $key] + $g;
            }
        }

        return $groups;
    }
}
