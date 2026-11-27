{{-- A37 · Вставка «вклад»: текущая лучшая ставка и ГЭСВ — обновляются вместе с данными. --}}
<a href="{{ route('deposits.show', $product) }}" class="embed">
    <span class="bank-mark embed-mark" style="--c: {{ $product->bank->brand_color ?? 'var(--brand-1)' }}">{{ $product->bank->mark() }}</span>
    <span class="embed-main"><span class="embed-kind">Вклад · {{ $product->type->label() }}</span><b class="embed-title">{{ $product->name }}</b>
        <span class="embed-sub">{{ $product->bank->displayName() }}@if ($rate?->term_months) · {{ $rate->term_months }} мес@endif</span></span>
    @if ($rate)<span class="embed-figure"><span class="embed-kind">ставка</span><b class="tabular">{{ rate_format($rate->rate) }}</b>@if ($rate->effective_rate)<span class="embed-sub">ГЭСВ {{ rate_format($rate->effective_rate) }}</span>@endif</span>@endif
    <span class="embed-go" aria-hidden="true">→</span>
</a>
