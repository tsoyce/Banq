<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
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
        $this->assertSame(1, (int) $pdo->query("select count(*) from users where email = ".$pdo->quote($admin->email))->fetchColumn());
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
}
