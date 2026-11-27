<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\CurrencyRate;
use App\Services\Currency\CurrencyRates;
use App\Services\Currency\Providers\CbuProvider;
use App\Services\Currency\Providers\NbrkProvider;
use App\Support\CurrencyInfo;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A40 · Курсы валют центробанков, карточка на главной, настраиваемая бегущая строка. */
class CurrencyRatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        Carbon::setTestNow(Carbon::parse('2026-11-27 12:00', 'Asia/Almaty'));
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function kz(): Country
    {
        return Country::query()->where('code', 'KZ')->firstOrFail();
    }

    private function fakeNbrk(): void
    {
        Http::fake(['nationalbank.kz/*' => Http::response(file_get_contents(base_path('tests/fixtures/nbrk-rates.xml')), 200, ['Content-Type' => 'text/xml'])]);
    }

    public function test_nbrk_feed_is_parsed_with_nominal_and_change(): void
    {
        $data = NbrkProvider::parse(file_get_contents(base_path('tests/fixtures/nbrk-rates.xml')));

        $this->assertSame('2026-11-27', $data['date']);
        $this->assertCount(7, $data['rates']);
        $amd = collect($data['rates'])->firstWhere('currency', 'AMD');
        $this->assertSame(10, $amd['nominal']);
        $this->assertSame(12.83, $amd['rate']);
        $this->assertSame(-0.03, collect($data['rates'])->firstWhere('currency', 'RUB')['change']);
    }

    public function test_sync_stores_rates_and_board_follows_admin_choice(): void
    {
        $this->fakeNbrk();
        $fx = app(CurrencyRates::class);

        $this->assertSame(7, $fx->sync($this->kz()));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'fdate=27.11.2026'));
        $this->assertSame(7, CurrencyRate::query()->count());

        // по умолчанию для KZ — USD, EUR, RUB, CNY
        $board = $fx->board($this->kz());
        $this->assertSame(['USD', 'EUR', 'RUB', 'CNY'], array_column($board['items'], 'code'));
        $this->assertSame(0.28, $board['items'][0]['delta']);
        $this->assertSame('Доллар США', $board['items'][0]['name']);
        $this->assertFalse($board['stale']);

        // повторная загрузка той же даты не плодит строки
        $fx->sync($this->kz());
        $this->assertSame(7, CurrencyRate::query()->count());

        app(Settings::class)->set(['fx.currencies' => ['KZ' => ['AMD', 'USD']]]);
        app()->forgetInstance(CurrencyRates::class);
        $board = app(CurrencyRates::class)->board($this->kz());
        $this->assertSame(['AMD', 'USD'], array_column($board['items'], 'code'));
        $this->assertEqualsWithDelta(1.283, $board['items'][0]['unit'], 0.0001);
    }

    public function test_delta_is_computed_from_history_when_source_has_none(): void
    {
        $kz = $this->kz();
        foreach (['2026-11-26' => 450.0, '2026-11-27' => 455.5] as $date => $rate) {
            CurrencyRate::query()->insert(['country_id' => $kz->id, 'currency' => 'USD', 'nominal' => 1, 'rate' => $rate, 'change' => null, 'date' => $date, 'source' => 'nbrk', 'created_at' => now(), 'updated_at' => now()]);
        }

        $usd = app(CurrencyRates::class)->board($kz, ['USD'])['items'][0];
        $this->assertEqualsWithDelta(5.5, $usd['delta'], 0.0001);
        $this->assertSame([450.0, 455.5], $usd['trend']);
    }

    public function test_source_failure_keeps_last_rates(): void
    {
        $kz = $this->kz();
        CurrencyRate::query()->insert(['country_id' => $kz->id, 'currency' => 'USD', 'nominal' => 1, 'rate' => 440, 'change' => 1, 'date' => '2026-11-20', 'source' => 'nbrk', 'created_at' => now(), 'updated_at' => now()]);
        Http::fake(['nationalbank.kz/*' => Http::response('Service Unavailable', 503)]);
        $fx = app(CurrencyRates::class);

        try {
            $fx->sync($kz);
            $this->fail('ожидалась ошибка источника');
        } catch (\RuntimeException) {
        }

        $this->assertFalse($fx->lastStatus($kz)['ok']);
        $board = $fx->board($kz);
        $this->assertSame('USD', $board['items'][0]['code']);
        $this->assertTrue($board['stale']);
        $this->get('/')->assertOk()->assertSee('показан последний полученный курс');
    }

    public function test_home_card_and_ticker_show_currency_rates(): void
    {
        $this->fakeNbrk();
        app(CurrencyRates::class)->sync($this->kz());

        $this->get('/')->assertOk()
            ->assertSee('Курсы валют')
            ->assertSee('458,85')
            ->assertSee('Национальный Банк Казахстана')
            ->assertSee('ticker-tag', false)
            ->assertSee('НБРК · 27 ноя', false)
            ->assertDontSee('Скоро здесь побегут ставки банков');
    }

    public function test_ticker_falls_back_and_follows_settings(): void
    {
        $this->get('/')->assertOk()->assertSee('Скоро здесь побегут ставки банков');

        app(Settings::class)->set(['ticker.sources' => ['text'], 'ticker.text' => "Первое объявление\nВторое объявление"]);
        $this->get('/')->assertSee('Первое объявление')->assertSee('Второе объявление');

        app(Settings::class)->set(['ticker.enabled' => false]);
        $this->get('/')->assertDontSee('Первое объявление');
    }

    public function test_admin_pages_save_ticker_and_currencies(): void
    {
        $this->fakeNbrk();
        $admin = $this->admin();
        $kz = $this->kz();

        $this->actingAs($admin)->get('/admin/ticker')->assertOk()->assertSee('Типы контента')->assertSee('Курсы валют');
        $this->actingAs($admin)->put('/admin/ticker', ['enabled' => 1, 'sources' => ['text', 'currency'], 'text' => 'Привет', 'speed' => 7])->assertRedirect();
        app()->forgetInstance(Settings::class);
        $this->assertSame(['text', 'currency'], settings('ticker.sources'));
        $this->assertSame(7, settings('ticker.speed'));
        $this->actingAs($admin)->put('/admin/ticker', ['sources' => ['nope'], 'speed' => 5])->assertSessionHasErrors('sources.0');

        $this->actingAs($admin)->post("/admin/currency/{$kz->id}/sync")->assertRedirect()->assertSessionHas('status');
        $this->actingAs($admin)->get('/admin/currency')->assertOk()->assertSee('Национальный Банк Казахстана')->assertSee('AMD');
        $this->actingAs($admin)->put('/admin/currency', ['codes' => ['KZ' => ['eur', 'USD']], 'home_card' => 1])->assertRedirect();
        app()->forgetInstance(Settings::class);
        $this->assertSame(['KZ' => ['EUR', 'USD']], settings('fx.currencies'));

        $this->actingAs($admin)->put('/admin/currency', ['codes' => ['KZ' => ['EUR']]]);
        app()->forgetInstance(Settings::class);
        $this->get('/')->assertDontSee('id="fx"', false);
    }

    public function test_sync_command(): void
    {
        $this->fakeNbrk();
        $this->artisan('banq:fx-sync', ['--country' => 'KZ'])->expectsOutputToContain('KZ: 7 курсов на 2026-11-27')->assertSuccessful();
    }

    public function test_other_providers_and_currency_info(): void
    {
        $uz = CbuProvider::parse([['Ccy' => 'USD', 'CcyNm_RU' => 'Доллар США', 'Nominal' => '1', 'Rate' => '12050.12', 'Diff' => '-3.10', 'Date' => '27.11.2026']]);
        $this->assertSame('2026-11-27', $uz['date']);
        $this->assertSame(-3.1, $uz['rates'][0]['change']);

        $this->assertSame('🇺🇸', CurrencyInfo::flag('USD'));
        $this->assertSame('🇪🇺', CurrencyInfo::flag('EUR'));
        $this->assertSame('', CurrencyInfo::flag('XAU'));
        $this->assertSame('Египетский фунт', CurrencyInfo::name('EGP', 'ЕГИПЕТСКИЙ ФУНТ'));
    }
}
