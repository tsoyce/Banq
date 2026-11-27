<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Card;
use App\Models\DepositProduct;
use App\Models\Review;
use App\Models\User;
use App\Services\Banks\BankDirectory;
use App\Services\Cards\CardCatalog;
use App\Services\Deposits\DepositCatalog;
use App\Services\Deposits\DepositFilters;
use App\Services\Deposits\DepositOffer;
use App\Services\Ratings\RatingBoard;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A28 · Личный кабинет /account: обзор, мои отзывы, избранное, сохранённые сравнения, настройки.
 * Изменения настроек — Account\SettingsController.
 */
class AccountController extends Controller
{
    public function __construct(private DepositCatalog $catalog) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('account.index', $this->shell($user) + [
            'recentReviews' => $user->reviews()->with('bank')->latest('updated_at')->take(3)->get(),
            'deposits' => $this->favoriteDeposits($user)->take(3),
            'comparisons' => $user->comparisons()->latest()->take(3)->get(),
        ]);
    }

    public function reviews(Request $request): View
    {
        $user = $request->user();
        $status = array_key_exists((string) $request->query('status'), Review::STATUSES) ? $request->query('status') : null;

        return view('account.reviews', $this->shell($user) + [
            'status' => $status,
            'reviews' => $user->reviews()->with(['bank', 'product'])->when($status, fn ($q) => $q->where('status', $status))
                ->latest('updated_at')->paginate(10)->withQueryString(),
            // Банки с вкладами, о которых пользователь ещё не писал
            'suggest' => Bank::query()->active()->whereNotIn('id', $user->reviews()->select('bank_id'))
                ->whereHas('depositProducts')->orderBy('sort')->take(6)->get(),
        ]);
    }

    public function favorites(Request $request, RatingBoard $board, BankDirectory $directory, CardCatalog $cards): View
    {
        $user = $request->user();
        $banks = Bank::query()->active()
            ->whereIn('id', $user->favorites()->where('favoritable_type', 'bank')->select('favoritable_id'))->get();

        return view('account.favorites', $this->shell($user) + [
            'deposits' => $this->favoriteDeposits($user),
            'banks' => $banks->map(fn (Bank $b) => ['bank' => $b, 'score' => $board->for($b), 'stats' => $directory->for($b)]),
            'filters' => new DepositFilters,
            // A33 · избранные карты (строками каталога с оценкой Banq)
            'cards' => Card::query()->active()->whereIn('id', $user->favorites()->where('favoritable_type', 'card')->select('favoritable_id'))
                ->with(['bank.country', 'paymentSystems', 'tiers'])->get()->map(fn (Card $c) => $cards->offer($c))->sortByDesc('score')->values(),
        ]);
    }

    public function comparisons(Request $request): View
    {
        $user = $request->user();

        return view('account.comparisons', $this->shell($user) + [
            'comparisons' => $user->comparisons()->latest()->get()->map(fn ($c) => ['item' => $c, 'products' => $c->products()]),
        ]);
    }

    public function settings(Request $request): View
    {
        return view('account.settings', $this->shell($request->user()) + [
            'countries' => Countries::LIST,
            'notify' => User::NOTIFY,
        ]);
    }

    /** Шапка кабинета: счётчики и ступень автора — на всех вкладках. */
    private function shell(User $user): array
    {
        $counts = $user->reviews()->selectRaw('status, count(*) as c, sum(helpful) as h')->groupBy('status')->get()->keyBy('status');
        $published = (int) ($counts['approved']->c ?? 0);
        $helpful = (int) ($counts['approved']->h ?? 0);

        return [
            'user' => $user,
            'stats' => [
                'published' => $published,
                'pending' => (int) ($counts['pending']->c ?? 0),
                'rejected' => (int) ($counts['rejected']->c ?? 0),
                'helpful' => $helpful,
                'favorites' => $user->favorites()->count(),
                'comparisons' => $user->comparisons()->count(),
            ],
            'level' => $user->level($published, $helpful),
        ];
    }

    /** @return Collection<int, DepositOffer> избранные вклады с лучшей текущей ставкой (₸) */
    private function favoriteDeposits(User $user): Collection
    {
        $filters = new DepositFilters;

        return DepositProduct::query()->active()
            ->whereIn('id', $user->favorites()->where('favoritable_type', 'deposit')->select('favoritable_id'))
            ->whereHas('bank', fn ($q) => $q->active())->with('bank')->get()
            ->map(function (DepositProduct $p) use ($filters) {
                $rate = $this->catalog->bestRate($p, $filters);

                return $rate ? new DepositOffer($p, $rate, $p->currentRates()->count()) : null;
            })
            ->filter()->sortByDesc(fn (DepositOffer $o) => (float) $o->rate->rate)->values();
    }
}
