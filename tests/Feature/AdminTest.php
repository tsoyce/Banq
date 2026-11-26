<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\DepositRate;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\BankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    public function test_home_renders_with_and_without_data(): void
    {
        $this->get('/')->assertOk()->assertSee('Топ ставок')->assertSee('Калькулятор дохода');
    }

    public function test_admin_requires_admin_role(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'user']))->get('/admin')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }

    public function test_settings_save_and_project_years(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'site_name' => 'TSOY.IN Banq', 'founded_year' => 2020, 'theme_default' => 'dark', 'copyright' => 'Tsoy',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect();

        app()->forgetInstance(Settings::class);
        $this->assertSame('TSOY.IN Banq', settings('site.name'));
        $this->assertSame('2020–'.date('Y'), project_years());
        $this->assertFileExists(public_path(settings('site.logo')));
        @unlink(public_path(settings('site.logo')));

        $this->get('/')->assertSee('© 2020–'.date('Y').' Tsoy');
    }

    public function test_import_preview_and_commit_keeps_rate_history(): void
    {
        (new BankSeeder)->run();
        $admin = $this->admin();
        $file = fn () => new UploadedFile(base_path('tests/fixtures/deposits-matrix.xlsx'), 'deposits.xlsx', null, null, true);

        $this->actingAs($admin)->post('/admin/import/preview', ['file' => $file()])->assertOk()->assertSee('Freedom Bank')->assertSee('14,46%');
        $this->actingAs($admin)->post('/admin/import', ['sheets' => ['Лист2']])->assertRedirect(route('admin.import.index'));

        // Halyk, Kaspi, Forte… узнаются по алиасам; без галочки «создать» неизвестные банки пропускаются
        $ffin = Bank::where('code', 'FFIN')->first();
        $rate = DepositRate::current()->whereHas('product', fn ($q) => $q->where('bank_id', $ffin->id)->where('type', 'demand'))->sole();
        $this->assertSame(['14.460', '15.450'], [$rate->rate, $rate->effective_rate]);
        $count = DepositRate::count();
        $this->assertGreaterThan(20, $count);

        // Повторный импорт того же файла ничего не дублирует
        $this->actingAs($admin)->post('/admin/import/preview', ['file' => $file()]);
        $this->actingAs($admin)->post('/admin/import', ['sheets' => ['Лист2']]);
        $this->assertSame($count, DepositRate::count());

        // Новая ставка закрывает старую, история остаётся
        $this->actingAs($admin)->post('/admin/import/preview', ['file' => $file()]);
        $this->actingAs($admin)->post('/admin/import', ['sheets' => ['Sheet1']]);
        $this->assertSame($count + 1, DepositRate::count());
        $this->assertSame('16.350', DepositRate::current()->whereHas('product', fn ($q) => $q->where('bank_id', $ffin->id)->where('type', 'demand'))->sole()->rate);

        $this->get('/')->assertSee('16,7%'); // лучшая номинальная ставка (BCC, срочный) — на главной
    }
}
