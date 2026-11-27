{{-- A40 · Бегущая строка: группы из App\Services\Ticker\Ticker (курсы валют, ставки, свой текст…). Дважды — для бесшовного круга. --}}
@php($groups = $ticker ?? [])
<div class="ticker flex-1 min-w-0">
    @if ($groups)
        <div class="ticker-track mono text-[12px]" style="animation-duration: {{ $tickerDuration ?? 50 }}s">
            @foreach ([1, 2] as $pass)
                @foreach ($groups as $g)
                    @if ($g['title'])
                        <a @if ($g['href']) href="{{ $g['href'] }}" @if (str_starts_with($g['href'], 'http')) target="_blank" rel="noopener" @endif @endif class="ticker-tag" @if ($pass === 2) aria-hidden="true" tabindex="-1" @endif>{{ $g['title'] }}</a>
                    @endif
                    @foreach ($g['items'] as $it)
                        <a @if (! empty($it['href'])) href="{{ $it['href'] }}" @endif class="ticker-item" @if ($pass === 2) aria-hidden="true" tabindex="-1" @endif>
                            <span class="{{ isset($it['value']) ? 'text-white/55' : 'text-white/80' }}">{{ $it['label'] }}</span>
                            @isset($it['value'])<b class="text-white">{{ $it['value'] }}</b>@endisset
                            @if (isset($it['delta']) && $it['delta'] !== null)
                                @php($d = (float) $it['delta'])
                                <span class="ticker-delta {{ $d > 0 ? 'is-up' : ($d < 0 ? 'is-down' : '') }}">{{ $d > 0 ? '▲' : ($d < 0 ? '▼' : '•') }}{{ $d == 0 ? '' : number_format(abs($d), 2, ',', ' ') }}</span>
                            @endif
                        </a>
                    @endforeach
                @endforeach
            @endforeach
        </div>
    @else
        <div class="ticker-track mono text-[12px] !animate-none"><span class="text-white/60">Скоро здесь побегут ставки банков — данные уже загружаются</span></div>
    @endif
</div>
