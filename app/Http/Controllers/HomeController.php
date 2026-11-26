<?php

namespace App\Http\Controllers;

use App\Enums\DepositType;
use App\Models\Bank;
use App\Models\DepositRate;
use Illuminate\View\View;

/** A10 · Главная-дашборд. */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        $rates = DepositRate::query()->current()
            ->with('product.bank')
            ->whereHas('product', fn ($q) => $q->active())
            ->orderByDesc('rate')
            ->get();

        $pulse = collect(DepositType::cases())->map(function (DepositType $type) use ($rates) {
            $top = $rates->first(fn ($r) => $r->product->type === $type);

            return ['type' => $type, 'rate' => $top?->rate, 'bank' => $top?->product->bank];
        });

        return view('home', [
            'pulse' => $pulse,
            'top' => $rates->unique(fn ($r) => $r->product->bank_id.'-'.$r->product->type->value)->take(8),
            'banksCount' => Bank::query()->active()->count(),
            'ratesCount' => $rates->count(),
            'updatedAt' => $rates->max('updated_at'),
        ]);
    }
}
