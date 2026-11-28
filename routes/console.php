<?php

use App\Services\Ratings\RatingBoard;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A18 · Ежедневный снимок объективного и народного рейтинга (история для ▲▼). Cron: php artisan schedule:run
Artisan::command('banq:ratings-snapshot', function (RatingBoard $board) {
    $this->info('Снимок рейтингов: '.$board->snapshot().' записей за '.today()->toDateString());
})->purpose('Сохранить сегодняшний объективный и народный рейтинг банков');

Schedule::command('banq:ratings-snapshot')->dailyAt('03:10');

// A40 · Официальные курсы валют центробанков (НБРК для KZ). Без cron курс всё равно обновится при первом заходе за день.
Artisan::command('banq:fx-sync {--country= : код страны (KZ); по умолчанию все активные с источником} {--days=0 : догрузить историю за N дней} {--date= : дата Y-m-d}', function (App\Services\Currency\CurrencyRates $fx) {
    $countries = App\Models\Country::query()->active()->get()
        ->filter(fn ($c) => $fx->provider($c) && (! $this->option('country') || $c->code === strtoupper($this->option('country'))));
    if ($countries->isEmpty()) {
        $this->warn('Нет стран с источником курсов');

        return 1;
    }
    $code = 0;
    foreach ($countries as $c) {
        try {
            if ((int) $this->option('days') > 0) {
                $this->info($c->code.': загружено дат — '.$fx->backfill($c, (int) $this->option('days')));
            } else {
                $n = $fx->sync($c, $this->option('date') ? Illuminate\Support\Carbon::parse($this->option('date')) : null);
                $this->info($c->code.': '.$n.' курсов на '.$fx->latestDate($c)?->toDateString().' ('.$fx->provider($c)->short().')');
            }
        } catch (Throwable $e) {
            $this->error($c->code.': '.$e->getMessage());
            $code = 1;
        }
    }

    return $code;
})->purpose('Загрузить официальные курсы валют центробанков');

Schedule::command('banq:fx-sync')->hourlyAt(7)->withoutOverlapping();

// A55 · Счётчики подписчиков соцсетей (YouTube, Telegram, VK, GitHub) — App\Services\Social\SocialStats
Artisan::command('banq:social-refresh', function (App\Services\Social\SocialStats $social) {
    foreach ($social->refresh() as $network => $r) {
        $r['error'] ? $this->warn($network.': '.$r['error']) : $this->info($network.': '.($r['count'] ?? 'нет ключа — ручное число'));
    }
})->purpose('Обновить счётчики подписчиков соцсетей автора');
Schedule::command('banq:social-refresh')->dailyAt('04:20')->withoutOverlapping();
