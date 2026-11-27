{{-- A37 · Вставка «банк» в тексте публикации: живые данные (лучшая ставка, число вкладов). --}}
@php($best = $stats['best'] ?? null)
<a href="{{ route('banks.show', $bank) }}" class="embed">
    <span class="bank-mark embed-mark" style="--c: {{ $bank->brand_color ?? 'var(--brand-1)' }}">{{ $bank->mark() }}</span>
    <span class="embed-main"><span class="embed-kind">Банк</span><b class="embed-title">{{ $bank->displayName() }}</b>
        <span class="embed-sub">{{ $stats['offers'] ?? 0 }} {{ plural($stats['offers'] ?? 0, 'вклад', 'вклада', 'вкладов') }} в каталоге</span></span>
    @if ($best)<span class="embed-figure"><span class="embed-kind">ставка до</span><b class="tabular">{{ rate_format($best->rate) }}</b></span>@endif
    <span class="embed-go" aria-hidden="true">→</span>
</a>
