{{-- A18 · Чип изменения: ▲ +0,12 / ▼ −0,10 (всегда со знаком, не только цветом). Параметр: $d (float|null), $unit. --}}
@if ($d !== null && abs($d) >= 0.005)
    <span class="delta {{ $d > 0 ? 'delta-up' : 'delta-down' }}" title="к прошлой оценке">{{ $d > 0 ? '▲ +' : '▼ −' }}{{ number_format(abs($d), 2, ',', '') }}{{ $unit ?? '' }}</span>
@elseif ($d !== null)
    <span class="delta delta-flat" title="без изменений">= 0</span>
@endif
