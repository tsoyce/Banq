{{-- A18 · Отзыв в ленте. $r — App\Models\Review (approved), $showBank — показать банк (страница рейтингов). --}}
<article id="review-{{ $r->id }}" class="py-5 border-t border-[var(--line)] first:border-t-0 scroll-mt-32">
    <header class="flex items-start gap-3">
        <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($r->user->name, 0, 1)) }}</span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold leading-tight">{{ $r->user->name }}
                @if ($showBank ?? false)<span class="muted font-normal">о</span> <a href="{{ route('banks.show', $r->bank) }}#review-{{ $r->id }}" class="hover:underline">{{ $r->bank->displayName() }}</a>@endif
            </p>
            <p class="text-xs muted mt-0.5">{{ $r->published_at?->translatedFormat('j F Y') }}@if ($r->product) · {{ $r->product->name }}@endif</p>
        </div>
        @include('partials.stars', ['score' => $r->score])
    </header>
    <h3 class="text-[17px] font-semibold mt-3">{{ $r->title }}</h3>
    <div class="text-[15px] leading-[1.55] mt-1.5 whitespace-pre-line {{ ($clamp ?? false) ? 'line-clamp-4' : '' }}">{{ $r->body }}</div>
    @if ($r->reply)
        <div class="plate p-4 mt-3 text-sm">
            <p class="font-semibold flex items-center gap-2">@include('partials.icon', ['i' => 'quote', 'c' => 'w-4 h-4 muted']) {{ $r->reply_author ?: 'Ответ банка' }}</p>
            <p class="mt-1 whitespace-pre-line">{{ $r->reply }}</p>
        </div>
    @endif
    @unless ($showBank ?? false)
        <footer class="mt-3 flex items-center gap-3 text-sm">
            @auth
                @if (auth()->id() !== $r->user_id)
                    <form method="POST" action="{{ route('reviews.vote', $r) }}">@csrf
                        <button class="btn !py-1.5 !px-3 text-[13px] {{ $r->voted ? 'btn-primary' : 'btn-ghost' }}" aria-pressed="{{ $r->voted ? 'true' : 'false' }}">@include('partials.icon', ['i' => 'thumb', 'c' => 'w-4 h-4']) Полезно <span class="tabular opacity-70">{{ $r->helpful }}</span></button>
                    </form>
                @else
                    <span class="chip">ваш отзыв · {{ $r->helpful }} «полезно»</span>
                @endif
            @else
                <a href="{{ route('login', ['to' => request()->getRequestUri()]) }}" class="btn btn-ghost !py-1.5 !px-3 text-[13px]">@include('partials.icon', ['i' => 'thumb', 'c' => 'w-4 h-4']) Полезно <span class="tabular opacity-70">{{ $r->helpful }}</span></a>
            @endauth
        </footer>
    @endunless
</article>
