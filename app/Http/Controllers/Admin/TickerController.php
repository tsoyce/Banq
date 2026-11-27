<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ticker\Ticker;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** A40 · Админка → Бегущая строка: включение, типы контента и их порядок, свой текст, скорость. */
class TickerController extends Controller
{
    public function edit(Ticker $ticker): View
    {
        $groups = $ticker->groups();

        return view('admin.ticker.edit', [
            'sources' => collect($ticker->sources())->map(fn ($s) => ['label' => $s->label(), 'hint' => $s->hint()])->all(),
            'selected' => $ticker->selected(),
            'enabled' => $ticker->enabled(),
            'text' => (string) settings('ticker.text', ''),
            'speed' => (int) settings('ticker.speed', 5),
            'preview' => $groups,
            'previewDuration' => $ticker->duration($groups),
        ]);
    }

    public function update(Request $request, Settings $settings, Ticker $ticker): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'sources' => ['nullable', 'array'],
            'sources.*' => [Rule::in(array_keys($ticker->sources()))],
            'text' => ['nullable', 'string', 'max:2000'],
            'speed' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $settings->set([
            'ticker.enabled' => $request->boolean('enabled'),
            'ticker.sources' => array_values(array_unique($data['sources'] ?? [])),
            'ticker.text' => $data['text'] ?? null,
            'ticker.speed' => (int) $data['speed'],
        ], 'ticker');

        return back()->with('status', 'Бегущая строка сохранена');
    }
}
