<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Country;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\User;
use App\Support\AdminScope;
use App\Support\Blog\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A37 · Публикации блога. Список — общий механизм ресурсов (поиск, фильтры, массовые действия, экспорт),
 * форма — свой редактор admin.posts.editor (Tiptap, resources/js/editor.js).
 * Тело очищается HtmlSanitizer; теги и связи с банками/продуктами пишутся в saved().
 */
class PostResource extends Resource
{
    public function key(): string
    {
        return 'posts';
    }

    public function label(): string
    {
        return 'Публикации';
    }

    public function singular(): string
    {
        return 'публикацию';
    }

    public function model(): string
    {
        return Post::class;
    }

    public function icon(): string
    {
        return 'news';
    }

    public function description(): ?string
    {
        return 'Статьи, новости, обзоры и гайды. Будущая дата публикации — материал выйдет сам в это время.';
    }

    public function formView(): string
    {
        return 'admin.posts.editor';
    }

    public function exchangeEntity(): ?string
    {
        return 'posts';
    }

    /** A46 · Где у записи страна — для фильтра в плашке админки и стран сотрудника. */
    public function countryScope(): ?string
    {
        return 'country_id';
    }

    public function countryNullable(): bool
    {
        return true; // публикация без страны — для всех стран
    }

    public function query(): Builder
    {
        return Post::query()->where('type', 'post')->with(['category', 'author'])->withCount(['comments', 'reactions']); // A42 · страницы — в PageResource
    }

    public function search(): array
    {
        return ['title', 'slug', 'lead', 'tags.name'];
    }

    public function defaultSort(): array
    {
        return ['updated_at', 'desc'];
    }

