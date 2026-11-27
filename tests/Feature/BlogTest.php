<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Card;
use App\Models\CardTier;
use App\Models\Comment;
use App\Models\Country;
use App\Models\PaymentSystem;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\Exchange\DataExchange;
use App\Services\Exchange\ImportOptions;
use App\Support\Blog\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** A37 · Журнал: модель, очистка HTML, каскадные связи, обмен данными, витрина, реакции, комментарии, редактор. */
class BlogTest extends TestCase
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
        foreach (['cards-demo.json', 'blog-demo.json'] as $f) {
            $r = $ex->import($ex->parse(file_get_contents(base_path('tests/fixtures/'.$f)), 'json'), ImportOptions::fromArray([]))->toArray();
            $this->assertSame(0, $r['totals']['error'], $f.': '.json_encode(array_filter($r['rows'], fn ($x) => $x['action'] === 'error'), JSON_UNESCAPED_UNICODE));
        }

        return $r;
    }

    public function test_migration_seeds_categories(): void
    {
        $this->assertSame(['news', 'analytics', 'reviews', 'guides', 'opinion'], PostCategory::query()->ordered()->pluck('slug')->all());
    }

    public function test_sanitizer_keeps_editor_markup_and_drops_dangerous(): void
    {
        $s = app(HtmlSanitizer::class);
        $html = $s->clean('<h1>Заголовок</h1><p onclick="x()">Текст <a href="javascript:alert(1)">плохо</a> <a href="java&#09;script:alert(1)">тоже</a> <a href="https://bank.kz">ok</a></p>'
            .'<script>alert(1)</script><iframe src="//x"></iframe><img src="x" onerror="y()"><img src="data:image/png;base64,AAA">'
            .'<aside data-tone="evil" style="color:red"><p>Врезка</p></aside><div data-embed="card" data-slug="demo-x">мусор</div><div data-embed="hack" data-slug="a"></div>'
            .'<table style="width:1px"><tbody><tr><td colspan="2">1</td></tr></tbody></table><span style="x">в</span><font>текст</font>');

        $this->assertStringContainsString('<h2>Заголовок</h2>', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('iframe', $html);
        $this->assertStringNotContainsString('data:image', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringContainsString('<a href="https://bank.kz" target="_blank" rel="noopener nofollow">ok</a>', $html);
        $this->assertStringContainsString('<a>плохо</a>', $html);
        $this->assertStringContainsString('<aside data-tone="info">', $html);
        $this->assertStringContainsString('<div data-embed="card" data-slug="demo-x"></div>', $html);
        $this->assertStringNotContainsString('hack', $html);
        $this->assertStringContainsString('<td colspan="2">', $html);
        $this->assertStringContainsString('текст', $html);
        $this->assertStringNotContainsString('<font', $html);
    }

    public function test_demo_imports_idempotently_with_cascading_links(): void
    {
        $this->importDemo();
        $this->assertSame(7, Post::count());

        // повторная заливка — без изменений
        $ex = app(DataExchange::class);
        $r = $ex->import($ex->parse(file_get_contents(base_path('tests/fixtures/blog-demo.json')), 'json'), ImportOptions::fromArray([]))->toArray();
        $this->assertSame(7, $r['totals']['unchanged'] ?? 0, json_encode($r['rows'], JSON_UNESCAPED_UNICODE));

        // Обзор карты: банк, пакет? (карта не в пакете), платёжные системы и уровни — каскадом
        $review = Post::where('slug', 'demo-world-elite-travel')->firstOrFail();
        $linked = collect($review->linked())->map(fn ($l) => $l->pluck('slug')->sort()->values()->all())->all();
        $this->assertSame(['demo-ffin-travel'], $linked['card']);
        $this->assertSame([Card::where('slug', 'demo-ffin-travel')->first()->bank->slug], $linked['bank']);
        $this->assertSame(['mastercard', 'unionpay'], $linked['payment_system']);
        $this->assertContains('mastercard-world-elite', $linked['card_tier']);
        $this->assertSame(['card:demo-ffin-travel'], $review->linkKeys(), 'в форме и экспорте — только отмеченные');

        // Пакет → банк и карты пакета; карта пакета видит публикацию
        $pkg = Post::where('slug', 'demo-paket-premium')->firstOrFail();
        $this->assertTrue(Post::query()->about(Card::where('slug', 'demo-bcc-infinite')->first())->whereKey($pkg->id)->exists());
        $this->assertTrue(Post::query()->about(ServicePackage::where('slug', 'demo-bcc-premium')->first())->whereKey($pkg->id)->exists());

        // Обзор карты из пакета → публикация видна и у пакета
        $p = Post::create(['title' => 'Обзор Infinite', 'slug' => 'infinite', 'status' => 'published', 'published_at' => now()]);
        $p->syncLinks(['card:demo-bcc-infinite']);
        $this->assertTrue(Post::query()->about(ServicePackage::where('slug', 'demo-bcc-premium')->first())->whereKey($p->id)->exists());
        $this->assertTrue(Post::query()->about(CardTier::where('slug', 'visa-infinite')->first())->whereKey($p->id)->exists());
        $this->assertTrue(Post::query()->about(PaymentSystem::where('code', 'VISA')->first())->whereKey($p->id)->exists());

        // Embed в теле гайда превращён в связь при правке через админку — а при импорте ссылки из links
        $this->assertSame('guide', Post::where('slug', 'demo-kak-vybrat-vklad')->value('kind'));
    }

    public function test_export_roundtrip_contains_posts(): void
    {
        $this->importDemo();
        $data = app(DataExchange::class)->export(['posts', 'post_categories']);
        $this->assertCount(5, $data['post_categories']);
        $row = collect($data['posts'])->firstWhere('slug', 'demo-world-elite-travel');
        $this->assertSame('reviews', $row['category']);
        $this->assertSame(['card:demo-ffin-travel'], $row['links']);
        $this->assertContains('карты', $row['tags']);
        $this->assertStringContainsString('<h2>', $row['body']);
    }

    public function test_feed_and_post_pages_for_guests(): void
    {
        $this->importDemo();
        Post::create(['title' => 'Будущее', 'slug' => 'future', 'status' => 'published', 'published_at' => now()->addDay()]);

        $this->get('/blog')->assertOk()
            ->assertSee('Как выбрать вклад в 2026 году')
            ->assertSee('Ставки по вкладам в ноябре')
            ->assertDontSee('Черновик: кэшбэк-карты зимы')
            ->assertDontSee('Будущее');

        $this->get('/blog/c/reviews')->assertOk()->assertSee('Обзор: дебетовая Travel')
            ->assertViewHas('posts', fn ($p) => $p->pluck('category.slug')->unique()->values()->all() === ['reviews']);
        $this->get('/blog/tag/'.\App\Models\Tag::where('name', 'премиум')->value('slug'))->assertOk()->assertSee('Премиальный пакет');
        $this->get('/blog?q=Нацбанк')->assertOk()->assertViewHas('posts', fn ($p) => $p->pluck('slug')->all() === ['demo-bazovaya-stavka'])->assertViewHas('featured', null);

        // «Показать ещё» — частичный ответ
        $this->get('/blog?page=1', ['X-Partial' => 'posts'])->assertOk()->assertHeader('X-Has-More', '0')->assertDontSee('<html', false);

        $page = $this->get('/blog/demo-kak-vybrat-vklad')->assertOk();
        $page->assertSee('id="1-nominalnaya-stavka-ili-gesv"', false) // якорь заголовка
            ->assertSee('class="embed"', false)                         // живая карточка карты
            ->assertSee('Кэшбэк Демо')
            ->assertSee('В материале')
            ->assertSee('application/ld+json', false);
        $this->assertSame(1, Post::where('slug', 'demo-kak-vybrat-vklad')->value('views'));
        $this->get('/blog/demo-kak-vybrat-vklad');
        $this->assertSame(1, Post::where('slug', 'demo-kak-vybrat-vklad')->value('views'), 'просмотр — раз в сессию');

        $this->get('/blog/demo-chernovik')->assertNotFound();
        $this->get('/blog/future')->assertNotFound();
        $this->actingAs($this->admin())->get('/blog/demo-chernovik')->assertOk()->assertSee('Видно только сотрудникам');

        $this->get('/blog/rss.xml')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')->assertSee('<rss', false)->assertSee('demo-stavki-noyabr');
    }

    public function test_posts_appear_on_bank_card_and_home_pages(): void
    {
        $this->importDemo();
        $card = Card::where('slug', 'demo-ffin-travel')->firstOrFail();
        $this->get('/cards/'.$card->slug)->assertOk()->assertSee('Обзор: дебетовая Travel');
        $this->get('/banks/'.$card->bank->slug)->assertOk()->assertSee('Обзор: дебетовая Travel');
        $this->get('/payment-systems/mastercard')->assertOk()->assertSee('Обзор: дебетовая Travel');
        $this->get('/packages/demo-bcc-premium')->assertOk()->assertSee('Премиальный пакет: что внутри');
        $this->get('/')->assertOk()->assertSee('Журнал')->assertSee('Нацбанк сохранил базовую ставку');
    }

    public function test_country_scoping(): void
    {
        $uz = Country::create(['code' => 'UZ', 'slug' => 'uz', 'name' => 'Узбекистан', 'currency' => 'UZS', 'is_active' => true, 'sort' => 20]);
        Post::create(['title' => 'Только для Узбекистана', 'slug' => 'uz-only', 'status' => 'published', 'published_at' => now(), 'country_id' => $uz->id]);
        Post::create(['title' => 'Для всех стран', 'slug' => 'all', 'status' => 'published', 'published_at' => now()]);

        $this->get('/blog')->assertSee('Для всех стран')->assertDontSee('Только для Узбекистана');
        $this->get('/blog?country=uz')->assertSee('Только для Узбекистана')->assertSee('Для всех стран');
    }

    public function test_reactions_and_comments(): void
    {
        $post = Post::create(['title' => 'Пост', 'slug' => 'post', 'status' => 'published', 'published_at' => now()]);
        $this->post('/blog/post/react', ['kind' => 'like'])->assertRedirect('/login');

        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/blog/post/react', ['kind' => 'like'])->assertOk()->assertJson(['on' => true, 'counts' => ['like' => 1]]);
        $this->actingAs($u)->postJson('/blog/post/react', ['kind' => 'fire'])->assertJson(['counts' => ['like' => 1, 'fire' => 1]]);
        $this->actingAs($u)->postJson('/blog/post/react', ['kind' => 'like'])->assertJson(['on' => false, 'counts' => ['like' => 0]]);
        $this->actingAs($u)->postJson('/blog/post/react', ['kind' => 'evil'])->assertUnprocessable();

        $this->actingAs($u)->post('/blog/post/comments', ['body' => 'Отличный разбор'])->assertRedirect();
        $c = Comment::firstOrFail();
        $this->assertSame('approved', $c->status);
        $this->actingAs($u)->post('/blog/post/comments', ['body' => 'Ответ', 'parent_id' => $c->id]);
        $reply = Comment::latest('id')->first();
        $this->assertSame($c->id, $reply->parent_id);
        $this->get('/blog/post')->assertSee('Отличный разбор')->assertSee('Ответ');

        // премодерация: чужие не видят, автор видит со статусом
        settings()->set(['blog.premoderation' => true], 'blog');
        $this->actingAs($u)->post('/blog/post/comments', ['body' => 'На проверке текст'])->assertSessionHasNoErrors();
        $this->assertSame('pending', Comment::latest('id')->value('status'));
        auth()->logout();
        $this->get('/blog/post')->assertDontSee('На проверке текст');
        $this->actingAs($u)->get('/blog/post')->assertSee('На проверке текст');

        // закрытые комментарии
        $post->update(['allow_comments' => false]);
        $this->actingAs($u)->post('/blog/post/comments', ['body' => 'Ещё'])->assertForbidden();

        // удалить чужой нельзя, свой можно
        $other = User::factory()->create();
        $this->actingAs($other)->delete('/blog/comments/'.$c->id)->assertForbidden();
        $this->actingAs($u)->delete('/blog/comments/'.$c->id)->assertRedirect();
        $this->assertNull(Comment::find($c->id));
        $this->assertNull(Comment::find($reply->id), 'ответы удаляются вместе с комментарием');
    }

    public function test_admin_editor_creates_and_updates_post(): void
    {
        $this->importDemo();
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/posts')->assertOk()->assertSee('Как выбрать вклад');
        $this->actingAs($admin)->get('/admin/posts/create')->assertOk()->assertSee('data-post-editor', false);
        $this->actingAs($admin)->get('/admin/posts/'.Post::first()->id.'/edit')->assertOk();
        $this->actingAs($admin)->get('/admin/posts?state=draft')->assertOk()->assertSee('Черновик: кэшбэк')->assertDontSee('Обзор: дебетовая Travel');

        $cover = UploadedFile::fake()->image('cover.jpg', 1200, 630);
        $this->actingAs($admin)->post('/admin/posts', [
            'title' => 'Новая карта: Mastercard World Elite от Freedom', 'lead' => 'Коротко о главном',
            'body' => '<h2>Что нового</h2><p onclick="x">Текст <script>alert(1)</script></p><div data-embed="card" data-slug="demo-ffin-travel"></div>',
            'status' => 'draft', 'kind' => 'news', 'category_id' => PostCategory::where('slug', 'news')->value('id'),
            'tags' => 'карты, Freedom,  новинки ', 'links' => ['bank:'.Bank::where('code', 'BCC')->value('slug')],
            'is_featured' => '0', 'allow_comments' => '1', '_action' => 'publish', 'cover_file' => $cover,
        ])->assertRedirect();

        $p = Post::latest('id')->firstOrFail();
        $this->assertSame('novaya-karta-mastercard-world-elite-ot-freedom', $p->slug);
        $this->assertTrue($p->isPublished(), '«Опубликовать» ставит статус и дату');
        $this->assertSame($admin->id, $p->author_id);
        $this->assertStringNotContainsString('script', $p->body);
        $this->assertStringNotContainsString('onclick', $p->body);
        $this->assertSame(['Freedom', 'карты', 'новинки'], $p->tags->pluck('name')->sort()->values()->all());
        $this->assertStringStartsWith('/uploads/blog/covers/', $p->cover);
        $this->assertFileExists(public_path(ltrim($p->cover, '/')));
        // вставленная карточка → связь + каскад
        $keys = $p->linkKeys();
        sort($keys);
        $this->assertSame(['bank:'.Bank::where('code', 'BCC')->value('slug'), 'card:demo-ffin-travel'], $keys);
        $this->assertArrayHasKey('card_tier', $p->linked());

        // правка: в черновики
        $this->actingAs($admin)->put('/admin/posts/'.$p->id, ['title' => $p->title, 'slug' => $p->slug, 'body' => $p->body, 'status' => 'published', 'kind' => 'news', '_action' => 'draft'])->assertRedirect();
        $this->assertSame('draft', $p->fresh()->status);

        // помощники редактора
        $this->actingAs($admin)->getJson('/admin/blog/lookup?q=Travel')->assertOk()->assertJsonFragment(['key' => 'card:demo-ffin-travel']);
        $this->actingAs($admin)->get('/admin/blog/embed?type=card&slug=demo-ffin-travel')->assertOk()->assertSee('Travel Демо');
        $this->actingAs($admin)->postJson('/admin/blog/upload', ['image' => UploadedFile::fake()->image('a.png')])->assertOk()->assertJsonStructure(['url']);
        $this->actingAs($admin)->postJson('/admin/blog/upload', ['image' => UploadedFile::fake()->create('a.php', 1, 'text/x-php')])->assertUnprocessable();

        @unlink(public_path(ltrim($p->cover, '/')));
    }

    public function test_bulk_publish_and_comment_moderation(): void
    {
        $admin = $this->admin();
        $p = Post::create(['title' => 'Черновик', 'slug' => 'd', 'status' => 'draft']);
        $this->actingAs($admin)->post('/admin/posts/bulk', ['action' => 'publish', 'ids' => [$p->id]])->assertRedirect();
        $this->assertTrue($p->fresh()->isPublished());

        $c = $p->comments()->create(['user_id' => $admin->id, 'body' => 'спам', 'status' => 'approved']);
        $this->actingAs($admin)->post('/admin/comments/bulk', ['action' => 'hide', 'ids' => [$c->id]])->assertRedirect();
        $this->assertSame('hidden', $c->fresh()->status);
        $this->actingAs($admin)->get('/admin/comments')->assertOk()->assertSee('спам');
        $this->actingAs($admin)->get('/admin/post-categories')->assertOk()->assertSee('Аналитика');
    }
}
