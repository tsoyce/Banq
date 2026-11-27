<?php

namespace App\Services\Database;

use App\Services\Installer\DatabaseConfigurator;
use App\Services\Installer\EnvWriter;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * A34 · Переключение базы сайта из админки: SQLite ⇄ MySQL.
 *
 * Порядок: проверка подключения → миграции на новой базе → перенос всех таблиц (по желанию)
 * → сверка числа строк → копия .env в storage/app/env-backups → запись .env.
 * Старая база не трогается, поэтому «Вернуть прежнюю» просто восстанавливает .env из копии.
 *
 * A38 · Список таблиц берётся только из текущей базы подключения: на MySQL getTables() без схемы
 * отдаёт таблицы всех баз сервера (так в перенос попадала чужая audit_logs → «Table doesn't exist»).
 * Миграции проверяются по коду выхода; после них все таблицы старой базы должны быть в новой;
 * ошибки SQL переводятся в понятный текст без дампа подключения.
 */
class DatabaseSwitcher
{
    public const TARGET = 'banq_target';

    public const KEYS = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_PREFIX'];

    /** Служебные таблицы, которые не переносятся (кеш и очередь пересоздаются сами). */
    public const SKIP = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    public function __construct(private DatabaseConfigurator $configurator) {}

    /** Что сейчас подключено: драйвер, база, версия, таблицы и строки. */
    public function current(): array
    {
        $name = Config::get('database.default');
        $cfg = Config::get("database.connections.{$name}", []);
        $info = [
            'driver' => $cfg['driver'] ?? $name,
            'database' => $cfg['database'] ?? '',
            'host' => ($cfg['driver'] ?? '') === 'sqlite' ? null : ($cfg['host'] ?? null).':'.($cfg['port'] ?? ''),
            'username' => $cfg['username'] ?? null,
            'prefix' => $cfg['prefix'] ?? '',
            'version' => null, 'tables' => [], 'rows' => 0, 'size' => null, 'error' => null,
        ];

        try {
            $conn = DB::connection($name);
            $info['version'] = $this->version($conn);
            $info['tables'] = $this->counts($conn);
            $info['rows'] = array_sum($info['tables']);
            if ($info['driver'] === 'sqlite' && is_file($info['database'])) {
                $info['size'] = filesize($info['database']);
            }
        } catch (Throwable $e) {
            $info['error'] = $e->getMessage();
        }

        return $info;
    }

    public function test(array $db): array
    {
        $res = $this->configurator->test($db);
        if (! $res['ok']) {
            return $res;
        }
        try {
            $conn = $this->connect($db);
            $tables = $this->counts($conn);
            $res['tables'] = count($tables);
            $res['rows'] = array_sum(array_intersect_key($tables, array_flip(['users', 'banks', 'deposit_products', 'cards'])));
            $res['same'] = $this->isCurrent($db);
        } catch (Throwable) {
            $res['tables'] = 0;
            $res['rows'] = 0;
        }

        return $res;
    }

    /**
     * Переключает сайт на новую базу.
     *
     * @param  bool  $copy  перенести данные текущей базы
     * @param  bool  $overwrite  разрешить очистить таблицы новой базы, если в них уже есть данные
     * @return array{tables:int, rows:int, backup:string, copied:array<string,int>}
     */
    public function switch(array $db, bool $copy = true, bool $overwrite = false): array
    {
        if ($this->isCurrent($db)) {
            throw new RuntimeException('Это и есть текущая база — переключать нечего.');
        }
        $test = $this->configurator->test($db);
        if (! $test['ok']) {
            throw new RuntimeException($test['message']);
        }

        $step = 'подключение к новой базе';
        try {
            $target = $this->connect($db);
            $source = DB::connection();
            $tables = array_values(array_diff($this->tables($source), self::SKIP));

            // проверка до миграций: миграции сами заливают справочники (платёжные системы)
            $step = 'проверка новой базы';
            $busy = array_filter(array_intersect($this->tables($target), $tables), fn ($t) => $target->table($t)->exists()); // A41: только наши таблицы
            if ($copy && $busy && ! $overwrite) {
                throw new RuntimeException('В новой базе уже есть данные ('.implode(', ', array_slice(array_values($busy), 0, 5)).'). Отметь «Перезаписать данные в новой базе» или выбери пустую базу.');
            }

            // A41 · остатки прошлой неудачной попытки (пустые таблицы без записи в migrations) мешали
            // миграциям: «1050 Table 'users' already exists». Наши таблицы в новой базе удаляем и создаём
            // заново, если данные всё равно переносятся (пусто или «перезаписать»), либо если они пустые.
            $step = 'очистка новой базы';
            $ours = array_values(array_intersect($this->tables($target), array_merge($tables, self::SKIP)));
            if ($ours && ($copy || ! $busy)) {
                $this->drop($target, $ours);
            }

            $step = 'создание таблиц (миграции) в новой базе';
            $this->migrate();

            $step = 'перенос данных';
            $missing = array_values(array_diff($tables, $this->tables($target)));
            if ($copy && $missing) {
                throw new RuntimeException('После миграций в новой базе нет таблиц: '.implode(', ', $missing).'. Данные не переносились, сайт остался на прежней базе.');
            }
            $copied = $copy ? $this->copy($source, $target, $tables) : [];
        } catch (QueryException $e) {
            throw new RuntimeException('Не получилось на шаге «'.$step.'»: '.$this->explain($e).' Сайт остался на прежней базе.', 0, $e);
        }

        $backup = $this->backupEnv();
        (new EnvWriter)->set($this->configurator->envValues($db));
        $this->forgetConfigCache();

        return ['tables' => count($copied), 'rows' => array_sum($copied), 'backup' => $backup, 'copied' => $copied];
    }

    /** A38 · Ошибка SQL → короткий русский текст без «(Connection: …, SQL: …)». */
    public function explain(QueryException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        $raw = $e->getPrevious()?->getMessage() ?? $e->getMessage();
        $raw = trim(preg_replace('/\s*\(Connection: .*$/s', '', $raw));
        $raw = preg_replace('/^SQLSTATE\[\w+\]:?\s*(\[\d+\]\s*)?/', '', $raw);

        return match ($code) {
            1044, 1045 => 'MySQL не пустил пользователя — проверь логин, пароль и права на базу.',
            1049 => 'такой базы на сервере MySQL нет — создай её в панели или поправь имя.',
            1142, 1227 => 'у пользователя MySQL не хватает прав (нужны CREATE, ALTER, INSERT, DELETE, INDEX, REFERENCES).',
            1146 => 'в базе нет нужной таблицы ('.$raw.').',
            2002, 2003, 2006 => 'сервер MySQL не отвечает — проверь хост и порт.',
            default => $raw.'.',
        };
    }

    /** Список копий .env: новые сверху. */
    public function backups(): array
    {
        $files = glob($this->backupDir().'/env-*') ?: [];
        rsort($files);

        return array_map(function ($f) {
            $vals = $this->dbValues((string) file_get_contents($f));

            return ['file' => basename($f), 'at' => filemtime($f), 'driver' => $vals['DB_CONNECTION'] ?? '?', 'database' => $vals['DB_DATABASE'] ?? '', 'host' => $vals['DB_HOST'] ?? null];
        }, array_slice($files, 0, 10));
    }

    /** Вернуть настройки базы из копии .env (остальные строки .env не трогаются). */
    public function restore(string $file): array
    {
        $path = $this->backupDir().'/'.basename($file);
        if (! preg_match('/^env-[0-9_-]+$/', basename($file)) || ! is_file($path)) {
            throw new RuntimeException('Копия не найдена.');
        }
        $vals = $this->dbValues((string) file_get_contents($path));
        if (empty($vals['DB_CONNECTION'])) {
            throw new RuntimeException('В копии нет настроек базы.');
        }
        $this->backupEnv();
        $env = new EnvWriter;
        $env->set($vals);
        $env->forget(array_diff(self::KEYS, array_keys($vals))); // в копии ключа не было — значит, был по умолчанию
        $this->forgetConfigCache();

        return $vals;
    }

    /** Миграции на новой базе; на время — она же основная, чтобы сидеры внутри миграций писали туда. */
    private function migrate(): void
    {
        $default = Config::get('database.default');
        DB::setDefaultConnection(self::TARGET);
        try {
            $code = Artisan::call('migrate', ['--database' => self::TARGET, '--force' => true]);
            if ($code !== 0) {
                throw new RuntimeException('Миграции на новой базе не прошли: '.trim(Artisan::output()));
            }
        } finally {
            DB::setDefaultConnection($default);
        }
    }

    /** A41 · Удалить таблицы новой базы (только перечисленные — чужие таблицы в той же базе не трогаем). */
    private function drop(Connection $c, array $tables): void
    {
        $this->foreignKeys($c, false);
        try {
            foreach ($tables as $t) {
                $c->getSchemaBuilder()->dropIfExists($t);
            }
        } finally {
            $this->foreignKeys($c, true);
        }
    }

    /** @return array<string,int> таблица → строк перенесено */
    private function copy(Connection $from, Connection $to, array $tables): array
    {
        $copied = [];
        $this->foreignKeys($to, false);
        try {
            foreach ($tables as $table) {
                if (! $this->hasTable($to, $table)) {
                    continue; // таблица есть только в старой базе (например, удалённая миграцией)
                }
                $to->table($table)->delete();
                $columns = array_intersect($this->columns($from, $table), $this->columns($to, $table));
                $n = 0;
                $query = $from->table($table)->select($columns);
                $order = in_array('id', $columns, true) ? 'id' : $columns[0];
                $query->orderBy($order)->chunk(500, function ($rows) use ($to, $table, &$n) {
                    $to->table($table)->insert($rows->map(fn ($r) => (array) $r)->all());
                    $n += $rows->count();
                });
                $copied[$table] = $n;
                $have = $to->table($table)->count();
                if ($have !== $from->table($table)->count()) {
                    throw new RuntimeException("Таблица {$table}: перенесено {$have} строк из ".$from->table($table)->count().'.');
                }
            }
            $this->resetSequences($to, array_keys($copied));
        } finally {
            $this->foreignKeys($to, true);
        }

        return $copied;
    }

    private function connect(array $db): Connection
    {
        $driver = $db['driver'] === 'sqlite' ? 'sqlite' : 'mysql';
        $base = Config::get("database.connections.{$driver}");
        $values = $this->configurator->envValues($db);
        Config::set('database.connections.'.self::TARGET, array_merge($base, $driver === 'sqlite'
            ? ['database' => $values['DB_DATABASE']]
            : ['host' => $db['host'], 'port' => $db['port'], 'database' => $db['database'], 'username' => $db['username'], 'password' => $db['password'] ?? '', 'prefix' => $db['prefix'] ?? '']));
        DB::purge(self::TARGET);

        return DB::connection(self::TARGET);
    }

    private function isCurrent(array $db): bool
    {
        $cfg = Config::get('database.connections.'.Config::get('database.default'), []);
        $v = $this->configurator->envValues($db);
        if (($cfg['driver'] ?? '') !== $v['DB_CONNECTION']) {
            return false;
        }

        return $v['DB_CONNECTION'] === 'sqlite'
            ? $this->samePath((string) $cfg['database'], $v['DB_DATABASE'])
            : ($cfg['host'] ?? '') === $v['DB_HOST'] && (int) ($cfg['port'] ?? 3306) === (int) $v['DB_PORT'] && ($cfg['database'] ?? '') === $v['DB_DATABASE'] && ($cfg['prefix'] ?? '') === $v['DB_PREFIX'];
    }

    private function samePath(string $a, string $b): bool
    {
        $ra = realpath($a);
        $rb = realpath($b);

        return $ra !== false && $rb !== false ? $ra === $rb : $a === $b;
    }

    /** Таблицы только текущей базы подключения (A38: не всех баз сервера MySQL). */
    private function tables(Connection $c): array
    {
        $prefix = $c->getTablePrefix();
        $schema = $c->getSchemaBuilder();

        return collect($schema->getTables($schema->getCurrentSchemaName()))
            ->pluck('name')
            ->filter(fn ($t) => $prefix === '' || str_starts_with($t, $prefix))
            ->map(fn ($t) => substr($t, strlen($prefix)))
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_'))
            ->unique()->values()->all();
    }

    /** @return array<string,int> */
    private function counts(Connection $c): array
    {
        $out = [];
        foreach (array_diff($this->tables($c), self::SKIP) as $t) {
            $out[$t] = $c->table($t)->count();
        }
        ksort($out);

        return $out;
    }

    private function hasTable(Connection $c, string $t): bool
    {
        return $c->getSchemaBuilder()->hasTable($t);
    }

    private function columns(Connection $c, string $t): array
    {
        return $c->getSchemaBuilder()->getColumnListing($t);
    }

    private function version(Connection $c): ?string
    {
        return (string) $c->selectOne($c->getDriverName() === 'sqlite' ? 'select sqlite_version() as v' : 'select version() as v')->v ?: null;
    }

    private function foreignKeys(Connection $c, bool $on): void
    {
        match ($c->getDriverName()) {
            'sqlite' => $c->statement('PRAGMA foreign_keys = '.($on ? 'ON' : 'OFF')),
            'mysql', 'mariadb' => $c->statement('SET FOREIGN_KEY_CHECKS='.($on ? 1 : 0)),
            default => null,
        };
    }

    /** MySQL: следующий AUTO_INCREMENT после переноса id; SQLite считает сам. */
    private function resetSequences(Connection $c, array $tables): void
    {
        if (! in_array($c->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        foreach ($tables as $t) {
            if (in_array('id', $this->columns($c, $t), true)) {
                $next = (int) $c->table($t)->max('id') + 1;
                $c->statement('ALTER TABLE `'.$c->getTablePrefix().$t.'` AUTO_INCREMENT = '.$next);
            }
        }
    }

    private function backupDir(): string
    {
        $dir = storage_path('app/env-backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir;
    }

    private function backupEnv(): string
    {
        $name = 'env-'.now()->format('Ymd-His').'-'.substr((string) hrtime(true), -4);
        $env = app()->environmentFilePath();
        if (is_file($env)) {
            copy($env, $this->backupDir().'/'.$name);
            @chmod($this->backupDir().'/'.$name, 0600);
        }

        return $name;
    }

    /** @return array<string,string> только строки DB_* из текста .env */
    private function dbValues(string $env): array
    {
        $out = [];
        foreach (self::KEYS as $k) {
            if (preg_match('/^'.$k.'=(.*)$/m', $env, $m)) {
                $v = trim($m[1]);
                if (strlen($v) >= 2 && $v[0] === '"' && str_ends_with($v, '"')) {
                    $v = stripcslashes(substr($v, 1, -1));
                }
                $out[$k] = $v;
            }
        }

        return $out;
    }

    private function forgetConfigCache(): void
    {
        $cached = app()->getCachedConfigPath();
        if (is_file($cached)) {
            @unlink($cached);
        }
    }
}