    public function filters(): array
    {
        return [
            'state' => ['label' => 'Состояние', 'options' => ['draft' => 'Черновики', 'scheduled' => 'Запланированные', 'published' => 'Опубликованные'], 'apply' => fn ($q, $v) => match ($v) {
                'draft' => $q->where(fn ($w) => $w->where('status', '!=', 'published')->orWhereNull('published_at')),
                'scheduled' => $q->where('status', 'published')->where('published_at', '>', now()),
                'published' => $q->published(),
            }],
            'category' => ['label' => 'Рубрика', 'options' => fn () => PostCategory::query()->ordered()->pluck('name', 'id')->all(), 'apply' => fn ($q, $v) => $q->where('category_id', $v)],
            'kind' => ['label' => 'Формат', 'options' => Post::KINDS, 'apply' => fn ($q, $v) => $q->where('kind', $v)],
            'country' => ['label' => 'Страна', 'options' => fn () => ['all' => 'Для всех стран'] + app(AdminScope::class)->countryQuery()->pluck('name', 'id')->all(),
                'apply' => fn ($q, $v) => $v === 'all' ? $q->whereNull('country_id') : $q->where('country_id', $v)],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Публикация', 'sort' => 'title', 'render' => fn (Post $p) => self::html(
                '<span class="flex items-center gap-3 min-w-0">'
                .($p->coverUrl() ? '<img src="'.e($p->coverUrl()).'" alt="" class="w-14 h-10 rounded-lg object-cover shrink-0" loading="lazy">' : '<span class="w-14 h-10 rounded-lg shrink-0 grid place-items-center text-white text-xs font-bold" style="background: linear-gradient(135deg, '.e($p->category?->colorOrDefault() ?? '#7b5cff').', #22d3ee)">'.e(mb_substr($p->title, 0, 1)).'</span>')
                .'<span class="min-w-0"><b class="block truncate max-w-[28rem]">'.($p->is_featured ? '★ ' : '').e($p->title).'</b><span class="faint text-xs block truncate">'.e(trim(($p->category?->name ?? 'Без рубрики').' · '.$p->kindLabel())).'</span></span></span>')],
            ['key' => 'state', 'label' => 'Состояние', 'sort' => 'status', 'render' => fn (Post $p) => self::html('<span class="chip status-'.['draft' => 'pending', 'scheduled' => 'pending', 'published' => 'approved'][$p->state()].'"><span class="status-dot"></span>'.e($p->stateLabel()).'</span>')],
            ['key' => 'published_at', 'label' => 'Дата', 'sort' => 'published_at', 'render' => fn (Post $p) => self::html('<span class="tabular text-sm whitespace-nowrap">'.($p->published_at ? e($p->published_at->translatedFormat('j M Y, H:i')) : '<span class="faint">—</span>').'</span>')],
            ['key' => 'author', 'label' => 'Автор', 'render' => fn (Post $p) => self::html('<span class="whitespace-nowrap text-sm">'.e($p->author?->name ?? '—').'</span>')],
            ['key' => 'views', 'label' => 'Просм.', 'sort' => 'views', 'render' => fn (Post $p) => self::html('<span class="tabular">'.number_format($p->views, 0, ',', ' ').'</span>')],
            ['key' => 'comments', 'label' => 'Комм.', 'sort' => 'comments_count', 'render' => fn (Post $p) => self::html('<span class="tabular">'.$p->comments_count.'</span>')],
        ];
    }

    public function fields(?Model $model = null): array
    {
        return [
            ['name' => 'title', 'label' => 'Заголовок', 'rules' => ['required', 'string', 'max:200']],
            ['name' => 'lead', 'label' => 'Лид', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:400']],
            ['name' => 'body', 'label' => 'Текст', 'type' => 'html', 'rules' => ['nullable', 'string', 'max:600000']],
            ['name' => 'slug', 'label' => 'Адрес', 'rules' => ['nullable', 'alpha_dash', 'max:191', Rule::unique('posts')->ignore($model)]],
            ['name' => 'status', 'label' => 'Статус', 'type' => 'select', 'options' => Post::STATUSES, 'rules' => ['required'], 'default' => 'draft'],
            ['name' => 'published_at', 'label' => 'Дата публикации', 'type' => 'datetime', 'rules' => ['nullable', 'date']],
            ['name' => 'category_id', 'label' => 'Рубрика', 'type' => 'select', 'options' => fn () => PostCategory::query()->ordered()->pluck('name', 'id')->all(), 'rules' => ['nullable', 'integer']],
            ['name' => 'kind', 'label' => 'Формат', 'type' => 'select', 'options' => Post::KINDS, 'rules' => ['required'], 'default' => 'article'],
            ['name' => 'country_id', 'label' => 'Страна', 'type' => 'select', 'options' => fn () => app(AdminScope::class)->countryQuery()->get()->mapWithKeys(fn ($c) => [$c->id => trim($c->flag.' '.$c->name)])->all(), 'rules' => ['nullable', 'integer'], 'help' => 'пусто — для всех стран'],
            ['name' => 'author_id', 'label' => 'Автор', 'type' => 'select', 'options' => fn () => User::query()->whereIn('role', ['admin', 'editor'])->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'integer']],
            ['name' => 'is_featured', 'label' => 'Главный материал', 'type' => 'bool'],
            ['name' => 'allow_comments', 'label' => 'Комментарии открыты', 'type' => 'bool', 'default' => true],
            ['name' => 'cover', 'label' => 'Обложка', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'cover_file', 'label' => 'Файл обложки', 'virtual' => true, 'rules' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192']],
            ['name' => 'cover_alt', 'label' => 'Описание обложки', 'rules' => ['nullable', 'string', 'max:200']],
            ['name' => 'cover_caption', 'label' => 'Подпись к обложке', 'rules' => ['nullable', 'string', 'max:200']],
            ['name' => 'tags', 'label' => 'Теги', 'virtual' => true, 'rules' => ['nullable', 'string', 'max:1000'], 'value' => fn (Post $p) => $p->tags->pluck('name')->implode(', ')],
            ['name' => 'links', 'label' => 'О ком материал', 'virtual' => true, 'rules' => ['nullable', 'array', 'max:40'], 'value' => fn (Post $p) => $p->linkKeys()],
            ['name' => 'seo_title', 'label' => 'SEO-заголовок', 'rules' => ['nullable', 'string', 'max:200']],
            ['name' => 'seo_description', 'label' => 'SEO-описание', 'rules' => ['nullable', 'string', 'max:300']],
            ['name' => 'source_name', 'label' => 'Источник', 'rules' => ['nullable', 'string', 'max:120']],
            ['name' => 'source_url', 'label' => 'Ссылка на источник', 'type' => 'url', 'rules' => ['nullable', 'url', 'max:255']],
        ];
    }

    public function rules(?Model $m): array
    {
        return parent::rules($m) + ['links.*' => ['string', 'max:200', 'regex:/^[a-z_]+:[A-Za-z0-9_-]+$/']];
    }

    public function defaults(Request $request): array
    {
        return ['author_id' => $request->user()?->id, 'allow_comments' => true, 'country_id' => country()?->id];
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        /** @var Post $m */
        $m->body = app(HtmlSanitizer::class)->clean($m->body);
        $m->reading_minutes = Post::readingMinutes($m->plainText());
        $m->author_id ??= $request->user()?->id;
        $m->kind ??= 'article';

        // Кнопки редактора: «Опубликовать» / «В черновики»
        match ($request->input('_action')) {
            'publish' => $m->status = 'published',
            'draft' => $m->status = 'draft',
            default => null,
        };
        if ($m->status === 'published' && ! $m->published_at) {
            $m->published_at = now();
        }

        if (! $m->slug) {
            $base = Str::limit(Str::slug($m->title, '-', 'ru'), 120, '') ?: 'post';
            for ($i = 1, $s = $base; Post::withoutGlobalScopes()->where('slug', $s)->whereKeyNot($m->id)->exists(); $s = $base.'-'.++$i);
            $m->slug = $s;
        }

        if ($file = $request->file('cover_file')) {
            $m->cover = self::storeImage($file, 'covers');
        }
        if ($request->boolean('cover_remove')) {
            $m->cover = null;
        }
        // Один «главный материал» на ленту: новый снимает отметку с прежнего
        if ($m->is_featured && $m->isDirty('is_featured')) {
            Post::query()->where('is_featured', true)->whereKeyNot($m->id)->update(['is_featured' => false]);
        }
    }

    public function saved(Model $m, array $data, Request $request): void
    {
        /** @var Post $m */
        $m->tags()->sync(Tag::idsFor(preg_split('/[,;\n]+/u', (string) $request->input('tags', ''))));

        // Связи: отмеченные в редакторе + продукты, вставленные в текст карточками
        preg_match_all('/data-embed="([a-z_]+)" data-slug="([A-Za-z0-9_-]+)"|data-slug="([A-Za-z0-9_-]+)" data-embed="([a-z_]+)"/', (string) $m->body, $mm, PREG_SET_ORDER);
        $embedded = array_map(fn ($x) => ($x[1] ?: $x[4]).':'.($x[2] ?: $x[3]), $mm);
        $embedded = array_filter($embedded, fn ($k) => isset(Post::LINK_TYPES[strtok($k, ':')])); // A43 · видео YouTube — не связь
        $m->syncLinks([...(array) $request->input('links', []), ...$embedded]);
    }

    /** Сохранить картинку в public/uploads/blog/{dir}/ГГГГ/ММ и вернуть путь от корня сайта. */
    public static function storeImage(\Illuminate\Http\UploadedFile $file, string $dir): string
    {
        $folder = 'uploads/blog/'.$dir.'/'.now()->format('Y/m');
        $name = Str::random(20).'.'.strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $file->move(public_path($folder), $name);

        return '/'.$folder.'/'.$name;
    }

    public function bulkActions(): array
    {
        return [
            'publish' => ['label' => 'Опубликовать', 'run' => fn (Collection $m) => 'Опубликовано: '.$m->each(fn (Post $p) => $p->update(['status' => 'published', 'published_at' => $p->published_at ?? now()]))->count()],
            'draft' => ['label' => 'В черновики', 'run' => fn (Collection $m) => 'В черновиках: '.$m->each->update(['status' => 'draft'])->count()],
            'comments_off' => ['label' => 'Закрыть комментарии', 'run' => fn (Collection $m) => 'Закрыто: '.$m->each->update(['allow_comments' => false])->count()],
            ...parent::bulkActions(),
        ];
    }

    public function title(Model $m): string
    {
        return $m->title ?: 'Без заголовка';
    }

    public function publicUrl(Model $m): ?string
    {
        return route('blog.show', $m).($m->isPublished() ? '' : '?preview=1');
    }

    public function summary(): array
    {
        return [
            'Опубликовано' => Post::query()->where('type', 'post')->published()->count(),
            'Черновиков' => Post::query()->where('type', 'post')->where(fn ($w) => $w->where('status', '!=', 'published')->orWhereNull('published_at'))->count(),
            'Запланировано' => Post::query()->where('status', 'published')->where('published_at', '>', now())->count(),
            'Просмотров' => (int) Post::query()->sum('views'),
        ];
    }
}
