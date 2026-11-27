<?php

namespace App\Http\Controllers;

use App\Enums\RatingKind;
use App\Models\Bank;
use App\Models\PaymentSystem;
use App\Models\Review;
use App\Models\ServicePackage;
use App\Services\Cards\CardCatalog;
use App\Services\Currency\CurrencyRates;
use App\Services\Ratings\RatingBoard;
use App\Support\Market;
use Illuminate\View\View;

/** A10/A13/A18/A40 · Главная-дашборд. Данные — App\Support\Market. */
class HomeController extends Controller
{
    public function __invoke(Market $market, RatingBoard $board, CardCatalog $cards, CurrencyRates $fx): View
    {
        $rates = $market->rates();
        // A40 · Карточка курсов валют (если у страны есть источник и карточка не выключена в админке)
        if ($showFx = (bool) settings('fx.home_card', true)) {
            $fx->ensureFresh(country());
        }

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
            // A33 · плитки раздела «Карты»
            'cardsCount' => $cards->all()->count(),
            'topCashback' => $cards->all()->max(fn ($c) => (float) $c->cashback_max) ?: null,
            'systemsCount' => PaymentSystem::query()->active()->count(),
            'packagesCount' => ServicePackage::query()->active()->inCountry()->count(),
            'fx' => $showFx ? $fx->board(country()) : null,
        ]);
    }
}
