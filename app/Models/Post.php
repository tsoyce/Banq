<?php

namespace App\Models;

use App\Support\CurrentCountry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

/**
 * A37 · Публикация блога: статья, новость, обзор, гайд, аналитика.
 * Видна на сайте, когда status = published и published_at ≤ сейчас (будущая дата — «запланирована»).
 * country_id = null — для всех стран. Связи с банками и продуктами — morph `postables` (LINK_TYPES).
 */
class Post extends Model
{
    public const KINDS = ['article' => 'Статья', 'news' => 'Новость', 'review' => 'Обзор', 'guide' => 'Гайд', 'analytics' => 'Аналитика'];

    public const STATUSES = ['draft' => 'Черновик', 'published' => 'Опубликовано'];

    /** Реакции: ключ → [эмодзи, подпись]. */
    public const REACTIONS = ['like' => ['❤️', 'Нравится'], 'fire' => ['🔥', 'Огонь'], 'think' => ['🤔', 'Есть вопросы'], 'money' => ['💰', 'Полезно']];

    /** Короткие имена связанных сущностей (morph map, общая с favorites). */
    public const LINK_TYPES = [
        'bank' => Bank::class, 'deposit' => DepositProduct::class, 'card' => Card::class,
        'package' => ServicePackage::class, 'payment_system' => PaymentSystem::class, 'card_tier' => CardTier::class,
    ];

    public const LINK_LABELS = ['bank' => 'Банк', 'deposit' => 'Вклад', 'card' => 'Карта', 'package' => 'Пакет услуг', 'payment_system' => 'Платёжная система', 'card_tier' => 'Уровень карты'];

    protected $guarded = ['id'];

    protected $casts = [
        'published_at' => 'datetime', 'is_featured' => 'boolean', 'in_feed' => 'boolean', 'nav_sort' => 'integer', 'allow_comments' => 'boolean',
        'views' => 'integer', 'reading_minutes' => 'integer', 'meta' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * A42 · Публикации и страницы живут в одной таблице (type). Post видит публикации и страницы,
     * которым включено «показывать в ленте»; Page — только страницы.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('type', function (Builder $q) {
            static::class === Page::class
                ? $q->where($q->qualifyColumn('type'), 'page')
                : $q->where(fn ($w) => $w->where($q->qualifyColumn('type'), 'post')->orWhere($q->qualifyColumn('in_feed'), true));
        });
    }

    public function isPage(): bool
    {
        return $this->type === 'page';
    }

    // ── Связи ──────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    public function banks(): MorphToMany
    {
        return $this->morphedByMany(Bank::class, 'postable')->withPivot('is_primary');
    }

    public function deposits(): MorphToMany
    {
        return $this->morphedByMany(DepositProduct::class, 'postable')->withPivot('is_primary');
    }

    public function cards(): MorphToMany
    {
        return $this->morphedByMany(Card::class, 'postable')->withPivot('is_primary');
    }

    public function packages(): MorphToMany
    {
        return $this->morphedByMany(ServicePackage::class, 'postable')->withPivot('is_primary');
    }

    public function paymentSystems(): MorphToMany
    {
        return $this->morphedByMany(PaymentSystem::class, 'postable')->withPivot('is_primary');
    }

    public function cardTiers(): MorphToMany
    {
        return $this->morphedByMany(CardTier::class, 'postable')->withPivot('is_primary');
    }

    /** Отношение по короткому типу связи. */
    public function linkRelation(string $type): MorphToMany
    {
        return match ($type) {
            'bank' => $this->banks(), 'deposit' => $this->deposits(), 'card' => $this->cards(),
            'package' => $this->packages(), 'payment_system' => $this->paymentSystems(), 'card_tier' => $this->cardTiers(),
        };
    }

    // ── Выборки ────────────────────────────────────────────

    /** Опубликовано и дата уже наступила. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** Для текущей страны витрины: общие (country_id = null) + своей страны. */
    public function scopeForCountry(Builder $q, Country|int|null $country = null): Builder
    {
        $id = $country instanceof Country ? $country->id : ($country ?? app(CurrentCountry::class)->get()?->id);

        return $q->where(fn ($w) => $w->whereNull('country_id')->when($id, fn ($x) => $x->orWhere('country_id', $id)));
    }

    /** Лента: опубликованное по стране, свежее сверху. */
    public function scopeFeed(Builder $q): Builder
    {
        return $q->published()->forCountry()->with(['category', 'author'])->orderByDesc('published_at')->orderByDesc('id');
    }

    /** Публикации, где упомянута сущность. */
    public function scopeAbout(Builder $q, Model $model): Builder
    {
        $type = array_search($model::class, self::LINK_TYPES, true);

        return $q->whereExists(fn ($e) => $e->from('postables')->whereColumn('postables.post_id', 'posts.id')
            ->where('postable_type', $type)->where('postable_id', $model->getKey()));
    }

