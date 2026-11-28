<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdSlot;
use App\Models\AdStat;
use App\Services\Ads\Snippets;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** A51 · Админка → Реклама → Обзор: показы и клики за 30 дней, кампании, места; настройки показа и ads.txt (A50). */
class AdsController extends Controller
{
    public function index(Snippets $snippets): View
    {
        $from = today()->subDays(29);
        $rows = AdStat::query()->where('date', '>=', $from)->selectRaw('date, sum(impressions) as v, sum(clicks) as c')->groupBy('date')->get()
            ->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());
        $days = collect(range(0, 29))->map(fn ($i) => $from->copy()->addDays($i)->toDateString())
            ->map(fn ($d) => ['date' => $d, 'v' => (int) ($rows[$d]->v ?? 0), 'c' => (int) ($rows[$d]->c ?? 0)]);
        $bySlot = AdStat::query()->where('date', '>=', $from)->selectRaw('slot, sum(impressions) as v, sum(clicks) as c')->groupBy('slot')->get()->keyBy('slot');

        return view('admin.ads.index', [
            'days' => $days,
            'total' => ['v' => $days->sum('v'), 'c' => $days->sum('c')],
            'campaigns' => AdCampaign::query()->withCount('creatives')->orderByDesc('is_active')->orderByDesc('impressions')->limit(12)->get(),
            'slots' => AdSlot::query()->orderBy('sort')->get(),
            'bySlot' => $bySlot,
            'autoAdsTxt' => $snippets->adsTxtLines(),
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'max_per_page' => ['required', 'integer', 'min:0', 'max:6'],
            'feed_position' => ['required', 'integer', 'min:0', 'max:30'],
            'label' => ['required', 'string', 'max:40'],
            'hide_for_staff' => ['nullable', 'boolean'],
            'ads_txt' => ['nullable', 'string', 'max:20000'],
        ]);
        $values = [
            'ads.enabled' => $request->boolean('enabled'),
            'ads.max_per_page' => (int) $data['max_per_page'],
            'ads.feed_position' => (int) $data['feed_position'],
            'ads.label' => $data['label'],
            'ads.hide_for_staff' => $request->boolean('hide_for_staff'),
        ];
        if ($request->user()->role === 'admin') { // ads.txt — только администратор
            $values['ads.txt'] = $data['ads_txt'] ?? null;
        }
        $settings->set($values, 'ads');

        return back()->with('status', 'Настройки рекламы сохранены');
    }
}
