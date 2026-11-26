{{-- A28 · Мой отзыв в кабинете: статус модерации, причина отказа, ответ банка, ссылки. $r — Review с bank. --}}
<article class="py-5 border-t border-[var(--line)] first:border-t-0">
    <div class="flex items-start gap-3">
        <span class="bank-mark !w-10 !h-10 !text-[0.6rem]" style="--c: {{ $r->bank->brand_color ?? 'var(--brand-1)' }}">{{ $r->bank->mark() }}</span>
        <div class="min-w-0 flex-1">
            <p class="font-semibold leading-tight"><a href="{{ route('banks.show', $r->bank) }}" class="hover:underline">{{ $r->bank->displayName() }}</a></p>
            <p class="text-xs mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="inline-flex items-center gap-1.5 font-semibold status-{{ $r->status }}"><i class="status-dot"></i>{{ $r->statusLabel() }}</span>
                <span class="muted">{{ ($r->published_at ?? $r->updated_at)?->translatedFormat('j F Y') }}</span>
                @if ($r->status === 'approved')<span class="muted tabular">{{ $r->helpful }} «полезно»</span>@endif
            </p>
        </div>
        @include('partials.stars', ['score' => $r->score])
    </div>
    <h3 class="font-semibold mt-3">{{ $r->title }}</h3>
    <p class="text-[15px] leading-[1.55] mt-1 muted line-clamp-3 whitespace-pre-line">{{ $r->body }}</p>
    @if ($r->status === 'rejected' && $r->reject_reason)
        <div class="plate p-3.5 mt-3 text-sm flex gap-2.5">
            @include('partials.icon', ['i' => 'info', 'c' => 'w-4 h-4 mt-0.5 status-rejected shrink-0'])
            <p><b>Причина:</b> {{ $r->reject_reason }}. Исправьте отзыв — он снова уйдёт на модерацию.</p>
        </div>
    @endif
    @if ($r->reply)
        <div class="plate p-3.5 mt-3 text-sm">
            <p class="font-semibold flex items-center gap-2">@include('partials.icon', ['i' => 'quote', 'c' => 'w-4 h-4 muted']) {{ $r->reply_author ?: 'Ответ банка' }}</p>
            <p class="mt-1 whitespace-pre-line line-clamp-3">{{ $r->reply }}</p>
        </div>
    @endif
    <div class="flex flex-wrap gap-2 mt-3">
        <a href="{{ route('banks.show', $r->bank) }}#review-form" class="btn btn-ghost !py-1.5 !px-3 text-[13px]">@include('partials.icon', ['i' => 'pen', 'c' => 'w-3.5 h-3.5']) Изменить</a>
        @if ($r->status === 'approved')<a href="{{ route('banks.show', $r->bank) }}#review-{{ $r->id }}" class="btn btn-outline !py-1.5 !px-3 text-[13px]">На странице банка @include('partials.icon', ['i' => 'arrow', 'c' => 'w-3.5 h-3.5'])</a>@endif
    </div>
</article>
