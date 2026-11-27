{{-- A37 · Вставка «пакет услуг». --}}
<a href="{{ route('packages.show', $package) }}" class="embed">
    <span class="bank-mark embed-mark" style="--c: {{ $package->bank?->brand_color ?? 'var(--brand-1)' }}">{{ $package->bank?->mark() }}</span>
    <span class="embed-main"><span class="embed-kind">Пакет услуг · {{ $package->segmentLabel() }}</span><b class="embed-title">{{ $package->name }}</b>
        <span class="embed-sub">{{ $package->bank?->displayName() }}@if ($package->fee_note) · {{ \Illuminate\Support\Str::limit($package->fee_note, 60) }}@endif</span></span>
    <span class="embed-figure"><span class="embed-kind">в месяц</span><b class="tabular">{{ $package->monthly_fee === null ? '—' : ((float) $package->monthly_fee === 0.0 ? 'бесплатно' : money_format((float) $package->monthly_fee, currency_symbol($package->bank?->country?->currency))) }}</b></span>
    <span class="embed-go" aria-hidden="true">→</span>
</a>
