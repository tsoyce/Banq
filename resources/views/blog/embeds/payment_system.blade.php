{{-- A37 · Вставка «платёжная система». --}}
<a href="{{ route('payment-systems.show', $ps) }}" class="embed">
    <span class="embed-plastic" style="background: {{ $ps->gradient() }}"></span>
    <span class="embed-main"><span class="embed-kind">Платёжная система · {{ $ps->scopeLabel() }}</span><b class="embed-title">{{ $ps->displayName() }}</b>
        <span class="embed-sub">{{ $ps->tiers()->count() }} {{ plural($ps->tiers()->count(), 'уровень', 'уровня', 'уровней') }} карт@if ($ps->founded_year) · с {{ $ps->founded_year }} года@endif</span></span>
    <span class="embed-go" aria-hidden="true">→</span>
</a>
