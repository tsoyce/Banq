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
