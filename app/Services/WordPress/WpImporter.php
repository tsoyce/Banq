<?php

namespace App\Services\WordPress;

use App\Admin\Resources\PageResource;
use App\Models\Bank;
use App\Models\Page;
use App\Models\PaymentSystem;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Redirect;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A43 · Перенос сайта WordPress в журнал Banq.
 *
 *   scan(url | snapshot)  — снимок через REST API (WpSource) или из файла (JSON-снимок, WXR) + план и проверка безопасности.
 *                           Ничего не пишет в базу: это «пробный прогон». План и снимок — storage/app/wordpress/.
 *   step(budget)          — один шаг переноса по плану: рубрики → записи (с картинками) → редиректы.
 *                           Каждый шаг укладывается в отведённые секунды (админка вызывает шаги по очереди, CLI — циклом).
 *   report()              — итог: что создано/обновлено/пропущено, отклонённые файлы, находки проверки.
 *
 * Сопоставление:
 *   посты → публикации ленты (/blog/{slug}), страницы → страницы (/{slug}, не в ленте);
 *   верхние рубрики WordPress → рубрики журнала; вложенные рубрики и метки → теги;
 *   рубрика/метка с именем банка или платёжной системы из каталога → связь «о ком материал» (с каскадом);
 *   автор — по имени среди сотрудников, иначе первый администратор; даты — date_gmt; обложка — featured_media;
 *   meta.wp — откуда пришла запись (id, тип, адрес), meta.wp_key — для повторного запуска (обновление вместо дубля).
 * Служебные страницы (клоны демо-главной темы, «Coming soon», страницы плагинов из одного шорткода) пропускаются.
 */
class WpImporter
{
    public const DIR = 'wordpress';

    /** Служебные страницы WordPress-тем и плагинов. */
    public const SKIP_PAGE_SLUGS = '/^(homepage|home|coming-soon|maintenance|sample-page|cart|cart-page|checkout|checkout-page|my-account|iam|logout|login|register|profile|members|followers|following|shop)(-\d+)?$/';

    private array $state = [];

    private array $snapshot = [];

    private float $deadline = INF;

    /** За шаг сделано хоть что-то (файл или запись) — только тогда шаг может уступить по времени. */
    private bool $progressed = false;

    /** @var array<string, string>|null нормализованное имя → «bank:slug» / «payment_system:slug» */
    private ?array $entityIndex = null;

    /** @var array<string, string>|null старый адрес → новый (для ссылок внутри текстов) */
    private ?array $linkMap = null;

    /** A52 · запасная копия старого сайта (https://old.tsoy.in): откуда брать файлы, которых нет на основном. */
    private ?string $mirror = null;

    /** @var array<int, ?int> автор WordPress → пользователь */
    private array $authors = [];

    public function __construct(private WpSource $source, private WpMedia $media) {}

    // ── Пробный прогон ─────────────────────────────────────

