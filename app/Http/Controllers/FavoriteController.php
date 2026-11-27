<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Card;
use App\Models\DepositProduct;
use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A28 · Избранное: переключить вклад или банк. Отвечает JSON для fetch (resources/js/app.js → favorite) или редиректом назад. */
class FavoriteController extends Controller
{
    public function toggle(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(Favorite::TYPES))],
            'id' => ['required', 'integer'],
        ]);

        $model = match ($data['type']) {
            'bank' => Bank::query()->active()->findOrFail($data['id']),
            'card' => Card::query()->active()->findOrFail($data['id']), // A30
            default => DepositProduct::query()->active()->findOrFail($data['id']),
        };

        $user = $request->user();
        $attrs = ['favoritable_type' => $data['type'], 'favoritable_id' => $model->getKey()];
        $existing = $user->favorites()->where($attrs)->first();
        $existing ? $existing->delete() : $user->favorites()->create($attrs);
        $user->forgetFavorites();
        $on = ! $existing;

        if ($request->wantsJson()) {
            return response()->json(['on' => $on, 'count' => $user->favorites()->count()]);
        }

        return back()->with('status', $on ? 'Добавлено в избранное' : 'Убрано из избранного');
    }
}
