<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A28 · Публичная карточка автора /u/{username}: кто пишет отзывы в народный рейтинг. */
class ProfileController extends Controller
{
    public function show(Request $request, string $username): View
    {
        $user = User::where('username', strtolower($username))->firstOrFail();
        $own = $request->user()?->is($user);
        abort_unless($user->is_public || $own || $request->user()?->isStaff(), 404);

        $approved = $user->reviews()->approved();
        $published = (clone $approved)->count();
        $helpful = (int) (clone $approved)->sum('helpful');
        $dist = (clone $approved)->selectRaw('score, count(*) as c')->groupBy('score')->pluck('c', 'score');

        return view('profile.show', [
            'user' => $user,
            'own' => $own,
            'stats' => [
                'published' => $published,
                'helpful' => $helpful,
                'avg' => $published ? round((float) (clone $approved)->avg('score'), 1) : null,
                'banks' => (clone $approved)->distinct('bank_id')->count('bank_id'),
            ],
            'dist' => $dist,
            'level' => $user->level($published, $helpful),
            'reviews' => $user->reviews()->approved()->with(['bank', 'product', 'user'])
                ->orderByDesc('published_at')->paginate(10, pageName: 'p')->withQueryString(),
        ]);
    }
}
