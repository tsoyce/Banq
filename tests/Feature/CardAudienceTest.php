<?php

namespace Tests\Feature;

use App\Admin\Resource;
use App\Enums\CardAudience;
use App\Models\Bank;
use App\Models\Card;
use App\Models\CardTier;
use App\Services\Exchange\DataExchange;
use App\Services\Exchange\ImportOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** A54 · Карты физлицам и бизнесу, вкладки, хлебные крошки «назад», значки уровней. */
class CardAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    private function importDemo(): void
    {
        $ex = app(DataExchange::class);
        $r = $ex->import($ex->parse(file_get_contents(base_path('tests/fixtures/cards-demo.json')), 'json'), ImportOptions::fromArray([]))->toArray();
        $this->assertSame(0, $r['totals']['error'], json_encode($r['rows'], JSON_UNESCAPED_UNICODE));
    }

    public function test_migration_moves_business_segment_and_business_tiers_to_audience(): void
    {
        $this->importDemo();
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
        $this->assertSame('business', DB::table('cards')->where('slug', 'demo-bcc-business')->value('segment') ?? 'business');

        $bank = DB::table('banks')->value('id');
        $mk = fn ($slug, $segment) => DB::table('cards')->insertGetId(['bank_id' => $bank, 'kind' => 'debit', 'name' => $slug, 'slug' => $slug, 'segment' => $segment, 'format' => 'physical', 'created_at' => now(), 'updated_at' => now()]);
        $mk('old-segment', 'business');
        $tiered = $mk('old-tier', 'premium');
        DB::table('card_networks')->insert(['card_id' => $tiered, 'payment_system_id' => DB::table('payment_systems')->where('code', 'MASTERCARD')->value('id'), 'card_tier_id' => DB::table('card_tiers')->where('slug', 'mastercard-business')->value('id')]);
        $mk('old-mass', 'mass');

        $this->artisan('migrate')->assertSuccessful();
        $this->assertSame(['business', null], [Card::where('slug', 'old-segment')->first()->audience->value, Card::where('slug', 'old-segment')->value('segment')]);
        $this->assertSame(['business', 'premium'], [Card::where('slug', 'old-tier')->first()->audience->value, Card::where('slug', 'old-tier')->value('segment')]);
        $this->assertSame('personal', Card::where('slug', 'old-mass')->first()->audience->value);
    }

    public function test_catalog_splits_personal_and_business(): void
    {
        $this->importDemo();
        $this->assertTrue(Card::where('slug', 'demo-bcc-business')->first()->isBusiness());

        $this->get('/cards')->assertOk()->assertSee('Кэшбэк Демо')->assertDontSee('Бизнес Демо')->assertSee('Физлицам')->assertSee('Бизнесу');
        $this->get('/cards?audience=business')->assertOk()->assertSee('Бизнес Демо')->assertDontSee('Кэшбэк Демо')->assertSee('для бизнеса');
        $this->get('/cards?audience=all')->assertOk()->assertSee('Бизнес Демо')->assertSee('Кэшбэк Демо');
        $this->get('/cards?audience=business&kind[]=credit', ['X-Partial' => 'results'])->assertOk()->assertDontSee('Бизнес Демо')->assertDontSee('Рассрочка Демо');
        // форма фильтров помнит аудиторию
        $this->get('/cards?audience=business')->assertSee('name="audience" value="business"', false);
    }

    public function test_payment_system_and_bank_pages_have_tabs_and_back_links(): void
    {
        $this->importDemo();
        $this->get('/payment-systems/visa')->assertOk()
            ->assertSee('data-audience="business"', false)->assertSee('data-audience="personal"', false)
            ->assertSee('?from=ps:visa', false)->assertSee('Для бизнеса')->assertSee('tier-badge', false);

        $bank = Bank::where('code', 'BCC')->first();
        $this->get('/banks/'.$bank->slug)->assertOk()->assertSee('?from=bank', false)->assertSee('data-kind="credit"', false);

        $this->get('/cards/demo-bcc-business?from=ps:visa')->assertOk()
            ->assertSeeInOrder(['Платёжные системы', 'Visa', 'Business', 'Бизнес Демо']);
        $this->get('/cards/demo-bcc-business?from=bank')->assertOk()->assertSeeInOrder(['Банки', $bank->displayName(), 'Бизнес Демо']);
        $this->get('/cards/demo-bcc-business?from=ps:mir')->assertOk()->assertSeeInOrder(['Карты', 'Бизнесу', 'Дебетовые', 'Бизнес Демо']); // чужая система — обычный путь
        $this->get('/cards/demo-bcc-cashback')->assertOk()->assertSee('/payment-systems/visa#tier-gold', false)->assertSee('/banks/'.$bank->slug, false);
    }

    public function test_import_understands_audience_labels_and_old_segment(): void
    {
        $this->importDemo();
        $ex = app(DataExchange::class);
        $r = $ex->import(['cards' => [
            ['bank' => 'BCC', 'name' => 'Корп', 'kind' => 'debit', 'audience' => 'юрлица и ИП'],
            ['bank' => 'BCC', 'name' => 'Старый бизнес', 'kind' => 'debit', 'segment' => 'business'],
            ['bank' => 'BCC', 'name' => 'По уровню', 'kind' => 'credit', 'networks' => ['Mastercard Business']],
            ['bank' => 'BCC', 'name' => 'Личная', 'kind' => 'debit', 'audience' => 'физлица'],
        ]], ImportOptions::fromArray([]))->toArray();
        $this->assertSame(0, $r['totals']['error'], json_encode($r['rows'], JSON_UNESCAPED_UNICODE));
        $aud = Card::whereIn('name', ['Корп', 'Старый бизнес', 'По уровню', 'Личная'])->get()->mapWithKeys(fn ($c) => [$c->name => $c->audience->value])->all();
        $this->assertSame(['Корп' => 'business', 'Старый бизнес' => 'business', 'По уровню' => 'business', 'Личная' => 'personal'], $aud);
        $this->assertNull(Card::where('name', 'Старый бизнес')->value('segment'));
        $this->assertSame(CardAudience::Business, CardAudience::fromLabel('Корпоративная'));
    }

    public function test_tier_badges_default_and_upload(): void
    {
        $tier = CardTier::where('slug', 'mir-privilege')->first();
        $this->assertSame('Привилегия', $tier->shortName());
        $this->assertSame('black', $tier->tone());
        $this->assertSame('gold', CardTier::where('slug', 'visa-gold')->first()->tone());
        $this->assertSame('business', CardTier::where('slug', 'visa-business')->first()->tone());
        $html = view('partials.tier-badge', ['tier' => $tier])->render();
        $this->assertStringContainsString('<svg class="tier-badge', $html);
        $this->assertStringContainsString('Привилегия', $html);

        $admin = $this->admin();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 101"><rect width="160" height="101" fill="#0f754e"/><text x="10" y="60">MIR Privilege</text></svg>';
        $form = fn ($file) => ['payment_system_id' => $tier->payment_system_id, 'name' => $tier->name, 'code' => $tier->code, 'level' => $tier->level, 'segment' => $tier->segment,
            'badge_text' => 'MIR Privilege', 'badge__upload' => $file];
        $this->actingAs($admin)->put('/admin/card-tiers/'.$tier->id, $form(UploadedFile::fake()->createWithContent('mir.svg', $svg)))->assertRedirect()->assertSessionHasNoErrors();
        $tier->refresh();
        $this->assertSame('MIR Privilege', $tier->badgeLabel());
        $this->assertStringStartsWith('/uploads/card-tiers/mir-', $tier->badge);
        $this->assertFileExists(public_path($tier->badge));
        $this->assertStringContainsString('<img src="'.$tier->badge.'"', view('partials.tier-badge', ['tier' => $tier])->render());

        $bad = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $this->actingAs($admin)->put('/admin/card-tiers/'.$tier->id, $form(UploadedFile::fake()->createWithContent('x.svg', $bad)))->assertSessionHasErrors('badge');
        $this->assertFalse(Resource::safeSvg('<svg onload="x()"></svg>'));
        $this->assertFalse(Resource::safeSvg('<svg><image href="https://evil.example/x.png"/></svg>'));
        $this->assertTrue(Resource::safeSvg($svg));

        File::delete(public_path($tier->badge));
    }
}
