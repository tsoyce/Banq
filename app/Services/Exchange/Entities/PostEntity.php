<?php

namespace App\Services\Exchange\Entities;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\User;
use App\Services\Exchange\Concerns\ResolvesBank;
use App\Services\Exchange\Entity;
use App\Services\Exchange\ImportContext;
use App\Services\Exchange\RowError;
use App\Support\Blog\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A37 · Публикации блога. Ключ — slug (если нет — из title). body — HTML (очищается по белому списку)
 * или Markdown в body_markdown. links — «тип:slug» связанных сущностей (bank:halyk, card:kaspi-gold…),
 * tags — названия тегов, category — slug или название рубрики, author — e-mail или username сотрудника.
 */
class PostEntity extends Entity
{
    use ResolvesBank;

    public function key(): string
    {
        return 'posts';
    }

    public function label(): string
    {
        return 'Публикации';
    }

    public function description(): string
    {
        return 'Статьи и новости блога. Ключ — slug. body — HTML (h2–h4, p, ul/ol, blockquote, table, img, aside data-tone, div data-embed="bank|deposit|card|package|payment_system" data-slug) '
            .'или body_markdown — Markdown. status: draft | published; published_at в будущем — отложенная публикация. links: ["bank:halyk", "deposit:…"]. country: код страны или пусто — для всех.';
    }

    public function aliases(): array
    {
        return ['blog', 'news', 'статьи', 'новости', 'публикации', 'блог'];
    }

    public function relations(): array
    {
        return ['tags', 'links'];
    }

    public function fields(): array
    {
        return [
            'slug' => ['string', 'example' => 'kak-vybrat-vklad', 'rules' => 'max:191|alpha_dash'],
            'title' => ['string', 'desc' => 'заголовок (обязательно для новой)', 'example' => 'Как выбрать вклад в 2026 году', 'rules' => 'max:200', 'aliases' => ['заголовок']],
            'lead' => ['string', 'desc' => 'подзаголовок / анонс', 'rules' => 'max:400', 'aliases' => ['лид', 'анонс']],
            'body' => ['text', 'desc' => 'HTML тела', 'aliases' => ['текст', 'html']],
            'body_markdown' => ['text', 'desc' => 'тело в Markdown (вместо body)', 'aliases' => ['markdown', 'md']],
            'category' => ['ref', 'desc' => 'slug или название рубрики', 'example' => 'guides', 'aliases' => ['рубрика']],
            'kind' => ['enum', 'values' => array_keys(Post::KINDS), 'example' => 'guide', 'aliases' => ['формат']],
            'status' => ['enum', 'values' => array_keys(Post::STATUSES), 'example' => 'published', 'aliases' => ['статус']],
            'published_at' => ['string', 'desc' => 'дата и время публикации (ISO 8601)', 'example' => '2026-11-27 10:00', 'aliases' => ['дата']],
            'country' => ['ref', 'desc' => 'код страны; пусто — для всех стран', 'example' => 'KZ', 'aliases' => ['страна']],
            'author' => ['ref', 'desc' => 'e-mail или username автора', 'aliases' => ['автор']],
            'cover' => ['string', 'desc' => 'путь /uploads/… или https-адрес картинки', 'rules' => 'max:255', 'aliases' => ['обложка']],
            'cover_alt' => ['string', 'rules' => 'max:200'],
            'cover_caption' => ['string', 'rules' => 'max:200'],
            'is_featured' => ['bool', 'desc' => 'главный материал ленты'],
            'allow_comments' => ['bool'],
            'seo_title' => ['string', 'rules' => 'max:200'],
            'seo_description' => ['string', 'rules' => 'max:300'],
            'source_name' => ['string', 'rules' => 'max:120', 'aliases' => ['источник']],
            'source_url' => ['string', 'rules' => 'url|max:255'],
            'tags' => ['list', 'desc' => 'теги', 'example' => ['вклады', 'гайд'], 'aliases' => ['теги']],
            'links' => ['list', 'desc' => 'о ком материал: тип:slug', 'example' => ['bank:halyk-bank'], 'aliases' => ['связи']],
        ];
    }

