<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\DepositProduct;
use App\Models\DepositRate;
use App\Services\Deposits\Income;
use App\Services\Import\DepositImporter;
use Database\Seeders\BankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A14 · Каталог вкладов: фильтры, карточка, сравнение. */
class DepositCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        (new BankSeeder)->run();
        app(DepositImporter::class)->import(base_path('tests/fixtures/deposits-matrix.xlsx'), 'deposits.xlsx', sheets: ['Лист2']);
    }

    private function product(string $code, string $type): DepositProduct
    {
        return DepositProduct::where('type', $type)->whereHas('bank', fn ($q) => $q->where('code', $code))->sole();
    }

    public function test_catalog_lists_offers_sorted_by_rate(): void
    {
        $res = $this->get('/deposits')->assertOk()->assertSee('Вклады')->assertSee('Freedom Bank');
        $offers = $res->viewData('offers');
        $this->assertGreaterThan(10, $offers->count());
        $rates = $offers->map(fn ($o) => (float) $o->rate->rate)->all();
        $sorted = $rates;
        rsort($sorted);
        $this->assertSame($sorted, $rates);
    }

    public function test_filters_by_type_bank_and_conditions(): void
    {
        $offers = $this->get('/deposits?type[]=savings')->viewData('offers');
        $this->assertNotEmpty($offers);
        $this->assertTrue($offers->every(fn ($o) => $o->product->type->value === 'savings'));

        $offers = $this->get('/deposits?bank[]='.Bank::where('code', 'FFIN')->value('slug'))->viewData('offers');
        $this->assertTrue($offers->every(fn ($o) => $o->bank()->code === 'FFIN'));

        $offers = $this->get('/deposits?withdraw=1')->viewData('offers');
        $this->assertTrue($offers->every(fn ($o) => $o->product->can_withdraw));

        $this->get('/deposits?q=freedom')->assertOk()->assertViewHas('offers', fn ($o) => $o->isNotEmpty() && $o->every(fn ($x) => $x->bank()->code === 'FFIN'));
        $this->get('/deposits?currency=USD')->assertOk()->assertViewHas('offers', fn ($o) => $o->isEmpty())->assertSee('Ничего не нашлось');
    }

    public function test_term_and_amount_pick_matching_rate(): void
    {
        $p = $this->product('FFIN', 'term');
        $p->update(['min_amount' => 50_000]);
        $p->rates()->create(['currency' => 'KZT', 'term_months' => 6, 'amount_from' => 10_000_000, 'rate' => 19.5, 'valid_from' => today()]);

        $best = fn (string $qs) => $this->get('/deposits?'.$qs)->viewData('offers')->first(fn ($o) => $o->product->is($p));
        $this->assertSame('19.500', $best('term=6&amount=20000000')->rate->rate);
        $this->assertNotSame('19.500', $best('term=12&amount=20000000')->rate->rate); // ставка на 6 мес не подходит под 12
        $this->assertNotSame('19.500', $best('term=6&amount=1000000')->rate->rate);   // сумма ниже порога
        $this->assertNull($best('amount=10000'));                                      // ниже min_amount вклада
    }

    public function test_partial_results_for_fetch(): void
    {
        $this->get('/deposits?sort=bank', ['X-Partial' => 'results'])->assertOk()
            ->assertSee('По банку')->assertDontSee('<html', false);
    }

    public function test_card_shows_rate_grid_history_and_similar(): void
    {
        $p = $this->product('FFIN', 'demand');
        $old = $p->currentRates()->first();
        $old->update(['valid_from' => today()->subMonth(), 'valid_to' => today()]);
        $p->rates()->create(['currency' => 'KZT', 'rate' => 15.1, 'effective_rate' => 16.0, 'valid_from' => today()]);

        $res = $this->get('/deposits/'.$p->slug)->assertOk()
            ->assertSee($p->name)->assertSee('15,1%')->assertSee('История ставки')->assertSee('Похожие вклады');
        $this->assertCount(2, $res->viewData('history'));
        $this->assertSame(15.1, $res->viewData('history')[1]['rate']);
        $this->assertTrue($res->viewData('similar')->every(fn ($o) => $o->product->type === $p->type && $o->product->bank_id !== $p->bank_id));

        $p->update(['is_active' => false]);
        $this->get('/deposits/'.$p->slug)->assertNotFound();
    }

    public function test_compare_up_to_four(): void
    {
        $ids = DepositProduct::orderBy('id')->limit(5)->pluck('id');
        $res = $this->get('/deposits/compare?ids='.$ids->join(','))->assertOk()->assertSee('Сравнение');
        $this->assertCount(4, $res->viewData('columns'));
        $this->assertSame($ids->take(4)->all(), $res->viewData('columns')->map(fn ($c) => $c['product']->id)->all());

        $this->get('/deposits/compare')->assertOk()->assertSee('Пока нечего сравнивать');
    }

    public function test_income_formula(): void
    {
        $this->assertEqualsWithDelta(160_000, Income::calc(1_000_000, 12, 15, 16.0), 0.01);
        $this->assertEqualsWithDelta(1_000_000 * ((1 + 0.15 / 12) ** 12 - 1), Income::calc(1_000_000, 12, 15), 0.01);
        $this->assertEqualsWithDelta(75_000, Income::calc(1_000_000, 6, 15, null, false), 0.01);
        $this->assertSame(0.0, Income::calc(0, 12, 15));
        $this->assertSame(['банков', 'банк', 'банка', 'банков', 'банк'], array_map(fn ($n) => plural($n, 'банк', 'банка', 'банков'), [9, 1, 3, 11, 21]));
    }

    public function test_home_links_into_catalog(): void
    {
        $p = DepositRate::current()->orderByDesc('rate')->first()->product;
        $this->get('/')->assertOk()->assertSee(route('deposits.show', $p), false)->assertSee(route('deposits.index'), false);
    }
}
