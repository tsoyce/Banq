<?php

namespace App\Http\Controllers;

use App\Models\DepositProduct;
use App\Models\SavedComparison;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A28 · Сохранённые сравнения вкладов: сохранить текущее со страницы сравнения, переименовать, удалить. */
class ComparisonController extends Controller
{
    public const LIMIT = 30;

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:120'],
            'amount' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'term' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);
        $user = $request->user();
        if ($user->comparisons()->count() >= self::LIMIT) {
            return back()->withErrors(['ids' => 'Сохранено максимум '.self::LIMIT.' сравнений — удалите старые в кабинете.']);
        }

        $ids = collect(explode(',', $data['ids']))->map(fn ($id) => (int) $id)->filter()->unique()->take(DepositController::COMPARE_LIMIT)->values();
        $products = DepositProduct::query()->whereIn('id', $ids)->with('bank')->get();
        abort_if($products->isEmpty(), 422);
        $ids = $ids->intersect($products->pluck('id'))->values();

        $user->comparisons()->create([
            'title' => ($data['title'] ?? null) ?: $products->map(fn ($p) => $p->bank->code)->unique()->join(' · '),
            'product_ids' => $ids->all(),
            'amount' => $data['amount'] ?? null,
            'term_months' => $data['term'] ?? null,
        ]);

        return back()->with('status', 'Сравнение сохранено в кабинете');
    }

    public function update(Request $request, SavedComparison $comparison): RedirectResponse
    {
        abort_unless($comparison->user_id === $request->user()->id, 404);
        $comparison->update($request->validate(['title' => ['required', 'string', 'max:120']]));

        return back()->with('status', 'Название сохранено');
    }

    public function destroy(Request $request, SavedComparison $comparison): RedirectResponse
    {
        abort_unless($comparison->user_id === $request->user()->id, 404);
        $comparison->delete();

        return back()->with('status', 'Сравнение удалено');
    }
}
