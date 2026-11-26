<?php

namespace Tests\Feature;

use App\Enums\RatingKind;
use App\Models\Bank;
use App\Models\Rating;
use App\Models\Review;
use App\Models\User;
use App\Services\Import\DepositImporter;
use App\Services\Ratings\RatingBoard;
use Database\Seeders\BankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A18 · Банки, три рейтинга, отзывы и модерация. */
class BankRatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        (new BankSeeder)->run();
        app(DepositImporter::class)->import(base_path('tests/fixtures/deposits-matrix.xlsx'), 'deposits.xlsx', sheets: ['Лист2']);
    }

    private function bank(string $code): Bank
    {
        return Bank::where('code', $code)->sole();
    }

    private function review(Bank $bank, int $score, string $status = 'approved', ?User $user = null): Review
    {
        return Review::create([
            'user_id' => ($user ?? User::factory()->create())->id, 'bank_id' => $bank->id, 'score' => $score,
            'title' => 'Отзыв', 'body' => str_repeat('текст ', 20), 'status' => $status,
            'published_at' => $status === 'approved' ? now() : null,
        ]);
    }

    public function test_banks_catalog_lists_active_banks_with_stats(): void
    {
        $this->bank('CITI')->update(['status' => 'revoked']);
        $res = $this->get('/banks')->assertOk()->assertSee('Freedom Bank')->assertDontSee('Citibank');
        $this->assertSame(17, $res->viewData('scores')->count());
        $this->assertNotNull($res->viewData('leader'));

        $this->get('/banks?q=фридом')->assertOk()->assertViewHas('scores', fn ($s) => $s->count() === 1 && $s->first()->bank->code === 'FFIN');
        $rates = $this->get('/banks?sort=rate')->viewData('scores')
            ->map(fn ($s) => (float) ($this->get('/banks')->viewData('stats')[$s->bank->id]['best']?->rate ?? 0))->take(3)->all();
        $this->assertSame($rates, collect($rates)->sortDesc()->values()->all());
    }

    public function test_bank_page_shows_deposits_ratings_and_reviews(): void
    {
        $ffin = $this->bank('FFIN');
        $this->review($ffin, 5);
        $this->review($ffin, 1, 'pending');

        $res = $this->get('/banks/'.$ffin->slug)->assertOk()
            ->assertSee('Все вклады Freedom Bank')->assertSee('Из чего сложилась оценка')->assertSee('Отзывы клиентов');
        $this->assertNotEmpty($res->viewData('offers'));
        $this->assertSame(1, $res->viewData('reviews')->total()); // pending не виден

        $this->bank('CITI')->update(['status' => 'revoked']);
        $this->get('/banks/'.$this->bank('CITI')->slug)->assertNotFound();
    }

    public function test_objective_rating_rewards_better_rates_and_is_bounded(): void
    {
        $board = app(RatingBoard::class);
        $scores = $board->all();
        foreach ($scores as $s) {
            $this->assertGreaterThanOrEqual(0, $s->objective['score']);
            $this->assertLessThanOrEqual(5, $s->objective['score']);
        }
        // банк без вкладов не попадает в объективный рейтинг
        $citi = $scores->get($this->bank('CITI')->id);
        $this->assertFalse($citi->objective['has_data']);
        $this->assertNull($citi->rank(RatingKind::Objective));

        // поднимаем ставку банку — его критерий «ставки» растёт
        $ffin = $this->bank('FFIN');
        $before = $scores->get($ffin->id)->objective['criteria']['rates'];
        $ffin->depositProducts()->each(fn ($p) => $p->rates()->create(['currency' => 'KZT', 'rate' => 30, 'valid_from' => today()]));
        app()->forgetScopedInstances();
        $after = app(RatingBoard::class)->for($ffin)->objective['criteria']['rates'];
        $this->assertGreaterThan($before, $after);
        $this->assertEquals(1.0, $after);
    }

    public function test_people_rating_uses_only_approved_reviews_with_bayesian_average(): void
    {
        $kaspi = $this->bank('KASPI');
        $halyk = $this->bank('HSBK');
        $this->review($kaspi, 5);                                      // один восторженный
        foreach (range(1, 20) as $_) {
            $this->review($halyk, 5);
        }    // много хороших
        $this->review($halyk, 1, 'rejected');

        $board = app(RatingBoard::class);
        $k = $board->for($kaspi)->people;
        $h = $board->for($halyk)->people;
        $this->assertSame(1, $k['count']);
        $this->assertSame(20, $h['count']);
        $this->assertEquals(round((5 * 3.5 + 5) / 6, 2), $k['score']);
        $this->assertGreaterThan($k['score'], $h['score']);
        $this->assertSame(1, $board->for($halyk)->rank(RatingKind::People));
    }

    public function test_registration_review_flow_and_moderation(): void
    {
        $bank = $this->bank('BCC');
        $this->post('/register', ['name' => 'Аня', 'email' => 'anya@example.kz', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'agree' => 1])
            ->assertRedirect();
        $user = User::where('email', 'anya@example.kz')->sole();
        $this->assertSame('user', $user->role);
        $this->get('/admin')->assertForbidden();

        $this->post("/banks/{$bank->slug}/reviews", ['score' => 4, 'title' => 'Норм', 'body' => 'коротко'])->assertSessionHasErrors('body');
        $body = str_repeat('Открыла вклад онлайн, проценты приходят вовремя. ', 3);
        $this->post("/banks/{$bank->slug}/reviews", ['score' => 4, 'title' => 'Хороший банк', 'body' => $body])->assertRedirect();
        $review = Review::sole();
        $this->assertSame('pending', $review->status);
        $this->get('/banks/'.$bank->slug)->assertSee('на модерации')->assertDontSee('id="review-'.$review->id.'"', false);

        // повторная отправка — правка того же отзыва
        $this->post("/banks/{$bank->slug}/reviews", ['score' => 5, 'title' => 'Отличный банк', 'body' => $body]);
        $this->assertSame(1, Review::count());
        $this->assertSame(5, $review->fresh()->score);

        // модерация
        auth()->logout();
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/reviews')->assertOk()->assertSee('Отличный банк');
        $this->put("/admin/reviews/{$review->id}", ['action' => 'reject'])->assertSessionHasErrors('reject_reason');
        $this->put("/admin/reviews/{$review->id}", ['action' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $review->fresh()->status);
        $this->assertNotNull($review->fresh()->published_at);
        $this->put("/admin/reviews/{$review->id}", ['action' => 'reply', 'reply' => 'Спасибо!', 'reply_author' => 'BCC'])->assertRedirect();

        $this->get('/banks/'.$bank->slug)->assertSee('Отличный банк')->assertSee('Спасибо!');
        app()->forgetScopedInstances(); // в тестах один контейнер на все запросы
        $this->assertSame(1, app(RatingBoard::class)->for($bank)->people['count']);
    }

    public function test_helpful_vote_toggles_and_cannot_vote_own(): void
    {
        $review = $this->review($this->bank('BCC'), 4);
        $voter = User::factory()->create();
        $this->actingAs($voter)->post("/reviews/{$review->id}/vote")->assertRedirect();
        $this->assertSame(1, $review->fresh()->helpful);
        $this->post("/reviews/{$review->id}/vote");
        $this->assertSame(0, $review->fresh()->helpful);

        $this->actingAs($review->user)->post("/reviews/{$review->id}/vote");
        $this->assertSame(0, $review->fresh()->helpful);

        $this->post("/reviews/{$review->id}/vote")->assertRedirect(); // гость → логин
        auth()->logout();
        $this->post("/reviews/{$review->id}/vote")->assertRedirect('/login');
    }

    public function test_admin_sets_author_rating_with_history_and_snapshot_gives_delta(): void
    {
        $bank = $this->bank('KASPI');
        $this->actingAs($this->admin())->get('/admin/ratings')->assertOk()->assertSee('Kaspi');
        $this->get("/admin/ratings/{$bank->id}/edit")->assertOk();

        // вчерашняя оценка → сегодняшняя
        Rating::create(['bank_id' => $bank->id, 'kind' => RatingKind::Author, 'score' => 4.0, 'period' => today()->subDay()]);
        $this->put("/admin/ratings/{$bank->id}", ['score' => 4.5, 'verdict' => 'Удобное приложение.', 'pros' => "Быстро\nОнлайн", 'cons' => 'Мало вкладов', 'assets_bn' => 9000, 'founded_year' => 1993, 'deposit_insured' => 1])
            ->assertRedirect('/admin/ratings');
        $this->assertSame(2, Rating::where('kind', 'author')->where('bank_id', $bank->id)->count());
        $this->assertEquals(9000, $bank->fresh()->assets_bn);

        app()->forgetScopedInstances();
        $s = app(RatingBoard::class)->for($bank);
        $this->assertEquals(4.5, $s->score(RatingKind::Author));
        $this->assertEquals(0.5, $s->delta(RatingKind::Author));
        $this->get('/banks/'.$bank->slug)->assertSee('Удобное приложение.')->assertSee('Мало вкладов');

        // снимок объективного: вчерашний ниже → дельта положительная
        Rating::create(['bank_id' => $this->bank('FFIN')->id, 'kind' => RatingKind::Objective, 'score' => 0.5, 'period' => today()->subDay()]);
        app()->forgetScopedInstances();
        $this->assertGreaterThan(0, app(RatingBoard::class)->for($this->bank('FFIN'))->delta(RatingKind::Objective));
        $this->post('/admin/ratings/snapshot')->assertRedirect();
        $this->assertTrue(Rating::where('kind', 'objective')->whereDate('period', today())->exists());
        $this->artisan('banq:ratings-snapshot')->assertSuccessful();
    }

    public function test_ratings_page_and_home_block(): void
    {
        $this->review($this->bank('KASPI'), 5);
        $this->get('/ratings')->assertOk()->assertSee('Рейтинг банков')->assertSee('Как считаем')->assertSee('Kaspi');
        $this->get('/ratings?by=people')->assertOk()->assertViewHas('by', RatingKind::People);
        $this->get('/')->assertOk()->assertSee('народный рейтинг · 1 отзыв');
    }

    public function test_login_returns_visitor_back(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $this->get('/login?to=/banks/kaspi%23review-form');
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertRedirect(url('/banks/kaspi#review-form'));
        auth()->logout();
        $this->get('/login?to=//evil.example');
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertRedirect(route('ratings'));
    }
}
