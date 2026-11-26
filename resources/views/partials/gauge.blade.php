{{-- A18 · Гейдж 270° (DESIGN §6.6). Параметры: $score (0–5 или null), $color (цвет серии), $size (px, по умолчанию 132), $sub (подпись под цифрой). --}}
@php
    $gid = 'g'.\Illuminate\Support\Str::random(6);
    $r = 42; $len = 2 * M_PI * $r; $arc = $len * 0.75;
    $val = $score === null ? 0 : max(0, min(1, (float) $score / 5)) * $arc;
    $color ??= 'var(--brand-1)';
@endphp
<div class="gauge" style="width: {{ $size ?? 132 }}px">
    <svg viewBox="0 0 100 100" role="img" aria-label="Оценка {{ score_format($score) }} из 5">
        <defs><linearGradient id="{{ $gid }}" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="{{ $color }}" stop-opacity=".55"/><stop offset="1" stop-color="{{ $color }}"/></linearGradient></defs>
        <circle cx="50" cy="50" r="{{ $r }}" fill="none" class="g-track" stroke-width="9" stroke-linecap="round" stroke-dasharray="{{ round($arc, 2) }} {{ round($len, 2) }}" transform="rotate(135 50 50)"/>
        @if ($score !== null)
            <circle cx="50" cy="50" r="{{ $r }}" fill="none" class="g-value" stroke="url(#{{ $gid }})" stroke-width="9" stroke-linecap="round" stroke-dasharray="{{ round($val, 2) }} {{ round($len, 2) }}" transform="rotate(135 50 50)"/>
        @endif
    </svg>
    <div class="g-center">
        <span class="font-display font-bold tabular leading-none tracking-[-.04em]" style="font-size: {{ round(($size ?? 132) * 0.27) }}px">{{ score_format($score) }}</span>
        <span class="text-[11px] muted mt-1">{{ $sub ?? 'из 5' }}</span>
    </div>
</div>
