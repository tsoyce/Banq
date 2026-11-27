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
        // A18 · Рейтинги: один расчёт на запрос
        $this->app->scoped(ObjectiveRating::class);
        $this->app->scoped(PeopleRating::class);
        $this->app->scoped(RatingBoard::class);
        $this->app->scoped(\App\Services\Cards\CardCatalog::class); // A33 · одна выборка карт на запрос
        $this->app->scoped(\App\Services\Currency\CurrencyRates::class); // A40
        $this->app->scoped(\App\Services\Ticker\Ticker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A20 · Индексы по varchar(191) влезают в лимит ключа даже на старых MySQL/MyISAM (191 × 4 байта utf8mb4 < 767/1000)
        Schema::defaultStringLength(191);

        // A28 · Короткие имена типов в favorites (deposit, bank) — не зависят от имён классов
        Relation::morphMap(Favorite::TYPES);

        // A13 · Бегущая строка в шапке витрины. A40: типы контента и порядок — из админки (App\Services\Ticker\Ticker)
        View::composer('layouts.app', function ($view) {
            $ticker = app(\App\Services\Ticker\Ticker::class);
            $groups = $ticker->groups();
            $view->with(['ticker' => $groups, 'tickerDuration' => $ticker->duration($groups)]);
        });
    }
}
