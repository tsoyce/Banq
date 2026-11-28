<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\Social\SocialStats;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A55 · Страница автора, соцсети со счётчиками, поддержка проекта. */
class AuthorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        Http::preventStrayRequests();
    }

    private function founder(): User
    {
        return User::factory()->create(['name' => 'Артём Цой', 'role' => 'admin', 'bio' => 'Пишу о банках.']);
    }

    public function test_author_page_shows_founder_posts_socials_and_donations(): void
    {
        $me = $this->founder();
        $editor = User::factory()->create(['name' => 'Редактор', 'role' => 'editor']);
        Post::create(['title' => 'Обзор Halyk 2025', 'slug' => 'halyk-2025', 'status' => 'published', 'published_at' => now()->subYears(5), 'author_id' => $me->id, 'views' => 1200]);
        Post::create(['title' => 'Чужая заметка', 'slug' => 'other', 'status' => 'published', 'published_at' => now(), 'author_id' => $editor->id]);
        Post::create(['title' => 'Черновик автора', 'slug' => 'draft', 'status' => 'draft', 'author_id' => $me->id]);

        $this->get('/author')->assertOk()
            ->assertSee('Артём Цой')->assertSee('Основатель TSOY.IN')->assertSee('My Unlimited Story')
            ->assertSee('Обзор Halyk 2025')->assertDontSee('Чужая заметка')->assertDontSee('Черновик автора')
            ->assertSee('пишет с '.now()->subYears(5)->year.' года')
            ->assertSee('https://t.me/tsoyin', false)->assertSee('https://www.youtube.com/channel/UC0l5mO3-_SB6x9jOFkwTQtQ', false)
            ->assertSee('https://tbank.ru/cf/LVYLwfut21', false)->assertSee('id="donate"', false)
            ->assertSee('Пишу о банках.');

        $this->get('/donate')->assertRedirect('/author#donate');
    }

    public function test_byline_of_founder_post_links_to_author_page(): void
    {
        $me = $this->founder();
        $editor = User::factory()->create(['name' => 'Редактор', 'role' => 'editor', 'is_public' => true]);
        $mine = Post::create(['title' => 'Мой пост', 'slug' => 'mine', 'status' => 'published', 'published_at' => now(), 'author_id' => $me->id]);
        $theirs = Post::create(['title' => 'Пост редактора', 'slug' => 'theirs', 'status' => 'published', 'published_at' => now(), 'author_id' => $editor->id]);

        $this->get($mine->url())->assertOk()->assertSee('href="'.route('author').'"', false)->assertSee('Поддержать проект');
        $this->get($theirs->url())->assertOk()->assertSee(route('profile.show', $editor->username), false);
    }

    public function test_counters_come_from_apis_and_fall_back_to_manual_numbers(): void
    {
        $this->founder();
        config(['banq.social.keys.youtube' => 'yt-key', 'banq.social.keys.vk' => null]);
        app(Settings::class)->set(['social.networks' => [
            ['network' => 'youtube', 'url' => 'https://www.youtube.com/channel/UC0l5mO3-_SB6x9jOFkwTQtQ', 'followers' => 1000, 'visible' => true],
            ['network' => 'telegram', 'url' => 'https://t.me/bankchannel', 'followers' => null, 'visible' => true],
            ['network' => 'vk', 'url' => 'https://vk.com/tsoyin', 'followers' => 321, 'visible' => true],
            ['network' => 'x', 'url' => 'https://x.com/tsoyce', 'followers' => 55, 'visible' => false],
        ]]);
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['statistics' => ['subscriberCount' => '1534']]]]),
            't.me/bankchannel' => Http::response('<div class="tgme_page_extra">12 345 subscribers</div>'),
        ]);

        $social = app(SocialStats::class);
        $result = $social->refresh();
        $this->assertSame(1534, $result['youtube']['count']);
        $this->assertSame(12345, $result['telegram']['count']);
        $this->assertNull($result['vk']['count']); // без токена — ручное число

        app(Settings::class)->flush();
        $by = collect($social->profiles())->keyBy('network');
        $this->assertSame(['youtube', 'telegram', 'vk'], $by->keys()->all()); // X скрыт
        $this->assertSame([1534, 'api'], [$by['youtube']['count'], $by['youtube']['source']]);
        $this->assertSame([321, 'manual'], [$by['vk']['count'], $by['vk']['source']]);
        $this->assertSame(1534 + 12345 + 321, $social->total());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'id=UC0l5mO3-_SB6x9jOFkwTQtQ') && str_contains($r->url(), 'key=yt-key'));

        // Ошибка API не стирает прошлое число
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 403), 't.me/*' => Http::response('<div class="tgme_page_extra">@bankchannel</div>')]);
        $result = $social->refresh();
        $this->assertSame('YouTube API: quota', $result['youtube']['error']);
        $this->assertSame(1534, $result['youtube']['count']);
        $this->assertStringContainsString('личный аккаунт', $result['telegram']['error']);
        app(Settings::class)->flush();
        $this->assertSame(1534, collect($social->profiles())->firstWhere('network', 'youtube')['count']);

        $this->get('/author')->assertOk()->assertSee('12,3 тыс.');
    }

    public function test_compact_numbers(): void
    {
        $this->assertSame('999', SocialStats::compact(999));
        $this->assertSame('9 999', SocialStats::compact(9999));
        $this->assertSame('12,3 тыс.', SocialStats::compact(12345));
        $this->assertSame('150 тыс.', SocialStats::compact(150000));
        $this->assertSame('3,4 млн', SocialStats::compact(3_400_000));
        $this->assertSame('@tsoyin', SocialStats::handle('telegram', 'https://t.me/tsoyin'));
        $this->assertSame('@name', SocialStats::handle('youtube', 'https://youtube.com/@name/videos'));
    }

    public function test_admin_edits_author_socials_keys_and_donations(): void
    {
        $admin = $this->founder();
        $this->actingAs($admin)->get('/admin/author')->assertOk()->assertSee('Автор и соцсети')->assertSee('tbank.ru', false);

        $this->actingAs($admin)->put('/admin/author', [
            'headline' => 'Автор и редактор',
            'slogan' => 'My Unlimited Story',
            'about' => "Первый абзац.\n\nВторой абзац.",
            'social_enabled' => 1,
            'networks' => [
                ['network' => 'telegram', 'url' => 'https://t.me/tsoyin', 'followers' => 777, 'visible' => 1],
                ['network' => 'instagram', 'url' => 'https://www.instagram.com/tsoyce', 'followers' => '', 'visible' => 0],
            ],
            'keys' => ['youtube' => 'secret-yt', 'vk' => ''],
            'donate_enabled' => 1,
            'donate_title' => 'Поддержать',
            'donations' => [['label' => 'Kaspi', 'currency' => 'kzt', 'url' => '', 'details' => '+7 700 000 00 00', 'note' => 'по номеру']],
        ])->assertSessionHasNoErrors()->assertRedirect();

        app(Settings::class)->flush();
        $this->assertSame('secret-yt', settings('social.keys')['youtube']);
        $this->assertSame('KZT', settings('donate.items')[0]['currency']);
        $this->assertCount(2, settings('social.networks'));

        // Ключ не уходит в HTML; пустое поле не стирает его
        $this->actingAs($admin)->get('/admin/author')->assertOk()->assertDontSee('secret-yt')->assertSee('задан в админке');
        $this->actingAs($admin)->put('/admin/author', ['networks' => [], 'keys' => ['youtube' => ''], 'donations' => []])->assertSessionHasNoErrors();
        app(Settings::class)->flush();
        $this->assertSame('secret-yt', settings('social.keys')['youtube']);

        // Способ поддержки без ссылки и реквизитов — ошибка
        $this->actingAs($admin)->put('/admin/author', ['donations' => [['label' => 'Пусто']]])->assertSessionHasErrors('donations.0.url');

        $this->get('/author')->assertOk()->assertDontSee('id="donate"', false);
    }

    public function test_author_page_renders_about_and_requisites(): void
    {
        $this->founder();
        app(Settings::class)->set([
            'author.about' => "Первый абзац.\n\nВторой абзац.",
            'donate.items' => [['label' => 'Kaspi', 'currency' => 'KZT', 'url' => null, 'details' => '+7 700 000 00 00', 'note' => null]],
        ]);
        $this->get('/author')->assertOk()->assertSee('<p>Первый абзац.</p>', false)->assertSee('<p>Второй абзац.</p>', false)
            ->assertSee('+7 700 000 00 00')->assertSee('Скопировано');
    }

    public function test_non_staff_cannot_open_admin_page(): void
    {
        $this->founder();
        $u = User::factory()->create(['role' => 'user']);
        $this->actingAs($u)->get('/admin/author')->assertForbidden();
    }
}
