<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Country;
use App\Models\Page;
use App\Models\PaymentSystem;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\User;
use App\Services\WordPress\WpContent;
use App\Services\WordPress\WpImporter;
use App\Services\WordPress\WpMedia;
use App\Services\WordPress\WxrReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A42 · страницы и редиректы; A43 · перенос с WordPress: проверка безопасности, файлы, план, перенос, повтор, WXR, админка. */
class WordPressImportTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://old.example';

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        File::deleteDirectory(storage_path('app/'.WpImporter::DIR));
        File::deleteDirectory(public_path(WpMedia::DIR));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/'.WpImporter::DIR));
        File::deleteDirectory(public_path(WpMedia::DIR));
        parent::tearDown();
    }

    private function png(int $w = 40, int $h = 30): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 120, 80, 255));
        ob_start();
        imagepng($im);

        return ob_get_clean();
    }

    private function jpegWithExif(): string
    {
        $im = imagecreatetruecolor(50, 20);
        ob_start();
        imagejpeg($im);
        $jpg = ob_get_clean();
        // APP1 «Exif» с GPS-подобной меткой сразу после SOI
        $payload = "Exif\0\0".str_repeat('GPSLatitude', 4);
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpg, 0, 2).$app1.substr($jpg, 2);
    }

    private function snapshot(): array
    {
        $s = self::SITE;
        $content = '<p>Обзор карты. <a href="'.$s.'/second-post/">Вторая запись</a>, <a href="'.$s.'/category/banki/halyk/">рубрика</a>, <a href="https://bank.kz">банк</a>.</p>'
            .'<figure class="wp-block-image size-large"><a href="'.$s.'/wp-content/uploads/2025/08/card.png"><img src="'.$s.'/wp-content/uploads/2025/08/card-1024x768.png" data-id="11" class="wp-image-11" srcset="x 1x" width="1024" height="768" alt="Карта"></a><figcaption>Подпись</figcaption></figure>'
            .'<figure class="wp-block-embed is-type-video wp-block-embed-youtube"><div class="wp-block-embed__wrapper"><iframe src="https://www.youtube.com/embed/0D2nFzErFb8?feature=oembed" allowfullscreen></iframe></div></figure>'
            .'<h5>Мелкий заголовок</h5><p>[wpmfpdf id=&#187;13&#8243; embed=&#187;1&#8243;]</p>'
            .'<figure class="wp-block-audio"><audio controls src="'.$s.'/wp-content/uploads/2019/06/song.mp3"></audio></figure>'
            .'<p><img src="'.$s.'/wp-content/uploads/2020/01/evil.jpg"></p>';
        $spam = '<p>Текст</p><div style="display:none"><a href="https://casino-win.xyz/">казино онлайн</a></div><script>eval(atob("YWxlcnQoMSk="))</script><p onclick="x()">клик</p>';

        return [
            'site' => ['name' => 'OLD', 'url' => $s, 'home' => $s],
            'source' => 'test', 'comments_total' => 3,
            'categories' => [
                ['id' => 8, 'name' => 'Банки', 'slug' => '%d0%b1%d0%b0%d0%bd%d0%ba%d0%b8', 'parent' => 0, 'link' => $s.'/category/%d0%b1%d0%b0%d0%bd%d0%ba%d0%b8/', 'count' => 2],
                ['id' => 39, 'name' => 'Halyk', 'slug' => 'halyk', 'parent' => 8, 'link' => $s.'/category/banki/halyk/', 'count' => 1],
                ['id' => 46, 'name' => 'VISA', 'slug' => 'visa', 'parent' => 8, 'link' => $s.'/category/banki/visa/', 'count' => 1],
                ['id' => 9, 'name' => 'Блог', 'slug' => 'blog', 'parent' => 0, 'link' => $s.'/category/blog/', 'count' => 1],
                ['id' => 1, 'name' => 'Без рубрики', 'slug' => '%d0%b1%d0%b5%d0%b7-%d1%80%d1%83%d0%b1%d1%80%d0%b8%d0%ba%d0%b8', 'parent' => 0, 'link' => $s.'/category/x/', 'count' => 1],
            ],
            'tags' => [['id' => 100, 'name' => 'Путешествия', 'slug' => 'travel', 'link' => $s.'/tag/travel/']],
            'users' => [['id' => 1, 'name' => 'Artyom TSOY', 'slug' => 'artyom', 'link' => $s.'/author/artyom/']],
            'media' => [
                ['id' => 11, 'source_url' => $s.'/wp-content/uploads/2025/08/card.png', 'mime_type' => 'image/png', 'alt_text' => 'Обложка'],
                ['id' => 12, 'source_url' => $s.'/wp-content/uploads/2025/08/cover.jpg', 'mime_type' => 'image/jpeg', 'alt_text' => 'Обложка'],
                ['id' => 13, 'source_url' => $s.'/wp-content/uploads/2021/02/terms.pdf', 'mime_type' => 'application/pdf', 'alt_text' => ''],
                ['id' => 14, 'source_url' => $s.'/wp-content/uploads/2020/01/evil.jpg', 'mime_type' => 'image/jpeg', 'alt_text' => ''],
            ],
            'posts' => [
                ['id' => 2506, 'slug' => 'halyk-2025', 'status' => 'publish', 'link' => $s.'/halyk-2025/', 'date_gmt' => '2025-08-05T15:55:20', 'modified_gmt' => '2025-08-06T10:00:00',
                    'title' => ['rendered' => 'Halyk &#8212; 2025'], 'content' => ['rendered' => $content], 'excerpt' => ['rendered' => '<p>Короткий анонс без обрезки.</p>'],
                    'author' => 1, 'featured_media' => 12, 'categories' => [39, 8, 9, 46], 'tags' => [100], 'comment_status' => 'open', 'sticky' => false],
                ['id' => 2099, 'slug' => '%d0%b2%d1%82%d0%be%d1%80%d0%b0%d1%8f', 'status' => 'publish', 'link' => $s.'/second-post/', 'date_gmt' => '2023-03-14T12:55:25',
                    'title' => ['rendered' => 'Вторая запись'], 'content' => ['rendered' => $spam], 'excerpt' => ['rendered' => '<p>Текст [&hellip;]</p>'],
                    'author' => 1, 'featured_media' => 0, 'categories' => [1], 'tags' => [], 'comment_status' => 'closed'],
            ],
            'pages' => [
                ['id' => 3001, 'slug' => 'oferta', 'status' => 'publish', 'link' => $s.'/oferta/', 'date_gmt' => '2021-01-01T00:00:00', 'title' => ['rendered' => 'Оферта'],
                    'content' => ['rendered' => '<p>Условия использования сайта, длинный текст оферты для проверки переноса страницы.</p><script src="https://bot.example/widget.js"></script>'], 'excerpt' => ['rendered' => ''], 'author' => 1, 'featured_media' => 0, 'parent' => 0],
                ['id' => 3002, 'slug' => 'homepage-3', 'status' => 'publish', 'link' => $s.'/homepage-3/', 'title' => ['rendered' => 'Homepage'], 'content' => ['rendered' => str_repeat('<p>демо темы</p>', 30)], 'excerpt' => ['rendered' => ''], 'author' => 1, 'featured_media' => 0],
                ['id' => 3003, 'slug' => 'members', 'status' => 'publish', 'link' => $s.'/members/', 'title' => ['rendered' => 'Member Directory'], 'content' => ['rendered' => '<p>[userpro template=memberlist]</p>'], 'excerpt' => ['rendered' => ''], 'author' => 1, 'featured_media' => 0],
            ],
        ];
    }

    private function fakeHttp(): void
    {
        $s = self::SITE;
        Http::fake([
            $s.'/wp-content/uploads/2025/08/card.png' => Http::response($this->png(3000, 1500)),
            $s.'/wp-content/uploads/2025/08/cover.jpg' => Http::response($this->jpegWithExif()),
            $s.'/wp-content/uploads/2021/02/terms.pdf' => Http::response("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
            $s.'/wp-content/uploads/2019/06/song.mp3' => Http::response('ID3'.str_repeat("\0", 64)),
            $s.'/wp-content/uploads/2020/01/evil.jpg' => Http::response("<?php system(\$_GET['c']); ?>"),
            '*' => Http::response('', 404),
        ]);
    }

    private function seedCatalog(): void
    {
        $kz = Country::query()->where('code', 'KZ')->first() ?? Country::query()->create(['code' => 'KZ', 'name' => 'Казахстан', 'currency' => 'KZT']);
        Bank::query()->create(['country_id' => $kz->id, 'code' => 'HSBK', 'slug' => 'halyk', 'name' => 'Halyk Bank', 'short_name' => 'Halyk', 'status' => 'active']);
        PaymentSystem::query()->firstOrCreate(['slug' => 'visa'], ['name' => 'Visa', 'is_active' => true]);
        User::factory()->create(['role' => 'admin', 'name' => 'Artyom TSOY']);
    }

    private function runImport(array $options = []): WpImporter
    {
        $imp = app(WpImporter::class);
        $imp->scan(snapshot: $this->snapshot(), options: $options);
        for ($i = 0; $i < 20 && ! $imp->step(60)['finished']; $i++);

        return $imp;
    }

    // ── Проверка и файлы ──────────────────────────────────

    public function test_scan_flags_hacked_markup(): void
    {
        $f = collect((new WpContent(['old.example']))->scan($this->snapshot()['posts'][1]['content']['rendered']));

        $this->assertTrue($f->contains(fn ($x) => $x['kind'] === 'hidden-links' && $x['level'] === 'danger'));
        $this->assertTrue($f->contains(fn ($x) => $x['kind'] === 'spam-link'));
        $this->assertTrue($f->contains(fn ($x) => $x['kind'] === 'script' && $x['level'] === 'danger'));
        $this->assertTrue($f->contains(fn ($x) => $x['kind'] === 'on-attr'));
        $this->assertTrue($f->contains(fn ($x) => $x['kind'] === 'odd-domain'));

        $clean = collect((new WpContent(['old.example']))->scan('<p>MCC 7534 — Шиномонтаж и вулканизация</p><iframe src="https://www.youtube.com/embed/abcdefgh"></iframe>'));
        $this->assertSame(['info'], $clean->pluck('level')->unique()->values()->all());
    }

    public function test_media_rejects_disguised_code_and_strips_exif(): void
    {
        $m = app(WpMedia::class);
        $bad = $m->store('<?php echo 1; ?>', 'https://x/wp-content/uploads/2020/01/a.jpg');
        $this->assertFalse($bad['ok']);
        $this->assertTrue($bad['danger']);

        $poly = $m->store($this->png().'<?php system($_GET[1]); ?>', 'https://x/wp-content/uploads/2020/01/b.png');
        $this->assertFalse($poly['ok']);
        $this->assertTrue($poly['danger']);

        $mismatch = $m->store('%PDF-1.4 x', 'https://x/wp-content/uploads/2020/01/c.jpg');
        $this->assertFalse($mismatch['ok']);

        $this->assertFalse($m->store('hello', 'https://x/log.txt')['ok']);

        $ok = $m->store($this->jpegWithExif(), 'https://x/wp-content/uploads/2025/08/Фото Кот.jpg');
        $this->assertTrue($ok['ok']);
        $this->assertMatchesRegularExpression('#^/uploads/blog/wp/2025/08/foto-kot-[0-9a-f]{6}\.jpg$#', $ok['path']);
        $bytes = file_get_contents(public_path(ltrim($ok['path'], '/')));
        $this->assertStringNotContainsString('GPSLatitude', $bytes);
        $this->assertStringNotContainsString('Exif', $bytes);

        $big = $m->store($this->png(3000, 1000), 'https://x/wp-content/uploads/2025/08/wide.png');
        [$w] = getimagesize(public_path(ltrim($big['path'], '/')));
        $this->assertSame(WpMedia::MAX_SIDE, $w);
    }

    // ── План (пробный прогон) ─────────────────────────────

    public function test_dry_run_plans_without_writing(): void
    {
        $this->seedCatalog();
        Http::fake();
        $state = app(WpImporter::class)->scan(snapshot: $this->snapshot());
        $items = collect($state['plan']['items'])->keyBy('key');

        Http::assertNothingSent();
        $this->assertSame(0, Post::withoutGlobalScopes()->count());
        $this->assertSame(0, Redirect::count());

        $this->assertSame('/blog/halyk-2025', $items['post:2506']['new_url']);
        $this->assertSame('/blog/vtoraya', $items['post:2099']['new_url']);        // кириллический slug → латиница
        $this->assertSame('/oferta', $items['page:3001']['new_url']);
        $this->assertNotNull($items['page:3002']['skip']);                         // клон демо-главной
        $this->assertNotNull($items['page:3003']['skip']);                         // страница плагина из шорткода
        $this->assertSame(['bank:halyk', 'payment_system:visa'], $items['post:2506']['links']);
        $this->assertSame(8, $items['post:2506']['category']);                     // «Банки» — верхняя рубрика большинства рубрик
        $this->assertContains('Halyk', $items['post:2506']['tags']);
        $this->assertSame(['posts' => 2, 'pages' => 1, 'skipped' => 2], array_intersect_key($state['plan']['counts'], array_flip(['posts', 'pages', 'skipped'])));
        $this->assertGreaterThan(0, $state['plan']['counts']['danger']);
    }

    // ── Перенос ────────────────────────────────────────────

    public function test_import_creates_posts_pages_media_and_redirects(): void
    {
        $this->seedCatalog();
        $this->fakeHttp();
        $imp = $this->runImport();
        $report = $imp->report();

        $post = Post::query()->where('slug', 'halyk-2025')->firstOrFail();
        $this->assertSame('Halyk — 2025', $post->title);
        $this->assertSame('Короткий анонс без обрезки.', $post->lead);
        $this->assertSame('2025-08-05 15:55:20', $post->published_at->clone()->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('banki', $post->category->slug);
        $this->assertSame('Artyom TSOY', $post->author->name);
        $this->assertStringStartsWith('/uploads/blog/wp/2025/08/cover-', $post->cover);
        $this->assertEqualsCanonicalizing(['halyk'], $post->banks->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(['visa'], $post->paymentSystems->pluck('slug')->all());
        $this->assertSame('post:2506', $post->meta['wp_key']);

        $body = $post->body;
        $this->assertStringContainsString('href="/blog/vtoraya"', $body);                    // ссылка на свою запись
        $this->assertStringContainsString('href="/blog/tag/halyk"', $body);                  // вложенная рубрика → тег
        $this->assertMatchesRegularExpression('#<img src="/uploads/blog/wp/2025/08/card-[0-9a-f]{6}\.png"#', $body); // оригинал, не 1024x768
        $this->assertStringNotContainsString('old.example', $body);
        $this->assertStringContainsString('data-embed="youtube" data-slug="0D2nFzErFb8"', $body);
        $this->assertStringContainsString('<h4>Мелкий заголовок</h4>', $body);
        $this->assertMatchesRegularExpression('#href="/uploads/blog/wp/2021/02/terms-[0-9a-f]{6}\.pdf"#', $body);
        $this->assertMatchesRegularExpression('#href="/uploads/blog/wp/2019/06/song-[0-9a-f]{6}\.mp3"#', $body);
        $this->assertStringNotContainsString('evil', $body);                                 // поддельная картинка убрана
        $this->assertStringNotContainsString('srcset', $body);

        $spam = Post::query()->where('slug', 'vtoraya')->firstOrFail();
        $this->assertNull($spam->lead);                                                       // автоанонс «[…]» — не лид
        $this->assertNull($spam->category_id);
        $this->assertFalse($spam->allow_comments);
        $this->assertStringNotContainsString('script', $spam->body);
        $this->assertStringNotContainsString('onclick', $spam->body);

        $page = Page::query()->where('slug', 'oferta')->firstOrFail();
        $this->assertFalse($page->in_feed);
        $this->assertStringNotContainsString('script', $page->body);
        $this->assertFalse(Page::query()->where('slug', 'homepage-3')->exists());

        $this->assertSame(3, $report['counts']['created']);                                    // 2 поста + 1 страница
        $this->assertTrue(collect($report['media_rejected'])->contains(fn ($m) => str_contains($m['url'], 'evil.jpg') && $m['danger']));
        $this->assertGreaterThan(5, $report['counts']['redirects']);

        // Витрина: страница по короткому адресу, не в ленте; старые адреса → 301
        $this->get('/oferta')->assertOk()->assertSee('Условия использования сайта');
        $this->get('/blog')->assertOk()->assertSee('Halyk — 2025')->assertDontSee('Оферта');
        $this->get('/halyk-2025/')->assertRedirect(url('/blog/halyk-2025'))->assertStatus(301);
        $this->get('/second-post')->assertRedirect(url('/blog/vtoraya'));
        $this->get('/?p=2506')->assertRedirect(url('/blog/halyk-2025'));
        $this->get('/category/%d0%b1%d0%b0%d0%bd%d0%ba%d0%b8/')->assertRedirect(url('/blog/c/banki'));
        $this->get('/feed/')->assertRedirect(url('/blog/rss.xml'));
        $this->get('/wp-content/uploads/2025/08/card-1024x768.png')->assertRedirect();
        $this->get('/nothing-here')->assertNotFound();
        $this->assertSame(1, Redirect::query()->where('from_path', '/halyk-2025')->value('hits'));
    }

    /** A44 · оригинал переименован в «-scaled-1» / лежит в другой папке; Jetpack i2.wp.com; повторный прогон не качает файлы заново. */
    public function test_renamed_originals_and_photon_links_are_found(): void
    {
        $this->seedCatalog();
        $s = self::SITE;
        $snap = $this->snapshot();
        $snap['posts'] = [$snap['posts'][0]];
        $snap['posts'][0]['content']['rendered'] = '<p><img src="'.$s.'/wp-content/uploads/2018/09/WP_2018_Pro_LI-1024x768.jpg"></p>'
            .'<p><img src="'.$s.'/wp-content/uploads/2018/09/rot_LI-e1536720574839-1024x566.jpg"></p>'
            .'<p><img src="https://i2.wp.com/old.example/wp-content/uploads/2020/02/image-1.png?fit=1024%2C480"></p>'
            .'<p><img src="'.$s.'/wp-content/uploads/2020/02/image-1024x480.png"></p>';
        $snap['posts'][0]['featured_media'] = 0;
        $snap['media'] = [
            ['id' => 21, 'source_url' => $s.'/wp-content/uploads/2018/09/WP_2018_Pro_LI-scaled-1.jpg', 'mime_type' => 'image/jpeg'],
            ['id' => 22, 'source_url' => $s.'/wp-content/uploads/2019/04/rot_LI-scaled-1.jpg', 'mime_type' => 'image/jpeg'],
            ['id' => 23, 'source_url' => $s.'/wp-content/uploads/2020/02/image-1.png', 'mime_type' => 'image/png'],
            ['id' => 24, 'source_url' => $s.'/wp-content/uploads/2021/05/image.png', 'mime_type' => 'image/png'],
            ['id' => 25, 'source_url' => $s.'/wp-content/uploads/2022/01/image.png', 'mime_type' => 'image/png'],
        ];
        Http::fake([
            $s.'/wp-content/uploads/2018/09/WP_2018_Pro_LI-scaled-1.jpg' => Http::response($this->jpegWithExif()),
            $s.'/wp-content/uploads/2019/04/rot_LI-scaled-1.jpg' => Http::response($this->jpegWithExif()),
            $s.'/wp-content/uploads/2020/02/image-1.png' => Http::response($this->png(40, 20)),
            '*' => Http::response('', 404),
        ]);
        $this->assertSame('wp_2018_pro_li', WpImporter::uploadStem('/x/WP_2018_Pro_LI-scaled-1.jpg'));
        $this->assertSame('rot_li', WpImporter::uploadStem('/x/rot_LI-e1536720574839-1024x566.jpg'));

        $imp = app(WpImporter::class);
        $imp->scan(snapshot: $snap, options: []);
        for ($i = 0; $i < 10 && ! $imp->step(60)['finished']; $i++);

        $body = Post::query()->where('slug', 'halyk-2025')->value('body');
        $this->assertMatchesRegularExpression('#src="/uploads/blog/wp/2018/09/wp-2018-pro-li-scaled-1-[0-9a-f]{6}\.jpg"#i', $body);
        $this->assertMatchesRegularExpression('#src="/uploads/blog/wp/2019/04/rot-li-scaled-1-[0-9a-f]{6}\.jpg"#i', $body);
        $this->assertSame(1, preg_match_all('#src="/uploads/blog/wp/2020/02/image-1-[0-9a-f]{6}\.png"#', $body));
        $this->assertStringNotContainsString('wp.com', $body);
        $rejected = collect($imp->report()['media_rejected'])->pluck('url');
        $this->assertTrue($rejected->contains(fn ($u) => str_contains($u, 'image-1024x480')));  // «image» в папках 2021 и 2022 — не угадываем

        // Повторный прогон с обновлением: уже перенесённые файлы не скачиваются снова
        Http::fake(['*' => Http::response('', 500)]);
        $imp->scan(snapshot: $snap, options: ['update' => true]);
        for ($i = 0; $i < 10 && ! $imp->step(60)['finished']; $i++);
        $this->assertSame($body, Post::query()->where('slug', 'halyk-2025')->value('body'));
    }

    public function test_rerun_keeps_edited_posts_and_update_option_overwrites(): void
    {
        $this->seedCatalog();
        $this->fakeHttp();
        $this->runImport();
        Post::query()->where('slug', 'halyk-2025')->update(['title' => 'Правлено руками']);

        $r = $this->runImport()->report();
        $this->assertSame(0, $r['counts']['created']);
        $this->assertSame(3, $r['counts']['kept']);
        $this->assertSame('Правлено руками', Post::query()->where('slug', 'halyk-2025')->value('title'));
        $this->assertSame(2, Post::query()->where('type', 'post')->count());

        $this->runImport(['update' => true]);
        $this->assertSame('Halyk — 2025', Post::query()->where('slug', 'halyk-2025')->value('title'));
        $this->assertSame(3, Post::withoutGlobalScopes()->count());
    }

    public function test_steps_pause_and_resume(): void
    {
        $this->seedCatalog();
        $this->fakeHttp();
        $imp = app(WpImporter::class);
        $imp->scan(snapshot: $this->snapshot());
        $first = $imp->step(0);                       // бюджет 0 — шаг сразу уступает
        $this->assertFalse($first['finished']);
        for ($i = 0; $i < 50 && ! ($p = $imp->step(0))['finished']; $i++);
        $this->assertTrue($p['finished']);
        $this->assertSame(3, Post::withoutGlobalScopes()->count());
    }

    public function test_wxr_export_is_read(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">
<channel><title>OLD</title><link>https://old.example</link><wp:base_blog_url>https://old.example</wp:base_blog_url>
<wp:author><wp:author_id>1</wp:author_id><wp:author_login>artyom</wp:author_login><wp:author_display_name>Artyom TSOY</wp:author_display_name></wp:author>
<wp:category><wp:term_id>8</wp:term_id><wp:category_nicename>banki</wp:category_nicename><wp:category_parent></wp:category_parent><wp:cat_name>Банки</wp:cat_name></wp:category>
<wp:category><wp:term_id>39</wp:term_id><wp:category_nicename>halyk</wp:category_nicename><wp:category_parent>banki</wp:category_parent><wp:cat_name>Halyk</wp:cat_name></wp:category>
<item><title>Пост из WXR</title><link>https://old.example/wxr-post/</link><dc:creator>artyom</dc:creator>
<content:encoded><![CDATA[Первый абзац.

https://www.youtube.com/watch?v=0D2nFzErFb8

Второй абзац.]]></content:encoded><excerpt:encoded><![CDATA[]]></excerpt:encoded>
<wp:post_id>5</wp:post_id><wp:post_date>2020-05-01 10:00:00</wp:post_date><wp:post_date_gmt>2020-05-01 05:00:00</wp:post_date_gmt><wp:comment_status>open</wp:comment_status>
<wp:post_name>wxr-post</wp:post_name><wp:status>publish</wp:status><wp:post_parent>0</wp:post_parent><wp:post_type>post</wp:post_type><wp:is_sticky>0</wp:is_sticky>
<category domain="category" nicename="halyk"><![CDATA[Halyk]]></category></item>
</channel></rss>
XML;
        $snap = app(WxrReader::class)->read($xml);
        $this->assertSame('https://old.example', $snap['site']['url']);
        $this->assertSame(8, collect($snap['categories'])->firstWhere('slug', 'halyk')['parent']);
        $this->assertSame([39], $snap['posts'][0]['categories']);
        $this->assertStringContainsString('<p>Первый абзац.</p>', $snap['posts'][0]['content']['rendered']);

        $this->seedCatalog();
        Http::fake();
        $imp = app(WpImporter::class);
        $imp->scan(snapshot: $snap);
        $imp->step(60);
        $post = Post::query()->where('slug', 'wxr-post')->firstOrFail();
        $this->assertStringContainsString('data-embed="youtube" data-slug="0D2nFzErFb8"', $post->body);
        $this->assertSame(['halyk'], $post->banks->pluck('slug')->all());

        $this->expectException(\RuntimeException::class);
        app(WxrReader::class)->read('<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><rss/>');
    }

    // ── Страницы и редиректы ──────────────────────────────

    public function test_pages_admin_and_navigation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.resource.store', 'pages'), [
            'title' => 'Наша миссия', 'body' => '<p>Про нас</p>', 'status' => 'published', '_action' => 'publish', 'nav' => 'footer', 'nav_sort' => 1, 'in_feed' => 0,
        ])->assertRedirect();
        $page = Page::query()->firstOrFail();
        $this->assertSame('nasha-missiya', $page->slug);
        $this->assertSame('page', $page->type);

        $this->actingAs($admin)->get(route('admin.resource.index', 'pages'))->assertOk()->assertSee('Наша миссия');
        $this->actingAs($admin)->get(route('admin.resource.edit', ['pages', $page->id]))->assertOk()->assertSee('Ссылка в меню сайта');
        $this->actingAs($admin)->get(route('admin.resource.index', 'posts'))->assertOk()->assertDontSee('Наша миссия');
        auth()->logout();

        $this->get('/nasha-missiya')->assertOk()->assertSee('Про нас');
        $this->get('/')->assertOk()->assertSee('href="'.url('/nasha-missiya').'"', false);  // ссылка в подвале
        $this->get('/blog/nasha-missiya')->assertNotFound();
        $page->update(['nav' => null]);
        Cache::forget('pages.nav');
        $this->get('/blog')->assertOk()->assertDontSee('Наша миссия');

        $page->update(['in_feed' => true]);
        $this->get('/blog')->assertOk()->assertSee('Наша миссия');

        // адрес маршрута сайта страницей не занять
        $this->actingAs($admin)->post(route('admin.resource.store', 'pages'), ['title' => 'x', 'slug' => 'blog', 'status' => 'draft'])->assertSessionHasErrors('slug');
        // черновик — только сотрудникам
        $draft = Page::query()->create(['title' => 'Черновик', 'slug' => 'draft-page', 'status' => 'draft', 'body' => '<p>x</p>']);
        auth()->logout();
        $this->get('/draft-page')->assertNotFound();
    }

    public function test_manual_redirects(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.resource.store', 'redirects'), ['from_path' => 'https://tsoy.in/Старый-Адрес/', 'to_url' => '/blog', 'status' => 301])->assertRedirect();
        $this->assertSame('/старый-адрес', Redirect::query()->value('from_path'));
        $this->actingAs($admin)->post(route('admin.resource.store', 'redirects'), ['from_path' => '/старый-адрес', 'to_url' => '/x', 'status' => 301])->assertSessionHasErrors('from_path');
        auth()->logout();
        $this->get('/%D0%A1%D1%82%D0%B0%D1%80%D1%8B%D0%B9-%D0%B0%D0%B4%D1%80%D0%B5%D1%81')->assertRedirect(url('/blog'));
        // 404 на существующем маршруте — тоже через редиректы
        Redirect::remember('/blog/old-slug', '/blog');
        $this->get('/blog/old-slug')->assertRedirect(url('/blog'));
        // редирект на самого себя не зацикливается
        Redirect::remember('/loop', '/loop');
        $this->get('/loop')->assertNotFound();
    }

    public function test_admin_wordpress_page_flow(): void
    {
        $this->seedCatalog();
        $admin = User::query()->where('role', 'admin')->first();
        $this->actingAs($admin)->get(route('admin.wordpress.index'))->assertOk()->assertSee('Пробный прогон');

        $file = UploadedFile::fake()->createWithContent('snap.json', json_encode($this->snapshot()));
        $this->actingAs($admin)->post(route('admin.wordpress.scan'), ['file' => $file])->assertRedirect(route('admin.wordpress.index'));
        $this->actingAs($admin)->get(route('admin.wordpress.index'))->assertOk()->assertSee('Halyk — 2025')->assertSee('Опасно');

        $this->fakeHttp();
        for ($i = 0; $i < 10; $i++) {
            $j = $this->actingAs($admin)->postJson(route('admin.wordpress.step'))->assertOk()->json();
            if ($j['finished']) {
                break;
            }
        }
        $this->assertTrue($j['finished']);
        $html = $this->actingAs($admin)->get(route('admin.wordpress.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Перенос завершён', $html);
        $this->actingAs($admin)->get(route('admin.wordpress.report'))->assertOk()->assertJsonPath('counts.created', 3);

        $editor = User::factory()->create(['role' => 'editor']);
        $this->actingAs($editor)->get(route('admin.wordpress.index'))->assertForbidden();
    }
}
