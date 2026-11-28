<?php

namespace App\Http\Controllers;

use App\Models\SavedComparison;
use App\Services\Compare\CompareRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A28 · Сохранённые сравнения: сохранить текущее со страницы сравнения, переименовать, удалить. A53: любого вида продуктов. */
class ComparisonController extends Controller
{
    public const LIMIT = 30;

    public function store(Request $request, CompareRegistry $registry): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(CompareRegistry::TYPES))],
            'ids' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:120'],
            'amount' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'term' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);
        $user = $request->user();
        if ($user->comparisons()->count() >= self::LIMIT) {
            return back()->withErrors(['ids' => 'Сохранено максимум '.self::LIMIT.' сравнений — удалите старые в кабинете.']);
        }

        $type = $registry->get($data['type'] ?? 'deposits');
        $ids = $type->ids($data['ids']);
        $items = $type->items($ids);
        abort_if($items->isEmpty(), 422);
        $deposits = $type->key() === 'deposits'; // сумма и срок — только у вкладов

        $user->comparisons()->create([
            'type' => $type->key(),
            'title' => ($data['title'] ?? null) ?: $type->defaultTitle($items),
            'product_ids' => $items->map(fn ($m) => $m->getKey())->values()->all(),
            'amount' => $deposits ? ($data['amount'] ?? null) : null,
            'term_months' => $deposits ? ($data['term'] ?? null) : null,
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
