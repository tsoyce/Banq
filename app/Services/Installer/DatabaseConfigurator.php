<?php

namespace App\Services\Installer;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/** A03 · Шаг 2 мастера: проверка подключения к БД и применение настроек на лету. */
class DatabaseConfigurator
{
    /** @return array{ok:bool,message:string,version:?string} */
    public function test(array $db): array
    {
        try {
            $pdo = $this->pdo($db);
            $version = $pdo->query($db['driver'] === 'sqlite' ? 'select sqlite_version()' : 'select version()')->fetchColumn() ?: null;

            return ['ok' => true, 'message' => 'Подключение успешно', 'version' => $version ? (string) $version : null];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->humanize($e->getMessage()), 'version' => null];
        }
    }

    /** Подключает Laravel к указанной БД в текущем запросе (до перезагрузки .env). */
    public function apply(array $db): void
    {
        $name = $db['driver'] === 'sqlite' ? 'sqlite' : 'mysql';
        $base = Config::get("database.connections.{$name}");
        Config::set("database.connections.{$name}", array_merge($base, $name === 'sqlite'
            ? ['database' => $this->sqlitePath($db['database'])]
            : ['host' => $db['host'], 'port' => $db['port'], 'database' => $db['database'], 'username' => $db['username'], 'password' => $db['password'], 'prefix' => $db['prefix'] ?? '']));
        Config::set('database.default', $name);
        DB::purge($name);
    }

    public function envValues(array $db): array
    {
        if ($db['driver'] === 'sqlite') {
            return ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->sqlitePath($db['database'])];
        }

        return [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['host'],
            'DB_PORT' => $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'] ?? '',
            'DB_PREFIX' => $db['prefix'] ?? '',
        ];
    }

    private function pdo(array $db): PDO
    {
        if ($db['driver'] === 'sqlite') {
            $path = $this->sqlitePath($db['database']);
            if (! is_file($path)) {
                touch($path);
            }

            return new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }

        return new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
            $db['username'], $db['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
    }

    private function sqlitePath(?string $file): string
    {
        $file = $file ?: 'database.sqlite';

        return str_starts_with($file, '/') ? $file : database_path($file);
    }

    private function humanize(string $msg): string
    {
        return match (true) {
            str_contains($msg, 'Access denied') => 'Неверный логин или пароль пользователя БД.',
            str_contains($msg, 'Unknown database') => 'База с таким именем не найдена — создай её в BrainyCP (Базы данных → MySQL).',
            str_contains($msg, 'getaddrinfo'), str_contains($msg, 'Connection refused'), str_contains($msg, 'No such file') => 'Сервер БД недоступен — проверь хост и порт (обычно localhost:3306).',
            default => $msg,
        };
    }
}
