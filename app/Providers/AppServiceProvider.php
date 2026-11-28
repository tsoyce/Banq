<?php

namespace App\Providers;

use App\Models\Favorite;
use App\Services\Ratings\ObjectiveRating;
use App\Services\Ratings\PeopleRating;
use App\Services\Ratings\RatingBoard;
use App\Support\CurrentCountry;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Settings::class);
        $this->app->scoped(Market::class);
        $this->app->scoped(CurrentCountry::class); // A21
        $this->app->scoped(\App\Admin\Navigation::class); // A70 · карта админки, бейджи — раз за запрос
        // A18 · Рейтинги: один расчёт на запрос
        $this->app->scoped(ObjectiveRating::class);
        $this->app->scoped(PeopleRating::class);
        $this->app->scoped(RatingBoard::class);
        $this->app->scoped(\App\Services\Cards\CardCatalog::class); // A33 · одна выборка карт на запрос
        $this->app->scoped(\App\Services\Currency\CurrencyRates::class); // A40
        $this->app->scoped(\App\Services\Ticker\Ticker::class);
        $this->app->scoped(\App\Services\Ads\AdServer::class); // A51 · счёт мест на странице
        $this->app->scoped(\App\Services\Ads\Snippets::class); // A50
        $this->app->scoped(\App\Support\Founder::class); // A55 · автор /author — один запрос на запрос
        $this->app->scoped(\App\Services\Social\SocialStats::class);
        $this->app->scoped(\App\Support\AdminScope::class); // A46
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A20 · Индексы по varchar(191) влезают в лимит ключа даже на старых MySQL/MyISAM (191 × 4 байта utf8mb4 < 767/1000)
        Schema::defaultStringLength(191);

        // A28 · Короткие имена типов в favorites (deposit, bank) — не зависят от имён классов
        Relation::morphMap(Favorite::TYPES + \App\Models\Post::LINK_TYPES); // A37: + package, payment_system (связи публикаций)

        // A37 · Свои лимиты для журнала: обычный throttle:N,M считает все маршруты пользователя одним счётчиком —
        // десяток реакций не должен блокировать комментарий
        \Illuminate\Support\Facades\RateLimiter::for('blog-comments', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(6)->by('comment:'.($r->user()?->id ?: $r->ip())));
        \Illuminate\Support\Facades\RateLimiter::for('blog-reactions', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by('react:'.($r->user()?->id ?: $r->ip())));

        // A13 · Бегущая строка в шапке витрины. A40: типы контента и порядок — из админки (App\Services\Ticker\Ticker)
        View::composer('layouts.app', function ($view) {
            $ticker = app(\App\Services\Ticker\Ticker::class);
            $groups = $ticker->groups();
            $view->with(['ticker' => $groups, 'tickerDuration' => $ticker->duration($groups)]);
        });
    }
}
