<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostReaction;
use App\Models\Tag;
use App\Support\Blog\PostRenderer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * A37 · Блог на витрине: лента (поиск, рубрика, тег, «Показать ещё» через X-Partial), публикация
 * (оглавление, живые вставки продуктов, связанные сущности, реакции, комментарии), RSS.
 */
class BlogController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request): View|Response
    {
        return $this->feed($request);
    }

    public function category(Request $request, PostCategory $category): View|Response
    {
        abort_unless($category->is_active, 404);

        return $this->feed($request, category: $category);
    }

    public function tag(Request $request, Tag $tag): View|Response
    {
        return $this->feed($request, tag: $tag);
    }

    private function feed(Request $request, ?PostCategory $category = null, ?Tag $tag = null): View|Response
    {
        $q = trim((string) $request->query('q'));
        $kind = array_key_exists((string) $request->query('kind'), Post::KINDS) ? (string) $request->query('kind') : null;
        $filtered = $category || $tag || $q !== '' || $kind;

        $base = fn (): Builder => Post::query()->feed()
            ->when($category, fn ($w) => $w->where('category_id', $category->id))
            ->when($tag, fn ($w) => $w->whereHas('tags', fn ($t) => $t->whereKey($tag->id)))
            ->when($kind, fn ($w) => $w->where('kind', $kind))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';
                $w->where(fn ($x) => $x->where('title', 'like', $like)->orWhere('lead', 'like', $like)->orWhere('body', 'like', $like));
            });

        // Главный материал — только на первой странице общей ленты
        $featured = ! $filtered && (int) $request->query('page', 1) === 1
            ? ($base()->where('is_featured', true)->first() ?? $base()->whereNotNull('cover')->first() ?? $base()->first())
            : null;

        /** @var LengthAwarePaginator $posts */
        $posts = $base()->when($featured, fn ($w) => $w->whereKeyNot($featured->id))
            ->withCount(['comments' => fn ($c) => $c->approved()])
            ->paginate(self::PER_PAGE)->withQueryString();

        if ($request->header('X-Partial') === 'posts') {
            return response(view('blog._grid', ['posts' => $posts])->render())->header('X-Has-More', $posts->hasMorePages() ? '1' : '0');
        }

        return view('blog.index', [
            'posts' => $posts, 'featured' => $featured, 'category' => $category, 'tag' => $tag, 'q' => $q, 'kind' => $kind,
            'categories' => PostCategory::query()->active()->ordered()
                ->withCount(['posts' => fn ($p) => $p->published()->forCountry()])->get(),
            'popular' => Post::query()->published()->forCountry()->where('published_at', '>=', now()->subDays(60))->orderByDesc('views')->limit(5)->get(),
            'tags' => Tag::query()->whereHas('posts', fn ($p) => $p->published()->forCountry())
                ->withCount(['posts' => fn ($p) => $p->published()->forCountry()])->orderByDesc('posts_count')->limit(18)->get(),
            'total' => Post::query()->published()->forCountry()->count(),
        ]);
    }

    public function show(Request $request, Post $post, PostRenderer $renderer): View
    {
        $staff = (bool) $request->user()?->isStaff();
        abort_unless($post->isPublished() || $staff, 404);

        // Просмотр — раз в сессию, сотрудники не накручивают
        if ($post->isPublished() && ! $staff && ! in_array($post->id, $seen = (array) $request->session()->get('blog.seen', []), true)) {
            Post::query()->whereKey($post->id)->increment('views');
            $post->views++;
            $request->session()->put('blog.seen', array_slice([...$seen, $post->id], -200));
        }

        $post->load(['category', 'author', 'tags', 'country']);
        $rendered = $renderer->render($post);
        $user = $request->user();

        $comments = $post->comments()->with('user')->whereNull('parent_id')
            ->where(fn ($w) => $w->approved()->when($user, fn ($x) => $x->orWhere('user_id', $user->id)))
            ->with(['replies' => fn ($r) => $r->with('user')->where(fn ($w) => $w->approved()->when($user, fn ($x) => $x->orWhere('user_id', $user->id)))->oldest()])
            ->oldest()->get();

        $tagIds = $post->tags->pluck('id');
        $related = Post::query()->feed()->whereKeyNot($post->id)
            ->where(fn ($w) => $w->when($post->category_id, fn ($x) => $x->where('category_id', $post->category_id))
                ->when($tagIds->isNotEmpty(), fn ($x) => $x->orWhereHas('tags', fn ($t) => $t->whereIn('tags.id', $tagIds))))
            ->limit(3)->get();
        if ($related->count() < 3) {
            $related = $related->concat(Post::query()->feed()->whereKeyNot($post->id)->whereNotIn('id', $related->pluck('id'))->limit(3 - $related->count())->get());
        }

        return view('blog.show', [
            'post' => $post, 'html' => $rendered['html'], 'toc' => $rendered['toc'], 'linked' => $post->linked(),
            'comments' => $comments, 'commentsCount' => $post->comments()->approved()->count(),
            'reactions' => $post->reactionCounts(),
            'mine' => $user ? PostReaction::query()->where('post_id', $post->id)->where('user_id', $user->id)->pluck('kind')->all() : [],
            'related' => $related,
            'prev' => Post::query()->published()->forCountry()->where('published_at', '<', $post->published_at ?? now())->orderByDesc('published_at')->first(['id', 'slug', 'title']),
            'next' => Post::query()->published()->forCountry()->where('published_at', '>', $post->published_at ?? now())->orderBy('published_at')->first(['id', 'slug', 'title']),
        ]);
    }

    /** Реакция: поставить / снять. JSON для fetch, редирект без JS. */
    public function react(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        abort_unless($post->isPublished(), 404);
        $kind = $request->validate(['kind' => ['required', 'in:'.implode(',', array_keys(Post::REACTIONS))]])['kind'];
        $attrs = ['post_id' => $post->id, 'user_id' => $request->user()->id, 'kind' => $kind];

        $on = DB::transaction(function () use ($attrs) {
            $deleted = PostReaction::query()->where($attrs)->delete();
            if (! $deleted) {
                PostReaction::query()->create($attrs);
            }

            return ! $deleted;
        });

        return $request->expectsJson()
            ? response()->json(['on' => $on, 'counts' => $post->reactionCounts()])
            : back();
    }

    public function comment(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->isPublished() && $post->allow_comments, 403);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:3000'],
            'parent_id' => ['nullable', 'integer'],
        ], [], ['body' => 'комментарий']);

        $parent = isset($data['parent_id']) ? $post->comments()->whereKey($data['parent_id'])->first() : null;
        $premod = (bool) settings('blog.premoderation', false) && ! $request->user()->isStaff();

        $c = $post->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $parent?->parent_id ?? $parent?->id, // один уровень ответов
            'body' => trim($data['body']),
            'status' => $premod ? 'pending' : 'approved',
            'ip' => $request->ip(),
        ]);

        return redirect()->to($post->url().'#comment-'.$c->id)
            ->with('status', $premod ? 'Комментарий отправлен — появится после проверки' : 'Комментарий опубликован');
    }

    public function destroyComment(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless($comment->user_id === $request->user()->id || $request->user()->isStaff(), 403);
        $post = $comment->post;
        $comment->delete();

        return redirect()->to($post->url().'#comments')->with('status', 'Комментарий удалён');
    }

    public function rss(): Response
    {
        $posts = Post::query()->feed()->limit(30)->get();

        return response()->view('blog.rss', ['posts' => $posts], 200)->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