    /** Снять снимок (или взять готовый), построить план. Базу не трогает. */
    public function scan(?string $url = null, ?array $snapshot = null, array $options = []): array
    {
        $snapshot ??= $this->source->snapshot($url ?? throw new RuntimeException('Нужен адрес сайта или файл снимка.'));
        $this->snapshot = $snapshot;
        $plan = $this->plan($snapshot, $options);

        // A44 · повторный пробный прогон того же сайта не качает заново уже перенесённые файлы
        $this->load();
        $prev = $this->state;
        $source = $snapshot['site']['url'] ?? $url;
        $keep = ($prev['source'] ?? null) === $source
            ? array_filter($prev['media'] ?? [], fn ($m) => ($m['ok'] ?? false) && is_file(public_path(ltrim($m['path'], '/'))))
            : [];
        File::ensureDirectoryExists(storage_path('app/'.self::DIR));
        file_put_contents($this->path('snapshot.json'), json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->state = [
            'source' => $snapshot['site']['url'] ?? $url, 'scanned_at' => now()->toIso8601String(), 'options' => $options,
            'phase' => 'scanned', 'plan' => $plan, 'media' => $keep, 'done' => [], 'results' => [], 'started_at' => null, 'finished_at' => null,
        ];
        $this->save();

        return $this->state;
    }

    public function plan(array $snap, array $options = []): array
    {
        $own = $this->ownHosts($snap);
        $content = new WpContent($own);
        $cats = collect($snap['categories'] ?? [])->keyBy('id');
        $tagsById = collect($snap['tags'] ?? [])->keyBy('id');
        $root = function (int $id) use ($cats) {
            for ($i = 0, $c = $cats[$id] ?? null; $c && ($c['parent'] ?? 0) && $i < 10; $i++) {
                $c = $cats[$c['parent']] ?? $c;
            }

            return $c;
        };
        $isUncategorized = fn ($c) => in_array(rawurldecode($c['slug'] ?? ''), ['uncategorized', 'без-рубрики'], true);

        // Рубрики журнала — из верхних рубрик WordPress
        $categories = [];
        foreach ($cats as $c) {
            if (($c['parent'] ?? 0) === 0 && ! $isUncategorized($c)) {
                $name = $this->text($c['name']);
                $categories[$c['id']] = ['wp_id' => $c['id'], 'name' => $name, 'slug' => Str::limit(Str::slug($name, '-', 'ru'), 80, '') ?: 'rubric-'.$c['id'], 'link' => $c['link'] ?? null, 'count' => $c['count'] ?? 0];
            }
        }

        $taken = [];
        $items = [];
        foreach (['post' => $snap['posts'] ?? [], 'page' => $snap['pages'] ?? []] as $type => $list) {
            foreach ($list as $wp) {
                $title = $this->text($wp['title']['rendered'] ?? '') ?: 'Без заголовка';
                $oldSlug = rawurldecode((string) ($wp['slug'] ?? ''));
                $html = (string) ($wp['content']['rendered'] ?? '');
                $skip = $type === 'page' ? $this->skipReason($oldSlug, $title, $html) : null;
                if (($wp['status'] ?? 'publish') !== 'publish') {
                    $skip = 'не опубликовано на WordPress ('.$wp['status'].')';
                }

                $slug = $this->slugFor($type, $oldSlug, $title, $wp['id'], $taken);
                $taken[$slug] = true;

                // рубрика — верхняя рубрика, к которой относится больше всего рубрик записи
                $catIds = array_values(array_filter((array) ($wp['categories'] ?? []), fn ($id) => isset($cats[$id]) && ! $isUncategorized($cats[$id])));
                $rootCount = [];
                foreach ($catIds as $id) {
                    if ($r = $root($id)) {
                        $rootCount[$r['id']] = ($rootCount[$r['id']] ?? 0) + 1;
                    }
                }
                arsort($rootCount);
                $category = $type === 'post' ? (array_key_first($rootCount)) : null;
                $tagNames = collect($catIds)->reject(fn ($id) => $id === $category)->map(fn ($id) => $this->text($cats[$id]['name']))
                    ->merge(collect((array) ($wp['tags'] ?? []))->map(fn ($id) => isset($tagsById[$id]) ? $this->text($tagsById[$id]['name']) : null))
                    ->filter()->unique()->values()->all();

                $items[] = [
                    'key' => $type.':'.$wp['id'], 'type' => $type, 'wp_id' => $wp['id'], 'title' => $title,
                    'old_url' => $wp['link'] ?? null, 'old_path' => Redirect::normalize((string) ($wp['link'] ?? '/'.$oldSlug)),
                    'slug' => $slug, 'new_url' => $type === 'page' ? '/'.$slug : '/blog/'.$slug,
                    'date' => $wp['date_gmt'] ?? $wp['date'] ?? null, 'category' => $category, 'tags' => $tagNames,
                    'links' => $type === 'post' ? $this->entityKeys([...$tagNames, ...($category ? [$categories[$category]['name'] ?? ''] : [])]) : [],
                    'skip' => $skip, 'images' => substr_count(strtolower($html), '<img'), 'chars' => mb_strlen(trim(strip_tags($html))),
                    'findings' => $skip ? [] : $content->scan($html),
                    'exists' => (bool) $this->existing($type, (int) $wp['id']),
                ];
            }
        }

        $mediaTotal = count($snap['media'] ?? []);
        $used = collect($items)->whereNull('skip')->sum('images');

        return [
            'site' => $snap['site'] ?? [], 'own_hosts' => $own, 'categories' => $categories, 'items' => $items,
            'counts' => [
                'posts' => collect($items)->where('type', 'post')->whereNull('skip')->count(),
                'pages' => collect($items)->where('type', 'page')->whereNull('skip')->count(),
                'skipped' => collect($items)->whereNotNull('skip')->count(),
                'update' => collect($items)->whereNull('skip')->where('exists', true)->count(),
                'media_total' => $mediaTotal, 'images_in_text' => $used, 'tags' => count($snap['tags'] ?? []),
                'categories' => count($categories), 'comments' => (int) ($snap['comments_total'] ?? 0),
                'danger' => collect($items)->sum(fn ($i) => collect($i['findings'])->where('level', 'danger')->count()),
                'warn' => collect($items)->sum(fn ($i) => collect($i['findings'])->where('level', 'warn')->count()),
                'linked' => collect($items)->filter(fn ($i) => $i['links'])->count(),
            ],
        ];
    }

    /** Причина пропуска страницы или null. */
    public function skipReason(string $slug, string $title, string $html): ?string
    {
        if (preg_match(self::SKIP_PAGE_SLUGS, $slug)) {
            return 'служебная страница темы или плагина';
        }
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/\[[^\]]{1,200}\]/u', '', $html)), ENT_QUOTES | ENT_HTML5)));
        if (mb_strlen($text) < 40 && ! preg_match('/<(img|iframe|audio|video|table)\b/i', $html)) {
            return $text === '' ? 'пустая (только шорткод плагина)' : 'почти пустая страница';
        }

        return null;
    }

