<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Support\Market;
use Illuminate\View\View;

/** A10/A13 · Главная-дашборд. Данные — App\Support\Market. */
class HomeController extends Controller
{
    public function __invoke(Market $market): View
    {
        $rates = $market->rates();

        return view('home', [
            'leaders' => $market->leaders(),
            'best' => $rates->first(),
            'top' => $market->top(8),
            'banksCount' => Bank::query()->active()->count(),
            'ratesCount' => $rates->count(),
            'updatedAt' => $rates->max('updated_at'),
        ]);
    }
}
