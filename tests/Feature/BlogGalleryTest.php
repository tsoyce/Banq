<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\WordPress\WpImporter;
use App\Services\WordPress\WpMedia;
use App\Support\Blog\Gallery;
use App\Support\Blog\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A52 · галереи (единый формат, старые галереи WordPress), лайтбокс на витрине, пропавшие картинки с запасной копии сайта. */
class BlogGalleryTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://new.example';

    private const MIRROR = 'https://old.example';

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

    private function clean(string $html): string
    {
        return app(HtmlSanitizer::class)->clean($html);
    }

    public function test_old_wordpress_galleries_become_one_format(): void
    {
        // WordPress 5.0–5.8: figure > ul > li > figure (раньше шло столбиком с маркерами списка)
        $old = '<figure class="wp-block-gallery columns-3"><ul class="blocks-gallery-grid">'
            .'<li class="blocks-gallery-item"><figure><img src="/a.jpg" alt="A"></figure></li>'
            .'<li class="blocks-gallery-item"><figure><img src="/b.jpg" alt="B"><figcaption>Бэ</figcaption></figure></li>'
            .'</ul><figcaption class="blocks-gallery-caption">Пингвины</figcaption></figure>';
        $html = $this->clean($old);
        $this->assertStringContainsString('<figure data-gallery="grid"><figure><img src="/a.jpg" alt="A" loading="lazy"></figure><figure><img src="/b.jpg" alt="B" loading="lazy"><figcaption>Бэ</figcaption></figure><figcaption>Пингвины</figcaption></figure>', $html);
        $this->assertStringNotContainsString('<ul>', $html);

        // WordPress 5.9+: figure > figure
        $new = '<figure class="wp-block-gallery"><figure class="wp-block-image"><img src="/a.jpg"></figure><figure class="wp-block-image"><a href="/a-big.jpg"><img src="/c.jpg"></a></figure></figure>';
        $this->assertStringContainsString('<figure data-gallery="grid"><figure><img src="/a.jpg"', $this->clean($new));
        $this->assertStringContainsString('<a href="/a-big.jpg"><img src="/c.jpg"', $this->clean($new));

        // повторная чистка ничего не меняет
        $this->assertSame($html, $this->clean($html));
    }

    public function test_layouts_are_whitelisted_and_plain_figures_untouched(): void
    {
        $g = fn ($l) => '<figure data-gallery="'.$l.'"><figure><img src="/a.jpg"></figure><figure><img src="/b.jpg"></figure></figure>';
        foreach (Gallery::LAYOUTS as $layout) {
            $this->assertStringContainsString('data-gallery="'.$layout.'"', $this->clean($g($layout)));
        }
        $this->assertStringContainsString('data-gallery="grid"', $this->clean($g('"><script>alert(1)</script>')));
        $this->assertStringContainsString('data-gallery="grid"', $this->clean($g('evil')));

        // одиночная картинка с подписью — не галерея
        $single = '<figure><img src="/a.jpg" alt=""><figcaption>Подпись</figcaption></figure>';
        $this->assertStringNotContainsString('data-gallery', $this->clean($single));
        // в figure кроме картинок есть текст — не трогаем
        $mixed = '<figure><figure><img src="/a.jpg"></figure><figure><img src="/b.jpg"></figure><p>текст</p></figure>';
        $this->assertStringNotContainsString('data-gallery', $this->clean($mixed));
        // список без картинок не превращается в галерею
        $this->assertStringContainsString('<ul><li>пункт</li></ul>', $this->clean('<figure><ul><li>пункт</li></ul></figure>'));
    }

    public function test_article_renders_galleries_with_lightbox(): void
    {
        $body = '<p>Текст</p>'
            .'<figure><ul><li><figure><img src="/a.jpg" alt=""></figure></li><li><figure><img src="/b.jpg" alt=""></figure></li><li><figure><img src="/c.jpg" alt=""></figure></li></ul></figure>'
            .'<figure data-gallery="slider"><figure><img src="/d.jpg" alt=""></figure><figure><img src="/e.jpg" alt=""></figure><figcaption>Слайды</figcaption></figure>';
        // старое тело из базы, как его сохранил перенос до A52
        $post = Post::create(['title' => 'Галерея', 'slug' => 'gallery', 'status' => 'published', 'published_at' => now()]);
        Post::query()->whereKey($post->id)->update(['body' => $body]);

        $html = $this->get('/blog/gallery')->assertOk()->getContent();
        $this->assertStringContainsString('id="post-body" data-lightbox', $html);
        $this->assertStringContainsString('<figure data-gallery="grid" data-count="3">', $html);
        $this->assertStringContainsString('<figure data-gallery="slider" data-count="2"><div class="gallery-track"><figure><img src="/d.jpg"', $html);
        $this->assertStringContainsString('</div><figcaption>Слайды</figcaption></figure>', $html);
        $this->assertStringNotContainsString('<li><figure>', $html);
    }

    // ── Пропавшие картинки с запасной копии ───────────────

    private function jpegWithExif(): string
    {
        $im = imagecreatetruecolor(60, 40);
        ob_start();
        imagejpeg($im);
        $jpg = ob_get_clean();
        $payload = "Exif\0\0".str_repeat('GPSLatitude', 4);

        return substr($jpg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpg, 2);
    }

    private function snapshot(): array
    {
        $s = self::SITE;
        $post = fn ($id, $slug, $html) => ['id' => $id, 'slug' => $slug, 'status' => 'publish', 'link' => "$s/$slug/", 'date_gmt' => '2022-02-01T10:00:00',
            'title' => ['rendered' => ucfirst($slug)], 'content' => ['rendered' => $html], 'excerpt' => ['rendered' => ''], 'author' => 1,
            'featured_media' => 0, 'categories' => [], 'tags' => [], 'comment_status' => 'open'];

        return [
            'site' => ['name' => 'NEW', 'url' => $s, 'home' => $s], 'source' => 'test',
            'categories' => [], 'tags' => [], 'users' => [], 'media' => [],
            'posts' => [
                $post(1, 'dakto', '<p>Документы</p><figure><img src="'.$s.'/wp-content/uploads/2022/02/med-zakluchenie.jpg" alt="Заключение"><figcaption>Мед</figcaption></figure>'
                    .'<p><img src="'.$s.'/wp-content/uploads/2022/02/evil.jpg"></p><p><img src="'.$s.'/wp-content/uploads/2022/02/gone.jpg"></p>'),
                $post(2, 'fine', '<p>Без картинок, абзац текста.</p>'),
            ],
            'pages' => [],
        ];
    }

    public function test_missing_files_are_taken_from_mirror_with_the_same_checks(): void
    {
        User::factory()->create(['role' => 'admin']);
        // основной сайт файлов не отдаёт; копия — после переноса (стабы Http::fake не перекрываются, поэтому одна функция)
        $m = self::MIRROR;
        $mirrorOn = false;
        $jpeg = $this->jpegWithExif();
        Http::fake(function ($request) use (&$mirrorOn, $m, $jpeg) {
            return match (true) {
                $mirrorOn && $request->url() === $m.'/wp-content/uploads/2022/02/med-zakluchenie.jpg' => Http::response($jpeg),
                $mirrorOn && $request->url() === $m.'/wp-content/uploads/2022/02/evil.jpg' => Http::response("<?php system(\$_GET['c']); ?>"),
                default => Http::response('', 404),
            };
        });
        $imp = app(WpImporter::class);
        $imp->scan(snapshot: $this->snapshot(), options: []);
        for ($i = 0; $i < 10 && ! $imp->step(60)['finished']; $i++);

        $dakto = Post::query()->where('slug', 'dakto')->firstOrFail();
        $this->assertStringNotContainsString('<img', $dakto->body);
        $fine = Post::query()->where('slug', 'fine')->firstOrFail();
        $fine->update(['title' => 'Правка в журнале']);

        $mirrorOn = true;
        $admin = User::query()->where('role', 'admin')->first();
        for ($i = 0; $i < 10; $i++) {
            $j = $this->actingAs($admin)->postJson(route('admin.wordpress.fill'), ['mirror' => $m])->assertOk()->json();
            if ($j['finished']) {
                break;
            }
        }
        $this->assertSame(1, $j['total']);
        $this->assertSame(1, $j['fixed']);
        $this->assertCount(2, $j['still_missing']); // PHP под видом JPEG и файл, которого нет и на копии

        $body = $dakto->fresh()->body;
        $this->assertMatchesRegularExpression('#<figure><img src="(/uploads/blog/wp/2022/02/med-zakluchenie-[0-9a-f]{6}\.jpg)" alt="Заключение" loading="lazy"><figcaption>Мед</figcaption></figure>#', $body);
        preg_match('#src="([^"]+)"#', $body, $src);
        $bytes = file_get_contents(public_path(ltrim($src[1], '/')));
        $this->assertStringNotContainsString('GPSLatitude', $bytes); // EXIF снят
        $this->assertStringNotContainsString('evil', $body);
        Http::assertSent(fn ($r) => $r->url() === $m.'/wp-content/uploads/2022/02/evil.jpg');

        // записи без пропавших картинок не переносятся заново — правка в журнале на месте
        $this->assertSame('Правка в журнале', $fine->fresh()->title);
        // опасный файл отмечен в отчёте
        $this->assertTrue(collect($imp->report()['media_rejected'])->contains(fn ($r) => str_contains($r['url'], 'evil.jpg') && $r['danger']));

        $this->actingAs($admin)->postJson(route('admin.wordpress.fill'), ['mirror' => 'javascript:alert(1)'])->assertStatus(422);
        $editor = User::factory()->create(['role' => 'editor']);
        $this->actingAs($editor)->postJson(route('admin.wordpress.fill'), ['mirror' => $m])->assertForbidden();
    }
}
