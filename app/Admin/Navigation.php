<?php

namespace App\Admin;

use App\Models\Comment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * A70 · Карта админки — один источник для меню, хлебных крошек, поиска ⌘K и обзора.
 *
 * Группы: Контент / Каталог / Данные / Реклама / Сайт / Люди. Пункт — ресурс из ResourceRegistry (подпись, иконка и роли
 * берутся у ресурса) или своя страница (route + роли). Пункт с маршрутом, которого ещё нет (например, «Виджеты» —
 * их добавит переработка витрины), показывается как «скоро» и не ломает меню.
 */
class Navigation
{
    /** @var array<string, array> группы по роли — бейджи считаются один раз за запрос */
    private array $memo = [];

    public function __construct(private ResourceRegistry $registry) {}

    /**
     * @return list<array{key: string, label: string, icon: string, items: list<array>}>
     */
    public function groups(?User $user = null): array
    {
        $role = ($user ?? auth()->user())?->role;
        if (isset($this->memo[(string) $role])) {
            return $this->memo[(string) $role];
        }
        $groups = [];
        foreach ($this->map() as $g) {
            $items = [];
            foreach ($g['items'] as $it) {
                $it = $this->resolve($it);
                if ($it && in_array($role, $it['roles'], true)) {
                    $items[] = $it;
                }
            }
            if ($items) {
                $groups[] = [...$g, 'items' => $items];
            }
        }

        return $this->memo[(string) $role] = $groups;
    }

    /** Все доступные пункты одним списком (поиск ⌘K). */
    public function items(?User $user = null): array
    {
        return collect($this->groups($user))->flatMap(fn ($g) => array_map(fn ($it) => [...$it, 'group' => $g['label']], $g['items']))->all();
    }

    /** Текущие [группа, пункт] для хлебных крошек — по активному пункту. */
    public function current(?User $user = null): ?array
    {
        foreach ($this->groups($user) as $g) {
            foreach ($g['items'] as $it) {
                if ($it['active']) {
                    return [$g, $it];
                }
            }
        }

        return null;
    }

    /** Подпись группы, где живёт ресурс (вместо прежней «Данные»/«Сообщество» над заголовком). */
    public function groupOf(string $resourceKey): ?string
    {
        foreach ($this->map() as $g) {
            foreach ($g['items'] as $it) {
                if (($it['resource'] ?? null) === $resourceKey) {
                    return $g['label'];
                }
            }
        }

        return null;
    }

    /** Быстрые действия: создать запись — для палитры и обзора. */
    public function quickActions(?User $user = null): array
    {
        $role = ($user ?? auth()->user())?->role;
        $out = [];
        foreach (['posts' => 'Новая публикация', 'pages' => 'Новая страница', 'banks' => 'Новый банк', 'deposits' => 'Новый вклад',
            'cards' => 'Новая карта', 'packages' => 'Новый пакет услуг', 'ad-campaigns' => 'Новая рекламная кампания', 'redirects' => 'Новый редирект'] as $key => $label) {
            $r = $this->registry->all()[$key] ?? null;
            if ($r && $r->canCreate() && in_array($role, $r->roles(), true)) {
                $out[] = ['label' => $label, 'icon' => $r->icon(), 'url' => route('admin.resource.create', $key), 'resource' => $key];
            }
        }
        $out[] = ['label' => 'Залить данные', 'icon' => 'upload', 'url' => route('admin.exchange.index'), 'resource' => null];

        return $out;
    }

    // ── Карта ───────────────────────────────────────────────

