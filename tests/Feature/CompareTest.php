<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\DepositProduct;
use App\Models\PaymentSystem;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\Exchange\DataExchange;
use App\Services\Exchange\ImportOptions;
use App\Services\Import\DepositImporter;
use Database\Seeders\BankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A53 · Универсальное сравнение: раздел, вклады/карты на своих страницах, пакеты, банки, МПС, сохранение любого вида. */
class CompareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        (new BankSeeder)->run();
        app(DepositImporter::class)->import(base_path('tests/fixtures/deposits-matrix.xlsx'), 'deposits.xlsx', sheets: ['Лист2']);
        $ex = app(DataExchange::class);
        $ex->import($ex->parse(file_get_contents(base_path('tests/fixtures/cards-demo.json')), 'json'), ImportOptions::fromArray([]));
    }

    public function test_hub_lists_every_product_type(): void
    {
        $this->get('/compare')->assertOk()
            ->assertSee('Сравнение')->assertSee('Вклады')->assertSee('Карты')->assertSee('Пакеты услуг')
            ->assertSee('Банки')->assertSee('Платёжные системы')->assertSee('Кредиты')->assertSee('скоро');
    }

    public function test_deposits_and_cards_keep_their_own_pages(): void
    {
        $this->get('/compare/deposits?ids=1,2')->assertRedirect('/deposits/compare?ids=1%2C2');
        $this->get('/compare/cards')->assertRedirect('/cards/compare');
        $this->get('/compare/loans')->assertNotFound();
        $ids = DepositProduct::whereHas('currentRates')->take(2)->pluck('id')->join(',');
        $this->get('/deposits/compare?ids='.$ids)->assertOk()->assertSee('Пакеты услуг'); // вкладки видов
    }

    public function test_packages_compare_side_by_side(): void
    {
        $a = ServicePackage::firstOrFail();
        $b = ServicePackage::create([
            'bank_id' => Bank::where('code', 'KASPI')->value('id'), 'name' => 'Базовый Тест', 'slug' => 'test-basic',
            'segment' => 'mass', 'monthly_fee' => 0, 'perks' => ['Бизнес-залы без ограничений', 'Кэшбэк 2%'], 'is_active' => true,
        ]);

        $res = $this->get('/compare/packages?ids='.$a->id.','.$b->id)->assertOk()
            ->assertSee('Сравнение пакетов услуг')->assertSee('Премиум Демо')->assertSee('Базовый Тест')
            ->assertSee('Персональный менеджер')->assertSee('Кэшбэк 2%')->assertSee('Только различия');
        $this->assertSame([$a->id, $b->id], $res->viewData('heads')->pluck('id')->all());
        $this->assertStringContainsString('class="win', $res->getContent());
        $this->get('/compare/packages')->assertOk()->assertSee('Пока нечего сравнивать');
    }

    public function test_banks_and_payment_systems_compare(): void
    {
        $banks = Bank::whereIn('code', ['KASPI', 'BCC'])->pluck('id');
        $this->get('/compare/banks?ids='.$banks->join(','))->assertOk()
            ->assertSee('Сравнение банков')->assertSee('Лучшая ставка по вкладу')->assertSee('Объективный')->assertSee('Гарантия вкладов');

        $ps = PaymentSystem::whereIn('code', ['VISA', 'MASTERCARD', 'UNIONPAY', 'MIR', 'ELCART'])->pluck('id');
        $res = $this->get('/compare/payment-systems?ids='.$ps->join(','))->assertOk()->assertSee('Уровней')->assertSee('Карт в каталоге');
        $this->assertCount(4, $res->viewData('items')); // не больше 4
    }

    public function test_compare_buttons_in_catalogs(): void
    {
        $this->get('/packages')->assertOk()->assertSee('$store.packageCompare', false);
        $this->get('/banks')->assertOk()->assertSee('$store.bankCompare', false);
        $this->get('/payment-systems')->assertOk()->assertSee('$store.systemCompare', false);
        $this->get('/banks/'.Bank::where('code', 'KASPI')->value('slug'))->assertOk()->assertSee('$store.bankCompare', false);
        $this->get('/')->assertOk()->assertSee('data-compare="deposits"', false);
        $this->get('/banks')->assertSee('data-compare="banks"', false);
    }

    public function test_save_comparison_of_any_type(): void
    {
        $user = User::factory()->create();
        $banks = Bank::whereIn('code', ['KASPI', 'BCC'])->pluck('id');
        $this->actingAs($user)->post('/comparisons', ['type' => 'banks', 'ids' => $banks->join(','), 'amount' => 5])->assertRedirect();
        $c = $user->comparisons()->sole();
        $this->assertSame(['banks', null], [$c->type, $c->amount]);
        $this->assertStringContainsString('/compare/banks?ids=', $c->url());
        $this->get('/account/comparisons')->assertOk()->assertSee($c->title)->assertSee('Банки');
        $this->get('/account')->assertOk()->assertSee('2 банка');

        $this->post('/comparisons', ['type' => 'loans', 'ids' => '1'])->assertSessionHasErrors('type');
    }
}
