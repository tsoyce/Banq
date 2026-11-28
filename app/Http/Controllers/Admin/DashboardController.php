<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Navigation;
use App\Admin\ResourceRegistry;
use App\Http\Controllers\Controller;
use App\Models\AdStat;
use App\Models\Bank;
use App\Models\Comment;
use App\Models\Country;
use App\Models\DepositProduct;
use App\Models\DepositRate;
use App\Models\Import;
use App\Models\Post;
use App\Models\Review;
use App\Support\AdminScope;
use App\Support\Installation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A09 · Обзор админки. A24: сводка по странам и «здоровье данных».
 * A70: бенто по группам меню (Контент / Каталог / Люди / Реклама) — числа в пределах страны из плашки;
 * «Требует внимания» — модерация и пробелы в данных одним списком; последние правки публикаций; быстрые действия.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, Navigation $nav, ResourceRegistry $registry, AdminScope $scope): View
    {
        $role = $request->user()->role;
        $stale = today()->subDays(60);
        // Запрос ресурса в пределах страны из плашки (как в его списке); ресурс, недоступный роли, — null
        $q = function (string $key) use ($registry, $scope, $role): ?Builder {
            $r = $registry->all()[$key] ?? null;

            return $r && in_array($role, $r->roles(), true) ? $scope->apply($r->query(), $r->countryScope(), $r->countryNullable()) : null;
        };
        $n = fn (string $key, ?\Closure $where = null) => ($b = $q($key)) ? ($where ? $where($b) : $b)->count() : null;
        $url = fn (string $key, array $params = []) => route('admin.resource.index', [$key, ...$params]);

        $since = today()->subDays(6);
        $ads = AdStat::query()->where('date', '>=', $since)->selectRaw('COALESCE(SUM(impressions), 0) AS views, COALESCE(SUM(clicks), 0) AS clicks')->first();
        $adsDaily = AdStat::query()->where('date', '>=', $since)->selectRaw('date, SUM(impressions) AS views')->groupBy('date')->pluck('views', 'date')
            ->mapWithKeys(fn ($v, $d) => [substr((string) $d, 0, 10) => (int) $v]);

        $pendingReviews = Review::pending()->count();
        $pendingComments = Comment::where('status', 'pending')->count();

        return view('admin.dashboard', [
            'greeting' => match (true) { now()->hour < 5 => 'Доброй ночи', now()->hour < 12 => 'Доброе утро', now()->hour < 18 => 'Добрый день', default => 'Добрый вечер' },
            'actions' => $nav->quickActions(),
            'bento' => array_values(array_filter([
                [
                    'group' => 'Контент', 'icon' => 'news', 'tone' => 'brand',
                    'value' => $n('posts', fn ($b) => $b->published()), 'label' => 'публикаций на сайте', 'url' => $url('posts', ['state' => 'published']),
                    'rows' => [
                        ['Черновики', $n('posts', fn ($b) => $b->where(fn ($w) => $w->where('status', '!=', 'published')->orWhereNull('published_at'))), $url('posts', ['state' => 'draft'])],
                        ['Запланировано', $n('posts', fn ($b) => $b->where('status', 'published')->where('published_at', '>', now())), $url('posts', ['state' => 'scheduled'])],
                        ['Страниц', $n('pages'), $url('pages')],
                    ],
                ],
                [
                    'group' => 'Каталог', 'icon' => 'bank', 'tone' => 'ink',
                    'value' => $n('banks', fn ($b) => $b->active()), 'label' => 'действующих банков', 'url' => $url('banks'),
                    'rows' => [
                        ['Вкладов', $n('deposits'), $url('deposits')],
                        ['Карт', $n('cards'), $url('cards')],
                        ['Пакетов услуг', $n('packages'), $url('packages')],
                        ['Ставок в силе', $n('rates', fn ($b) => $b->current()), $url('rates', ['state' => 'current'])],
                    ],
                ],
                [
                    'group' => 'Люди', 'icon' => 'users', 'tone' => 'plain',
                    'value' => $pendingReviews + $pendingComments, 'label' => 'ждут модерации', 'url' => $pendingComments && ! $pendingReviews ? $url('comments', ['status' => 'pending']) : route('admin.reviews.index'),
                    'rows' => array_values(array_filter([
                        ['Отзывы', $pendingReviews, route('admin.reviews.index')],
                        ['Комментарии', $pendingComments, $url('comments', ['status' => 'pending'])],
                        $role === 'admin' ? ['Пользователей', $n('users'), $url('users')] : null,
                    ])),
                ],
                [
                    'group' => 'Реклама', 'icon' => 'chart', 'tone' => 'plain',
                    'value' => (int) $ads->views, 'label' => 'показов за 7 дней', 'url' => route('admin.ads.index'),
                    'rows' => [
                        ['Кликов', (int) $ads->clicks, route('admin.ads.index')],
                        ['CTR', $ads->views ? number_format($ads->clicks / $ads->views * 100, 2, ',', '').'%' : '—', route('admin.ads.index')],
                        ['Кампаний в работе', $n('ad-campaigns', fn ($b) => $b->where('is_active', true)), $url('ad-campaigns')],
                    ],
                    'spark' => collect(range(6, 0))->map(fn ($d) => $adsDaily[today()->subDays($d)->toDateString()] ?? 0)->all(),
                ],
            ])),
            'attention' => collect([
                ['Отзывы на модерации', $pendingReviews, route('admin.reviews.index'), 'quote'],
                ['Комментарии на модерации', $pendingComments, $url('comments', ['status' => 'pending']), 'quote'],
                ['Банки без вкладов', $scope->apply(Bank::active()->doesntHave('depositProducts'), 'country_id')->count(), $url('banks', ['data' => 'empty']), 'bank'],
                ['Банки без действующих ставок', $scope->apply(Bank::active()->whereDoesntHave('depositProducts', fn ($p) => $p->whereHas('currentRates')), 'country_id')->count(), $url('banks', ['data' => 'norates']), 'bank'],
                ['Вклады без ставок', $scope->apply(DepositProduct::active()->doesntHave('currentRates'), 'bank.country_id')->count(), $url('deposits', ['rates' => 'none']), 'piggy'],
                ['Ставки старше 60 дней', $scope->apply(DepositProduct::active()->whereHas('currentRates')->whereDoesntHave('currentRates', fn ($r) => $r->where('valid_from', '>=', $stale)), 'bank.country_id')->count(), $url('deposits', ['rates' => 'stale']), 'clock'],
                ['Банки без финансов (рейтинг)', $scope->apply(Bank::active()->whereNull('assets_bn'), 'country_id')->count(), $url('banks', ['data' => 'nofin']), 'chart'],
            ])->map(fn ($r) => array_combine(['label', 'n', 'url', 'icon'], $r))->sortByDesc(fn ($r) => $r['n'] > 0)->values(),
            'posts' => ($b = $q('posts')) ? $b->reorder()->latest('updated_at')->take(6)->get() : collect(),
            'countries' => $scope->apply(Country::query()->ordered(), 'id')->withCount([ // A46
                'banks', 'banks as active_banks_count' => fn ($q) => $q->active(),
            ])->get()->map(fn (Country $c) => [
                'country' => $c,
                'products' => DepositProduct::whereHas('bank', fn ($q) => $q->where('country_id', $c->id))->count(),
                'rates' => DepositRate::current()->whereHas('product.bank', fn ($q) => $q->where('country_id', $c->id))->count(),
                'posts' => Post::query()->where('type', 'post')->where('country_id', $c->id)->count(),
            ]),
            'imports' => Import::query()->with(['user', 'token'])->latest('id')->take(5)->get(),
            'install' => Installation::info(),
        ]);
    }
}