    // ── Состояние и подписи ────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at && $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->status === 'published' && $this->published_at && $this->published_at->isFuture();
    }

    /** draft | scheduled | published — для админки. */
    public function state(): string
    {
        return $this->isScheduled() ? 'scheduled' : ($this->isPublished() ? 'published' : 'draft');
    }

    public function stateLabel(): string
    {
        return ['draft' => 'Черновик', 'scheduled' => 'Запланировано', 'published' => 'Опубликовано'][$this->state()];
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function coverUrl(): ?string
    {
        if (! $this->cover) {
            return null;
        }

        return Str::startsWith($this->cover, ['http://', 'https://']) ? $this->cover : asset(ltrim($this->cover, '/'));
    }

    public function url(): string
    {
        return $this->type === 'page' ? url('/'.$this->slug) : route('blog.show', $this->slug); // A42 · страница — по адресу /{slug}
    }

    public function metaTitle(): string
    {
        return $this->seo_title ?: $this->title;
    }

    public function metaDescription(): string
    {
        return $this->seo_description ?: ($this->lead ?: Str::limit(trim(strip_tags((string) $this->body)), 180));
    }

    /** Чистый текст тела — для поиска, RSS, оценки времени чтения. */
    public function plainText(): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) $this->body)), ENT_QUOTES | ENT_HTML5)));
    }

    /** Время чтения: ~180 слов в минуту (русский текст), минимум 1. */
    public static function readingMinutes(string $text): int
    {
        return max(1, (int) round(count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)) / 180));
    }

    /** Связи, отмеченные редактором (для формы и экспорта): ['bank:halyk', 'card:kaspi-gold', …]. */
    public function linkKeys(): array
    {
        $out = [];
        foreach (array_keys(self::LINK_TYPES) as $type) {
            foreach ($this->linkRelation($type)->wherePivot('is_primary', true)->get() as $m) {
                $out[] = $type.':'.$m->slug;
            }
        }

        return $out;
    }

    /** Все связанные сущности (отмеченные + каскадные), сгруппированные по типу: [type => Collection]. */
    public function linked(): array
    {
        $out = [];
        foreach (array_keys(self::LINK_TYPES) as $type) {
            $items = $this->linkRelation($type)->get();
            if ($items->isNotEmpty()) {
                $out[$type] = $items;
            }
        }

        return $out;
    }

    /** Найти сущность по «тип:slug». */
    public static function resolveLink(string $key): ?Model
    {
        [$type, $slug] = array_pad(explode(':', trim($key), 2), 2, null);
        $class = self::LINK_TYPES[$type] ?? null;

        return $class && $slug ? $class::query()->where('slug', $slug)->first() : null;
    }

    /**
     * Каскад связей: о чём ещё автоматически говорит материал про сущность.
     * Карта → банк, пакеты с этой картой, платёжные системы и уровни; вклад → банк;
     * пакет → банк и карты пакета; уровень карты → платёжная система.
     *
     * @return list<array{0: string, 1: Model}>
     */
    public static function cascade(string $type, Model $m): array
    {
        $out = [];
        $add = function (string $t, iterable $models) use (&$out) {
            foreach ($models as $x) {
                $x && $out[] = [$t, $x];
            }
        };
        match ($type) {
            'card' => [$add('bank', [$m->bank]), $add('package', $m->packages), $add('payment_system', $m->paymentSystems), $add('card_tier', $m->tiers)],
            'deposit' => $add('bank', [$m->bank]),
            'package' => [$add('bank', [$m->bank]), $add('card', $m->cards)],
            'card_tier' => $add('payment_system', [$m->paymentSystem]),
            default => null,
        };

        return $out;
    }

    /** Записать связи из списка «тип:slug» вместе с каскадом; неизвестные — в $missing. */
    public function syncLinks(array $keys, ?array &$missing = null): void
    {
        $missing = [];
        $byType = array_fill_keys(array_keys(self::LINK_TYPES), []);
        $derived = [];
        foreach (array_unique(array_filter(array_map('trim', $keys))) as $key) {
            $type = strtok($key, ':');
            if (! ($m = self::resolveLink($key))) {
                $missing[] = $key;

                continue;
            }
            $byType[$type][$m->getKey()] = ['is_primary' => true];
            array_push($derived, ...self::cascade($type, $m));
        }
        foreach ($derived as [$type, $m]) {
            $byType[$type][$m->getKey()] ??= ['is_primary' => false];
        }
        foreach ($byType as $type => $ids) {
            $this->linkRelation($type)->sync($ids);
        }
    }

    /** Счётчики реакций [kind => n] (одним запросом). */
    public function reactionCounts(): array
    {
        $counts = $this->reactions()->selectRaw('kind, count(*) as n')->groupBy('kind')->pluck('n', 'kind')->all();

        return array_map(fn ($k) => (int) ($counts[$k] ?? 0), array_combine(array_keys(self::REACTIONS), array_keys(self::REACTIONS)));
    }
}
