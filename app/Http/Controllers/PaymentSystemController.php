<?php

namespace App\Http\Controllers;

use App\Models\PaymentSystem;
use App\Services\Cards\CardCatalog;
use Illuminate\View\View;

/** A33 · Каталог платёжных систем и страница системы: уровни карт с привилегиями, карты банков текущей страны. */
class PaymentSystemController extends Controller
{
    public function __construct(private CardCatalog $catalog) {}

    public function index(): View
    {
        $here = $this->catalog->systems()->pluck('cards_here', 'id');
        $systems = PaymentSystem::query()->active()->ordered()->with('tiers')->get()
            ->each(fn ($p) => $p->setAttribute('cards_here', (int) ($here[$p->id] ?? 0)));

        return view('payment-systems.index', [
            'systems' => $systems,
            'total' => $this->catalog->all()->count(),
            'scopes' => $systems->groupBy('scope'),
        ]);
    }

    public function show(PaymentSystem $system): View
    {
        abort_unless($system->is_active, 404);
        $system->load('tiers');
        $cards = $this->catalog->all()->filter(fn ($c) => $c->paymentSystems->contains('id', $system->id))->values();
        $offers = $this->catalog->sort($cards->map(fn ($c) => $this->catalog->offer($c)), 'score')->values();
        $byTier = $cards->flatMap(fn ($c) => $c->tiers->where('payment_system_id', $system->id)->pluck('id'))->countBy();

        return view('payment-systems.show', [
            'system' => $system,
            'offers' => $offers,
            'byTier' => $byTier,
            'banks' => $cards->pluck('bank')->unique('id')->values(),
            'others' => PaymentSystem::query()->active()->ordered()->whereKeyNot($system->id)->limit(8)->get(),
        ]);
    }
}
