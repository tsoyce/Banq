<?php

namespace App\Http\Controllers;

use App\Services\Compare\CompareRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A53 · Раздел «Сравнение»: /compare — все виды продуктов с тем, что уже выбрано (списки в браузере),
 * /compare/{type}?ids= — таблица одного вида. Вклады и карты уходят на свои страницы (A14, A33).
 */
class CompareController extends Controller
{
    public function __construct(private CompareRegistry $registry) {}

    public function index(): View
    {
        return view('compare.index', ['types' => $this->registry->all(), 'soon' => CompareRegistry::SOON]);
    }

    public function show(Request $request, string $type): View|RedirectResponse
    {
        $t = $this->registry->get($type) ?? abort(404);
        if (! $t->generic()) {
            return redirect()->to($t->url([], $request->query()));
        }

        $items = $t->items($t->ids($request->query('ids')));

        return view('compare.show', [
            'type' => $t,
            'items' => $items,
            'heads' => $items->map(fn ($m) => $t->head($m)),
            'rows' => $items->isEmpty() ? [] : $t->rows($items),
            'types' => $this->registry->all(),
        ]);
    }
}
