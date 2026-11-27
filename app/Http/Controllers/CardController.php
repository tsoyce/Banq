<?php

namespace App\Http\Controllers;

use App\Enums\CardKind;
use App\Models\Card;
use App\Services\Cards\CardCatalog;
use App\Services\Cards\CardFilters;
use App\Support\CurrentCountry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** A33 · Витрина карт: каталог с фильтрами (fetch без перезагрузки), карточка карты, сравнение до 4 карт. */
class CardController extends Controller
{
    public function __construct(private CardCatalog $catalog) {}

    public function index(Request $request): View|Response
    {
        $filters = CardFilters::fromRequest($request);
        $data = [
            'filters' => $filters,
            'offers' => $this->catalog->search($filters),
            'kindCounts' => $this->catalog->kindCounts(),
            'systems' => $this->catalog->systems(),
            'banks' => $this->catalog->banks(),
            'currencies' => $this->catalog->currencies(),
            'stats' => $this->catalog->stats(),
        ];

        if ($request->header('X-Partial') === 'results') {
            return response()->view('cards._results', $data);
        }

        return view('cards.index', $data);
    }

    public function show(Card $card): View
    {
        abort_unless($card->is_active && $card->bank?->status === 'active', 404);
        $card->load(['bank.country', 'paymentSystems', 'tiers.paymentSystem', 'packages' => fn ($q) => $q->where('is_active', true)]);
        app(CurrentCountry::class)->set($card->bank->country); // страница живёт в стране банка

        return view('cards.show', [
            'card' => $card,
            'offer' => $this->catalog->offer($card),
            'rank' => $this->catalog->rank($card),
            'sameKind' => $this->catalog->all()->where('kind', $card->kind)->count(),
            'similar' => $this->catalog->similar($card),
            'siblings' => $this->catalog->all()->where('bank_id', $card->bank_id)->whereNotIn('id', [$card->id])->values(),
        ]);
    }

    public function compare(Request $request): View
    {
        $limit = (int) config('banq.cards.compare_limit', 4);
        $ids = collect(explode(',', (string) $request->query('ids')))->map(fn ($id) => (int) $id)->filter()->unique()->take($limit)->values();
        $cards = Card::query()->active()->whereIn('id', $ids)->with(['bank.country', 'paymentSystems', 'tiers'])->get()
            ->sortBy(fn ($c) => $ids->search($c->id))->values();
        $offers = $cards->map(fn (Card $c) => $this->catalog->offer($c));

        return view('cards.compare', [
            'offers' => $offers,
            'limit' => $limit,
            'hasCredit' => $cards->contains(fn (Card $c) => $c->kind === CardKind::Credit),
        ]);
    }
}
