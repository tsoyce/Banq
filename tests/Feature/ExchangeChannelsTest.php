<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Bank;
use App\Models\DepositProduct;
use App\Models\DepositRate;
use App\Models\User;
use App\Services\Exchange\DataExchange;
use App\Services\Exchange\ImportOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A39 · Три способа загрузки: файлы (XML, YAML, NDJSON, TSV), API для агентов, табличный редактор. */
class ExchangeChannelsTest extends TestCase
{
    use RefreshDatabase;

    private const BUNDLE = [
        'banks' => [['country' => 'KZ', 'code' => 'BCC', 'name' => 'Банк ЦентрКредит',
            'products' => [['name' => 'Срочный', 'type' => 'term', 'rates' => [['term_months' => 12, 'rate' => 14.5], ['term_months' => 6, 'rate' => 14]]]]]],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    private function seedBundle(): void
    {
        $ex = app(DataExchange::class);
        $ex->import($ex->parse(json_encode(self::BUNDLE), 'json'), new ImportOptions);
    }

    public function test_foreign_xml_shapes_are_understood(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<banks>
  <bank code="BCC" country="KZ"><name>Банк ЦентрКредит</name><aliases><alias>ЦентрКредит</alias><alias>CenterCredit</alias></aliases>
    <deposit_products><deposit_product><name>Срочный</name><type>term</type>
      <rates><rate><term_months>12</term_months><rate>14,5%</rate></rate></rates></deposit_product></deposit_products>
  </bank>
</banks>
XML;
        $ex = app(DataExchange::class);
        $this->assertSame('xml', $ex->detect($xml));
        $r = $ex->import($ex->parse($xml), new ImportOptions)->toArray();
        $this->assertSame(3, $r['totals']['created'], json_encode($r['rows'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(['ЦентрКредит', 'CenterCredit'], Bank::first()->aliases);
        $this->assertEquals(14.5, (float) DepositRate::first()->rate);
    }

    public function test_xml_with_doctype_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(DataExchange::class)->parse('<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><banks><bank><name>&e;</name></bank></banks>', 'xml');
    }

    public function test_yaml_ndjson_tsv_and_detection(): void
    {
        $ex = app(DataExchange::class);
        $yaml = "banks:\n  - country: KZ\n    code: BCC\n    name: Банк ЦентрКредит\n    financials_at: 2026-10-01\n";
        $this->assertSame('yaml', $ex->detect($yaml));
        $this->assertSame(1, $ex->import($ex->parse($yaml), new ImportOptions)->total('created'));
        $this->assertSame('2026-10-01', Bank::first()->financials_at->toDateString());

        $nd = '{"_entity":"banks","country":"KZ","code":"KASPI","name":"Kaspi Bank"}'."\n".'{"_entity":"banks","country":"KZ","code":"HALYK","name":"Halyk Bank"}';
        $this->assertSame('ndjson', $ex->detect($nd));
        $this->assertSame(2, $ex->import($ex->parse($nd), new ImportOptions)->total('created'));

        $tsv = "Банк\tВклад\tТип\tСрок\tСтавка\n";
        $this->assertSame('tsv', $ex->detect($tsv));
    }

    public function test_row_id_allows_renaming_key_and_delete_marks(): void
    {
        $this->seedBundle();
        $bank = Bank::where('code', 'BCC')->first();
        $ex = app(DataExchange::class);

        // по _id меняем код — это переименование, а не новый банк
        $r = $ex->import(['banks' => [['_id' => $bank->id, 'country' => 'KZ', 'code' => 'CENTER', 'name' => $bank->name]]], new ImportOptions)->toArray();
        $this->assertSame(1, $r['totals']['updated'], json_encode($r['rows'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, Bank::count());
        $this->assertSame('CENTER', $bank->fresh()->code);

        // удаление без разрешения — ошибка строки
        $r = $ex->import(['deposit_products' => [['_id' => DepositProduct::first()->id, '_delete' => true]]], new ImportOptions)->toArray();
        $this->assertSame(1, $r['totals']['error']);
        $this->assertSame(1, DepositProduct::count());

        // «удалить» ставку = закрыть, история остаётся
        $rate = DepositRate::where('term_months', 12)->first();
        $r = $ex->import(['deposit_rates' => [['_id' => $rate->id, '_delete' => 'да']]], new ImportOptions(allowDelete: true))->toArray();
        $this->assertSame(1, $r['totals']['deleted']);
        $this->assertNotNull($rate->fresh()->valid_to);
        $this->assertSame(2, DepositRate::count());

        // правка ставки по _id: старая закрывается, новая добавляется
        $six = DepositRate::where('term_months', 6)->first();
        $r = $ex->import(['deposit_rates' => [['_id' => $six->id, 'product' => $six->product->slug, 'term_months' => 6, 'rate' => '15,1']]], new ImportOptions)->toArray();
        $this->assertSame(1, $r['totals']['updated'], json_encode($r['rows'], JSON_UNESCAPED_UNICODE));
        $this->assertEquals(15.1, (float) DepositRate::where('term_months', 6)->whereNull('valid_to')->value('rate'));

        // удаление вклада — по ключу, ставки уходят каскадом
        $r = $ex->import(['deposit_products' => [['bank' => 'CENTER', 'name' => 'Срочный', '_action' => 'delete']]], new ImportOptions(allowDelete: true))->toArray();
        $this->assertSame(1, $r['totals']['deleted'], json_encode($r['rows'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, DepositRate::count());
    }

    public function test_agent_api_about_openapi_paging_upsert_delete_idempotency(): void
    {
        [, $write] = ApiToken::issue('agent', ['read', 'write']);
        [, $full] = ApiToken::issue('admin-agent', ['read', 'write', 'delete']);
        $h = ['Authorization' => "Bearer {$write}"];

        $this->getJson('/api/v1')->assertOk()->assertJsonStructure(['agent_workflow', 'entities' => ['banks'], 'formats']);
        $this->getJson('/api/v1/openapi.json')->assertOk()->assertJsonPath('openapi', '3.1.0')->assertJsonPath('components.schemas.deposit_rates.required', ['product', 'rate']);

        // POST по сущности + Idempotency-Key
        $rows = [['country' => 'KZ', 'code' => 'BCC', 'name' => 'Банк ЦентрКредит'], ['country' => 'KZ', 'code' => 'KASPI', 'name' => 'Kaspi Bank']];
        $this->postJson('/api/v1/banks?dry_run=1', $rows, $h)->assertOk()->assertJsonPath('totals.created', 2);
        $this->assertSame(0, Bank::count());
        $this->postJson('/api/v1/banks', $rows, $h + ['Idempotency-Key' => 'k1'])->assertOk()->assertJsonPath('totals.created', 2);
        $this->postJson('/api/v1/banks', $rows, $h + ['Idempotency-Key' => 'k1'])->assertOk()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('totals.created', 2);
        $this->postJson('/api/v1/banks', [['country' => 'KZ', 'code' => 'X', 'name' => 'X']], $h + ['Idempotency-Key' => 'k1'])->assertStatus(409);
        $this->postJson('/api/v1/banks', $rows, $h)->assertOk()->assertJsonPath('totals.unchanged', 2);

        // постранично и с _id
        $this->getJson('/api/v1/banks?limit=1&ids=1', $h)->assertOk()->assertJsonPath('total', 2)->assertJsonPath('count', 1)->assertJsonPath('next_offset', 1)->assertJsonStructure(['data' => [['_id']]]);
        $this->getJson('/api/v1/banks?limit=1&offset=1', $h)->assertOk()->assertJsonPath('next_offset', null);
        $this->getJson('/api/v1/banks?updated_since='.now()->addDay()->toIso8601String(), $h)->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/banks?updated_since=вчера', $h)->assertStatus(422);

        // XML телом
        $xml = '<banq><banks><row><country>KZ</country><code>HALYK</code><name>Halyk Bank</name></row></banks></banq>';
        $this->call('POST', '/api/v1/import', [], [], [], ['HTTP_AUTHORIZATION' => "Bearer {$write}", 'CONTENT_TYPE' => 'application/xml'], $xml)->assertOk()->assertJsonPath('totals.created', 1);

        // удаление: нужно право delete
        $this->deleteJson('/api/v1/banks', [['code' => 'KASPI']], $h)->assertForbidden();
        $this->deleteJson('/api/v1/banks', [['country' => 'KZ', 'code' => 'KASPI']], ['Authorization' => "Bearer {$full}"])->assertOk()->assertJsonPath('totals.deleted', 1);
        $this->assertSame(2, Bank::count());
    }

    public function test_grid_page_rows_and_save(): void
    {
        $this->seedBundle();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/exchange/grid')->assertRedirect('/admin/exchange/grid/deposit_rates');
        $this->actingAs($admin)->get('/admin/exchange/grid/deposit_rates')->assertOk()->assertSee('Только действующие')->assertSee('sheet(', false);
        $this->actingAs($admin)->get('/admin/exchange/grid/nope')->assertNotFound();

        $data = $this->actingAs($admin)->getJson('/admin/exchange/grid/deposit_rates/rows?country=KZ&current=1')->assertOk()->json();
        $this->assertCount(2, $data['rows']);
        $this->assertArrayHasKey('_id', $data['rows'][0]);
        $this->assertContains('BCC', $data['options']['bank']);

        $row = collect($data['rows'])->firstWhere('term_months', 12);
        $rows = [
            ['_id' => $row['_id']] + array_map(fn ($v) => $v === null ? '' : (string) $v, $row) + ['rate' => '16'],
            ['bank' => 'BCC', 'product' => 'Срочный', 'term_months' => '24', 'rate' => '13,5'],
            ['product' => 'нет-такого', 'rate' => '1'],
        ];
        $rows[0]['rate'] = '16';

        $check = $this->actingAs($admin)->postJson('/admin/exchange/grid/deposit_rates', ['rows' => $rows, 'dry_run' => true, 'country' => 'KZ'])->assertOk()->json();
        $this->assertSame(['created' => 1, 'updated' => 1, 'error' => 1], array_intersect_key($check['totals'], array_flip(['created', 'updated', 'error'])));
        $this->assertSame(3, collect($check['rows'])->firstWhere('action', 'error')['row']);
        $this->assertSame(2, DepositRate::count());

        $this->actingAs($admin)->postJson('/admin/exchange/grid/deposit_rates', ['rows' => $rows, 'country' => 'KZ'])->assertOk()->assertJsonPath('totals.created', 1);
        $this->assertEquals(16, (float) DepositRate::where('term_months', 12)->whereNull('valid_to')->value('rate'));
        $this->assertSame(4, DepositRate::count());
    }
}