    // ── Перенос по шагам ──────────────────────────────────

    /** Один шаг: не дольше $budget секунд. Возвращает прогресс. */
    public function step(float $budget = 20): array
    {
        $this->load();
        if (! $this->state) {
            throw new RuntimeException('Сначала пробный прогон (scan).');
        }
        if ($this->state['phase'] === 'done') {
            return $this->progress();
        }
        @set_time_limit((int) max(120, $budget * 4));
        $this->deadline = microtime(true) + $budget;
        $this->progressed = false;
        $this->state['started_at'] ??= now()->toIso8601String();
        $this->mirror = ($this->state['options']['mirror'] ?? null) ?: null; // A52

        try {
            if (in_array($this->state['phase'], ['scanned', 'taxonomy'], true)) {
                $this->state['phase'] = 'taxonomy';
                $this->importCategories();
                $this->state['phase'] = 'items';
            }
            if ($this->state['phase'] === 'items') {
                foreach ($this->state['plan']['items'] as $item) {
                    if ($item['skip'] || in_array($item['key'], $this->state['done'], true)) {
                        continue;
                    }
                    $this->checkTime();
                    $this->state['current'] = $item['title'];
                    $this->state['results'][$item['key']] = $this->importItem($item);
                    $this->state['done'][] = $item['key'];
                    $this->progressed = true;
                    $this->save();
                }
                $this->state['phase'] = 'redirects';
            }
            if ($this->state['phase'] === 'redirects') {
                $this->state['redirects'] = $this->importRedirects();
                $this->state['phase'] = 'done';
                $this->state['finished_at'] = now()->toIso8601String();
                $this->state['current'] = null;
                Cache::forget('pages.nav');
            }
        } catch (WpImportPaused) {
            // время шага вышло — продолжим со следующего
        }
        $this->save();

        return $this->progress();
    }

    public function progress(): array
    {
        $this->state ?: $this->load();
        $total = collect($this->state['plan']['items'] ?? [])->whereNull('skip')->count();

        return [
            'phase' => $this->state['phase'] ?? null, 'done' => count($this->state['done'] ?? []), 'total' => $total,
            'media' => count($this->state['media'] ?? []), 'current' => $this->state['current'] ?? null,
            'finished' => ($this->state['phase'] ?? null) === 'done',
        ];
    }

    /** Состояние с диска (объект может жить дольше запроса — не доверяем памяти). */
    public function state(): array
    {
        $this->load();

        return $this->state;
    }

    /** Итоговый отчёт. */
    public function report(): array
    {
        $s = $this->state();
        if (! $s) {
            return [];
        }
        $items = collect($s['plan']['items'] ?? []);
        $results = $s['results'] ?? [];
        $media = collect($s['media'] ?? []);

        return [
            'source' => $s['source'] ?? null, 'scanned_at' => $s['scanned_at'] ?? null, 'finished_at' => $s['finished_at'] ?? null, 'phase' => $s['phase'] ?? null,
            'counts' => ($s['plan']['counts'] ?? []) + [
                'created' => collect($results)->where('action', 'created')->count(),
                'updated' => collect($results)->where('action', 'updated')->count(),
                'kept' => collect($results)->where('action', 'kept')->count(),
                'media_ok' => $media->where('ok', true)->count(),
                'media_rejected' => $media->where('ok', false)->count(),
                'media_bytes' => (int) $media->where('ok', true)->sum('bytes'),
                'redirects' => (int) ($s['redirects'] ?? 0),
            ],
            'items' => $items->map(fn ($i) => $i + ['result' => $results[$i['key']] ?? null])->values()->all(),
            'media_rejected' => $media->where('ok', false)->map(fn ($m, $url) => ['url' => $url, 'reason' => $m['reason'], 'danger' => $m['danger'] ?? false])->values()->all(),
            'findings' => $items->flatMap(fn ($i) => collect(($results[$i['key']]['findings'] ?? null) ?? $i['findings'])->map(fn ($f) => $f + ['title' => $i['title'], 'url' => $i['new_url'], 'old_url' => $i['old_url']]))
                ->sortBy(fn ($f) => ['danger' => 0, 'warn' => 1, 'info' => 2][$f['level']] ?? 3)->values()->all(),
            'unused_media' => max(0, ($s['plan']['counts']['media_total'] ?? 0) - $media->where('ok', true)->filter(fn ($m) => $m['wp_id'] ?? null)->count()),
        ];
    }

