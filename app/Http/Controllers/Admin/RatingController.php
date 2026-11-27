<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RatingKind;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Rating;
use App\Services\Ratings\ObjectiveRating;
use App\Services\Ratings\RatingBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A18 · Рейтинги в админке: авторская оценка банка (0–5, вывод, плюсы/минусы), просмотр объективного
 * и народного, ручной снимок. Авторская оценка хранится по дням — правка в другой день создаёт новую запись.
 */
class RatingController extends Controller
{
    public function index(RatingBoard $board): View
    {
        return view('admin.ratings.index', ['scores' => $board->all()->values(), 'criteria' => ObjectiveRating::CRITERIA]);
    }

    public function edit(Bank $bank, RatingBoard $board): View
    {
        return view('admin.ratings.edit', [
            'bank' => $bank,
            'score' => $board->for($bank),
            'history' => $bank->ratings()->where('kind', RatingKind::Author)->orderByDesc('period')->take(10)->get(),
            'criteria' => ObjectiveRating::CRITERIA,
        ]);
    }

    public function update(Request $request, Bank $bank): RedirectResponse
    {
        $data = $request->validate([
            'score' => ['required', 'numeric', 'between:0,5'],
            'verdict' => ['nullable', 'string', 'max:2000'],
            'pros' => ['nullable', 'string', 'max:2000'],
            'cons' => ['nullable', 'string', 'max:2000'],
            'assets_bn' => ['nullable', 'numeric', 'min:0'],
            'capital_bn' => ['nullable', 'numeric', 'min:0'],
            'financials_at' => ['nullable', 'date'],
            'founded_year' => ['nullable', 'integer', 'between:1850,'.date('Y')],
            'license' => ['nullable', 'string', 'max:255'],
            'deposit_insured' => ['boolean'],
        ]);
        $lines = fn (?string $t) => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $t))));

        Rating::updateOrCreate(
            ['bank_id' => $bank->id, 'kind' => RatingKind::Author, 'period' => today()->toDateString()],
            ['score' => round((float) $data['score'], 1), 'verdict' => $data['verdict'] ?? null, 'pros' => $lines($data['pros'] ?? null), 'cons' => $lines($data['cons'] ?? null), 'user_id' => $request->user()->id],
        );
        // Данные для объективного рейтинга живут в справочнике банков
        $bank->update([
            'assets_bn' => $data['assets_bn'] ?? null,
            'capital_bn' => $data['capital_bn'] ?? null,
            'financials_at' => $data['financials_at'] ?? null,
            'founded_year' => $data['founded_year'] ?? null,
            'license' => $data['license'] ?? null,
            'deposit_insured' => $request->boolean('deposit_insured'),
        ]);

        return redirect()->route('admin.ratings.index')->with('status', "Оценка «{$bank->displayName()}» сохранена");
    }

    public function snapshot(RatingBoard $board): RedirectResponse
    {
        return back()->with('status', 'Снимок рейтингов сохранён: '.$board->snapshot().' записей');
    }
}
