<?php

namespace App\Providers;

use App\Services\Ratings\ObjectiveRating;
use App\Services\Ratings\PeopleRating;
use App\Services\Ratings\RatingBoard;
use App\Support\Market;
use App\Support\Settings;
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
        // A18 · Рейтинги: один расчёт на запрос
        $this->app->scoped(ObjectiveRating::class);
        $this->app->scoped(PeopleRating::class);
        $this->app->scoped(RatingBoard::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A13 · Бегущая строка ставок в шапке витрины
        View::composer('layouts.app', fn ($view) => $view->with('ticker', app(Market::class)->top(14)));
    }
}
