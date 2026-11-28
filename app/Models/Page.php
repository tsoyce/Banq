<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * A42 · Статическая страница: «О проекте», «Оферта», «Контакты»… Та же таблица posts (type = page) и тот же редактор.
 * Адрес — /{slug} (Route::fallback → PageController), в ленту не попадает, пока не включено in_feed.
 * nav: aside — ссылка под левым меню, footer — в подвале.
 */
class Page extends Post
{
    protected $table = 'posts';

    public const NAV = ['' => 'Нигде', 'aside' => 'Под левым меню', 'footer' => 'В подвале'];

    protected $attributes = ['type' => 'page', 'in_feed' => false, 'allow_comments' => false, 'kind' => 'article'];

    /** Внешние ключи связей (теги, комментарии) — общие с публикациями. */
    public function getForeignKey(): string
    {
        return 'post_id';
    }

    public function getMorphClass(): string
    {
        return Post::class;
    }

    /**
     * Страницы для меню: список ['title' => …, 'url' => …]. Кешируется (простыми массивами — модели в кеш не кладём)
     * до сохранения любой страницы. Отложенная публикация появится в меню не позже чем через 10 минут.
     */
    public static function nav(string $place): Collection
    {
        try {
            $all = Cache::remember('pages.nav', 600, fn () => static::query()->published()->whereNotNull('nav')
                ->orderBy('nav_sort')->orderBy('title')->get(['id', 'slug', 'title', 'nav', 'type'])
                ->map(fn (Page $p) => ['nav' => $p->nav, 'title' => $p->title, 'slug' => $p->slug])->all());
        } catch (\Throwable) {
            return collect(); // до установки или миграций
        }

        return collect($all)->where('nav', $place)->map(fn ($p) => $p + ['url' => url('/'.$p['slug'])])->values();
    }

    protected static function booted(): void
    {
        parent::booted();
        static::saved(fn () => Cache::forget('pages.nav'));
        static::deleted(fn () => Cache::forget('pages.nav'));
    }
}
