<?php

// A05 · Глобальные хелперы (подключены через composer.json → autoload.files).

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
