<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A18 · Модерация отзывов: очередь, одобрить / отклонить с причиной, ответ банка, удаление. */
class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('status'), Review::STATUSES) ? $request->query('status') : 'pending';

        return view('admin.reviews.index', [
            'status' => $status,
            'reviews' => Review::query()->where('status', $status)->with(['user', 'bank', 'product'])
                ->orderBy($status === 'pending' ? 'updated_at' : 'moderated_at', $status === 'pending' ? 'asc' : 'desc')
                ->paginate(20)->withQueryString(),
            'counts' => Review::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,reply'],
            'reject_reason' => ['nullable', 'required_if:action,reject', 'string', 'max:255'],
            'reply' => ['nullable', 'string', 'max:3000'],
            'reply_author' => ['nullable', 'string', 'max:120'],
        ], ['reject_reason.required_if' => 'Укажите причину — её увидит автор.']);

        $changes = ['reply' => $data['reply'] ?? $review->reply, 'reply_author' => $data['reply_author'] ?? $review->reply_author];
        if ($data['action'] !== 'reply') {
            $changes += [
                'status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
                'reject_reason' => $data['action'] === 'reject' ? $data['reject_reason'] : null,
                'moderated_by' => $request->user()->id,
                'moderated_at' => now(),
                'published_at' => $data['action'] === 'approve' ? ($review->published_at ?? now()) : $review->published_at,
            ];
        }
        $review->update($changes);

        return back()->with('status', match ($data['action']) {
            'approve' => 'Отзыв опубликован',
            'reject' => 'Отзыв отклонён',
            default => 'Ответ сохранён',
        });
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return back()->with('status', 'Отзыв удалён');
    }
}
