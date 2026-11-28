{{-- A51 · Правка объявления: как оно выглядит в подходящем месте (без учёта показов). --}}
@php
    $slot = \App\Models\AdSlot::query()->orderBy('sort')->get()->first(fn ($s) => $s->accepts($creative->type) && (! $creative->campaign?->slots || in_array($s->key, $creative->campaign->slots, true)));
    $ok = \App\Services\Ads\AdServer::complete($creative->only(['type', 'title', 'image', 'url', 'html']));
@endphp
<div class="card p-6 space-y-4">
    <div class="flex items-center justify-between gap-3">
        <p class="font-display font-bold text-lg">Как выглядит</p>
        <span class="chip {{ $ok && $creative->is_active ? '!text-[var(--ok)]' : 'faint' }}">{{ ! $ok ? 'не заполнено' : ($creative->is_active ? 'в показе' : 'выключено') }}</span>
    </div>
    @if ($slot)
        <p class="text-xs muted">Место «{{ $slot->name }}»</p>
        <div class="max-w-full" style="{{ $slot->width <= 400 ? 'max-width: 320px' : '' }}">
            @include('ads.slot', ['adPick' => ['slot' => $slot->toArray(), 'creative' => $creative->only(['id', 'campaign_id', 'type', 'title', 'body', 'cta', 'image', 'image_mobile', 'alt', 'url', 'sponsor', 'disclosure', 'html']), 'fallback' => null, 'token' => null]])
        </div>
    @else
        <p class="text-sm muted">Нет мест для формата «{{ \App\Models\AdCreative::TYPES[$creative->type] ?? $creative->type }}» — проверьте форматы мест и места кампании.</p>
    @endif
    <div class="grid grid-cols-3 gap-3">
        <div class="plate p-3"><p class="label">Показы</p><p class="text-xl font-bold tabular mt-1">{{ number_format($creative->impressions, 0, ',', ' ') }}</p></div>
        <div class="plate p-3"><p class="label">Клики</p><p class="text-xl font-bold tabular mt-1">{{ $creative->clicks }}</p></div>
        <div class="plate p-3"><p class="label">CTR</p><p class="text-xl font-bold tabular mt-1">{{ $creative->ctr() !== null ? number_format($creative->ctr(), 2, ',', '').'%' : '—' }}</p></div>
    </div>
</div>
