{{-- A37 · Один комментарий (и его ответы). $c — Comment, $reply — это ответ. --}}
<li class="comment {{ ($reply ?? false) ? 'is-reply' : '' }}" id="comment-{{ $c->id }}">
    @include('partials.avatar', ['u' => $c->user, 'c' => ($reply ?? false) ? '!w-8 !h-8 text-xs' : '!w-10 !h-10 text-sm'])
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap text-sm">
            @if ($c->user?->profileUrl())<a href="{{ $c->user->profileUrl() }}" class="font-semibold hover:underline underline-offset-4">{{ $c->user->name }}</a>@else<b>{{ $c->user?->name ?? 'Читатель' }}</b>@endif
            @if ($c->user?->isStaff())<span class="chip !py-0 !px-2 !text-[0.68rem]" style="background: var(--grad); color: #fff">редакция</span>@endif
            <time class="faint text-xs" datetime="{{ $c->created_at->toIso8601String() }}" title="{{ $c->created_at->translatedFormat('j F Y, H:i') }}">{{ $c->created_at->diffForHumans() }}</time>
            @if ($c->status !== 'approved')<span class="chip status-pending !py-0 !text-[0.68rem]"><span class="status-dot"></span>{{ \App\Models\Comment::STATUSES[$c->status] }}</span>@endif
        </div>
        <p class="comment-body">{{ $c->body }}</p>
        <div class="flex items-center gap-3 text-xs font-semibold muted mt-1.5">
            @auth
                @if ($post->allow_comments && $post->isPublished())<button type="button" class="hover:text-[var(--text)] inline-flex items-center gap-1" @click="replyTo({{ $c->parent_id ?? $c->id }}, @js($c->user?->name))">@include('partials.icon', ['i' => 'reply', 'c' => 'w-3.5 h-3.5']) Ответить</button>@endif
                @if (auth()->id() === $c->user_id || auth()->user()->isStaff())
                    <form method="POST" action="{{ route('blog.comments.destroy', $c) }}" onsubmit="return confirm('Удалить комментарий?')">@csrf @method('DELETE')<button class="hover:text-[var(--bad)]">Удалить</button></form>
                @endif
            @endauth
        </div>
        @if (! ($reply ?? false) && $c->replies->isNotEmpty())
            <ul class="comment-replies">
                @foreach ($c->replies as $r)@include('blog._comment', ['c' => $r, 'reply' => true])@endforeach
            </ul>
        @endif
    </div>
</li>