    private function map(): array
    {
        $both = ['admin', 'editor'];
        $admin = ['admin'];

        return [
            ['key' => 'content', 'label' => 'Контент', 'icon' => 'news', 'items' => [
                ['resource' => 'posts', 'keywords' => 'блог журнал статьи новости записи'],
                ['resource' => 'pages', 'keywords' => 'о проекте контакты'],
                ['resource' => 'post-categories', 'keywords' => 'категории разделы блога'],
                ['resource' => 'comments', 'badge' => fn () => Comment::where('status', 'pending')->count(), 'keywords' => 'модерация'],
                ['route' => 'admin.reviews.index', 'match' => 'admin.reviews.*', 'label' => 'Отзывы', 'icon' => 'quote', 'roles' => $both,
                    'badge' => fn () => Review::pending()->count(), 'keywords' => 'модерация народный рейтинг'],
                ['route' => 'admin.widgets.index', 'match' => 'admin.widgets.*', 'label' => 'Виджеты', 'icon' => 'grid', 'roles' => $both,
                    'keywords' => 'меню главная сайдбар блоки порядок'],
            ]],
            ['key' => 'catalog', 'label' => 'Каталог', 'icon' => 'bank', 'items' => [
                ['resource' => 'countries'],
                ['resource' => 'banks', 'keywords' => 'бвд'],
                ['resource' => 'deposits', 'keywords' => 'депозиты'],
                ['resource' => 'rates', 'keywords' => 'проценты гэсв'],
                ['resource' => 'cards', 'keywords' => 'дебетовые кредитные'],
                ['resource' => 'packages', 'keywords' => 'тарифы'],
                ['resource' => 'payment-systems', 'keywords' => 'мпс visa mastercard мир'],
                ['resource' => 'card-tiers', 'keywords' => 'classic gold platinum значки'],
                ['route' => 'admin.ratings.index', 'match' => 'admin.ratings.*', 'label' => 'Рейтинги', 'icon' => 'star', 'roles' => $both, 'keywords' => 'оценки места'],
            ]],
            ['key' => 'data', 'label' => 'Данные', 'icon' => 'swap', 'items' => [
                ['route' => 'admin.exchange.index', 'match' => ['admin.exchange.index', 'admin.exchange.check', 'admin.exchange.show'], 'label' => 'Экспорт / Импорт', 'icon' => 'swap', 'roles' => $both,
                    'keywords' => 'обмен json csv выгрузка загрузка история'],
                ['route' => 'admin.exchange.grid.index', 'match' => ['admin.exchange.grid', 'admin.exchange.grid.*'], 'label' => 'Таблица', 'icon' => 'grid', 'roles' => $both, 'keywords' => 'excel сетка'],
                ['route' => 'admin.import.index', 'match' => 'admin.import.*', 'label' => 'Матрица ставок', 'icon' => 'upload', 'roles' => $both, 'keywords' => 'excel xlsx депозиты'],
                ['route' => 'admin.exchange.tokens', 'label' => 'Токены API', 'icon' => 'key', 'roles' => $both, 'keywords' => 'агент api ключи'],
                ['route' => 'admin.currency.index', 'match' => 'admin.currency.*', 'label' => 'Курсы валют', 'icon' => 'coins', 'roles' => $both, 'keywords' => 'нацбанк usd eur'],
                ['route' => 'admin.flags.index', 'match' => 'admin.flags.*', 'label' => 'Флаги', 'icon' => 'globe', 'roles' => $both, 'keywords' => 'страны значки'],
                ['route' => 'admin.wordpress.index', 'match' => 'admin.wordpress.*', 'label' => 'Перенос с WordPress', 'icon' => 'download', 'roles' => $admin, 'keywords' => 'wp tsoy.in миграция картинки'],
            ]],
            ['key' => 'ads', 'label' => 'Реклама', 'icon' => 'chart', 'items' => [
                ['route' => 'admin.ads.index', 'match' => 'admin.ads.*', 'label' => 'Обзор рекламы', 'icon' => 'chart', 'roles' => $both, 'keywords' => 'статистика показы клики ads.txt'],
                ['resource' => 'ad-campaigns', 'keywords' => 'реклама'],
                ['resource' => 'ad-creatives', 'keywords' => 'баннеры реклама'],
                ['resource' => 'ad-slots', 'label' => 'Места', 'keywords' => 'реклама'],
                ['resource' => 'code-snippets', 'keywords' => 'метрика adsense google analytics счётчики'],
            ]],
            ['key' => 'site', 'label' => 'Сайт', 'icon' => 'settings', 'items' => [
                ['route' => 'admin.settings.edit', 'match' => 'admin.settings.*', 'label' => 'Настройки', 'icon' => 'settings', 'roles' => $both,
                    'keywords' => 'название логотип фавикон подвал копирайт годы тема'],
                ['route' => 'admin.author.edit', 'match' => 'admin.author.*', 'label' => 'Автор и соцсети', 'icon' => 'user', 'roles' => $both, 'keywords' => 'донаты подписчики'],
                ['route' => 'admin.ticker.edit', 'match' => 'admin.ticker.*', 'label' => 'Бегущая строка', 'icon' => 'news', 'roles' => $both, 'keywords' => 'тикер'],
                ['resource' => 'redirects', 'keywords' => '301 ссылки'],
                ['route' => 'admin.database.index', 'match' => 'admin.database.*', 'label' => 'База данных', 'icon' => 'layers', 'roles' => $admin, 'keywords' => 'mysql sqlite бэкап'],
            ]],
            ['key' => 'people', 'label' => 'Люди', 'icon' => 'users', 'items' => [
                ['resource' => 'users', 'keywords' => 'роли редакторы админы e-mail'],
            ]],
        ];
    }

    /** Пункт карты → готовый пункт меню (или null, если ресурса нет). */
    private function resolve(array $it): ?array
    {
        if ($key = $it['resource'] ?? null) {
            $r = $this->registry->all()[$key] ?? null;
            if (! $r) {
                return null;
            }

            return [
                'key' => $key, 'label' => $it['label'] ?? $r->label(), 'icon' => $r->icon(), 'roles' => $r->roles(),
                'url' => route('admin.resource.index', $key),
                'active' => request()->routeIs('admin.resource.*') && request()->route('key') === $key,
                'badge' => isset($it['badge']) ? ($it['badge'])() : 0,
                'keywords' => trim(($it['keywords'] ?? '').' '.$r->singular()), 'soon' => false,
            ];
        }

        $exists = Route::has($it['route']);

        return [
            'key' => $it['route'], 'label' => $it['label'], 'icon' => $it['icon'], 'roles' => $it['roles'],
            'url' => $exists ? route($it['route']) : null,
            'active' => $exists && request()->routeIs(...(array) ($it['match'] ?? $it['route'])),
            'badge' => $exists && isset($it['badge']) ? ($it['badge'])() : 0,
            'keywords' => $it['keywords'] ?? '', 'soon' => ! $exists,
        ];
    }
}
