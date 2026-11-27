<?php

namespace App\Http\Middleware;

use App\Support\Typograph;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A29 · Пропускает HTML-ответы витрины и админки через App\Support\Typograph (неразрывные пробелы). */
class Typography
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('banq.typograph', true)
            && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse
            && ! $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            $content = $response->getContent();
            if (is_string($content) && $content !== '') {
                // setContent() у Illuminate\Http\Response затирает original (view) — сохраняем для тестов и отладки
                $original = $response->original ?? null;
                $response->setContent(Typograph::html($content));
                if ($original !== null) {
                    $response->original = $original;
                }
            }
        }

        return $response;
    }
}
