<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Country;
use App\Models\DepositProduct;
use App\Models\DepositRate;
use App\Models\Import;
use App\Models\Review;
use App\Support\Installation;
use Illuminate\View\View;

/** A09 · Обзор админки. A24: сводка по странам и «здоровье данных» — что заполнить в первую очередь. */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stale = today()->subDays(60);

        return view('admin.dashboard', [
            'stats' => [
                ['Стран', Country::count(), route('admin.resource.index', 'countries')],
                ['Банков', Bank::count(), route('admin.resource.index', 'banks')],
                ['Вкладов', DepositProduct::count(), route('admin.resource.index', 'deposits')],
                ['Действующих ставок', DepositRate::current()->count(), route('admin.resource.index', ['rates', 'state' => 'current'])],
            ],
            'countries' => app(\App\Support\AdminScope::class)->apply(Country::query()->ordered(), 'id')->withCount([ // A46
                'banks', 'banks as active_banks_count' => fn ($q) => $q->active(),
            ])->get()->map(fn (Country $c) => [
                'country' => $c,
                'products' => DepositProduct::whereHas('bank', fn ($q) => $q->where('country_id', $c->id))->count(),
                'rates' => DepositRate::current()->whereHas('product.bank', fn ($q) => $q->where('country_id', $c->id))->count(),
            ]),
            'health' => [
                ['Банки без вкладов', Bank::active()->doesntHave('depositProducts')->count(), route('admin.resource.index', ['banks', 'data' => 'empty'])],
                ['Банки без действующих ставок', Bank::active()->whereDoesntHave('depositProducts', fn ($p) => $p->whereHas('currentRates'))->count(), route('admin.resource.index', ['banks', 'data' => 'norates'])],
                ['Вклады без ставок', DepositProduct::active()->doesntHave('currentRates')->count(), route('admin.resource.index', ['deposits', 'rates' => 'none'])],
                ['Ставки старше 60 дней', DepositProduct::active()->whereHas('currentRates')->whereDoesntHave('currentRates', fn ($r) => $r->where('valid_from', '>=', $stale))->count(), route('admin.resource.index', ['deposits', 'rates' => 'stale'])],
                ['Банки без финансов (рейтинг)', Bank::active()->whereNull('assets_bn')->count(), route('admin.resource.index', ['banks', 'data' => 'nofin'])],
                ['Отзывы на модерации', Review::pending()->count(), route('admin.reviews.index')],
            ],
            'imports' => Import::query()->with(['user', 'token'])->latest('id')->take(6)->get(),
            'install' => Installation::info(),
        ]);
    }
}
