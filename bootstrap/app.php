<?php

use App\Http\Controllers\PageController;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\ResolveCountry;
use App\Http\Middleware\TrailingSlash;
use App\Http\Middleware\Typography;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $middleware->append(TrailingSlash::class); // A44 · /адрес/ → один 301 (вместо .htaccess, который за nginx уводил на http)
        $middleware->redirectGuestsTo('/login');
        // A21 · Текущая страна витрины (после расшифровки cookie)
        $middleware->web(append: [ResolveCountry::class, Typography::class]); // A36 · неразрывные пробелы в HTML
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A42 · 404 на существующем маршруте (например, /blog/старый-адрес) — пробуем редирект со старого адреса
        $exceptions->render(function (Throwable $e, Request $request) {
            $missing = $e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException;

            return $missing && ! $request->is('api/*', 'admin/*') ? PageController::redirectFor($request) : null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
