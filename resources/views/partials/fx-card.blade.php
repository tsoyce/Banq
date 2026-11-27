{{-- A40 · Карточка «Курсы валют»: официальный курс центробанка страны, ▲▼ к прошлому курсу, спарклайн за 30 дней, конвертер.
     $fx — App\Services\Currency\CurrencyRates::board(). Валюты выбираются в админке «Курсы валют». --}}
@php
    $p = $fx['provider'];
    $num = fn ($v, $d = 2) => number_format((float) $v, $d, ',', ' ');
    $spark = function (array $t) {
        if (count($t) < 2) return null;
        $min = min($t); $max = max($t); $span = max($max - $min, $max * 0.0005);
        $n = count($t) - 1;
        return collect($t)->map(fn ($v, $i) => round($i / $n * 64, 1).','.round(22 - ($v - $min) / $span * 20, 1))->implode(' ');
    };
@endphp
<section id="fx" class="card p-6 space-y-5 scroll-mt-32 fx-card" data-reveal
         x-data="fxConvert(@js(collect($fx['items'])->mapWithKeys(fn ($i) => [$i['code'] => $i['unit']])), @js($fx['symbol']))">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="font-display font-bold text-xl">Курсы валют</h2>
            <p class="text-sm muted mt-1">Официальный курс {{ $p->short() }}@if ($fx['date']) на {{ $fx['date']->translatedFormat('j F') }}@endif</p>
        </div>
        <a href="{{ $p->url() }}" target="_blank" rel="noopener" class="chip shrink-0 hover:!bg-[var(--ink)] hover:!text-[var(--ink-text)] transition" title="{{ $p->name() }}">
            <span class="dot {{ $fx['stale'] ? '!bg-[var(--warn)]' : '' }}"></span>{{ $p->short() }} @include('partials.icon', ['i' => 'external', 'c' => 'w-3 h-3'])
        </a>
    </div>

    @if ($fx['items'])
        <ul class="fx-list">
            @foreach ($fx['items'] as $n => $i)
                @php($d = $i['delta'])
                <li class="fx-row" style="--d: {{ 0.04 * $n }}s">
                    <span class="fx-flag" aria-hidden="true">{{ $i['flag'] ?: $i['symbol'] }}</span>
                    <span class="min-w-0">
                        <b class="block leading-tight">{{ $i['code'] }}@if ($i['nominal'] > 1) <span class="muted font-normal text-xs">×{{ $i['nominal'] }}</span>@endif</b>
                        <span class="block text-xs muted truncate">{{ $i['name'] }}</span>
                    </span>
                    @if ($pts = $spark($i['trend']))
                        <svg class="fx-spark {{ $d > 0 ? 'is-up' : ($d < 0 ? 'is-down' : '') }}" viewBox="0 0 64 24" preserveAspectRatio="none" aria-hidden="true"><polyline points="{{ $pts }}" pathLength="1"/></svg>
                    @else
                        <span></span>
                    @endif
                    <span class="text-right">
                        <b class="block font-display font-semibold text-lg tabular leading-tight whitespace-nowrap"><span x-data x-count="{{ number_format($i['rate'], 2, '.', '') }}">{{ $num($i['rate']) }}</span><span class="text-xs muted font-sans font-medium">&nbsp;{{ $fx['symbol'] }}</span></b>
                        @if ($d !== null)
                            <span class="fx-delta {{ $d > 0 ? 'is-up' : ($d < 0 ? 'is-down' : '') }}" title="{{ $i['pct'] !== null ? ($d >= 0 ? '+' : '−').$num(abs($i['pct'])).'%' : '' }}">{{ $d > 0 ? '▲' : ($d < 0 ? '▼' : '') }}&nbsp;{{ $d == 0 ? 'без изменений' : $num(abs($d)) }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>

        {{-- Конвертер: сумма в валюте ⇄ в нацвалюте по официальному курсу --}}
        <div class="plate p-3 space-y-2">
            <div class="flex items-center gap-2">
                <input type="text" inputmode="decimal" class="input tabular font-semibold !py-2 min-w-0 flex-1" :value="fmt(amount, 0)" @input="amount = parse($event.target.value)" aria-label="Сумма">
                <label class="fx-select">
                    <select x-model="code" aria-label="Валюта">
                        @foreach ($fx['items'] as $i)<option value="{{ $i['code'] }}">{{ $i['code'] }}</option>@endforeach
                    </select>
                </label>
                <button type="button" class="btn btn-ghost !p-2.5 shrink-0" @click="reverse = !reverse" :class="reverse && 'is-flipped'" aria-label="Поменять направление" title="Поменять направление">@include('partials.icon', ['i' => 'swap', 'c' => 'w-4 h-4 transition-transform duration-300'])</button>
            </div>
            <p class="flex items-baseline justify-between gap-3 px-1 text-sm">
                <span class="muted" x-text="reverse ? fmt(amount, 0) + ' ' + symbol + ' =' : fmt(amount, 0) + ' ' + code + ' ='"></span>
                <b class="tabular text-base" x-text="reverse ? fmt(result, 2) + ' ' + code : fmt(result, 2) + ' ' + symbol"></b>
            </p>
        </div>
        @if ($fx['stale'])<p class="text-xs" style="color: var(--warn)">{{ $p->short() }} сейчас не отвечает — показан последний полученный курс.</p>@endif
    @else
        <div class="plate p-5 text-sm muted flex items-center gap-3">
            <span class="skeleton w-9 h-9 rounded-full shrink-0"></span>
            Курсы {{ $p->short() }} загружаются — обновите страницу через минуту.
        </div>
    @endif
</section>
