<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Bank;
use App\Models\Country;
use App\Models\DepositRate;
use App\Models\Import;
use App\Services\Exchange\DataExchange;
use App\Services\Exchange\ImportOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A22/A23 · Универсальный обмен данными: пакеты, форматы, история ставок, dry-run, CLI, API. */
class DataExchangeTest extends TestCase
{
    use RefreshDatabase;

    private const BUNDLE = [
        'countries' => [['code' => 'UZ', 'name' => 'Узбекистан', 'name_genitive' => 'Узбекистана', 'currency' => 'UZS', 'currency_symbol' => 'сум',
            'deposit_guarantee' => ['short' => 'ФГВ', 'limits' => ['term' => 100000000]]]],
        'banks' => [
            ['country' => 'KZ', 'code' => 'BCC', 'name' => 'Банк ЦентрКредит', 'aliases' => 'ЦентрКредит|CenterCredit', 'deposit_insured' => 'да',
                'products' => [['name' => 'Срочный', 'type' => 'Пополнение', 'min_amount' => '50 000',
                    'rates' => [['term_months' => 12, 'rate' => '14,5%', 'effective_rate' => 15.4], ['term_months' => 6, 'rate' => 14]]]]],
            ['country' => 'UZ', 'code' => 'BCC', 'name' => 'Другой BCC в Узбекистане'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    private function run_(array $bundle, array $opts = []): array
    {
        $ex = app(DataExchange::class);

        return $ex->import($ex->parse(json_encode($bundle), 'json'), ImportOptions::fromArray($opts))->toArray();
    }

    public function test_nested_bundle_imports_and_reimport_is_idempotent(): void
    {
        $r = $this->run_(self::BUNDLE);
        $this->assertSame(['created' => 6, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0, 'skipped' => 0, 'error' => 0], $r['totals'], json_encode($r['rows'], JSON_UNESCAPED_UNICODE));

        $bcc = Bank::query()->whereHas('country', fn ($q) => $q->where('code', 'KZ'))->where('code', 'BCC')->firstOrFail();
        $this->assertSame(['ЦентрКредит', 'CenterCredit'], $bcc->aliases);
        $this->assertTrue($bcc->deposit_insured);
        $this->assertSame('term', $bcc->depositProducts()->first()->type->value);
        $this->assertEquals(14.5, (float) $bcc->depositRates()->where('term_months', 12)->value('rate'));
        $this->assertSame(2, Bank::query()->where('code', 'BCC')->count(), 'код уникален внутри страны');
        $this->assertSame(100000000, Country::where('code', 'UZ')->first()->guaranteeLimit('term'));

        $again = $this->run_(self::BUNDLE);
        $this->assertSame(6, $again['totals']['unchanged']);
        $this->assertSame(2, Import::count());
    }

    public function test_new_rate_closes_old_one_and_history_is_kept(): void
    {
        $this->run_(self::BUNDLE);
        $r = $this->run_(['deposit_rates' => [['bank' => 'ЦентрКредит', 'product' => 'Срочный', 'term_months' => 12, 'rate' => 15, 'valid_from' => today()->addDay()->toDateString()]]]);

        $this->assertSame(1, $r['totals']['updated']);
        $this->assertEquals([14.5, 15.0], $r['rows'][0]['changes']['rate']);
        $rates = DepositRate::query()->where('term_months', 12)->orderBy('id')->get();
        $this->assertCount(2, $rates);
        $this->assertNotNull($rates[0]->valid_to);
        $this->assertNull($rates[1]->valid_to);
    }

    public function test_dry_run_writes_nothing_and_errors_do_not_stop_import(): void
    {
        $bundle = self::BUNDLE;
        $bundle['banks'][] = ['code' => 'BAD', 'name' => 'Плохой', 'website' => 'не ссылка'];
        $bundle['deposit_rates'] = [['bank' => 'NOPE', 'product' => 'x', 'rate' => 1]];

        $r = $this->run_($bundle, ['dry_run' => true]);
        $this->assertSame(6, $r['totals']['created']);
        $this->assertSame(2, $r['totals']['error']);
        $this->assertSame(0, Bank::count());
        $this->assertSame(0, Import::count());
        $this->assertStringContainsString('не найден', collect($r['rows'])->where('entity', 'deposit_rates')->firstWhere('action', 'error')['message']);
    }

    public function test_export_roundtrip_in_every_format_is_unchanged(): void
    {
        $this->run_(self::BUNDLE);
        $ex = app(DataExchange::class);
        foreach (array_keys($ex->formats()) as $format) { // A39 · + ndjson, xml, yaml, tsv
            $bundle = $ex->export();
            $content = $ex->render($bundle, $format);
            $r = $ex->import($ex->parse($content, $format), new ImportOptions)->toArray();
            $this->assertSame(0, $r['totals']['created'] + $r['totals']['updated'] + $r['totals']['error'], $format.': '.json_encode($r['rows'], JSON_UNESCAPED_UNICODE));
        }
    }

    public function test_flat_csv_with_russian_headers(): void
    {
        $this->run_(self::BUNDLE);
        $csv = "Банк;Вклад;Срок;Ставка;ГЭСВ\nBCC;Срочный;24;13,2;14,0\n";
        $ex = app(DataExchange::class);
        $r = $ex->import($ex->parse($csv, 'csv', 'rates'), new ImportOptions(country: 'KZ'))->toArray();
        $this->assertSame(1, $r['totals']['created'], json_encode($r['rows'], JSON_UNESCAPED_UNICODE));
        $this->assertEquals(13.2, (float) DepositRate::where('term_months', 24)->value('rate'));
    }

    public function test_cli_import_export_schema(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'banq').'.json';
        file_put_contents($path, json_encode(self::BUNDLE));
        $this->artisan('banq:import', ['file' => $path, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Bank::count());
        $this->artisan('banq:import', ['file' => $path])->assertSuccessful();
        $this->assertSame(2, Bank::count());
        $this->artisan('banq:export', ['entities' => ['banks'], '--format' => 'csv'])->expectsOutputToContain('Банк ЦентрКредит')->assertSuccessful();
        $this->artisan('banq:schema')->expectsOutputToContain('deposit_rates')->assertSuccessful();
        @unlink($path);
    }

    public function test_api_requires_token_with_ability(): void
    {
        [, $read] = ApiToken::issue('read', ['read']);
        [, $write] = ApiToken::issue('write', ['read', 'write']);

        $this->getJson('/api/v1/schema')->assertUnauthorized();
        $this->getJson('/api/v1/schema', ['Authorization' => "Bearer {$read}"])->assertOk()->assertJsonPath('order.0', 'countries');
        $this->postJson('/api/v1/import', self::BUNDLE, ['Authorization' => "Bearer {$read}"])->assertForbidden();

        $this->postJson('/api/v1/import?dry_run=1', self::BUNDLE, ['Authorization' => "Bearer {$write}"])->assertOk()->assertJsonPath('totals.created', 6);
        $this->assertSame(0, Bank::count());
        $this->postJson('/api/v1/import', self::BUNDLE, ['Authorization' => "Bearer {$write}"])->assertOk()->assertJsonPath('totals.created', 6);
        $this->assertSame('api', Import::first()->source);
        $this->assertSame('api', DepositRate::first()->source);

        $this->getJson('/api/v1/banks?country=KZ', ['Authorization' => "Bearer {$read}"])->assertOk()->assertJsonPath('count', 1)->assertJsonPath('data.0.code', 'BCC');
        $this->get('/api/v1/export?entities=deposit_rates&format=csv', ['Authorization' => "Bearer {$read}"])->assertOk()->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }
}
