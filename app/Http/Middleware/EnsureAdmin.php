<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A09 · Доступ в /admin — только роли admin и editor. */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'editor'], true), 403);

        return $next($request);
    }
}
