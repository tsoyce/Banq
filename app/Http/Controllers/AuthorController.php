<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostCategory;
use App\Services\Social\SocialStats;
use App\Support\Founder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * A55 · Страница автора /author: основатель TSOY.IN — профиль, соцсети со счётчиками, его публикации
 * (в т.ч. перенесённые с WordPress), поддержка проекта. Публикации — «Показать ещё» через X-Partial, как в ленте журнала.
 */
class AuthorController extends Controller
{
    private const PER_PAGE = 12;

    public function show(Request $request, Founder $founder, SocialStats $social): View|Response
    {
        $user = $founder->user();
        abort_unless($user, 404);
        $social->ensureFresh();

        $category = $request->query('c') ? PostCategory::query()->active()->where('slug', (string) $request->query('c'))->first() : null;
        $base = fn () => Post::query()->feed()->where('author_id', $user->id);

        $posts = $base()->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->withCount(['comments' => fn ($c) => $c->approved()])
            ->paginate(self::PER_PAGE)->withQueryString();

        if ($request->header('X-Partial') === 'posts') {
            return response(view('blog._grid', ['posts' => $posts])->render())->header('X-Has-More', $posts->hasMorePages() ? '1' : '0');
        }

        $stats = $base()->reorder()->selectRaw('count(*) as n, coalesce(sum(views), 0) as views, min(published_at) as first')->first();
        $first = $stats?->first ? \Illuminate\Support\Carbon::parse($stats->first) : null;

        return view('author.show', [
            'user' => $user,
            'posts' => $posts,
            'category' => $category,
            'categories' => PostCategory::query()->active()->ordered()
                ->withCount(['posts' => fn ($p) => $p->published()->forCountry()->where('author_id', $user->id)])
                ->get()->where('posts_count', '>', 0)->values(),
            'popular' => $base()->reorder()->orderByDesc('views')->limit(5)->get(),
            'stats' => [
                'posts' => (int) ($stats->n ?? 0),
                'views' => (int) ($stats->views ?? 0),
                'since' => $first?->year,
                'followers' => $social->total(),
            ],
            'profiles' => settings('social.enabled', true) ? $social->profiles() : [],
            'about' => collect(preg_split("/\R{2,}/", trim((string) (settings('author.about') ?: $user->bio))))->filter()->values(),
        ]);
    }
}
