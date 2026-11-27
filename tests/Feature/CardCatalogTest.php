<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\CardTier;
use App\Models\Import;
use App\Models\PaymentSystem;
use App\Models\ServicePackage;
use App\Services\Exchange\DataExchange;
use App\Services\Exchange\Entities\CardEntity;
use App\Services\Exchange\ImportOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A30–A33 · Раздел «Карты»: справочник систем, обмен данными, админка, витрина, избранное. */
class CardCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    private function importDemo(): array
    {
        $ex = app(DataExchange::class);

        return $ex->import($ex->parse(file_get_contents(base_path('tests/fixtures/cards-demo.json')), 'json'), ImportOptions::fromArray([]))->toArray();
    }

    public function test_migration_seeds_payment_systems_and_tiers(): void
    {
        $this->assertSame(10, PaymentSystem::count());
        $visa = PaymentSystem::where('code', 'VISA')->firstOrFail();
        $this->assertSame(['classic', 'gold', 'business', 'platinum', 'signature', 'infinite'], $visa->tiers->pluck('code')->all());
        $this->assertSame(5, CardTier::where('slug', 'visa-infinite')->value('level'));
        $this->assertSame(['KG'], PaymentSystem::where('code', 'ELCART')->value('countries') ? PaymentSystem::where('code', 'ELCART')->first()->countries : null);
    }

    public function test_network_strings_are_understood(): void
    {
        [$ps, $tier] = CardEntity::network('Mastercard World Elite');
        $this->assertSame(['MASTERCARD', 'world_elite'], [$ps->code, $tier->code]);
        [$ps, $tier] = CardEntity::network('VISA:gold');
        $this->assertSame(['VISA', 'gold'], [$ps->code, $tier->code]);
        [$ps, $tier] = CardEntity::network('UnionPay');
        $this->assertSame(['UNIONPAY', null], [$ps->code, $tier]);
        [$ps, $tier] = CardEntity::network('Мир Привилегия Плюс');
        $this->assertSame('privilege_plus', $tier->code);
    }

    public function test_demo_bundle_imports_idempotently_and_roundtrips(): void
    {
        $r = $this->importDemo();
        $this->assertSame(0, $r['totals']['error'], json_encode($r['rows'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(6, Card::count());
        $this->assertSame(1, ServicePackage::count());

        $travel = Card::where('slug', 'demo-ffin-travel')->firstOrFail();
        $this->assertSame('credit', Card::where('slug', 'demo-bcc-credit')->first()->kind->value);
        $this->assertSame(['MASTERCARD:world_elite', 'UNIONPAY:platinum'], CardEntity::networkLabels($travel));
        $this->assertSame(['demo-bcc-infinite'], ServicePackage::first()->cards->pluck('slug')->all());
        $this->assertSame('BCC', Card::where('slug', 'demo-bcc-infinite')->first()->bank->code, 'bank в строке карты перекрывает родителя');

        $again = $this->importDemo();
        $this->assertSame(0, $again['totals']['created'] + $again['totals']['updated']);

        // экспорт → импорт = без изменений
        $ex = app(DataExchange::class);
        foreach (['json', 'csv', 'xlsx'] as $format) {
            $file = $ex->render($ex->export(['payment_systems', 'card_tiers', 'cards', 'service_packages']), $format);
            $back = $ex->import($ex->parse($file, $format), ImportOptions::fromArray([]))->toArray();
            $this->assertSame(0, $back['totals']['created'] + $back['totals']['updated'] + $back['totals']['error'], $format.': '.json_encode(array_filter($back['rows'], fn ($x) => $x['action'] !== 'unchanged'), JSON_UNESCAPED_UNICODE));
        }

        // смена сетей — это изменение, видно в журнале
        $r = $ex->import(['cards' => [['slug' => 'demo-ffin-travel', 'networks' => 'VISA:signature']]], ImportOptions::fromArray([]))->toArray();
        $this->assertSame('updated', $r['rows'][0]['action']);
        $this->assertSame(['VISA:signature'], CardEntity::networkLabels($travel));
        $this->assertGreaterThan(0, Import::count());
    }

    public function test_unknown_tier_is_a_row_error(): void
    {
        $this->importDemo();
        $ex = app(DataExchange::class);
        $r = $ex->import(['cards' => [['country' => 'KZ', 'bank' => 'BCC', 'name' => 'X', 'kind' => 'дебетовая', 'networks' => 'VISA:diamond']]], ImportOptions::fromArray([]))->toArray();
        $this->assertSame('error', $r['rows'][0]['action']);
        $this->assertStringContainsString('diamond', $r['rows'][0]['message']);
    }

    public function test_storefront_pages_render(): void
    {
        $this->importDemo();
        $this->get('/cards')->assertOk()->assertSee('Кэшбэк Демо')->assertSee('Оценка Banq', false);
        $this->get('/cards?kind[]=credit')->assertOk()->assertSee('Рассрочка Демо')->assertDontSee('Travel Демо');
        $this->get('/cards?system[]=unionpay')->assertOk()->assertSee('Travel Демо')->assertDontSee('Бонус Демо');
        $this->get('/cards?free=1&sort=fee', ['X-Partial' => 'results'])->assertOk()->assertDontSee('<html', false)->assertDontSee('Infinite Демо');
        $this->get('/cards/demo-ffin-travel')->assertOk()->assertSee('Mastercard World Elite')->assertSee('Из чего оценка');
        $ids = Card::whereIn('slug', ['demo-bcc-cashback', 'demo-bcc-credit'])->pluck('id')->join(',');
        $this->get('/cards/compare?ids='.$ids)->assertOk()->assertSee('Льготный период');
        $this->get('/payment-systems')->assertOk()->assertSee('UnionPay')->assertSee('Элкарт');
        $this->get('/payment-systems/visa')->assertOk()->assertSee('Visa Infinite');
        $this->get('/packages')->assertOk()->assertSee('Премиум Демо');
        $this->get('/packages/demo-bcc-premium')->assertOk()->assertSee('Infinite Демо');
        $this->get('/banks/bcc')->assertOk()->assertSee('Карты BCC');
        $this->get('/')->assertOk()->assertSee('кэшбэк до 10%');

        Card::where('slug', 'demo-bcc-credit')->update(['is_active' => false]);
        $this->get('/cards/demo-bcc-credit')->assertNotFound();
    }

    public function test_admin_crud_with_networks_and_package_cards(): void
    {
        $this->importDemo();
        $admin = $this->admin();
        foreach (['cards', 'packages', 'payment-systems', 'card-tiers'] as $key) {
            $this->actingAs($admin)->get("/admin/{$key}")->assertOk();
            $this->actingAs($admin)->get("/admin/{$key}/create")->assertOk();
        }
        $this->actingAs($admin)->get('/admin/payment-systems/'.PaymentSystem::where('code', 'VISA')->value('id').'/edit')->assertOk()->assertSee('Уровни карт');

        $visa = PaymentSystem::where('code', 'VISA')->first();
        $gold = CardTier::where('slug', 'visa-gold')->first();
        $bank = Card::first()->bank;
        $this->actingAs($admin)->post('/admin/cards', [
            'bank_id' => $bank->id, 'name' => 'Новая', 'kind' => 'debit', 'format' => 'physical', 'annual_fee' => '0',
            'networks' => [$visa->id.':'.$gold->id, PaymentSystem::where('code', 'UNIONPAY')->value('id').':0'], 'features' => ['apple_pay'],
        ])->assertRedirect();
        $card = Card::where('name', 'Новая')->firstOrFail();
        $this->assertSame(['VISA:gold', 'UNIONPAY'], CardEntity::networkLabels($card));
        $this->assertSame(['apple_pay'], $card->features);
        $this->assertStringStartsWith($bank->slug, $card->slug);

        $pkg = ServicePackage::first();
        $this->actingAs($admin)->put('/admin/packages/'.$pkg->id, ['bank_id' => $pkg->bank_id, 'name' => $pkg->name, 'segment' => 'premium', 'cards' => Card::where('bank_id', $pkg->bank_id)->pluck('id')->all()])->assertRedirect();
        $this->assertSame(Card::where('bank_id', $pkg->bank_id)->count(), $pkg->cards()->count());
    }

    public function test_card_can_be_favorited(): void
    {
        $this->importDemo();
        $user = \App\Models\User::factory()->create(['role' => 'user']);
        $card = Card::where('slug', 'demo-ffin-travel')->first();
        $this->actingAs($user)->postJson('/favorites', ['type' => 'card', 'id' => $card->id])->assertOk()->assertJson(['on' => true]);
        $this->actingAs($user)->get('/account/favorites')->assertOk()->assertSee('Избранные карты')->assertSee('Travel Демо');
    }
}
