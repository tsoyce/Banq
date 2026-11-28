<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Redirect;
use App\Support\Blog\PostRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A42 · Последний маршрут сайта (Route::fallback): страница по адресу /{slug} → редирект со старого адреса (301) → 404.
 * Так «О проекте» живёт по короткому адресу, а старые ссылки tsoy.in (WordPress, A43) ведут на новые.
 */
class PageController extends Controller
{
    public function __invoke(Request $request, PostRenderer $renderer): Response
    {
        $path = trim(rawurldecode($request->path()), '/');

        if ($path !== '' && ! str_contains($path, '/') && in_array($request->method(), ['GET', 'HEAD'], true)) {
            $page = Page::query()->where('slug', mb_strtolower($path))->first();
            if ($page && ($page->isPublished() || $request->user()?->isStaff())) {
                $page->load('author');
                $rendered = $renderer->render($page);

                return response()->view('pages.show', ['page' => $page, 'html' => $rendered['html'], 'toc' => $rendered['toc']]);
            }
        }

        if ($response = self::redirectFor($request)) {
            return $response;
        }

        abort(404);
    }

    /** Редирект со старого адреса, если он есть и не ведёт сам на себя. */
    public static function redirectFor(Request $request): ?Response
    {
        $r = Redirect::match($request);
        if (! $r || Redirect::normalize($r->target()) === Redirect::normalize($request->getRequestUri())) {
            return null;
        }

        return redirect()->away($r->target(), $r->status);
    }
}
