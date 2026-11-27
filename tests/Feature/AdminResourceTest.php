<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Bank;
use App\Models\Country;
use App\Models\DepositProduct;
use App\Models\DepositRate;
use App\Models\Import;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** A24 · Админка: универсальный CRUD, поиск, массовые действия, экран обмена, токены. A21: страны на витрине. */
class AdminResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        (new BankSeeder)->run();
    }

    private function product(): DepositProduct
    {
        $bank = Bank::where('code', 'BCC')->firstOrFail();
        $p = $bank->depositProducts()->create(['name' => 'Срочный', 'slug' => 'bcc-term', 'type' => 'term']);
        $p->rates()->create(['currency' => 'KZT', 'term_months' => 12, 'rate' => 14.5, 'valid_from' => today()->subMonth()]);

        return $p;
    }

    public function test_every_resource_list_and_form_renders(): void
    {
        $this->product();
        $admin = $this->admin();
        foreach (['countries', 'banks', 'deposits', 'rates', 'users'] as $key) {
            $this->actingAs($admin)->get("/admin/{$key}")->assertOk();
            $this->actingAs($admin)->get("/admin/{$key}/create")->assertOk();
        }
        $this->actingAs($admin)->get('/admin/banks/'.Bank::first()->id.'/edit')->assertOk()->assertSee('Вклады банка');
        $this->actingAs($admin)->get('/admin/deposits/'.DepositProduct::first()->id.'/edit')->assertOk()->assertSee('Действующие ставки')->assertSee('14,50%');
        $this->actingAs($admin)->get('/admin/countries/'.Country::first()->id.'/edit')->assertOk()->assertSee('КФГД');
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Что заполнить');
    }

    public function test_search_filter_and_sort(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/banks?q=Halyk')->assertOk()->assertSee('Halyk Bank')->assertDontSee('Freedom Bank');
        $this->actingAs($admin)->get('/admin/banks?status=revoked')->assertOk()->assertSee('Под фильтр ничего не попало');
        $this->actingAs($admin)->get('/admin/banks?sort=name&dir=asc')->assertOk();
        $this->actingAs($admin)->getJson('/admin/search?q=halyk')->assertOk()->assertJsonPath('0.type', 'Банк');
    }

    public function test_create_and_update_bank_and_country(): void
    {
        $admin = $this->admin();
        $kz = Country::where('code', 'KZ')->first();

        $this->actingAs($admin)->post('/admin/countries', ['name' => 'Узбекистан', 'code' => 'uz', 'currency' => 'uzs', 'is_active' => 1,
            'deposit_guarantee' => ['short' => 'ФГВ', 'limits' => ['term' => 200000000]]])->assertRedirect()->assertSessionHasNoErrors();
        $uz = Country::where('code', 'UZ')->firstOrFail();
        $this->assertSame('UZS', $uz->currency);
        $this->assertSame(200000000, $uz->guaranteeLimit('term'));

        // тот же код банка в другой стране — можно, в той же — нельзя
        $this->actingAs($admin)->post('/admin/banks', ['country_id' => $uz->id, 'code' => 'BCC', 'name' => 'Узбекский BCC', 'status' => 'active', 'aliases' => "Узбек\nUZB"])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/banks', ['country_id' => $kz->id, 'code' => 'BCC', 'name' => 'Дубль', 'status' => 'active'])->assertSessionHasErrors('code');
        $this->assertSame(['Узбек', 'UZB'], Bank::where('country_id', $uz->id)->first()->aliases);

        $bank = Bank::where('code', 'HSBK')->first();
        $this->actingAs($admin)->put("/admin/banks/{$bank->id}", ['country_id' => $kz->id, 'code' => 'HSBK', 'name' => 'Halyk', 'status' => 'active', 'deposit_insured' => 0, 'brand_color' => '#00805F'])
            ->assertRedirect("/admin/banks/{$bank->id}/edit");
        $this->assertFalse($bank->fresh()->deposit_insured);
    }

    public function test_rate_form_closes_old_rate(): void
    {
        $p = $this->product();
        $this->actingAs($this->admin())->post('/admin/rates', ['deposit_product_id' => $p->id, 'currency' => 'KZT', 'term_months' => 12, 'rate' => 15.25])
            ->assertRedirect()->assertSessionHas('status', 'Новая ставка сохранена, прежняя закрыта — история сохранена.');
        $this->assertSame(2, DepositRate::count());
        $this->assertEquals(15.25, (float) DepositRate::current()->value('rate'));
        $this->assertSame('manual', DepositRate::current()->value('source'));
        $this->actingAs($this->admin())->get('/admin/rates/'.DepositRate::first()->id.'/edit')->assertNotFound();
    }

    public function test_bulk_actions_and_export(): void
    {
        $admin = $this->admin();
        $ids = Bank::whereIn('code', ['HSBK', 'BCC'])->pluck('id')->all();
        $this->actingAs($admin)->post('/admin/banks/bulk', ['action' => 'revoked', 'ids' => $ids])->assertRedirect();
        $this->assertSame(2, Bank::where('status', 'revoked')->count());

        // «все по фильтру»
        $this->actingAs($admin)->post('/admin/banks/bulk', ['action' => 'active', 'all' => 1, 'filters' => ['status' => 'revoked']]);
        $this->assertSame(0, Bank::where('status', 'revoked')->count());

        $res = $this->actingAs($admin)->post('/admin/banks/bulk', ['action' => 'export', 'format' => 'csv', 'ids' => $ids])->assertOk();
        $this->assertStringContainsString('HSBK', $res->getContent());

        $kz = Country::first();
        $this->actingAs($admin)->post('/admin/countries/bulk', ['action' => 'delete', 'ids' => [$kz->id]])->assertSessionHas('status');
        $this->assertNotNull($kz->fresh(), 'страну с банками удалить нельзя');
    }

    public function test_users_are_admin_only_and_admin_cannot_demote_self(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
        $this->actingAs($editor)->get('/admin/banks')->assertOk();

        $admin = $this->admin();
        $this->actingAs($admin)->put("/admin/users/{$admin->id}", ['name' => 'A', 'email' => $admin->email, 'role' => 'user']);
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_exchange_check_then_apply_and_tokens(): void
    {
        $admin = $this->admin();
        $json = json_encode(['banks' => [['code' => 'BCC', 'products' => [['name' => 'Новый вклад', 'type' => 'savings', 'rates' => [['term_months' => 6, 'rate' => 17]]]]]]]);

        $this->actingAs($admin)->post('/admin/exchange/check', ['text' => $json, 'mode' => 'upsert', 'country' => 'KZ'])
            ->assertOk()->assertSee('Проверка')->assertSee('Новый вклад');
        $this->assertSame(0, DepositProduct::count());
        $token = collect(session()->all()['exchange']['pending'])->keys()->first();

        $this->actingAs($admin)->post('/admin/exchange/apply', ['token' => $token])->assertRedirect();
        $this->assertSame(1, DepositProduct::count());
        $this->assertEquals(17, (float) DepositRate::first()->rate);
        $this->actingAs($admin)->get('/admin/exchange/history/'.Import::first()->id)->assertOk();

        $file = UploadedFile::fake()->createWithContent('rates.csv', "bank,product,term_months,rate\nBCC,Новый вклад,6,17.5\n");
        $this->actingAs($admin)->post('/admin/exchange/check', ['file' => $file, 'entity' => 'deposit_rates', 'mode' => 'upsert', 'country' => 'KZ'])->assertOk()->assertSee('17.5');

        foreach (['import', 'export', 'format', 'history'] as $tab) {
            $this->actingAs($admin)->get("/admin/exchange?tab={$tab}")->assertOk();
        }
        $this->actingAs($admin)->get('/admin/exchange/export?entities[]=banks&format=xlsx')->assertOk();
        $this->actingAs($admin)->get('/admin/exchange/template/banks/csv')->assertOk();

        $this->actingAs($admin)->post('/admin/exchange/tokens', ['name' => 'Claude', 'abilities' => ['read', 'write']])->assertSessionHas('plain_token');
        $this->assertTrue(ApiToken::first()->can('write'));
        $this->actingAs($admin)->get('/admin/exchange/tokens')->assertOk()->assertSee('Claude');
    }

    public function test_storefront_is_scoped_to_country(): void
    {
        $this->product();
        $uz = Country::create(['code' => 'UZ', 'name' => 'Узбекистан', 'name_genitive' => 'Узбекистана', 'currency' => 'UZS', 'currency_symbol' => 'сум']);
        $b = Bank::create(['country_id' => $uz->id, 'code' => 'KAP', 'name' => 'Капиталбанк', 'slug' => 'kapitalbank']);
        $b->depositProducts()->create(['name' => 'Сумовой', 'slug' => 'kap-sum', 'type' => 'term'])
            ->rates()->create(['currency' => 'UZS', 'term_months' => 12, 'rate' => 22, 'valid_from' => today()]);

        $this->get('/deposits')->assertOk()->assertSee('банков Казахстана')->assertSee('Срочный')->assertDontSee('Сумовой');
        $this->get('/deposits?country=uz')->assertOk()->assertSee('банков Узбекистана')->assertSee('Сумовой')->assertDontSee('bcc-term')
            ->assertCookie('banq_country');
        $this->get('/banks?country=uz')->assertOk()->assertSee('Капиталбанк')->assertDontSee('Halyk Bank');
        $this->get('/deposits/kap-sum')->assertOk()->assertSee('сум');
        $this->get('/')->assertOk()->assertSee('Узбекистан'); // переключатель стран
    }
}
