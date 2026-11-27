<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\User;
use App\Services\Database\DatabaseSwitcher;
use Database\Seeders\BankSeeder;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PDO;
use PDOException;
use Tests\TestCase;

/** A34 · Переключение базы из админки: перенос данных в новую SQLite, запись .env, откат из копии. */
class DatabaseSwitchTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        (new BankSeeder)->run();

        $this->dir = sys_get_temp_dir().'/banq-db-'.uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir.'/.env', "APP_NAME=Banq\nDB_CONNECTION=sqlite\n# DB_DATABASE=laravel\nMAIL_MAILER=log\n");
        $this->app->useEnvironmentPath($this->dir);
        $this->app->useStoragePath($this->dir.'/storage');
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
        parent::tearDown();
    }

    public function test_only_admin_sees_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'editor']))->get('/admin/database')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/database')->assertOk()->assertSee('Сейчас подключено')->assertSee('SQLite')->assertSee('banks');
    }

    public function test_check_reports_connection(): void
    {
        $this->actingAs($this->admin())->postJson('/admin/database/test', ['driver' => 'sqlite', 'database' => $this->dir.'/new.sqlite'])
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('same', false);
        $this->actingAs($this->admin())->postJson('/admin/database/test', ['driver' => 'mysql'])->assertStatus(422);
    }

    public function test_switch_copies_everything_and_can_be_restored(): void
    {
        $admin = $this->admin();
        $banks = Bank::count();
        $target = $this->dir.'/new.sqlite';

        $this->actingAs($admin)->post('/admin/database/switch', ['driver' => 'sqlite', 'database' => $target, 'copy' => 1])
            ->assertSessionHasErrors('confirm');
        $this->assertFileDoesNotExist($target);

        $this->actingAs($admin)->post('/admin/database/switch', ['driver' => 'sqlite', 'database' => $target, 'copy' => 1, 'confirm' => 1])
            ->assertRedirect('/admin/database')->assertSessionHasNoErrors()->assertSessionHas('status');

        $pdo = new PDO('sqlite:'.$target);
        $this->assertSame($banks, (int) $pdo->query('select count(*) from banks')->fetchColumn());
        $this->assertSame(1, (int) $pdo->query('select count(*) from users where email = '.$pdo->quote($admin->email))->fetchColumn());
        $this->assertGreaterThan(0, (int) $pdo->query('select count(*) from payment_systems')->fetchColumn());
        $this->assertGreaterThan(0, (int) $pdo->query('select count(*) from migrations')->fetchColumn());

        $env = file_get_contents($this->dir.'/.env');
        $this->assertStringContainsString('DB_DATABASE='.$target, $env);
        $this->assertStringContainsString('MAIL_MAILER=log', $env, 'остальные строки .env не тронуты');

        // в непустую базу без «перезаписать» — отказ
        $this->actingAs($admin)->post('/admin/database/switch', ['driver' => 'sqlite', 'database' => $target, 'copy' => 1, 'confirm' => 1])
            ->assertSessionHasErrors('database');

        $backup = collect(glob($this->dir.'/storage/app/env-backups/env-*'))->map(fn ($f) => basename($f))->first();
        $this->actingAs($admin)->get('/admin/database')->assertOk()->assertSee('Вернуть');
        $this->actingAs($admin)->post('/admin/database/restore', ['file' => $backup])->assertRedirect('/admin/database');
        $env = file_get_contents($this->dir.'/.env');
        $this->assertStringContainsString('# DB_DATABASE=', $env, 'ключа не было в копии — снова по умолчанию');
        $this->assertDoesNotMatchRegularExpression('/^DB_DATABASE=/m', $env);

        $this->actingAs($admin)->post('/admin/database/restore', ['file' => '../.env'])->assertSessionHasErrors('database');
    }

    /**
     * A38 · На MySQL сервер видит и чужие базы: getTables() без схемы отдавал их таблицы,
     * и перенос падал «Table 'tsoy_banq.audit_logs' doesn't exist». Здесь чужая база — ATTACH к новой SQLite.
     */
    public function test_switch_ignores_tables_of_other_databases_on_server(): void
    {
        $other = $this->dir.'/other.sqlite';
        $pdo = new PDO('sqlite:'.$other);
        $pdo->exec('create table audit_logs (id integer primary key, note text)');
        $pdo->exec("insert into audit_logs (note) values ('чужая')");
        $pdo = null;

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $e) use ($other) {
            if ($e->connectionName === DatabaseSwitcher::TARGET) {
                $e->connection->statement("attach database '{$other}' as other");
            }
        });

        $target = $this->dir.'/new.sqlite';
        $this->actingAs($this->admin())->post('/admin/database/switch', ['driver' => 'sqlite', 'database' => $target, 'copy' => 1, 'confirm' => 1])
            ->assertRedirect('/admin/database')->assertSessionHasNoErrors();

        $pdo = new PDO('sqlite:'.$target);
        $this->assertSame(Bank::count(), (int) $pdo->query('select count(*) from banks')->fetchColumn());
        $this->assertSame(0, (int) $pdo->query("select count(*) from sqlite_master where name = 'audit_logs'")->fetchColumn(), 'чужая таблица не попала в новую базу');
        $this->assertSame(1, (int) (new PDO('sqlite:'.$other))->query('select count(*) from audit_logs')->fetchColumn(), 'чужая база не тронута');
    }

    public function test_sql_errors_are_explained_without_connection_dump(): void
    {
        $pdoError = new PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'tsoy_banq.audit_logs' doesn't exist");
        $pdoError->errorInfo = ['42S02', 1146, "Table 'tsoy_banq.audit_logs' doesn't exist"];
        $e = new QueryException('banq_target', 'select exists(select * from `audit_logs`) as `exists`', [], $pdoError);

        $text = app(DatabaseSwitcher::class)->explain($e);
        $this->assertStringContainsString('нет нужной таблицы', $text);
        $this->assertStringNotContainsString('Connection:', $text);
        $this->assertStringNotContainsString('select exists', $text);

        $denied = new PDOException('SQLSTATE[HY000] [1045] Access denied');
        $denied->errorInfo = ['HY000', 1045, 'Access denied'];
        $this->assertStringContainsString('логин, пароль', app(DatabaseSwitcher::class)->explain(new QueryException('banq_target', 'x', [], $denied)));
    }

    /** A41 · Остатки прошлой попытки: пустая users без записи в migrations → раньше «1050 Table 'users' already exists». */
    public function test_switch_recreates_leftover_empty_tables_and_keeps_foreign_ones(): void
    {
        $target = $this->dir.'/new.sqlite';
        $pdo = new PDO('sqlite:'.$target);
        $pdo->exec('create table users (id integer primary key)');
        $pdo->exec('create table my_own (id integer primary key)');
        $pdo->exec('insert into my_own (id) values (7)');
        $pdo = null;

        $this->actingAs($this->admin())->post('/admin/database/switch', ['driver' => 'sqlite', 'database' => $target, 'copy' => 1, 'confirm' => 1])
            ->assertRedirect('/admin/database')->assertSessionHasNoErrors();

        $pdo = new PDO('sqlite:'.$target);
        $this->assertSame(Bank::count(), (int) $pdo->query('select count(*) from banks')->fetchColumn());
        $this->assertSame(1, (int) $pdo->query('select count(*) from users')->fetchColumn());
        $this->assertSame(1, (int) $pdo->query('select count(*) from my_own')->fetchColumn(), 'чужая таблица в той же базе не тронута');
    }
}