    public function reset(): void
    {
        File::delete([$this->path('state.json'), $this->path('snapshot.json')]);
        $this->state = [];
    }

    // ── A52 · Дотянуть пропавшие файлы с запасной копии ─────

    /**
     * Записи, в которых при переносе пропали свои картинки (файла не было на основном сайте), переносятся заново —
     * файлы берутся с запасной копии сайта ($mirror, например https://old.tsoy.in) по тому же пути /wp-content/uploads/….
     * Остальные записи не трогаются (их правки в журнале сохраняются). Шагами по времени, как step().
     */
    public function fillMissing(string $mirror, float $budget = 20): array
    {
        $this->load();
        if (($this->state['phase'] ?? null) !== 'done') {
            throw new RuntimeException('Сначала завершите перенос.');
        }
        $mirror = rtrim($mirror, '/');
        if (! preg_match('#^https?://[a-z0-9.-]+(:\d+)?$#i', $mirror)) {
            throw new RuntimeException('Адрес копии — только сайт, например https://old.tsoy.in');
        }
        $this->mirror = $mirror;
        $this->snapshot ?: $this->loadSnapshot();
        @set_time_limit((int) max(120, $budget * 4));
        $this->deadline = microtime(true) + $budget;
        $this->progressed = false;

        $fill = $this->state['fill'] ?? [];
        if (($fill['mirror'] ?? null) !== $mirror || ($fill['finished'] ?? false)) {
            $todo = collect($this->state['results'] ?? [])
                ->filter(fn ($r) => collect($r['missing'] ?? [])->contains(fn ($u) => $this->isOwnUpload(html_entity_decode($u, ENT_QUOTES | ENT_HTML5))))
                ->keys()->values()->all();
            $fill = ['mirror' => $mirror, 'todo' => $todo, 'done' => [], 'fixed' => 0, 'started_at' => now()->toIso8601String(), 'finished' => false];
        }
        $items = collect($this->state['plan']['items'] ?? [])->keyBy('key');

        try {
            foreach ($fill['todo'] as $key) {
                if (in_array($key, $fill['done'], true) || ! $items->has($key)) {
                    continue;
                }
                $this->checkTime();
                $before = count($this->state['results'][$key]['missing'] ?? []);
                $this->state['current'] = $items[$key]['title'];
                $result = $this->importItem($items[$key], force: true);
                $this->state['results'][$key] = $result;
                $fill['fixed'] += max(0, $before - count($result['missing'] ?? []));
                $fill['done'][] = $key;
                $this->progressed = true;
                $this->state['fill'] = $fill;
                $this->save();
            }
            $fill['finished'] = true;
            $fill['finished_at'] = now()->toIso8601String();
            $this->state['current'] = null;
        } catch (WpImportPaused) {
            // продолжим следующим шагом
        }
        $this->state['fill'] = $fill;
        $this->save();

        return [
            'mirror' => $mirror, 'done' => count($fill['done']), 'total' => count($fill['todo']), 'fixed' => $fill['fixed'], 'finished' => $fill['finished'],
            'current' => $this->state['current'] ?? null,
            'still_missing' => $fill['finished'] ? collect($fill['todo'])->flatMap(fn ($k) => $this->state['results'][$k]['missing'] ?? [])->filter(fn ($u) => $this->isOwnUpload(html_entity_decode($u, ENT_QUOTES | ENT_HTML5)))->values()->all() : [],
        ];
    }

    /** Тот же путь на запасной копии. */
    private function onMirror(string $url): string
    {
        $url = str_starts_with($url, '//') ? 'https:'.$url : $url;
        $path = (string) parse_url($url, PHP_URL_PATH);

        return $this->mirror.implode('/', array_map(fn ($seg) => rawurlencode(rawurldecode($seg)), explode('/', $path)));
    }

    // ── Внутреннее ─────────────────────────────────────────

    private function importCategories(): void
    {
        foreach ($this->state['plan']['categories'] as $wpId => $c) {
            $cat = PostCategory::query()->where('slug', $c['slug'])->first()
                ?? PostCategory::query()->whereRaw('lower(name) = ?', [mb_strtolower($c['name'])])->first()
                ?? PostCategory::query()->create(['slug' => $c['slug'], 'name' => $c['name'], 'is_active' => true, 'sort' => 600]);
            $this->state['plan']['categories'][$wpId]['id'] = $cat->id;
            $this->state['plan']['categories'][$wpId]['slug'] = $cat->slug;
        }
        $this->save();
    }

