<?php

use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\ResolveCountry;
use App\Http\Middleware\Typography;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // A23 · /api/v1
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // A03 · Первым делом — проверка установки (см. App\Http\Middleware\EnsureInstalled)
        $middleware->prepend(EnsureInstalled::class);
        $middleware->redirectGuestsTo('/login');
        // A21 · Текущая страна витрины (после расшифровки cookie)
        $middleware->web(append: [ResolveCountry::class, Typography::class]); // A29 · неразрывные пробелы в HTML
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
