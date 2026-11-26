<?php

namespace App\Http\Controllers;

use App\Enums\RatingKind;
use App\Models\Review;
use App\Services\Banks\BankDirectory;
use App\Services\Ratings\ObjectiveRating;
use App\Services\Ratings\RatingBoard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A18 · Страница рейтингов /ratings: три вида рядом, сводная таблица, методика, свежие отзывы. */
class RatingController extends Controller
{
    public function __invoke(Request $request, RatingBoard $board, BankDirectory $directory): View
    {
        $by = RatingKind::tryFrom((string) $request->query('by')) ?? RatingKind::Objective;

        return view('ratings.index', [
            'by' => $by,
            'tops' => collect(RatingKind::cases())->mapWithKeys(fn ($k) => [$k->value => $board->top($k, 5)]),
            'table' => $board->all()->sortBy(fn ($s) => [$s->rank($by) ?? PHP_INT_MAX, $s->bank->sort])->values(),
            'stats' => $directory->stats(),
            'criteria' => ObjectiveRating::CRITERIA,
            'weights' => config('banq.ratings.objective_weights'),
            'latest' => Review::query()->approved()->with(['bank', 'user'])->latest('published_at')->take(4)->get(),
            'reviewsCount' => Review::query()->approved()->count(),
            'prior' => ['C' => config('banq.ratings.people_prior_weight'), 'm' => config('banq.ratings.people_prior_mean')],
        ]);
    }
}
