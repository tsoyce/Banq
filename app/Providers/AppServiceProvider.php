<?php

namespace App\Providers;

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
