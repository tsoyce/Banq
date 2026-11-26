<?php

// A05 · Глобальные хелперы (подключены через composer.json → autoload.files).

use App\Models\Country;
use App\Support\CurrentCountry;
use App\Support\Settings;

if (! function_exists('settings')) {
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key, $default);
    }
}

if (! function_exists('project_years')) {
    function project_years(): string
    {
        return app(Settings::class)->years();
    }
}

if (! function_exists('rate_format')) {
    function rate_format(float|string|null $rate): string
    {
        return $rate === null ? '—' : rtrim(rtrim(number_format((float) $rate, 2, ',', ' '), '0'), ',').'%';
    }
}

if (! function_exists('plural')) {
    /** A14 · Русское склонение по числу: plural(5, 'вклад', 'вклада', 'вкладов') → «вкладов». */
    function plural(int|float $n, string $one, string $few, string $many): string
    {
        $n = abs((int) $n) % 100;
        $d = $n % 10;

        return match (true) {
            $n > 10 && $n < 20 => $many,
            $d === 1 => $one,
            $d >= 2 && $d <= 4 => $few,
            default => $many,
        };
    }
}

if (! function_exists('score_format')) {
    /** A18 · Оценка рейтинга 0–5: 4.623 → «4,6». */
    function score_format(float|string|null $score, int $decimals = 1): string
    {
        return $score === null ? '—' : number_format((float) $score, $decimals, ',', '');
    }
}

if (! function_exists('money_format')) {
    /** A18 · Деньги по DESIGN §3.3: 1180394 → «1 180 394 ₸» (неразрывные пробелы). A21: знак по умолчанию — валюта текущей страны. */
    function money_format(float|int|string|null $v, ?string $sign = null): string
    {
        $sign ??= app(CurrentCountry::class)->symbol();

        return $v === null ? '—' : number_format((float) $v, 0, ',', "\u{202F}")."\u{00A0}".$sign;
    }
}

if (! function_exists('country')) {
    /** A21 · Текущая страна витрины (App\Models\Country) или null, пока стран нет. */
    function country(): ?Country
    {
        return app(CurrentCountry::class)->get();
    }
}
