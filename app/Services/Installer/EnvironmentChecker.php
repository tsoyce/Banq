<?php

namespace App\Services\Installer;

/** A03 · Шаг 1 мастера: проверка PHP, расширений и прав на запись. */
class EnvironmentChecker
{
    public const MIN_PHP = '8.3.0';

    public const EXTENSIONS = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'zip', 'gd', 'intl'];

    /** @return list<array{group:string,name:string,ok:bool,hint:?string}> */
    public function checks(): array
    {
        $rows = [[
            'group' => 'PHP',
            'name' => 'PHP ≥ '.self::MIN_PHP.' (сейчас '.PHP_VERSION.')',
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'hint' => 'В BrainyCP: Сайты → PHP-версия → 8.3',
        ]];

        foreach (self::EXTENSIONS as $ext) {
            $rows[] = ['group' => 'Расширения', 'name' => $ext, 'ok' => extension_loaded($ext), 'hint' => "Включи расширение {$ext} в настройках PHP"];
        }

        foreach (['.env' => base_path('.env'), 'storage/' => storage_path(), 'bootstrap/cache/' => base_path('bootstrap/cache'), 'public/uploads/' => public_path('uploads')] as $label => $path) {
            $writable = file_exists($path) ? is_writable($path) : is_writable(dirname($path));
            $rows[] = ['group' => 'Права на запись', 'name' => $label, 'ok' => $writable, 'hint' => "chmod -R ug+rw {$label}"];
        }

        return $rows;
    }

    public function passes(): bool
    {
        return collect($this->checks())->every(fn ($c) => $c['ok']);
    }
}
