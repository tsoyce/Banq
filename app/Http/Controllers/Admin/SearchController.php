<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Country;
use App\Models\DepositProduct;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A24 · Глобальный поиск админки (⌘K): страны, банки, вклады, пользователи. JSON для палитры в layouts.admin. */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';
        $hit = fn ($type, $title, $sub, $url) => compact('type', 'title', 'sub', 'url');

        $results = collect()
            ->merge(Country::query()->where('name', 'like', $like)->orWhere('code', $q)->limit(5)->get()
                ->map(fn ($c) => $hit('Страна', trim($c->flag.' '.$c->name), $c->code.' · '.$c->currency, route('admin.resource.edit', ['countries', $c->id]))))
            ->merge(Bank::query()->with('country')->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('short_name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('aliases', 'like', $like))->limit(8)->get()
                ->map(fn ($b) => $hit('Банк', $b->displayName(), trim(($b->country?->flag ?? '').' '.$b->code.' · '.$b->name), route('admin.resource.edit', ['banks', $b->id]))))
            ->merge(DepositProduct::query()->with('bank')->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('slug', 'like', $like))->limit(8)->get()
                ->map(fn ($p) => $hit('Вклад', $p->name, $p->bank?->displayName().' · '.$p->type?->label(), route('admin.resource.edit', ['deposits', $p->id]))));

        if ($request->user()->role === 'admin') {
            $results = $results->merge(User::query()->where('name', 'like', $like)->orWhere('email', 'like', $like)->limit(5)->get()
                ->map(fn ($u) => $hit('Пользователь', $u->name, $u->email, route('admin.resource.edit', ['users', $u->id]))));
        }

        return response()->json($results->values());
    }
}
