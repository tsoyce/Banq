{{-- A18 · Звёзды 0–5 с долями. Параметры: $score, $starSize (класс размера иконки, по умолчанию w-4 h-4). --}}
@php($star = 'M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z')
<span class="stars" role="img" aria-label="{{ score_format($score) }} из 5">
    @for ($k = 0; $k < 5; $k++)<svg class="{{ $starSize ?? 'w-4 h-4' }}" viewBox="0 0 24 24" fill="currentColor"><path d="{{ $star }}"/></svg>@endfor
    <span class="on" style="width: {{ max(0, min(100, (float) $score / 5 * 100)) }}%">
        @for ($k = 0; $k < 5; $k++)<svg class="{{ $starSize ?? 'w-4 h-4' }}" viewBox="0 0 24 24" fill="currentColor"><path d="{{ $star }}"/></svg>@endfor
    </span>
</span>