    public function find(array $row, ImportContext $ctx): ?Model
    {
        if (isset($row['slug'])) {
            return Post::query()->where('slug', $row['slug'])->first();
        }
        if (! isset($row['title'])) {
            throw new RowError('нужен slug или title');
        }

        return Post::query()->where('slug', Str::limit(Str::slug($row['title'], '-', 'ru'), 120, ''))->first();
    }

    public function attributes(array $row, ImportContext $ctx, ?Model $existing): array
    {
        if (array_key_exists('country', $row)) {
            $row['country_id'] = $row['country'] ? $ctx->country($row['country'])->id : null;
        }
        if (array_key_exists('category', $row)) {
            $row['category_id'] = $row['category'] ? (PostCategory::query()->where('slug', $row['category'])->orWhere('name', $row['category'])->value('id')
                ?? throw new RowError("рубрика «{$row['category']}» не найдена")) : null;
        }
        if (isset($row['author'])) {
            $row['author_id'] = User::query()->where('email', $row['author'])->orWhere('username', $row['author'])->value('id')
                ?? throw new RowError("автор «{$row['author']}» не найден");
        }
        if (isset($row['body_markdown'])) {
            $row['body'] = Str::markdown($row['body_markdown'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        }
        if (array_key_exists('body', $row)) {
            $row['body'] = app(HtmlSanitizer::class)->clean($row['body']);
            $row['reading_minutes'] = Post::readingMinutes((new Post(['body' => $row['body']]))->plainText());
        }
        if (isset($row['published_at'])) {
            try {
                $row['published_at'] = Carbon::parse($row['published_at']);
            } catch (\Throwable) {
                throw new RowError('published_at: не понял дату');
            }
        }
        unset($row['country'], $row['category'], $row['author'], $row['body_markdown']);

        if (! $existing) {
            $row['title'] ?? throw new RowError('для новой публикации нужен title');
            $row['slug'] ??= self::uniqueSlugFor(Post::class, Str::limit(Str::slug($row['title'], '-', 'ru'), 120, ''));
            $row['status'] ??= 'draft';
            $row['kind'] ??= 'article';
            if (! array_key_exists('country_id', $row)) {
                $row['country_id'] = null;
            }
        }
        if (($row['status'] ?? $existing?->status) === 'published' && ! ($row['published_at'] ?? $existing?->published_at)) {
            $row['published_at'] = now();
        }

        return $row;
    }

    protected function syncRelations(Model $model, array $relations, ImportContext $ctx): array
    {
        /** @var Post $model */
        $changes = [];
        if (array_key_exists('tags', $relations)) {
            $before = $model->tags()->orderBy('name')->pluck('name')->all();
            $model->tags()->sync(Tag::idsFor($relations['tags'] ?? []));
            $after = $model->tags()->orderBy('name')->pluck('name')->all();
            $before === $after || $changes['tags'] = [implode(' | ', $before) ?: null, implode(' | ', $after) ?: null];
        }
        if (array_key_exists('links', $relations)) {
            $before = $model->linkKeys();
            $model->syncLinks((array) ($relations['links'] ?? []), $missing);
            if ($missing) {
                throw new RowError('не найдено: '.implode(', ', $missing));
            }
            $after = $model->linkKeys();
            sort($before);
            sort($after);
            $before === $after || $changes['links'] = [implode(' | ', $before) ?: null, implode(' | ', $after) ?: null];
        }

        return $changes;
    }

    public function newModel(): Model
    {
        return new Post;
    }

    public function rowLabel(array $row): string
    {
        return $row['slug'] ?? $row['title'] ?? '?';
    }

    public function exportQuery(array $filters): Builder
    {
        return Post::query()->with(['category', 'author', 'country', 'tags'])->orderByDesc('published_at')->orderByDesc('id')
            ->when($filters['country'] ?? null, fn ($q, $c) => $q->where(fn ($w) => $w->whereNull('country_id')->orWhereHas('country', fn ($x) => $x->where('code', strtoupper($c)))));
    }

    public function toRow(Model $m): array
    {
        /** @var Post $m */
        $row = $this->exportFields($m, ['body_markdown', 'tags', 'links', 'published_at']);

        return ['category' => $m->category?->slug, 'country' => $m->country?->code, 'author' => $m->author?->email]
            + $row + ['published_at' => $m->published_at?->format('Y-m-d H:i:s'), 'tags' => $m->tags->sortBy('name')->pluck('name')->values()->all(), 'links' => $m->linkKeys()];
    }
}
