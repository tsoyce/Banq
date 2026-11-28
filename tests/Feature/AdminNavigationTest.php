<?php

namespace Tests\Feature;

use App\Admin\Navigation;
use App\Models\Bank;
use App\Models\Card;
use App\Models\Country;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A70 · Карта админки: группы меню и роли, «скоро» для будущих экранов, хлебные крошки, поиск ⌘K по всем разделам, обзор. */
class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        (new BankSeeder)->run();
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_groups_follow_roles_and_future_screens_are_soon(): void
    {
        $nav = app(Navigation::class);
        $labels = fn (User $u) => collect($nav->groups($u))->pluck('label')->all();

        $this->assertSame(['Контент', 'Каталог', 'Данные', 'Реклама', 'Сайт', 'Люди'], $labels($this->admin()));
        // у редактора нет «Людей» (пользователи — только admin), базы данных, переноса с WP и кодов
        $editor = $this->editor();
        $this->assertNotContains('Люди', $labels($editor));
        $items = collect((new Navigation(app(\App\Admin\ResourceRegistry::class)))->items($editor))->pluck('label');
        $this->assertNotContains('База данных', $items);
        $this->assertNotContains('Перенос с WordPress', $items);
        $this->assertNotContains('Коды и счётчики', $items);
        $this->assertContains('Публикации', $items);

        // «Виджеты» — пока без маршрута: пункт есть, ссылки нет
        $widgets = collect($nav->items($this->admin()))->firstWhere('label', 'Виджеты');
        $this->assertTrue($widgets['soon']);
        $this->assertNull($widgets['url']);
    }

    public function test_layout_shows_groups_badges_and_breadcrumbs(): void
    {
        $admin = $this->admin();
        Review::create(['user_id' => $admin->id, 'bank_id' => Bank::first()->id, 'score' => 4, 'title' => 'Отзыв', 'body' => str_repeat('текст ', 20), 'status' => 'pending']);

        $this->actingAs($admin)->get('/admin/banks')->assertOk()
            ->assertSee('Каталог')->assertSee('Реклама')->assertSee('Люди')
            ->assertSee('aria-label="Хлебные крошки"', false)
            ->assertSeeInOrder(['Админка', 'Каталог', 'Банки'])
            ->assertSee('скоро')
            ->assertSee('admin-badge', false);

        $bank = Bank::where('code', 'HALYK')->first() ?? Bank::first();
        $this->actingAs($admin)->get("/admin/banks/{$bank->id}/edit")->assertOk()
            ->assertSeeInOrder(['Админка', 'Каталог', 'Банки', $bank->displayName()]);
    }

    public function test_search_covers_all_sections_and_respects_roles_and_countries(): void
    {
        $admin = $this->admin();
        $bank = Bank::first();
        Post::create(['title' => 'Обзор кешбэка Zorro', 'slug' => 'zorro', 'status' => 'draft', 'author_id' => $admin->id]);
        Card::create(['bank_id' => $bank->id, 'kind' => 'debit', 'name' => 'Zorro Card', 'slug' => 'zorro-card']);
        Redirect::remember('/old-zorro', '/blog/zorro');
        User::factory()->create(['name' => 'Zorro Reader', 'email' => 'zorro@example.com']);

        $types = collect($this->actingAs($admin)->getJson('/admin/search?q=zorro')->assertOk()->json())->pluck('type');
        $this->assertContains('Публикации', $types);
        $this->assertContains('Карты', $types);
        $this->assertContains('Редиректы', $types);
        $this->assertContains('Пользователи', $types);

        // редактор не видит пользователей; сотрудник другой страны не видит карту банка Казахстана
        $uz = Country::create(['code' => 'UZ', 'name' => 'Узбекистан', 'currency' => 'UZS', 'is_active' => true]);
        $editor = $this->editor();
        $editor->forceFill(['admin_countries' => [$uz->id]])->save();
        $types = collect($this->actingAs($editor)->getJson('/admin/search?q=zorro')->assertOk()->json())->pluck('type');
        $this->assertNotContains('Пользователи', $types);
        $this->assertNotContains('Карты', $types);
        $this->assertContains('Публикации', $types); // публикация без страны — общая

        $this->actingAs($admin)->getJson('/admin/search?q=z')->assertOk()->assertExactJson([]);
    }

    public function test_dashboard_for_both_roles(): void
    {
        $admin = $this->admin();
        Post::create(['title' => 'Свежая правка', 'slug' => 'fresh', 'status' => 'draft', 'author_id' => $admin->id]);

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('Требует внимания')->assertSee('Последние правки')->assertSee('Свежая правка')
            ->assertSee('показов за 7 дней')->assertSee('Пользователей')
            ->assertSee('Залить данные')
            ->assertDontSee('aria-label="Хлебные крошки"', false);

        $this->actingAs($this->editor())->get('/admin')->assertOk()
            ->assertSee('Требует внимания')->assertDontSee('Пользователей');
    }
}
