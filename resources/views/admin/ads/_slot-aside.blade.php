{{-- A51 · Правка места: где стоит в шаблоне, статистика за 30 дней, пример резерва. --}}
<div class="card p-6 space-y-4">
    <p class="font-display font-bold text-lg">Как поставить</p>
    <p class="text-sm muted">Место выводится строкой в шаблоне — её можно перенести в любой блок страницы:</p>
    <pre class="plate p-3 text-xs font-mono overflow-x-auto">@@include('ads.slot', ['slot' => '{{ $slot->key }}'])</pre>
    <div class="grid grid-cols-2 gap-3">
        <div class="plate p-4"><p class="label">Показов за 30 дней</p><p class="text-2xl font-bold tabular mt-1">{{ number_format($views, 0, ',', ' ') }}</p></div>
        <div class="plate p-4"><p class="label">Кликов</p><p class="text-2xl font-bold tabular mt-1">{{ $clicks }}</p></div>
    </div>
    <p class="label">Резерв места</p>
    <div class="rounded-xl border-2 border-dashed border-[var(--line)] grid place-items-center text-xs faint tabular" style="aspect-ratio: {{ $slot->width }} / {{ $slot->height }}">{{ $slot->width }} × {{ $slot->height }}</div>
    @if ($slot->m_width)
        <div class="max-w-[220px] rounded-xl border-2 border-dashed border-[var(--line)] grid place-items-center text-xs faint tabular" style="aspect-ratio: {{ $slot->m_width }} / {{ $slot->m_height }}">телефон {{ $slot->m_width }} × {{ $slot->m_height }}</div>
    @endif
</div>
