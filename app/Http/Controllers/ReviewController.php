<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A18 · Отзывы пользователей (народный рейтинг). Один отзыв на банк от пользователя:
 * повторная отправка — правка, которая снова уходит на модерацию. «Полезно» — один голос на отзыв.
 */
class ReviewController extends Controller
{
    public function store(Request $request, Bank $bank): RedirectResponse
    {
        abort_unless($bank->status === 'active', 404);
        $data = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
            'title' => ['required', 'string', 'min:5', 'max:160'],
            'body' => ['required', 'string', 'min:'.config('banq.ratings.review_min_length', 80), 'max:5000'],
            'deposit_product_id' => ['nullable', 'integer', Rule::exists('deposit_products', 'id')->where('bank_id', $bank->id)],
        ], [
            'score.required' => 'Поставьте оценку от 1 до 5.',
            'body.min' => 'Расскажите подробнее — хотя бы :min символов.',
        ]);

        Review::updateOrCreate(
            ['user_id' => $request->user()->id, 'bank_id' => $bank->id],
            $data + ['status' => 'pending', 'reject_reason' => null, 'moderated_by' => null, 'moderated_at' => null, 'ip' => $request->ip()],
        );

        return redirect()->to(route('banks.show', $bank).'#review-form')->with('review_status', 'Спасибо! Отзыв появится после проверки модератором.');
    }

    public function vote(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->status === 'approved', 404);
        $user = $request->user();

        if ($review->user_id !== $user->id) {
            $changes = $review->voters()->toggle([$user->id => ['created_at' => now()]]);
            $review->increment('helpful', count($changes['attached']) - count($changes['detached']));
        }

        return redirect()->to(url()->previous(route('banks.show', $review->bank)).'#review-'.$review->id);
    }
}
