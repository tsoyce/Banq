{{-- A33 · Пластиковая карта. $card — App\Models\Card; $tilt — объёмный наклон за курсором; $c — доп. классы. --}}
@php
    $net = $card->networks()->first();
    $tierName = $net['tier'] ? trim(\Illuminate\Support\Str::after($net['tier']->name, $net['system']->name)) : null;
@endphp
<div class="plastic {{ $card->format === 'virtual' ? 'is-virtual' : '' }} {{ $c ?? '' }}" style="--face: {{ $card->face() }}" @if ($tilt ?? false) x-data x-tilt data-tilt="14" @endif role="img"
     aria-label="{{ $card->name }} — {{ $card->bank?->displayName() }}{{ $net ? ', '.($net['tier']?->name ?? $net['system']->name) : '' }}">
    <div class="plastic-in">
        <div class="plastic-top">
            <div class="min-w-0">
                <div class="plastic-bank truncate">{{ $card->bank?->displayName() }}</div>
                <div class="plastic-kind">{{ $card->kind?->label() }}</div>
            </div>
            @if ($card->hasFeature('contactless') || $card->format !== 'virtual')
                <svg class="plastic-nfc" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8.5 16.4a5 5 0 0 1 7 0M5 12.9a10 10 0 0 1 14 0M1.4 9.3a15 15 0 0 1 21.2 0"/></svg>
            @endif
        </div>
        <div class="plastic-mid">
            @if ($card->format !== 'virtual')<span class="plastic-chip" aria-hidden="true"></span>@endif
            <span class="plastic-num">•••• {{ str_pad((string) (($card->id * 7919) % 10000), 4, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div class="plastic-bottom">
            <div class="min-w-0">
                <div class="plastic-name">{{ $card->name }}</div>
                @if ($tierName)<div class="plastic-tier">{{ $tierName }}</div>@endif
            </div>
            @if ($net)@include('partials.ps-mark', ['ps' => $net['system'], 's' => 'font-size: 7.5cqi'])@endif
        </div>
    </div>
</div>
