<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A46 · Выбор страны в верхней плашке админки (session admin.country). Пусто — все доступные страны. */
class CountryScopeController extends Controller
{
    public function __invoke(Request $request, AdminScope $scope): RedirectResponse
    {
        $scope->select($request->integer('country') ?: null);
        $back = (string) $request->input('_back');

        // фильтр «страна» в адресе списка противоречил бы плашке — убираем его и номер страницы
        if ($back !== '' && str_starts_with($back, url('/admin'))) {
            $parts = parse_url($back);
            parse_str($parts['query'] ?? '', $q);
            unset($q['country'], $q['page']);

            return redirect(strtok($back, '?').($q ? '?'.http_build_query($q) : ''));
        }

        return redirect()->route('admin.dashboard');
    }
}
