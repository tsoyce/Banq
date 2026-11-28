<?php

namespace App\Http\Middleware;

use App\Http\Controllers\PageController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A44 · Адрес со слешем в конце (так выглядят все старые ссылки WordPress: /halyk-2025/) — один редирект 301.
 * Раньше это делал .htaccess, но Apache за nginx видит http и отдавал лишний переход через http://.
 * Есть запись в «Редиректах» — сразу на новый адрес, иначе — на тот же адрес без слеша.
 */
class TrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        if ($path === '/' || ! str_ends_with($path, '/') || ! $request->isMethodSafe() || $request->is('api/*')
            || is_dir(public_path(ltrim($path, '/')))) {
            return $next($request);
        }
        if (! $request->is('admin/*') && ($to = PageController::redirectFor($request))) {
            return $to;
        }
        $query = $request->getQueryString();

        return redirect()->to(rtrim($request->url(), '/').($query ? '?'.$query : ''), 301);
    }
}
