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