    private function importItem(array $item, bool $force = false): array
    {
        $this->snapshot ?: $this->loadSnapshot();
        $wp = collect($this->snapshot[$item['type'] === 'page' ? 'pages' : 'posts'] ?? [])->firstWhere('id', $item['wp_id']);
        if (! $wp) {
            return ['action' => 'missing', 'error' => 'нет в снимке'];
        }
        $existing = $this->existing($item['type'], $item['wp_id']);
        if ($existing && ! $force && ! ($this->state['options']['update'] ?? false)) {
            return ['action' => 'kept', 'id' => $existing->id, 'url' => $existing->url(), 'findings' => $item['findings']];
        }

        $content = new WpContent(
            $this->state['plan']['own_hosts'],
            media: fn (string $url, ?int $att) => $this->localMedia($url, $att),
            link: fn (string $url) => $this->newUrlFor($url),
            mediaById: fn (int $id) => collect($this->snapshot['media'] ?? [])->firstWhere('id', $id)['source_url'] ?? null,
            localizeExternalImages: (bool) ($this->state['options']['external_images'] ?? true),
        );
        $body = $content->transform((string) ($wp['content']['rendered'] ?? ''));

        $cover = null;
        $coverAlt = null;
        if (($wp['featured_media'] ?? 0) && ($m = collect($this->snapshot['media'] ?? [])->firstWhere('id', $wp['featured_media']))) {
            $cover = $this->localMedia($m['source_url'], $m['id']);
            $coverAlt = Str::limit($this->text($m['alt_text'] ?? '') ?: $this->text($m['title']['rendered'] ?? ''), 195, '');
        }
        $date = $item['date'] ? Carbon::parse($item['date'], 'UTC')->setTimezone(config('app.timezone')) : now();

        $model = $existing ?? ($item['type'] === 'page' ? new Page : new Post);
        $model->fill([
            'type' => $item['type'], 'in_feed' => false, 'title' => Str::limit($item['title'], 197), 'slug' => $existing?->slug ?? $item['slug'],
            'lead' => $this->lead($wp), 'body' => $body, 'status' => 'published', 'published_at' => $date,
            'kind' => 'article', 'author_id' => $this->authorId($wp['author'] ?? null),
            'category_id' => $item['category'] ? ($this->state['plan']['categories'][$item['category']]['id'] ?? null) : null,
            'cover' => $cover ?? $existing?->cover, 'cover_alt' => $coverAlt ?: $existing?->cover_alt,
            'allow_comments' => $item['type'] === 'post' && ($wp['comment_status'] ?? 'open') === 'open',
            'country_id' => null, 'is_featured' => false,
            'meta' => array_merge((array) ($existing?->meta ?? []), [
                'wp_key' => $item['key'],
                'wp' => ['id' => $item['wp_id'], 'type' => $item['type'], 'link' => $item['old_url'], 'modified' => $wp['modified_gmt'] ?? null, 'imported_at' => now()->toIso8601String()],
            ]),
        ]);
        $model->reading_minutes = Post::readingMinutes($model->plainText());
        $model->timestamps = false;
        $model->created_at ??= $date;
        $model->updated_at = isset($wp['modified_gmt']) ? Carbon::parse($wp['modified_gmt'], 'UTC')->setTimezone(config('app.timezone')) : $date;
        $model->save();

        if ($item['type'] === 'post') {
            $model->tags()->sync(Tag::idsFor($item['tags']));
            $model->syncLinks($item['links']);
        }

        return ['action' => $existing ? 'updated' : 'created', 'id' => $model->id, 'url' => $model->url(),
            'findings' => $content->findings, 'missing' => array_values(array_unique($content->missing))];
    }

    /** Локальная копия файла WordPress (с кешем в состоянии). Оригинал вместо уменьшенной копии «-1024x576». */
    private function localMedia(string $url, ?int $attachment): ?string
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
        // A44 · Jetpack Photon: i2.wp.com/tsoy.in/wp-content/… → сам файл на своём сайте
        if (preg_match('#^(?:https?:)?//i\d\.wp\.com/([^/?]+)(/[^?]*)#', $url, $jp) && in_array(strtolower($jp[1]), $this->state['plan']['own_hosts'] ?? [], true)) {
            $url = 'https://'.$jp[1].$jp[2];
        }
        $mediaList = collect($this->snapshot['media'] ?? []);
        $orig = null;
        if ($attachment && ($m = $mediaList->firstWhere('id', $attachment)) && $this->sameUpload($m['source_url'], $url)) {
            $orig = $m;
        }
        if (! $orig && preg_match('#^(.*)-\d{2,5}x\d{2,5}(\.\w{3,4})$#', strtok($url, '?'), $mm)) {
            $orig = $mediaList->first(fn ($m) => strtok($m['source_url'], '?') === $mm[1].$mm[2] || preg_replace('#^https?:#', '', strtok($m['source_url'], '?')) === preg_replace('#^https?:#', '', $mm[1].$mm[2]));
        }
        $orig ??= $mediaList->first(fn ($m) => preg_replace('#^https?:#', '', strtok($m['source_url'], '?')) === preg_replace('#^https?:#', '', strtok($url, '?')));
        $orig ??= $this->renamedOriginal($mediaList, $url);
        $key = $orig['source_url'] ?? $url;

