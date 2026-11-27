<?php

namespace App\Http\Controllers;

use App\Enums\RatingKind;
use App\Models\Bank;
use App\Models\Review;
use App\Services\Ratings\RatingBoard;
use App\Support\Market;
use Illuminate\View\View;

/** A10/A13/A18 · Главная-дашборд. Данные — App\Support\Market. */
class HomeController extends Controller
{
    public function __invoke(Market $market, RatingBoard $board): View
    {
        $rates = $market->rates();

        return view('home', [
            'leaders' => $market->leaders(),
            'best' => $rates->first(),
            'top' => $market->top(8),
            'banksCount' => Bank::query()->active()->inCountry()->count(),
            'ratesCount' => $rates->count(),
            'updatedAt' => $rates->max('updated_at'),
            // A18 · Живой блок рейтингов: народный, а пока отзывов нет — объективный
            'ratingKind' => $kind = $board->top(RatingKind::People, 1)->isNotEmpty() ? RatingKind::People : RatingKind::Objective,
            'ratingTop' => $board->top($kind, 3),
            'reviewsCount' => Review::query()->approved()->whereHas('bank', fn ($b) => $b->inCountry())->count(),
        ]);
    }
}
