{{-- A51 · Правка кампании: объявления и показы по дням за 30 дней. --}}
@php($fmt = fn ($n) => number_format((int) $n, 0, ',', ' '))
<div class="card p-6 space-y-3">
    <div class="flex items-center justify-between gap-3">
        <p class="font-display font-bold text-lg">Объявления</p>
        <a href="{{ route('admin.resource.create', ['ad-creatives', 'campaign' => $campaign->id]) }}" class="btn btn-primary !py-2 text-sm">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Добавить</a>
    </div>
    @forelse ($creatives as $cr)
        <a href="{{ route('admin.resource.edit', ['ad-creatives', $cr->id]) }}" class="flex items-center gap-3 py-2 border-t border-[var(--line)] first:border-0 hover:opacity-80 {{ $cr->is_active ? '' : 'opacity-50' }}">
            @if ($cr->image)<img src="{{ $cr->image }}" alt="" class="w-14 h-10 object-cover rounded-lg">@else<span class="icon-tile">@include('partials.icon', ['i' => $cr->type === 'html' ? 'code' : 'image', 'c' => 'w-4 h-4'])</span>@endif
            <span class="min-w-0 flex-1"><b class="block truncate">{{ $cr->name }}</b><span class="text-xs muted">{{ \App\Models\AdCreative::TYPES[$cr->type] ?? $cr->type }}</span></span>
            <span class="text-sm tabular text-right">{{ $fmt($cr->impressions) }}<span class="block text-xs faint">{{ $cr->clicks }} кликов</span></span>
        </a>
    @empty
        <p class="text-sm muted">Пока пусто — без объявлений кампания ничего не показывает.</p>
    @endforelse
</div>
<div class="card p-6">
    <p class="font-display font-bold text-lg mb-3">За 30 дней</p>
    @if ($days->isEmpty())
        <p class="text-sm muted">Показов ещё не было.</p>
    @else
        <table class="w-full text-sm tabular">
            <thead><tr class="faint text-xs text-left"><th class="py-1 font-semibold">День</th><th class="font-semibold text-right">Показы</th><th class="font-semibold text-right">Клики</th><th class="font-semibold text-right">CTR</th></tr></thead>
            <tbody>
                @foreach ($days->reverse() as $date => $d)
                    <tr class="border-t border-[var(--line)]"><td class="py-1.5">{{ \Illuminate\Support\Carbon::parse($date)->format('d.m') }}</td><td class="text-right">{{ $fmt($d->v) }}</td><td class="text-right">{{ (int) $d->c }}</td><td class="text-right">{{ $d->v ? number_format($d->c / $d->v * 100, 2, ',', '').'%' : '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
