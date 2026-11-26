<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\User;
use App\Support\Installation;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    public function test_everything_redirects_to_installer_until_installed(): void
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/admin')->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee('Запускаем');
    }

    public function test_installer_is_closed_after_install(): void
    {
        $this->markInstalled();
        $this->get('/install')->assertNotFound();
    }

    public function test_full_install_flow_on_sqlite(): void
    {
        $dir = sys_get_temp_dir().'/banq-env-'.uniqid();
        mkdir($dir);
        copy(base_path('.env.example'), $dir.'/.env');
        $this->app->useEnvironmentPath($dir);
        $db = $dir.'/test.sqlite';

        $this->postJson('/install/database/test', ['driver' => 'sqlite', 'database' => $db])->assertJson(['ok' => true]);
        $this->post('/install/database', ['driver' => 'sqlite', 'database' => $db])->assertRedirect(route('install.site'));
        $this->assertStringContainsString('DB_DATABASE='.$db, file_get_contents($dir.'/.env'));

        $this->post('/install/site', [
            'site_name' => 'TSOY.IN', 'founded_year' => 2026,
            'admin_name' => 'Artyom', 'admin_email' => 'admin@example.test',
            'admin_password' => 'secret-pass-123', 'admin_password_confirmation' => 'secret-pass-123',
            'seed_banks' => 1,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(Installation::isInstalled());
        $this->assertSame('admin', User::where('email', 'admin@example.test')->value('role'));
        $this->assertNotNull(Bank::where('code', 'FFIN')->first());
        $this->assertSame('TSOY.IN', settings('site.name'));
        $this->assertStringContainsString('APP_DEBUG=false', file_get_contents($dir.'/.env'));

        File::deleteDirectory($dir);
    }

    public function test_bad_database_credentials_show_human_error(): void
    {
        $this->postJson('/install/database/test', [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x',
        ])->assertJson(['ok' => false]);
    }
}
