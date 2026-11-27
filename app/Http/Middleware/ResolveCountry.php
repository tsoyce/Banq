<?php

namespace App\Http\Middleware;

use App\Support\CurrentCountry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A21 · Определяет текущую страну витрины; ?country=xx запоминается в cookie на год. */
class ResolveCountry
{
    public function __construct(private CurrentCountry $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $country = $this->current->resolve($request);
        $response = $next($request);

        if ($country && $request->query('country') && $request->cookie(CurrentCountry::COOKIE) !== $country->slug) {
            $response->headers->setCookie(cookie(CurrentCountry::COOKIE, $country->slug, 60 * 24 * 365));
        }

        return $response;
    }
}
