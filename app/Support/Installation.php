<?php

namespace App\Support;

/**
 * A03 · Состояние установки. Признак — файл storage/app/installed.json
 * (пишет мастер установки на последнем шаге). Путь переопределяется config('banq.install_lock').
 */
class Installation
{
    public static function lockPath(): string
    {
        return config('banq.install_lock') ?: storage_path('app/installed.json');
    }

    public static function isInstalled(): bool
    {
        return is_file(self::lockPath());
    }

    public static function markInstalled(array $meta = []): void
    {
        file_put_contents(self::lockPath(), json_encode(
            ['installed_at' => now()->toIso8601String()] + $meta,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));
    }

    public static function info(): array
    {
        return self::isInstalled() ? (json_decode((string) file_get_contents(self::lockPath()), true) ?: []) : [];
    }
}
