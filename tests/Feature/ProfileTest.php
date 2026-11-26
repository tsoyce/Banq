<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\DepositProduct;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewModerated;
use App\Services\Import\DepositImporter;
use Database\Seeders\BankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** A28 · Личный кабинет, публичный профиль, избранное, сохранённые сравнения, настройки, уведомления. */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        (new BankSeeder)->run();
        app(DepositImporter::class)->import(base_path('tests/fixtures/deposits-matrix.xlsx'), 'deposits.xlsx', sheets: ['Лист2']);
    }

    private function user(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['name' => 'Аня Ким', 'password' => 'secret123', 'role' => 'user']);
    }

    private function review(User $user, string $code, int $score, string $status = 'approved'): Review
    {
        return Review::create([
            'user_id' => $user->id, 'bank_id' => Bank::where('code', $code)->sole()->id, 'score' => $score,
            'title' => 'Отзыв '.$code, 'body' => str_repeat('текст ', 20), 'status' => $status, 'helpful' => 3,
            'published_at' => $status === 'approved' ? now() : null,
        ]);
    }

    public function test_users_get_unique_usernames(): void
    {
        $a = $this->user();
        $b = $this->user(['email' => 'b@example.kz']);
        $this->assertSame('anya-kim', $a->username);
        $this->assertSame('anya-kim-2', $b->username);
        $this->assertNotContains(User::factory()->create(['name' => 'Admin'])->username, ['admin']);
    }

    public function test_account_requires_login_and_shows_tabs(): void
    {
        $this->get('/account')->assertRedirect('/login');
        $user = $this->user();
        $this->review($user, 'BCC', 5);
        $this->review($user, 'FFIN', 2, 'pending');

        $this->actingAs($user);
        $res = $this->get('/account')->assertOk()->assertSee('Личный кабинет')->assertSee('Отзыв BCC');
        $this->assertSame(['published' => 1, 'pending' => 1, 'rejected' => 0, 'helpful' => 3, 'favorites' => 0, 'comparisons' => 0], $res->viewData('stats'));
        $this->assertSame('Первый отзыв', $res->viewData('level')['label']);
        $this->get('/account/reviews?status=pending')->assertOk()->assertSee('Отзыв FFIN')->assertDontSee('Отзыв BCC');
        foreach (['favorites', 'comparisons', 'settings'] as $tab) {
            $this->get('/account/'.$tab)->assertOk();
        }
    }

    public function test_favorites_toggle_for_deposits_and_banks(): void
    {
        $user = $this->user();
        $product = DepositProduct::whereHas('currentRates')->firstOrFail();
        $bank = Bank::where('code', 'KASPI')->sole();
        $this->actingAs($user);

        $this->postJson('/favorites', ['type' => 'deposit', 'id' => $product->id])->assertOk()->assertJson(['on' => true, 'count' => 1]);
        $this->post('/favorites', ['type' => 'bank', 'id' => $bank->id])->assertRedirect();
        $this->assertTrue($user->fresh()->hasFavorite($product));

        $res = $this->get('/account/favorites')->assertOk()->assertSee($product->name)->assertSee($bank->displayName());
        $this->assertCount(1, $res->viewData('deposits'));
        $this->get('/deposits/'.$product->slug)->assertOk()->assertSee('В избранном');

        $this->postJson('/favorites', ['type' => 'deposit', 'id' => $product->id])->assertJson(['on' => false, 'count' => 1]);
        $this->postJson('/favorites', ['type' => 'user', 'id' => 1])->assertUnprocessable();
        $bank->update(['status' => 'revoked']);
        $this->postJson('/favorites', ['type' => 'bank', 'id' => $bank->id])->assertNotFound();
    }

    public function test_save_rename_and_delete_comparison(): void
    {
        $user = $this->user();
        $other = $this->user(['email' => 'other@example.kz']);
        $ids = DepositProduct::whereHas('currentRates')->take(3)->pluck('id');
        $this->actingAs($user);

        $this->get('/deposits/compare?ids='.$ids->join(','))->assertOk()->assertSee('Сохранить');
        $this->post('/comparisons', ['ids' => $ids->join(',').',999999', 'amount' => 2000000, 'term' => 6])->assertRedirect();
        $c = $user->comparisons()->sole();
        $this->assertSame($ids->all(), $c->product_ids);
        $this->assertStringContainsString('amount=2000000', $c->url());
        $this->get('/account/comparisons')->assertOk()->assertSee($c->title);

        $this->put('/comparisons/'.$c->id, ['title' => 'Мой выбор'])->assertRedirect();
        $this->assertSame('Мой выбор', $c->fresh()->title);
        $this->actingAs($other)->delete('/comparisons/'.$c->id)->assertNotFound();
        $this->actingAs($user)->delete('/comparisons/'.$c->id)->assertRedirect();
        $this->assertSame(0, $user->comparisons()->count());
    }

    public function test_update_profile_avatar_and_privacy(): void
    {
        $user = $this->user();
        $this->actingAs($user)->put('/account/settings/profile', [
            'name' => 'Анна', 'username' => 'Anna-KZ', 'city' => 'Алматы', 'country_code' => 'KZ', 'bio' => 'Слежу за ставками',
            'is_public' => '1', 'avatar' => UploadedFile::fake()->image('me.png', 200, 200),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('anna-kz', $user->username);
        $this->assertSame('Алматы, Казахстан', $user->location());
        $avatar = public_path($user->avatar);
        $this->assertFileExists($avatar);

        $this->put('/account/settings/profile', ['name' => 'Анна', 'username' => 'admin'])->assertSessionHasErrors('username');
        $this->delete('/account/settings/avatar')->assertRedirect();
        $this->assertFileDoesNotExist($avatar);
        $this->assertNull($user->fresh()->avatar);
        File::deleteDirectory(public_path('uploads/avatars'));
    }

    public function test_public_profile_shows_only_approved_reviews_and_respects_privacy(): void
    {
        $user = $this->user(['city' => 'Шымкент', 'bio' => 'Пишу честно']);
        $this->review($user, 'BCC', 5);
        $this->review($user, 'FFIN', 1, 'pending');

        $res = $this->get('/u/'.$user->username)->assertOk()->assertSee('Аня Ким')->assertSee('Пишу честно')->assertSee('Отзыв BCC')->assertDontSee('Отзыв FFIN');
        $this->assertSame(1, $res->viewData('stats')['published']);
        $this->get('/banks/'.Bank::where('code', 'BCC')->sole()->slug)->assertSee(route('profile.show', $user->username), false);

        $user->update(['is_public' => false]);
        $this->get('/u/'.$user->username)->assertNotFound();
        $this->actingAs($user)->get('/u/'.$user->username)->assertOk()->assertSee('Профиль скрыт');
        $this->get('/u/nobody-here')->assertNotFound();
    }

    public function test_change_email_password_and_notifications(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->put('/account/settings/email', ['email' => 'new@example.kz', 'current_password' => 'wrong'])->assertSessionHasErrorsIn('email', 'current_password');
        $this->put('/account/settings/email', ['email' => 'new@example.kz', 'current_password' => 'secret123'])->assertSessionHasNoErrors();
        $this->assertSame('new@example.kz', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);

        $this->put('/account/settings/password', ['current_password' => 'secret123', 'password' => 'newsecret1', 'password_confirmation' => 'newsecret1'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('newsecret1', $user->fresh()->password));

        $this->assertTrue($user->wants('review_status'));
        $this->put('/account/settings/notifications', ['notify' => ['rate_changes' => '1']])->assertRedirect();
        $user->refresh();
        $this->assertFalse($user->wants('review_status'));
        $this->assertTrue($user->wants('rate_changes'));
    }

    public function test_moderation_notifies_author_when_enabled(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $author = $this->user();
        $quiet = $this->user(['email' => 'quiet@example.kz', 'notify' => ['review_status' => false]]);
        $r1 = $this->review($author, 'BCC', 4, 'pending');
        $r2 = $this->review($quiet, 'BCC', 4, 'pending');

        $this->actingAs($admin)->put('/admin/reviews/'.$r1->id, ['action' => 'approve'])->assertRedirect();
        $this->put('/admin/reviews/'.$r2->id, ['action' => 'approve'])->assertRedirect();
        $this->put('/admin/reviews/'.$r1->id, ['action' => 'reply', 'reply' => 'Спасибо за отзыв!'])->assertRedirect();

        Notification::assertSentToTimes($author, ReviewModerated::class, 2);
        Notification::assertNothingSentTo($quiet);
    }

    public function test_delete_account_removes_user_data(): void
    {
        $user = $this->user();
        $this->review($user, 'BCC', 5);
        $this->actingAs($user);
        $this->delete('/account', ['current_password' => 'secret123'])->assertSessionHasErrorsIn('delete', 'confirm');
        $this->delete('/account', ['current_password' => 'secret123', 'confirm' => '1'])->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull(User::find($user->id));
        $this->assertSame(0, Review::count());

        $admin = User::factory()->create(['role' => 'admin', 'password' => 'secret123']);
        $this->actingAs($admin)->delete('/account', ['current_password' => 'secret123', 'confirm' => '1'])->assertForbidden();
    }
}
