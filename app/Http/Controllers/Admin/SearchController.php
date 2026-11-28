<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Navigation;
use App\Admin\ResourceRegistry;
use App\Http\Controllers\Controller;
use App\Support\AdminScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A24 · Глобальный поиск админки (⌘K). A70: ищет по всем ресурсам, которые видит роль, в порядке меню
 * (поиск ресурса — его же колонки из Resource::search()), в пределах стран сотрудника. Разделы и быстрые
 * действия палитра фильтрует сама (App\Admin\Navigation), сюда приходят только записи.
 */
class SearchController extends Controller
{
    /** Ресурсы без осмысленного заголовка записи в поиске не участвуют. */
    private const SKIP = ['rates'];

    private const PER = ['banks' => 6, 'posts' => 6, 'deposits' => 5, 'cards' => 5];

    public function __invoke(Request $request, Navigation $nav, ResourceRegistry $registry, AdminScope $scope): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $all = $registry->all();
        $results = [];
        foreach ($nav->items($request->user()) as $item) {
            $r = $all[$item['key']] ?? null;
            if (! $r || in_array($r->key(), self::SKIP, true)) {
                continue;
            }
            $query = $scope->apply($r->query(), $r->countryScope(), $r->countryNullable(), selection: false);
            foreach ($r->applySearch($query, $q)->reorder()->latest($query->getModel()->getQualifiedKeyName())->limit(self::PER[$r->key()] ?? 4)->get() as $m) {
                $results[] = [
                    'type' => $item['label'], 'title' => $r->title($m), 'sub' => $this->hint($r->key(), $m),
                    'url' => route('admin.resource.edit', [$r->key(), $m->getKey()]),
                ];
            }
            if (count($results) >= 40) {
                break;
            }
        }

        return response()->json($results);
    }

    /** Подсказка под заголовком: чей продукт, код, e-mail, статус. */
    private function hint(string $key, Model $m): string
    {
        return (string) match ($key) {
            'countries' => $m->code.' · '.$m->currency,
            'banks' => trim(($m->country?->name ?? '').' · '.$m->code.' · '.$m->name, ' ·'),
            'deposits' => trim(($m->bank?->displayName() ?? '').' · '.($m->type?->label() ?? ''), ' ·'),
            'cards', 'packages' => $m->bank?->displayName() ?? '',
            'card-tiers' => $m->paymentSystem?->name ?? '',
            'users' => $m->email.' · '.$m->role,
            'posts', 'pages' => '/'.ltrim((string) $m->slug, '/').(method_exists($m, 'stateLabel') ? ' · '.$m->stateLabel() : ''),
            'comments' => mb_strimwidth(strip_tags((string) $m->body), 0, 80, '…'),
            'redirects' => $m->from_path.' → '.$m->to_url,
            default => '#'.$m->getKey(),
        };
    }
}
