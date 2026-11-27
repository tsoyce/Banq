<?php

namespace App\Http\Controllers;

use App\Models\ServicePackage;
use App\Services\Cards\CardCatalog;
use App\Support\CurrentCountry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A33 · Пакеты услуг банков: список по сегментам и страница пакета (стоимость, состав, карты). */
class ServicePackageController extends Controller
{
    public function __construct(private CardCatalog $catalog) {}

    public function index(Request $request): View
    {
        $segment = array_key_exists((string) $request->query('segment'), ServicePackage::SEGMENTS) ? (string) $request->query('segment') : null;
        $all = ServicePackage::query()->active()->inCountry()->with(['bank.country', 'cards.paymentSystems', 'cards.tiers'])
            ->orderBy('sort')->orderBy('monthly_fee')->get();

        return view('packages.index', [
            'packages' => $segment ? $all->where('segment', $segment)->values() : $all,
            'counts' => $all->countBy('segment')->all(),
            'segment' => $segment,
            'total' => $all->count(),
        ]);
    }

    public function show(ServicePackage $package): View
    {
        abort_unless($package->is_active && $package->bank?->status === 'active', 404);
        $package->load(['bank.country', 'cards' => fn ($q) => $q->where('is_active', true)->with(['paymentSystems', 'tiers'])]);
        app(CurrentCountry::class)->set($package->bank->country);

        return view('packages.show', [
            'package' => $package,
            'offers' => $package->cards->map(fn ($c) => $this->catalog->offer($c)),
            'others' => ServicePackage::query()->active()->inCountry()->whereKeyNot($package->id)->with('bank')
                ->orderByRaw('bank_id = ? desc', [$package->bank_id])->orderBy('monthly_fee')->limit(6)->get(),
        ]);
    }
}
