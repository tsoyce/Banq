<?php

namespace App\Http\Middleware;

use App\Services\Installer\EnvWriter;
use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A03 · Глобальный middleware (стоит первым).
 *  - нет .env / APP_KEY → создаёт и перезапрашивает страницу;
 *  - сайт не установлен → всё ведёт на /install;
 *  - сайт установлен → /install отвечает 404.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->runningUnitTests() && EnvWriter::bootstrap()) {
            return redirect($request->fullUrl());
        }

        $installing = $request->is('install', 'install/*');

        if (! Installation::isInstalled() && ! $installing && ! $request->is('up', 'build/*', 'uploads/*', 'favicon.ico')) {
            return redirect('/install');
        }

        if (Installation::isInstalled() && $installing) {
            abort(404);
        }

        return $next($request);
    }
}
