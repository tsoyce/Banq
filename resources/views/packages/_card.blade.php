{{-- A33 · Карточка пакета услуг в списке. $p — ServicePackage (bank, cards). --}}
@php $sign = currency_symbol($p->bank->country?->currency); @endphp
{{-- A53 · обёртка: кнопка сравнения не может лежать внутри ссылки --}}
<div class="relative">
<a href="{{ route('packages.show', $p) }}" class="card lift p-6 flex flex-col gap-5 h-full" data-reveal style="--d: {{ 0.03 * ($i ?? 0) }}s">
    <div class="flex items-center gap-3 pr-12">
        <span class="bank-mark !w-10 !h-10 !text-[0.6rem]" style="--c: {{ $p->bank->brand_color ?? 'var(--brand-1)' }}">{{ $p->bank->mark() }}</span>
        <div class="min-w-0 flex-1"><p class="text-sm muted truncate">{{ $p->bank->displayName() }}</p><p class="font-display font-bold text-xl truncate">{{ $p->name }}</p></div>
        <span class="chip">{{ $p->segmentLabel() }}</span>
    </div>
    <div>
        <p class="font-display font-bold text-4xl tabular leading-none">
            @if ($p->monthly_fee !== null){{ (float) $p->monthly_fee ? number_format((float) $p->monthly_fee, 0, ',', "\u{202F}") : '0' }}<span class="text-base muted font-semibold"> {{ $sign }}/мес</span>
            @elseif ($p->annual_fee !== null){{ number_format((float) $p->annual_fee, 0, ',', "\u{202F}") }}<span class="text-base muted font-semibold"> {{ $sign }}/год</span>
            @else<span class="muted text-2xl">цена не указана</span>@endif
        </p>
        @if ($p->fee_note)<p class="text-sm text-[var(--ok)] font-semibold mt-2">{{ $p->fee_note }}</p>@endif
    </div>
    @if ($p->perks)
        <ul class="grid gap-1.5">@foreach (array_slice($p->perks, 0, 4) as $perk)<li class="perk !text-sm">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) {{ $perk }}</li>@endforeach</ul>
    @endif
    @if ($p->cards->isNotEmpty())
        <div class="flex items-center gap-2 mt-auto pt-2 border-t border-[var(--line)]">
            @foreach ($p->cards->take(4) as $c)<span class="mini-card" style="background: {{ $c->face() }}" title="{{ $c->name }}"></span>@endforeach
            <span class="text-xs muted">{{ $p->cards->count() }} {{ plural($p->cards->count(), 'карта', 'карты', 'карт') }} в пакете</span>
        </div>
    @endif
</a>
@include('partials.compare-toggle', ['type' => 'packages', 'item' => $p, 'class' => 'absolute top-6 right-6 z-10'])
</div>