        if (! isset($this->state['media'][$key])) {
            $this->checkTime();
            $r = $this->media->fetch($key);
            $r['wp_id'] = $orig['id'] ?? null;
            $r['variants'] = [];
            $this->state['media'][$key] = $r;
            $this->progressed = true;
        }
        // A52 · на основном сайте файла нет — пробуем ту же дорожку на запасной копии (те же проверки WpMedia: сигнатура, код внутри, EXIF прочь)
        $entry = $this->state['media'][$key];
        if (! $entry['ok'] && $this->mirror && ! ($entry['danger'] ?? false) && $this->isOwnUpload($key) && ! in_array($this->mirror, $entry['mirror_tried'] ?? [], true)) {
            $this->checkTime();
            foreach (array_unique([$key, $url]) as $try) {
                if (! $this->isOwnUpload($try)) {
                    continue;
                }
                $r = $this->media->fetch($this->onMirror($try), $key);
                if ($r['ok'] || ($r['danger'] ?? false)) {
                    break;
                }
            }
            $this->state['media'][$key] = ($r['ok'] || ($r['danger'] ?? false))
                ? $r + ['wp_id' => $entry['wp_id'] ?? null, 'variants' => $entry['variants'] ?? [], 'from' => $this->mirror]
                : $entry + ['mirror_tried' => [...($entry['mirror_tried'] ?? []), $this->mirror]];
            $this->progressed = true;
        }
        if ($key !== $url && ! in_array($url, $this->state['media'][$key]['variants'] ?? [], true)) {
            $this->state['media'][$key]['variants'][] = $url;
        }

