<?php

namespace App\Admin\Resources;

use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A42 · Статические страницы: тот же редактор, что у публикаций (admin.posts.editor с $isPage),
 * адрес /{slug}, в ленту не попадают (in_feed), место в меню — nav (aside | footer).
 */
class PageResource extends PostResource
{
    public function key(): string
    {
        return 'pages';
    }

    public function label(): string
    {
        return 'Страницы';
    }

    public function singular(): string
    {
        return 'страницу';
    }

    public function model(): string
    {
        return Page::class;
    }

    public function icon(): string
    {
        return 'list';
    }

    public function description(): ?string
    {
        return '«О проекте», «Контакты», «Оферта» — постоянные страницы по адресу /адрес. В ленту журнала не попадают, если не включить.';
    }

    public function exchangeEntity(): ?string
    {
        return null;
    }

    public function query(): Builder
    {
        return Page::query()->with(['author']);
    }

    public function search(): array
    {
        return ['title', 'slug', 'lead'];
    }

    public function filters(): array
    {
        return [
            'state' => parent::filters()['state'],
            'nav' => ['label' => 'Меню', 'options' => ['aside' => 'Под левым меню', 'footer' => 'В подвале', 'none' => 'Нигде'],
                'apply' => fn ($q, $v) => $v === 'none' ? $q->whereNull('nav') : $q->where('nav', $v)],
        ];
    }

    public function columns(): array
    {
        $cols = collect(parent::columns())->keyBy('key');

        return [
            ['key' => 'title', 'label' => 'Страница', 'sort' => 'title', 'render' => fn (Page $p) => self::html('<b class="block truncate max-w-[28rem]">'.e($p->title).'</b><span class="faint text-xs font-mono">/'.e($p->slug).'</span>')],
            $cols['state'],
            ['key' => 'nav', 'label' => 'Меню', 'sort' => 'nav', 'render' => fn (Page $p) => Page::NAV[$p->nav ?? ''] ?? '—'],
            ['key' => 'in_feed', 'label' => 'В ленте', 'sort' => 'in_feed', 'render' => fn (Page $p) => self::yesNo($p->in_feed)],
            ['key' => 'updated_at', 'label' => 'Обновлено', 'sort' => 'updated_at', 'render' => fn (Page $p) => self::html('<span class="tabular text-sm whitespace-nowrap">'.e($p->updated_at?->translatedFormat('j M Y, H:i')).'</span>')],
            $cols['views'],
        ];
    }

    public function fields(?Model $model = null): array
    {
        $keep = ['title', 'lead', 'body', 'status', 'published_at', 'author_id', 'cover', 'cover_file', 'cover_alt', 'cover_caption', 'seo_title', 'seo_description'];
        $fields = collect(parent::fields($model))->keyBy('name')->only($keep)->values()->all();

        return [...$fields,
            ['name' => 'slug', 'label' => 'Адрес', 'rules' => ['nullable', 'alpha_dash', 'max:191', Rule::unique('posts')->ignore($model), Rule::notIn(self::reservedSlugs())]],
            ['name' => 'nav', 'label' => 'Ссылка в меню', 'type' => 'select', 'options' => Page::NAV, 'rules' => ['nullable', Rule::in(array_keys(Page::NAV))]],
            ['name' => 'nav_sort', 'label' => 'Порядок в меню', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:65000'], 'default' => 500],
            ['name' => 'in_feed', 'label' => 'Показывать в ленте журнала', 'type' => 'bool'],
            ['name' => 'allow_comments', 'label' => 'Комментарии', 'type' => 'bool'],
        ];
    }

    /** Первые сегменты маршрутов сайта (blog, cards, admin…) — такие адреса страницам не даём. */
    public static function reservedSlugs(): array
    {
        return collect(Route::getRoutes()->getRoutes())->map(fn ($r) => strtok(trim($r->uri(), '/'), '/'))
            ->filter(fn ($s) => $s && ! str_contains($s, '{'))->unique()->values()->merge(['uploads', 'build', 'storage', 'favicon.ico', 'robots.txt'])->all();
    }

    public function defaults(Request $request): array
    {
        return ['author_id' => $request->user()?->id, 'allow_comments' => false, 'in_feed' => false, 'nav_sort' => 500];
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        $m->type = 'page';
        $m->nav = $m->nav ?: null;
        $m->nav_sort ??= 500;
        if (! $m->slug) {
            $base = Str::limit(Str::slug($m->title, '-', 'ru'), 120, '') ?: 'page';
            $reserved = self::reservedSlugs();
            for ($i = 1, $s = $base; in_array($s, $reserved, true) || Page::withoutGlobalScopes()->where('slug', $s)->whereKeyNot($m->id)->exists(); $s = $base.'-'.++$i);
            $m->slug = $s;
        }
        parent::saving($m, $data, $request);
        $m->is_featured = false;
    }

    public function saved(Model $m, array $data, Request $request): void
    {
        // у страниц нет тегов и связей с продуктами
    }

    public function publicUrl(Model $m): ?string
    {
        return $m->url().($m->isPublished() ? '' : '?preview=1');
    }

    public function bulkActions(): array
    {
        return collect(parent::bulkActions())->except('comments_off')->all();
    }

    public function summary(): array
    {
        return [
            'Опубликовано' => Page::query()->published()->count(),
            'Черновиков' => Page::query()->where(fn ($w) => $w->where('status', '!=', 'published')->orWhereNull('published_at'))->count(),
            'В меню' => Page::query()->whereNotNull('nav')->count(),
        ];
    }
}
