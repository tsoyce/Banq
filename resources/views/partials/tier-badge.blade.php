{{-- A54 · Значок уровня карты: загруженный в админке (badge) или нейтральный SVG «мини-карта» — металл по уровню, подпись, ступени.
     Не копирует фирменные логотипы систем: только буквы марки из справочника. $tier — CardTier, $c — классы размера (по умолчанию w-16). --}}
@php
    $ps = $tier->paymentSystem;
    $url = $tier->badgeUrl();
    $label = $tier->badgeLabel();
    $tones = [
        'classic' => ['#a9bdd1', '#556d87', '#ffffff'],
        'gold' => ['#f6dc8c', '#b07f22', '#3a2a06'],
        'platinum' => ['#f3f5f8', '#959fab', '#1f2937'],
        'black' => ['#3b3b46', '#08080c', '#efe6c8'],
        'business' => ['#3157a4', '#0d1b3e', '#ffffff'],
        'green' => ['#7fd8a4', '#1d7247', '#ffffff'],
    ];
    [$from, $to, $ink] = $tones[$tier->tone()] ?? $tones['classic'];
    $gid = 'tb'.$tier->id.'-'.substr(md5(uniqid('', true)), 0, 6);
    $len = max(1, mb_strlen($label));
    $fs = round(min(17, 128 / ($len * 0.6)), 1);
@endphp
@if ($url)
    <img src="{{ $url }}" alt="{{ $tier->name }}" loading="lazy" class="tier-badge is-img {{ $c ?? 'w-16' }}">
@else
    <svg class="tier-badge {{ $c ?? 'w-16' }}" viewBox="0 0 160 101" role="img" aria-label="{{ $tier->name }}">
        <defs>
            <linearGradient id="{{ $gid }}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{{ $from }}"/><stop offset="1" stop-color="{{ $to }}"/></linearGradient>
            <linearGradient id="{{ $gid }}s" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".5" stop-color="#fff" stop-opacity=".35"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>
        </defs>
        <rect width="160" height="101" rx="12" fill="url(#{{ $gid }})"/>
        <path d="M60 0h34L48 101H14z" fill="url(#{{ $gid }}s)" opacity=".7"/>
        <rect x=".5" y=".5" width="159" height="100" rx="11.5" fill="none" stroke="#fff" stroke-opacity=".22"/>
        <rect x="16" y="36" width="24" height="18" rx="3.5" fill="#e9c46a" opacity=".85"/>
        <path d="M16 45h24M28 36v18" stroke="#7a5a12" stroke-opacity=".35" stroke-width="1"/>
        @if ($ps)<text x="16" y="25" fill="{{ $ink }}" font-family="Onest, system-ui, sans-serif" font-size="13" font-weight="800" letter-spacing=".06em" opacity=".8">{{ mb_strtoupper($ps->mark()) }}</text>@endif
        <text x="16" y="84" fill="{{ $ink }}" font-family="Onest, system-ui, sans-serif" font-size="{{ $fs }}" font-weight="700">{{ $label }}</text>
        @for ($l = 1; $l <= 6; $l++)
            <circle cx="{{ 112 + $l * 6.5 }}" cy="26" r="2.3" fill="{{ $ink }}" opacity="{{ $l <= $tier->level ? '.9' : '.25' }}"/>
        @endfor
    </svg>
@endif