        return $this->state['media'][$key]['ok'] ? $this->state['media'][$key]['path'] : null;
    }

    /**
     * A44 · WordPress переименовал оригинал (большие фото → «-scaled», повторная загрузка → «-scaled-1», поворот → «-e1536…»),
     * а в тексте осталась ссылка на старую копию «-1024x768», которой на сервере уже нет. Ищем файл с той же основой имени:
     * сначала в той же папке ГГГГ/ММ, иначе — только если такой файл в библиотеке один.
     */
    private function renamedOriginal(Collection $mediaList, string $url): ?array
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (! $this->isOwnUpload($url) || ! preg_match('#/uploads/(\d{4}/\d{2})/#', $path, $dir)) {
            return null;
        }
        $stem = self::uploadStem($path);
        $same = $mediaList->filter(fn ($m) => $this->isOwnUpload($m['source_url']) && self::uploadStem((string) parse_url($m['source_url'], PHP_URL_PATH)) === $stem);
        $inDir = $same->filter(fn ($m) => str_contains($m['source_url'], '/uploads/'.$dir[1].'/'));

        return $inDir->first() ?? ($same->count() === 1 ? $same->first() : null);
    }

    /** Основа имени загрузки: без расширения, «-1024x768», «-scaled(-N)», «-e1536720574839». */
    public static function uploadStem(string $path): string
    {
        $name = strtolower(preg_replace('#\.\w{2,4}$#', '', basename($path)));
        do {
            $before = $name;
            $name = preg_replace(['#-\d{2,5}x\d{2,5}$#', '#-scaled(?:-\d+)?$#', '#-e\d{10,13}$#'], '', $name);
        } while ($name !== $before);

        return $name;
    }

    private function isOwnUpload(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_contains($url, '/wp-content/uploads/') && ($host === '' || in_array($host, $this->state['plan']['own_hosts'] ?? [], true));
    }

    private function sameUpload(string $original, string $variant): bool
    {
        $a = preg_replace('#\.\w+$#', '', strtok($original, '?'));
        $b = preg_replace('#-\d{2,5}x\d{2,5}$#', '', preg_replace('#\.\w+$#', '', strtok($variant, '?')));

        return preg_replace('#^https?:#', '', $a) === preg_replace('#^https?:#', '', $b) || str_contains($b, basename($a));
    }

    /** Новый адрес для ссылки на старый сайт (запись, страница, рубрика, метка) или null. */
    private function newUrlFor(string $url): ?string
    {
        $key = Redirect::normalize($url);
        $this->linkMap ??= collect($this->redirectMap())->mapWithKeys(fn ($to, $from) => [Redirect::normalize($from) => $to])->all();
        $hash = parse_url($url, PHP_URL_FRAGMENT);

        return isset($this->linkMap[$key]) ? $this->linkMap[$key].($hash ? '#'.$hash : '') : null;
    }

    /** Старый адрес → новый: записи, страницы, рубрики, метки, автор, лента, короткие ссылки ?p=. */
    private function redirectMap(): array
    {
        $plan = $this->state['plan'];
        $this->snapshot ?: $this->loadSnapshot();
        $map = [];
        foreach ($plan['items'] as $i) {
            if ($i['skip']) {
                continue;
            }
            $map[$i['old_path']] = $i['new_url'];
            $map['/?'.($i['type'] === 'page' ? 'page_id' : 'p').'='.$i['wp_id']] = $i['new_url'];
        }
        $cats = collect($this->snapshot['categories'] ?? []);
        foreach ($cats as $c) {
            if (empty($c['link'])) {
                continue;
            }
            $map[$c['link']] = isset($plan['categories'][$c['id']]) ? '/blog/c/'.$plan['categories'][$c['id']]['slug'] : '/blog/tag/'.(Str::slug($this->text($c['name']), '-', 'ru') ?: 'tag');
            $map['/?cat='.$c['id']] = $map[$c['link']];
        }
        foreach ($this->snapshot['tags'] ?? [] as $t) {
            if (! empty($t['link'])) {
                $map[$t['link']] = '/blog/tag/'.(Str::slug($this->text($t['name']), '-', 'ru') ?: 'tag');
            }
        }
        foreach ($this->snapshot['users'] ?? [] as $u) {
            if (! empty($u['link'])) {
                $map[$u['link']] = ($id = $this->authorId($u['id'])) && ($url = User::query()->find($id)?->profileUrl()) ? parse_url($url, PHP_URL_PATH) : '/blog';
            }
        }
        $map['/feed'] = '/blog/rss.xml';
        $map['/comments/feed'] = '/blog/rss.xml';
        $map['/blog-feed'] = '/blog/rss.xml';

        return $map;
    }

    private function importRedirects(): int
    {
        $n = 0;
        $site = $this->state['source'] ?? '';
        foreach ($this->redirectMap() as $from => $to) {
            $from = Redirect::normalize($from);
            if ($from === '/' || $from === Redirect::normalize($to) || $this->routeExists($from)) {
                continue; // не перехватываем живые адреса нового сайта
            }
            Redirect::remember($from, $to, 'wp', 'WordPress '.$site);
            $n++;
        }
        foreach ($this->state['media'] as $url => $m) {
            if (! ($m['ok'] ?? false)) {
                continue;
            }
            foreach ([$url, ...($m['variants'] ?? [])] as $u) {
                if (in_array(parse_url($u, PHP_URL_HOST), $this->state['plan']['own_hosts'], true)) {
                    Redirect::remember($u, $m['path'], 'wp', 'файл WordPress');
                    $n++;
                }
            }
        }

        return $n;
    }

    /** Путь уже обслуживается новым сайтом (страница, маршрут) — редирект с него не нужен. */
    private function routeExists(string $path): bool
    {
        if (str_contains($path, '?')) {
            return false;
        }
        $first = strtok(trim($path, '/'), '/');
        if (in_array($first, PageResource::reservedSlugs(), true)) {
            return true;
        }

        return ! str_contains(trim($path, '/'), '/') && Page::withoutGlobalScopes()->where('type', 'page')->where('slug', trim($path, '/'))->exists();
    }

    private function slugFor(string $type, string $oldSlug, string $title, int $wpId, array $taken): string
    {
        $base = preg_match('/^[a-z0-9-]{1,120}$/', $oldSlug) ? $oldSlug : (Str::limit(Str::slug($oldSlug !== '' ? $oldSlug : $title, '-', 'ru'), 120, '') ?: $type.'-'.$wpId);
        $base = trim($base, '-') ?: $type.'-'.$wpId;
        $reserved = $type === 'page' ? PageResource::reservedSlugs() : [];
        $mine = $this->existing($type, $wpId);
        for ($i = 1, $s = $base; ; $s = $base.'-'.++$i) {
            if (isset($taken[$s]) || in_array($s, $reserved, true)) {
                continue;
            }
            $other = Post::withoutGlobalScopes()->where('slug', $s)->first();
            if (! $other || ($mine && $other->id === $mine->id)) {
                return $s;
            }
        }
    }

    private function existing(string $type, int $wpId): ?Post
    {
        try {
            $q = $type === 'page' ? Page::query() : Post::withoutGlobalScope('type')->where('type', 'post');

            return $q->where('meta->wp_key', $type.':'.$wpId)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    private function lead(array $wp): ?string
    {
        $raw = (string) ($wp['excerpt']['rendered'] ?? '');
        // автоматический анонс WordPress обрезан «[…]» или «Читать далее» — лидом не годится
        if ($raw === '' || preg_match('/(\[(&hellip;|…)\]|&hellip;\s*<a|more-link|Читать далее|Continue reading)/iu', $raw)) {
            return null;
        }
        $text = $this->text($raw);

        return $text === '' ? null : Str::limit($text, 390, '…');
    }

    private function authorId(?int $wpUser): ?int
    {
        if (array_key_exists((int) $wpUser, $this->authors)) {
            return $this->authors[(int) $wpUser];
        }
        $forced = $this->state['options']['author_id'] ?? null;
        $name = collect($this->snapshot['users'] ?? [])->firstWhere('id', $wpUser)['name'] ?? null;
        $staff = User::query()->whereIn('role', ['admin', 'editor']);
        $id = $forced
            ?: ($name ? (clone $staff)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->value('id') : null)
            ?: User::query()->where('role', 'admin')->orderBy('id')->value('id');

        return $this->authors[(int) $wpUser] = $id;
    }

    /** «Halyk», «VISA», «Jysan Bank» → связи с банком / платёжной системой каталога. */
    public function entityKeys(array $names): array
    {
        if ($this->entityIndex === null) {
            $this->entityIndex = [];
            try {
                foreach (Bank::query()->get(['id', 'slug', 'name', 'short_name', 'code', 'aliases']) as $b) {
                    foreach (array_filter([$b->name, $b->short_name, $b->code, ...((array) $b->aliases)]) as $n) {
                        $this->entityIndex[self::norm($n)] ??= 'bank:'.$b->slug;
                    }
                }
                foreach (PaymentSystem::query()->get() as $p) {
                    foreach (array_filter([$p->name, $p->short_name, $p->slug, ...((array) $p->aliases)]) as $n) {
                        $this->entityIndex[self::norm($n)] ??= 'payment_system:'.$p->slug;
                    }
                }
            } catch (\Throwable) {
                // каталога ещё нет — связей не будет
            }
            // короткие коды («IT», «C») совпадают с обычными рубриками — не сопоставляем
            $this->entityIndex = array_filter($this->entityIndex, fn ($k) => mb_strlen((string) $k) >= 3, ARRAY_FILTER_USE_KEY);
        }
        $aliases = ['homcredit' => 'homecredit', 'altyni' => 'altyn', 'tsentrkredit' => 'centercredit', 'tsentrkreditbank' => 'centercredit', 'sber' => 'sberbank'];
        $out = [];
        foreach ($names as $n) {
            $k = self::norm((string) $n);
            $k = isset($this->entityIndex[$k]) ? $k : ($aliases[$k] ?? $k);
            if (isset($this->entityIndex[$k])) {
                $out[] = $this->entityIndex[$k];
            }
        }

        return array_values(array_unique($out));
    }

    public static function norm(string $s): string
    {
        $s = Str::slug(html_entity_decode($s, ENT_QUOTES | ENT_HTML5), '', 'ru');

        return (string) preg_replace('/^(bank)|(bank|banka)$/', '', $s);
    }

    private function ownHosts(array $snap): array
    {
        $hosts = [];
        foreach ([$snap['site']['url'] ?? null, $snap['site']['home'] ?? null] as $u) {
            if ($u && ($h = parse_url($u, PHP_URL_HOST))) {
                $h = preg_replace('/^www\./', '', strtolower($h));
                array_push($hosts, $h, 'www.'.$h);
            }
        }

        return array_values(array_unique($hosts)) ?: ['localhost'];
    }

    private function text(?string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5)));
    }

    private function checkTime(): void
    {
        if ($this->progressed && microtime(true) > $this->deadline) {
            throw new WpImportPaused;
        }
    }

    private function path(string $file): string
    {
        return storage_path('app/'.self::DIR.'/'.$file);
    }

    private function load(): void
    {
        $f = $this->path('state.json');
        $this->state = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    }

    private function loadSnapshot(): void
    {
        $f = $this->path('snapshot.json');
        $this->snapshot = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    }

    private function save(): void
    {
        File::ensureDirectoryExists(storage_path('app/'.self::DIR));
        file_put_contents($this->path('state.json'), json_encode($this->state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    }
}
