<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CurrencyRate;
use App\Services\Currency\CurrencyRates;
use App\Support\CurrencyInfo;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/** A40 · Админка → Курсы валют: источник по стране, статус, «обновить сейчас», выбор валют для карточки и ленты. */
class CurrencyController extends Controller
{
    public function index(CurrencyRates $fx): View
    {
        $countries = app(\App\Support\AdminScope::class)->apply(Country::query()->active()->ordered(), 'id')->get()->map( // A46 · страны из плашки
            function (Country $c) use ($fx) {
            $provider = $fx->provider($c);
            $date = $provider ? $fx->latestDate($c) : null;

            return [
                'country' => $c,
                'provider' => $provider,
                'status' => $fx->lastStatus($c),
                'date' => $date,
                'codes' => $provider ? $fx->codes($c) : [],
                'available' => $provider ? ($fx->available($c) ?: collect($fx->codes($c))->mapWithKeys(fn ($k) => [$k => CurrencyInfo::name($k)])->all()) : [],
                'rows' => $date ? CurrencyRate::query()->where('country_id', $c->id)->whereDate('date', $date)->orderBy('currency')->get() : collect(),
                'days' => $provider ? CurrencyRate::query()->where('country_id', $c->id)->distinct()->count('date') : 0,
            ];
        });

        return view('admin.currency.index', ['countries' => $countries, 'homeCard' => (bool) settings('fx.home_card', true)]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'codes' => ['nullable', 'array'],
            'codes.*' => ['nullable', 'array'],
            'codes.*.*' => ['string', 'regex:/^[A-Za-z]{3}$/'],
            'home_card' => ['nullable', 'boolean'],
        ]);

        $all = (array) settings('fx.currencies', []);
        foreach ($data['codes'] ?? [] as $country => $codes) {
            if (Country::query()->where('code', strtoupper($country))->exists()) {
                $all[strtoupper($country)] = array_values(array_unique(array_map('strtoupper', $codes ?? [])));
            }
        }
        $settings->set(['fx.currencies' => $all, 'fx.home_card' => $request->boolean('home_card')], 'fx');

        return back()->with('status', 'Курсы валют: настройки сохранены');
    }

    public function sync(Request $request, Country $country, CurrencyRates $fx): RedirectResponse
    {
        @set_time_limit(180);
        try {
            $days = min(60, max(0, (int) $request->input('days', 0)));
            $message = $days
                ? 'Загружена история: '.$fx->backfill($country, $days).' дат'
                : 'Получено курсов: '.$fx->sync($country).' на '.$fx->latestDate($country)?->translatedFormat('j F');
        } catch (Throwable $e) {
            return back()->withErrors(['sync' => $fx->provider($country)?->short().' не ответил: '.$e->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
