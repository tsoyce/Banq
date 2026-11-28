<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdSlot;
use App\Models\AdStat;
use App\Models\CodeSnippet;
use App\Models\User;
use App\Services\Ads\AdServer;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** A50 · коды и счётчики, ads.txt. A51 · своя реклама: выбор, ограничения, учёт показов и кликов, админка. */
class AdsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    private function campaign(array $c = [], array $cr = []): AdCreative
    {
        $campaign = AdCampaign::create($c + ['name' => 'Весна', 'advertiser' => 'Банк', 'freq_cap' => 0]);

        return $campaign->creatives()->create($cr + ['type' => 'native', 'name' => 'Карточка', 'title' => 'Вклад 17% в тенге', 'body' => 'Откройте онлайн', 'url' => 'https://bank.example/deposit?utm_source=banq', 'sponsor' => 'Пример Банк', 'disclosure' => 'БИН 123']);
    }

    private function token(AdCreative $cr, string $slot = 'sidebar'): string
    {
        return app(AdServer::class)->sign($cr->id, $cr->campaign_id, $slot);
    }

    // ── A50 · Коды и счётчики ─────────────────────────────

    public function test_snippets_render_by_id_country_and_staff(): void
    {
        CodeSnippet::create(['name' => 'GA', 'provider' => 'gtag', 'tracking_id' => 'G-ABC1234']);
        CodeSnippet::create(['name' => 'Ads', 'provider' => 'gtag', 'tracking_id' => 'AW-123456789']);
        CodeSnippet::create(['name' => 'GTM', 'provider' => 'gtm', 'tracking_id' => 'GTM-XYZ12']);
        CodeSnippet::create(['name' => 'Метрика', 'provider' => 'metrika', 'tracking_id' => '98765432', 'countries' => ['RU']]);
        CodeSnippet::create(['name' => 'Свой', 'provider' => 'custom', 'code' => '<script>window.mine=1</script>', 'placement' => 'body_end', 'skip_staff' => false]);
        CodeSnippet::create(['name' => 'Битый', 'provider' => 'gtm', 'tracking_id' => 'GTM-<script>']);

        $html = $this->get('/blog')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'googletagmanager.com/gtag/js?id=G-ABC1234'));
        $this->assertStringContainsString("gtag('config','AW-123456789')", $html);
        $this->assertStringContainsString("'dataLayer','GTM-XYZ12'", $html);
        $this->assertStringContainsString('ns.html?id=GTM-XYZ12', $html);
        $this->assertStringNotContainsString('mc.yandex.ru', $html, 'Метрика только для России');
        $this->assertStringNotContainsString('GTM-<script>', $html);
        $this->assertMatchesRegularExpression('#window\.mine=1</script>\s*</body>#', $html);

        // сотрудник: счётчики не грузятся, свой код с skip_staff=false — да
        $staff = $this->actingAs($this->admin())->get('/blog')->getContent();
        $this->assertStringNotContainsString('gtag/js', $staff);
        $this->assertStringContainsString('window.mine=1', $staff);
        // в админке кодов нет
        $this->assertStringNotContainsString('window.mine=1', $this->get('/admin')->getContent());
    }

    public function test_admin_validates_ids_and_editor_has_no_access(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/code-snippets')->assertOk();
        $this->actingAs($admin)->get('/admin/code-snippets/create')->assertOk()->assertSee('Яндекс Метрика');

        $this->actingAs($admin)->post('/admin/code-snippets', ['name' => 'GA', 'provider' => 'gtag', 'tracking_id' => 'UA-1', 'placement' => 'head'])->assertSessionHasErrors('tracking_id');
        $this->actingAs($admin)->post('/admin/code-snippets', ['name' => 'AdSense', 'provider' => 'adsense', 'tracking_id' => 'pub-1234567890123456', 'placement' => 'head', 'skip_staff' => 1, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame('ca-pub-1234567890123456', CodeSnippet::firstWhere('provider', 'adsense')->tracking_id);
        $this->actingAs($admin)->post('/admin/code-snippets', ['name' => 'Пусто', 'provider' => 'custom', 'placement' => 'head'])->assertSessionHasErrors('code');

        $editor = User::factory()->create(['role' => 'editor']);
        $this->actingAs($editor)->get('/admin/code-snippets')->assertForbidden();
    }

    public function test_ads_txt_merges_manual_text_and_adsense(): void
    {
        $this->get('/ads.txt')->assertNotFound();
        CodeSnippet::create(['name' => 'AdSense', 'provider' => 'adsense', 'tracking_id' => 'ca-pub-1234567890123456']);
        app(Settings::class)->set(['ads.txt' => "example.com, 42, RESELLER\ngoogle.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0"]);

        $body = $this->get('/ads.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8')->getContent();
        $this->assertStringContainsString('example.com, 42, RESELLER', $body);
        $this->assertSame(1, substr_count($body, 'pub-1234567890123456'));
    }

    // ── A51 · Своя реклама ────────────────────────────────

    public function test_native_ad_shows_with_label_reserved_place_and_signed_click(): void
    {
        $cr = $this->campaign();
        $html = $this->get('/blog')->assertOk()->getContent();

        $this->assertStringContainsString('Вклад 17% в тенге', $html);
        $this->assertStringContainsString('Реклама · Пример Банк', $html);
        $this->assertStringContainsString('title="БИН 123"', $html);
        $this->assertStringContainsString('rel="sponsored noopener"', $html);
        $this->assertStringContainsString('--ar: 300 / 250', $html);
        $this->assertStringNotContainsString('bank.example', $html, 'прямой ссылки нет — только через /ads/c');
        $this->assertMatchesRegularExpression('#href="http://localhost/ads/c/'.$cr->id.'\.'.$cr->campaign_id.'\.sidebar\.\d+\.[0-9a-f]{12}\.[0-9a-f]{24}"#', $html);
    }

    public function test_targeting_schedule_budget_and_off_switch(): void
    {
        $cr = $this->campaign(['countries' => ['UZ']]);
        $this->assertStringNotContainsString('Вклад 17%', $this->get('/blog')->getContent(), 'другая страна');

        $cr->campaign->update(['countries' => ['KZ'], 'starts_at' => now()->addDay()]);
        $this->assertStringNotContainsString('Вклад 17%', $this->get('/blog')->getContent(), 'ещё не началась');

        $cr->campaign->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subHour()]);
        $this->assertStringNotContainsString('Вклад 17%', $this->get('/blog')->getContent(), 'закончилась');

        $cr->campaign->update(['ends_at' => null, 'max_impressions' => 10, 'impressions' => 10]);
        $this->assertStringNotContainsString('Вклад 17%', $this->get('/blog')->getContent(), 'бюджет исчерпан');

        $cr->campaign->update(['max_impressions' => null, 'slots' => ['home-wide']]);
        $this->assertStringNotContainsString('Вклад 17%', $this->get('/blog')->getContent(), 'не это место');

        $cr->campaign->update(['slots' => null]);
        $this->assertStringContainsString('Вклад 17%', $this->get('/blog')->getContent());

        app(Settings::class)->set(['ads.enabled' => false]);
        $this->assertStringNotContainsString('Вклад 17%', $this->get('/blog')->getContent(), 'реклама выключена');
    }

    public function test_one_campaign_once_per_page_and_page_limit(): void
    {
        $this->campaign();
        $server = app(AdServer::class);
        $this->assertNotNull($server->pick('sidebar'));
        $this->assertNull($server->pick('home-wide'), 'та же кампания второй раз на странице не показывается');

        foreach (range(1, 4) as $i) {
            $this->campaign(['name' => "К{$i}"]);
        }
        app()->forgetScopedInstances();
        app(Settings::class)->set(['ads.max_per_page' => 2]);
        $server = app(AdServer::class);
        $this->assertNotNull($server->pick('sidebar'));
        $this->assertNotNull($server->pick('home-wide'));
        $this->assertNull($server->pick('article-inline'), 'не больше двух мест на странице');
    }

    public function test_view_beacon_counts_once_sets_frequency_and_caps(): void
    {
        $cr = $this->campaign(['freq_cap' => 1]);
        $token = $this->token($cr);
        $ua = ['User-Agent' => 'Mozilla/5.0 (Macintosh) Safari/605'];

        $res = $this->withHeaders($ua)->postJson('/ads/e', ['v' => [$token, $token, 'forged.1.sidebar.1.aa.bb']])->assertNoContent();
        $this->postJson('/ads/e', ['v' => [$token]], $ua)->assertNoContent(); // повтор той же метки
        $this->assertSame(1, $cr->fresh()->impressions);
        $this->assertSame(1, $cr->campaign->fresh()->impressions);
        $this->assertSame(1, AdStat::firstWhere('creative_id', $cr->id)->impressions);

        $cookie = $res->getCookie(AdServer::FREQ_COOKIE);
        $this->assertSame(now()->format('Ymd').'|'.$cr->campaign_id.':1', $cookie->getValue());
        $this->assertStringNotContainsString('Вклад 17%', $this->withCookie(AdServer::FREQ_COOKIE, $cookie->getValue())->get('/blog')->getContent(), 'частота: 1 в день');

        // боты и сотрудники не считаются
        $this->postJson('/ads/e', ['v' => [$this->token($cr)]], ['User-Agent' => 'Googlebot/2.1'])->assertNoContent();
        $this->actingAs($this->admin())->postJson('/ads/e', ['v' => [$this->token($cr)]], $ua)->assertNoContent();
        $this->assertSame(1, $cr->fresh()->impressions);

        // старая метка не принимается
        Carbon::setTestNow(now()->subHours(3));
        $old = $this->token($cr);
        Carbon::setTestNow();
        $this->assertNull(app(AdServer::class)->verify($old, 7200));
    }

    public function test_hide_sets_cookie_and_campaign_disappears(): void
    {
        $cr = $this->campaign();
        $res = $this->postJson('/ads/e', ['h' => $this->token($cr)])->assertNoContent();
        $hidden = $res->getCookie(AdServer::HIDE_COOKIE)->getValue();
        $this->assertSame((string) $cr->campaign_id, $hidden);
        $this->assertStringNotContainsString('Вклад 17%', $this->withCookie(AdServer::HIDE_COOKIE, $hidden)->get('/blog')->getContent());
    }

    public function test_click_redirects_and_counts_once(): void
    {
        $cr = $this->campaign();
        $token = $this->token($cr, 'home-wide');
        $ua = ['User-Agent' => 'Mozilla/5.0 Firefox/130'];

        $this->get('/ads/c/'.$token, $ua)->assertRedirect('https://bank.example/deposit?utm_source=banq')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/ads/c/'.$token, $ua)->assertRedirect();
        $this->assertSame(1, $cr->fresh()->clicks);
        $this->assertSame(1, AdStat::where(['creative_id' => $cr->id, 'slot' => 'home-wide'])->value('clicks'));

        $this->get('/ads/c/'.$cr->id.'.'.$cr->campaign_id.'.home-wide.'.time().'.abcdefabcdef.000000000000000000000000')->assertNotFound();
    }

    public function test_banner_and_html_creatives_and_network_fallback(): void
    {
        $this->campaign(['slots' => ['home-wide']], ['type' => 'image', 'image' => '/uploads/ads/b.jpg', 'image_mobile' => '/uploads/ads/m.jpg', 'alt' => 'Баннер', 'title' => null]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<source media="(max-width: 640px)" srcset="/uploads/ads/m.jpg">', $html);
        $this->assertMatchesRegularExpression('#src="/uploads/ads/b\.jpg" alt="Баннер" width="1200" height="200" loading="lazy"#', $html);
        $this->assertStringContainsString('--ar: 1200 / 200; --ar-m: 640 / 320', $html);

        AdCampaign::query()->delete();
        AdServer::flush();
        $this->campaign(['slots' => ['sidebar']], ['type' => 'html', 'html' => '<b onclick="x()">Hi</b>', 'title' => null]);
        $html = $this->get('/blog')->getContent();
        $this->assertStringContainsString('sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox"', $html);
        $this->assertStringContainsString('srcdoc="&lt;b onclick=&quot;x()&quot;&gt;Hi&lt;/b&gt;"', $html);

        AdCampaign::query()->delete();
        AdServer::flush();
        AdSlot::where('key', 'sidebar')->first()->update(['fallback_code' => '<ins class="adsbygoogle" data-ad-client="ca-pub-1"></ins><script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>']);
        $html = $this->get('/blog')->getContent();
        $this->assertStringContainsString('<template data-ad-code><ins class="adsbygoogle"', $html);
    }

    public function test_inline_ad_goes_before_a_heading_after_first_third(): void
    {
        $this->campaign(['slots' => ['article-inline']]);
        $p = fn ($n) => str_repeat('<p>Абзац текста '.$n.' про вклады и ставки.</p>', 1);
        $html = $p(1).$p(2).'<h2>Раздел</h2>'.$p(3).$p(4).'<blockquote>'.$p(5).$p(6).'</blockquote>'.'<h2>Второй</h2>'.$p(7).$p(8).$p(9).$p(10);

        $out = app(AdServer::class)->inject($html, 'article-inline');
        $adPos = strpos($out, 'class="ad ');
        $this->assertNotFalse($adPos);
        $this->assertGreaterThan(strpos($out, '</blockquote>'), $adPos, 'не внутри цитаты и не в первой трети');
        $this->assertMatchesRegularExpression('#</blockquote><div class="ad .*</div>\s*<h2>Второй#s', $out, 'перед подзаголовком «Второй»');

        app()->forgetScopedInstances();
        $short = app(AdServer::class)->inject('<p>Коротко.</p>', 'article-inline');
        $this->assertStringStartsWith('<p>Коротко.</p>', $short);
        $this->assertStringContainsString('class="ad ', $short);
    }

    public function test_admin_pages_upload_and_editor_limits(): void
    {
        $admin = $this->admin();
        $cr = $this->campaign();
        AdStat::create(['date' => today(), 'campaign_id' => $cr->campaign_id, 'creative_id' => $cr->id, 'slot' => 'sidebar', 'impressions' => 40, 'clicks' => 2]);

        $this->actingAs($admin)->get('/admin/ads')->assertOk()->assertSee('Показов за 30 дней')->assertSee('home-wide');
        foreach (['ad-campaigns', 'ad-creatives', 'ad-slots'] as $key) {
            $this->actingAs($admin)->get("/admin/{$key}")->assertOk();
            $this->actingAs($admin)->get("/admin/{$key}/create")->assertOk();
        }
        $this->actingAs($admin)->get('/admin/ad-campaigns/'.$cr->campaign_id.'/edit')->assertOk()->assertSee('Карточка')->assertSee('5,00%');
        $this->actingAs($admin)->get('/admin/ad-creatives/'.$cr->id.'/edit')->assertOk()->assertSee('Вклад 17% в тенге')->assertSee('в показе');
        $this->actingAs($admin)->get('/admin/ad-slots/'.AdSlot::firstWhere('key', 'sidebar')->id.'/edit')->assertOk()->assertSee("@include('ads.slot', ['slot' => 'sidebar'])", false);

        // баннер с загрузкой картинки
        $this->actingAs($admin)->post('/admin/ad-creatives', [
            'campaign_id' => $cr->campaign_id, 'type' => 'image', 'name' => 'Баннер', 'url' => 'https://bank.example', 'weight' => 5, 'is_active' => 1,
            'image__upload' => UploadedFile::fake()->image('banner.png', 1200, 200),
        ])->assertSessionHasNoErrors();
        $banner = AdCreative::firstWhere('name', 'Баннер');
        $this->assertMatchesRegularExpression('#^/uploads/ad-creatives/banner-\w{6}\.png$#', $banner->image);
        $this->assertFileExists(public_path($banner->image));
        @unlink(public_path($banner->image));

        // баннер без картинки — ошибка; конец раньше начала — ошибка
        $this->actingAs($admin)->post('/admin/ad-creatives', ['campaign_id' => $cr->campaign_id, 'type' => 'image', 'name' => 'Пусто', 'url' => 'https://x.example', 'weight' => 5])->assertSessionHasErrors('image');
        $this->actingAs($admin)->post('/admin/ad-campaigns', ['name' => 'X', 'priority' => 5, 'freq_cap' => 3, 'starts_at' => '2026-12-10T00:00', 'ends_at' => '2026-12-01T00:00'])->assertSessionHasErrors('ends_at');

        // редактор: кампании — да, HTML — нет
        $editor = User::factory()->create(['role' => 'editor']);
        $this->actingAs($editor)->get('/admin/ad-campaigns')->assertOk();
        $this->actingAs($editor)->post('/admin/ad-creatives', ['campaign_id' => $cr->campaign_id, 'type' => 'html', 'name' => 'H', 'html' => '<b>x</b>', 'weight' => 5])->assertSessionHasErrors('type');

        // настройки показа
        $this->actingAs($admin)->put('/admin/ads', ['enabled' => 1, 'max_per_page' => 2, 'feed_position' => 4, 'label' => 'Партнёрский материал', 'ads_txt' => 'example.com, 1, DIRECT'])->assertSessionHasNoErrors();
        $this->assertSame(2, settings('ads.max_per_page'));
        $this->assertStringContainsString('Партнёрский материал', $this->get('/blog')->getContent());
    }
}
