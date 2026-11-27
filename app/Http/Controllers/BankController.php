<?php

namespace App\Http\Controllers;

use App\Enums\RatingKind;
use App\Models\Bank;
use App\Services\Banks\BankDirectory;
use App\Services\Cards\CardCatalog;
use App\Services\Deposits\DepositCatalog;
use App\Services\Deposits\DepositFilters;
use App\Services\Ratings\BankScore;
use App\Services\Ratings\ObjectiveRating;
use App\Services\Ratings\RatingBoard;
use App\Support\CurrentCountry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A18 · Витрина банков: каталог /banks и страница банка /banks/{slug}
 * (о банке, все вклады, лучшие ставки, три рейтинга, отзывы).
 */
class BankController extends Controller
{
    public const SORTS = ['objective' => 'По рейтингу', 'people' => 'Народный', 'rate' => 'По ставке', 'name' => 'По алфавиту'];

    public function __construct(private RatingBoard $board, private BankDirectory $directory) {}

    public function index(Request $request): View
    {
        $sort = array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'objective';
        $stats = $this->directory->stats();
        $q = trim((string) $request->query('q'));

        $scores = $this->board->all()
            ->when($q !== '', fn ($c) => $c->filter(fn (BankScore $s) => str_contains(
                Bank::normalize(implode(' ', [$s->bank->name, $s->bank->short_name, $s->bank->code, ...($s->bank->aliases ?? [])])),
                Bank::normalize($q),
            )));

        $scores = match ($sort) {
            'name' => $scores->sortBy(fn (BankScore $s) => mb_strtolower($s->bank->displayName())),
            'rate' => $scores->sortByDesc(fn (BankScore $s) => (float) ($stats[$s->bank->id]['best']?->rate ?? 0)),
            'people' => $scores->sortBy(fn (BankScore $s) => [$s->rank(RatingKind::People) ?? PHP_INT_MAX, $s->bank->sort]),
            default => $scores->sortBy(fn (BankScore $s) => [$s->rank(RatingKind::Objective) ?? PHP_INT_MAX, $s->bank->sort]),
        };

        return view('banks.index', [
            'scores' => $scores->values(),
            'stats' => $stats,
            'sort' => $sort,
            'q' => $q,
            'leader' => $this->board->top(RatingKind::Objective, 1)->first(),
            'total' => $this->board->all()->count(),
            'kdifCount' => $this->board->all()->filter(fn (BankScore $s) => $s->bank->deposit_insured)->count(),
        ]);
    }

    public function show(Request $request, Bank $bank, DepositCatalog $catalog, CardCatalog $cards): View
    {
        abort_unless($bank->status === 'active', 404);
        app(CurrentCountry::class)->set($bank->country); // A21 · страница живёт в стране банка
        $score = $this->board->for($bank);
        $offers = $catalog->search(new DepositFilters(banks: [$bank->slug]));

        $reviews = $bank->reviews()->approved()->with(['user', 'product'])
            ->withExists(['voters as voted' => fn ($q) => $q->whereKey($request->user()?->id ?? 0)])
            ->orderByDesc($request->query('reviews') === 'helpful' ? 'helpful' : 'published_at')->orderByDesc('id')
            ->paginate(10, pageName: 'p')->withQueryString()->fragment('reviews');

        return view('banks.show', [
            'bank' => $bank,
            'score' => $score,
            'stats' => $this->directory->for($bank),
            'offers' => $offers,
            'filters' => new DepositFilters,
            'reviews' => $reviews,
            'mine' => $request->user() ? $bank->reviews()->where('user_id', $request->user()->id)->first() : null,
            'products' => $bank->depositProducts()->active()->orderBy('name')->get(['id', 'name']),
            'criteria' => ObjectiveRating::CRITERIA,
            'weights' => config('banq.ratings.objective_weights'),
            'totalRanked' => collect(RatingKind::cases())->mapWithKeys(fn ($k) => [$k->value => $this->board->top($k)->count()]),
            // A33 · карты и пакеты банка
            'cardOffers' => $cards->sort($cards->all()->where('bank_id', $bank->id)->map(fn ($c) => $cards->offer($c)), 'score')->values(),
            'packages' => $bank->servicePackages()->where('is_active', true)->with(['bank.country', 'cards'])->orderBy('sort')->orderBy('monthly_fee')->get(),
        ]);
    }
}
