<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Country;
use App\Models\User;
use App\Support\AdminScope;
use App\Support\Flags;
use Database\Seeders\BankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** A46 · SVG-флаги стран и валют, свои флаги в админке, страна в плашке админки и страны сотрудника, «Экспорт / Импорт» без XLSX. */
class FlagsAndAdminScopeTest extends TestCase
{
    use RefreshDatabase;

    private Country $uz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        (new BankSeeder)->run();
        $this->uz = Country::create(['code' => 'UZ', 'name' => 'Узбекистан', 'currency' => 'UZS', 'is_active' => true]);
        Bank::create(['country_id' => $this->uz->id, 'code' => 'NBU', 'slug' => 'nbu', 'name' => 'Национальный банк Узбекистана']);
    }

    protected function tearDown(): void
    {
        foreach (glob(public_path(Flags::DIR.'/zz-*')) ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function kz(): Country
    {
        return Country::where('code', 'KZ')->firstOrFail();
    }

    public function test_library_resolves_country_and_currency_flags(): void
    {
        $this->assertFileExists(public_path('flags/4x3/kz.svg'));
        $this->assertFileExists(public_path('flags/1x1/eu.svg'));
        $this->assertSame('us', Flags::forCurrency('USD'));
        $this->assertSame('eu', Flags::forCurrency('EUR'));
        $this->assertSame('cn', Flags::forCurrency('cny'));
        $this->assertNull(Flags::forCurrency('XAU'));
        $this->assertStringEndsWith('/flags/1x1/kz.svg', Flags::url('KZ', '1x1'));
        $this->assertNull(Flags::url('../etc'));
        $this->assertSame('Казахстан', Flags::name('kz'));
        $this->assertStringContainsString('<img src=', $this->kz()->flagImg());
        $this->assertStringContainsString('flag-none', Flags::img('qq', fallback: true));
    }

    public function test_fx_card_and_country_switch_show_svg_flags(): void
    {
        $this->get('/')->assertOk()->assertSee('flags/1x1/kz.svg', false);
    }

    public function test_admin_uploads_custom_flag_and_rejects_svg_with_script(): void
    {
        $admin = $this->admin();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 3"><rect width="4" height="3" fill="#0af"/></svg>';
        $this->actingAs($admin)->post('/admin/flags', ['code' => 'ZZ', 'name' => 'Свой регион', 'file' => UploadedFile::fake()->createWithContent('zz.svg', $svg)])
            ->assertRedirect();
        $this->assertSame('Свой регион', Flags::name('zz'));
        $this->assertStringContainsString('/uploads/flags/zz-', Flags::url('zz'));

        $bad = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script></svg>';
        $this->actingAs($admin)->from('/admin/flags')->post('/admin/flags', ['code' => 'zz-bad', 'file' => UploadedFile::fake()->createWithContent('bad.svg', $bad)])
            ->assertSessionHasErrors('file');
        $this->assertFalse(Flags::has('zz-bad'));

        $this->actingAs($admin)->get('/admin/flags')->assertOk()->assertSee('Свой регион')->assertSee('Казахстан');
        $this->actingAs($admin)->delete('/admin/flags/zz')->assertRedirect();
        $this->assertFalse(Flags::has('zz'));
    }

    public function test_country_filter_in_top_bar_narrows_lists(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/banks')->assertOk()->assertSee('Все страны')->assertSee('NBU')->assertSee('BCC');

        $this->actingAs($admin)->post('/admin/country', ['country' => $this->uz->id, '_back' => url('/admin/banks?country=1&page=2')])
            ->assertRedirect(url('/admin/banks'));
        $this->actingAs($admin)->get('/admin/banks')->assertOk()->assertSee('NBU')->assertDontSee('>BCC<', false);

        $this->actingAs($admin)->post('/admin/country', ['country' => ''])->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get('/admin/banks')->assertSee('BCC');
    }

    public function test_staff_scoped_to_countries_cannot_see_or_save_foreign_data(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->forceFill(['admin_countries' => [$this->uz->id]])->save();
        $kzBank = Bank::where('code', 'BCC')->firstOrFail();

        $this->actingAs($editor)->get('/admin/banks')->assertOk()->assertSee('NBU')->assertDontSee('Все страны');
        $this->actingAs($editor)->get("/admin/banks/{$kzBank->id}/edit")->assertNotFound();
        $this->actingAs($editor)->get('/admin/countries')->assertOk()->assertSee('Узбекистан')->assertDontSee('Казахстана');

        $this->actingAs($editor)->post('/admin/banks', ['country_id' => $this->kz()->id, 'code' => 'XKZ', 'name' => 'Чужой банк', 'status' => 'active'])
            ->assertSessionHasErrors();
        $this->assertDatabaseMissing('banks', ['code' => 'XKZ']);

        // выгрузка «всех стран» у сотрудника — только его страна
        $this->actingAs($editor);
        $this->assertSame('UZ', app(AdminScope::class)->clampCode(null));
        $this->assertSame('UZ', app(AdminScope::class)->clampCode('KZ'));
    }

    public function test_admin_sets_staff_countries(): void
    {
        $admin = $this->admin();
        $editor = User::factory()->create(['role' => 'editor']);
        $this->actingAs($admin)->get("/admin/users/{$editor->id}/edit")->assertOk()->assertSee('Страны сотрудника');
        $this->actingAs($admin)->put("/admin/users/{$editor->id}", [
            'name' => $editor->name, 'email' => $editor->email, 'role' => 'editor', 'admin_countries' => [(string) $this->uz->id],
        ])->assertRedirect();
        $this->assertSame([$this->uz->id], $editor->fresh()->admin_countries);
    }

    public function test_export_labels_have_no_xlsx_and_offer_every_format(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/banks')->assertOk()->assertDontSee('Всё в XLSX')->assertSee('Экспорт')->assertSee('Импорт')->assertSee('YAML');
        $this->actingAs($admin)->get('/admin/exchange')->assertOk()->assertSee('Экспорт / Импорт');
        $this->actingAs($admin)->post('/admin/banks/bulk', ['action' => 'export', 'ids' => [Bank::first()->id], 'format' => 'yaml'])
            ->assertOk()->assertHeader('Content-Disposition');
    }
}
