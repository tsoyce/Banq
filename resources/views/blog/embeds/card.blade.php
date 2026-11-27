{{-- A37 · Вставка «карта»: мини-пластик, ключевые цифры. --}}
@php($figs = array_slice($card->keyFigures(), 0, 2))
<a href="{{ route('cards.show', $card) }}" class="embed">
    <span class="embed-plastic" style="background: {{ $card->face() }}"></span>
    <span class="embed-main"><span class="embed-kind">{{ $card->kind?->label() }} карта</span><b class="embed-title">{{ $card->name }}</b>
        <span class="embed-sub">{{ $card->bank?->displayName() }}@if ($n = $card->networks()->first()) · {{ $n['tier']?->name ?? $n['system']->name }}@endif</span></span>
    @foreach ($figs as [$label, $value, $hl])
        <span class="embed-figure {{ $loop->last ? 'max-sm:hidden' : '' }}"><span class="embed-kind">{{ $label }}</span><b class="tabular">{{ $value }}</b></span>
    @endforeach
    <span class="embed-go" aria-hidden="true">→</span>
</a>
