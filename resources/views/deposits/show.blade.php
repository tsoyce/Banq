{{-- A14 · Карточка вклада: ставка, калькулятор, сетка ставок по срокам и суммам, история, условия, похожие вклады. --}}
@php
    $bank = $product->bank;
    $code = \Illuminate\Support\Str::limit($bank->code, 4, '');
    $color = $bank->brand_color ?? 'var(--brand-1)';
    $cmp = ['id' => $product->id, 'code' => $code, 'color' => $bank->brand_color ?? '#8b5cf6', 'name' => $bank->displayName()];
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₸';
    $termLabel = fn (?int $m) => $m === null ? 'любой срок' : $m.' мес';
    $amountLabel = fn (?float $a) => $a === null ? 'любая сумма' : 'от '.number_format($a, 0, ',', ' ');
    $capLabel = ['monthly' => 'ежемесячно', 'end' => 'в конце срока', 'none' => 'нет'][$product->capitalization] ?? null;
@endphp
@extends('layouts.app', [
    'title' => $product->name.' — '.$bank->displayName(),
    'description' => $product->name.' в '.$bank->displayName().': ставка '.rate_format($best?->rate).', ГЭСВ '.rate_format($best?->effective_rate).'. '.$product->type->label().' вклад — '.mb_strtolower($product->type->hint()).'.',
])

@section('content')
<div class="space-y-5">
    <nav class="text-sm muted flex items-center gap-2 px-1 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span>
        <a href="{{ route('deposits.index') }}" class="hover:text-[var(--text)]">Вклады</a><span class="faint">/</span>
        <a href="{{ route('deposits.index', ['bank' => [$bank->slug]]) }}" class="hover:text-[var(--text)]">{{ $bank->displayName() }}</a>
    </nav>

    {{-- Бенто: вклад + ставка --}}
    <section class="grid md:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)] gap-5">
        <div class="card p-7 md:p-9 flex flex-col justify-between gap-8" data-reveal>
            <div class="flex items-center gap-4">
                <span class="bank-mark !w-14 !h-14 !text-[0.8rem]" style="--c: {{ $color }}">{{ $code }}</span>
                <div>
                    <p class="font-semibold">{{ $bank->displayName() }}</p>
                    <p class="text-sm muted">@if ($bank->deposit_insured)@include('partials.icon', ['i' => 'shield', 'c' => 'w-3.5 h-3.5 inline -mt-0.5 text-[var(--ok)]']) участник {{ $bank->country?->guaranteeName() ?? 'системы гарантирования' }} @else вклады не застрахованы @endif</p>
                </div>
            </div>
            <div>
                <span class="chip mb-4">{{ $product->type->label() }} · {{ mb_strtolower($product->type->hint()) }}</span>
                <h1 class="font-display font-bold text-4xl sm:text-5xl leading-[1.04]">{{ $product->name }}</h1>
                @if ($product->description)<p class="muted mt-4 max-w-2xl">{{ $product->description }}</p>@endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" x-data class="btn" :class="$store.compare.has({{ $product->id }}) ? 'btn-primary' : 'btn-ghost'" @click="$store.compare.toggle(@js($cmp))">
                    @include('partials.icon', ['i' => 'scale', 'c' => 'w-4 h-4'])
                    <span x-text="$store.compare.has({{ $product->id }}) ? 'В сравнении' : 'Добавить к сравнению'">Добавить к сравнению</span>
                </button>
                @if ($product->source_url || $bank->website)
                    <a href="{{ $product->source_url ?: $bank->website }}" target="_blank" rel="noopener nofollow" class="btn btn-outline">На сайт банка @include('partials.icon', ['i' => 'external', 'c' => 'w-4 h-4'])</a>
                @endif
            </div>
        </div>

        <div class="card card-ink p-7 md:p-9 flex flex-col justify-between gap-6 overflow-hidden" data-reveal style="--d:.06s">
            <div class="flex items-start justify-between gap-4">
                <span class="text-sm font-semibold opacity-70">Ставка до</span>
                @if ($best)<span class="chip !bg-white/10 !text-current">{{ $best->currency }} · {{ $termLabel($best->term_months) }}</span>@endif
            </div>
            <p class="font-display font-semibold tabular leading-none text-[5.5rem] sm:text-[6.5rem]">
                @if ($best)<span x-data x-count="{{ number_format((float) $best->rate, 2, '.', '') }}">{{ number_format((float) $best->rate, 2, ',', '') }}</span><span class="text-[0.4em] align-top opacity-60">%</span>@else—@endif
            </p>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-2xl bg-white/[.07] p-3.5"><p class="opacity-60">ГЭСВ</p><p class="font-bold text-lg tabular">{{ rate_format($best?->effective_rate) }}</p></div>
                <div class="rounded-2xl bg-white/[.07] p-3.5"><p class="opacity-60">Гарантия {{ $bank->country?->guaranteeName() }}</p><p class="font-bold text-lg tabular">{{ $kdifLimit ? 'до '.number_format($kdifLimit / 1e6, 0, ',', ' ').' млн '.$bank->country->symbol() : '—' }}</p></div>
            </div>
        </div>
    </section>

    <div class="grid xl:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
        <div class="space-y-5 min-w-0">
            {{-- Сетка ставок --}}
            <section class="card p-3 sm:p-5" data-reveal>
                <div class="flex items-center justify-between px-3 pt-2 pb-4 gap-3">
                    <h2 class="font-display font-bold text-2xl">Ставки по срокам и суммам</h2>
                    <span class="text-xs muted">@if ($best)с {{ $best->valid_from->translatedFormat('j F Y') }}@endif</span>
                </div>
                @forelse ($matrix as $currency => $m)
                    <div class="overflow-x-auto">
                        <table class="table rate-grid tabular">
                            <thead><tr>
                                <th>{{ $currency }}</th>
                                @foreach ($m['terms'] as $t)<th class="!text-right">{{ $termLabel($t) }}</th>@endforeach
                            </tr></thead>
                            <tbody>
                            @foreach ($m['amounts'] as $a)
                                <tr>
                                    <td class="muted text-sm whitespace-nowrap">{{ $amountLabel($a) }}</td>
                                    @foreach ($m['terms'] as $t)
                                        @php $cell = $m['cells'][($a === null ? '' : $a).'|'.($t ?? '')] ?? null; @endphp
                                        <td class="text-right {{ $cell && $m['best'] && $cell->is($m['best']) && count($m['cells']) > 1 ? 'is-best' : '' }}">
                                            @if ($cell)
                                                <b class="text-lg">{{ rate_format($cell->rate) }}</b>
                                                <span class="block text-xs muted">ГЭСВ {{ rate_format($cell->effective_rate) }}</span>
                                            @else<span class="faint">—</span>@endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <p class="px-3 pb-6 muted">Действующих ставок нет.</p>
                @endforelse
                @if (collect($matrix)->every(fn ($m) => $m['terms'] === [null]))
                    <p class="text-xs faint px-3 pt-3">Банк не разделяет ставку по срокам — в источнике указана одна ставка.</p>
                @endif
            </section>

            {{-- История ставки --}}
            <section class="card p-6" data-reveal>
                <div class="flex items-center justify-between gap-3 mb-5">
                    <h2 class="font-display font-bold text-2xl">История ставки</h2>
                    @if (count($history) > 1)
                        @php $delta = end($history)['rate'] - $history[0]['rate']; @endphp
                        <span class="chip {{ $delta >= 0 ? '!text-[var(--ok)]' : '!text-[var(--bad)]' }}">{{ $delta >= 0 ? '+' : '' }}{{ number_format($delta, 2, ',', '') }} п.п.</span>
                    @endif
                </div>
                @if (count($history) > 1)
                    @php
                        $vals = array_column($history, 'rate');
                        $lo = min($vals) - 0.5; $hi = max($vals) + 0.5; $W = 640; $H = 170;
                        $t0 = $history[0]['date']->timestamp; $t1 = max($t0 + 1, end($history)['date']->timestamp);
                        $pts = array_map(fn ($h) => [round(($h['date']->timestamp - $t0) / ($t1 - $t0) * $W, 1), round($H - ($h['rate'] - $lo) / ($hi - $lo) * $H, 1)], $history);
                        $step = ''; foreach ($pts as $k => [$x, $y]) { $step .= $k ? " H{$x} V{$y}" : "M{$x} {$y}"; }
                    @endphp
                    <svg viewBox="-4 -8 {{ $W + 8 }} {{ $H + 16 }}" class="w-full h-44" preserveAspectRatio="none" role="img" aria-label="График истории ставки">
                        <defs><linearGradient id="hl" x1="0" x2="1"><stop offset="0" stop-color="var(--brand-1)"/><stop offset="1" stop-color="var(--brand-2)"/></linearGradient>
                            <linearGradient id="hf" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="var(--brand-1)" stop-opacity=".22"/><stop offset="1" stop-color="var(--brand-1)" stop-opacity="0"/></linearGradient></defs>
                        <path d="{{ $step }} V{{ $H }} H0 Z" fill="url(#hf)"/>
                        <path d="{{ $step }}" fill="none" stroke="url(#hl)" stroke-width="3" vector-effect="non-scaling-stroke" class="spark-line" pathLength="1000"/>
                    </svg>
                    <div class="flex justify-between text-xs muted mt-2 tabular">
                        @foreach ([$history[0], end($history)] as $h)<span>{{ $h['date']->translatedFormat('j M Y') }} · <b class="text-[var(--text)]">{{ rate_format($h['rate']) }}</b></span>@endforeach
                    </div>
                @else
                    <div class="plate p-6 text-center">
                        <p class="font-semibold">Ставка не менялась @if ($best)с {{ $best->valid_from->translatedFormat('j F Y') }}@endif</p>
                        <p class="text-sm muted mt-1">График появится после первого изменения — мы храним все прошлые ставки.</p>
                    </div>
                @endif
            </section>

            {{-- Условия --}}
            <section class="card p-6" data-reveal>
                <h2 class="font-display font-bold text-2xl mb-3">Условия</h2>
                <dl class="grid sm:grid-cols-2 gap-x-8">
                    @foreach (array_filter([
                        ['Тип вклада', $product->type->label()],
                        ['Валюта', implode(', ', array_keys($matrix) ?: ($product->currencies ?? [$bank->country?->currency ?? 'KZT']))],
                        ['Пополнение', $product->can_replenish ? 'да' : 'нет'],
                        ['Частичное снятие', $product->can_withdraw ? 'да' : 'нет'],
                        ['Минимальная сумма', $product->min_amount ? $money($product->min_amount) : 'не указана'],
                        $product->max_amount ? ['Максимальная сумма', $money($product->max_amount)] : null,
                        ['Срок', $product->term_min || $product->term_max ? trim(($product->term_min ? 'от '.$product->term_min.' ' : '').($product->term_max ? 'до '.$product->term_max.' ' : '')).' мес' : 'не указан'],
                        $capLabel ? ['Капитализация', $capLabel] : null,
                        ['Открытие онлайн', $product->online_opening ? 'да' : 'в отделении'],
                        ['Гарантия '.($bank->country?->guaranteeName() ?? 'вклада'), $kdifLimit ? 'до '.$money($kdifLimit) : 'нет'],
                    ]) as [$k, $v])
                        <div class="list-row !py-3 justify-between sm:[&:nth-child(2)]:border-t-0"><dt class="muted text-sm">{{ $k }}</dt><dd class="font-semibold text-right">{{ $v }}</dd></div>
                    @endforeach
                </dl>
                @foreach ((array) $product->conditions as $c)
                    <p class="text-sm mt-3 flex gap-2">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4 mt-0.5 text-[var(--ok)] flex-none']) {{ is_array($c) ? implode(' — ', $c) : $c }}</p>
                @endforeach
            </section>
        </div>

        {{-- Правая колонка: калькулятор + другие вклады --}}
        <aside class="space-y-5 xl:sticky xl:top-[88px]">
            <section class="card p-6 space-y-5" data-reveal
                     x-data="depositCalc({ rate: {{ (float) ($best?->rate ?? 0) }}, effective: {{ $best?->effective_rate !== null ? (float) $best->effective_rate : 'null' }}, months: {{ $best?->term_months ?? 12 }} })">
                <div>
                    <h2 class="font-display font-bold text-xl">Сколько заработаю</h2>
                    <p class="text-sm muted mt-1">По ставке этого вклада</p>
                </div>
                <label class="field"><span>Сумма</span>
                    <input type="text" inputmode="numeric" class="input tabular font-semibold" :value="money(amount)" @input="setAmount($event)"></label>
                <label class="field"><span class="flex justify-between">Срок <b class="tabular" x-text="months + ' мес'"></b></span>
                    <input type="range" min="1" max="36" x-model.number="months" class="range" aria-label="Срок"></label>
                <div class="card-ink rounded-[var(--radius-sm)] p-5 space-y-1">
                    <p class="text-sm opacity-60">Доход</p>
                    <p class="font-display font-bold text-4xl tabular" x-text="'+' + money(income())">+0 ₸</p>
                    <p class="text-sm opacity-60">Итого <b class="tabular opacity-100" x-text="money(amount + income())"></b></p>
                </div>
                @if ($kdifLimit)
                    <p class="text-xs muted flex gap-2" x-show="amount > {{ $kdifLimit }}" x-cloak>@include('partials.icon', ['i' => 'shield', 'c' => 'w-4 h-4 flex-none text-[var(--warn)]']) Сумма выше гарантии {{ $bank->country?->guaranteeName() }} ({{ $money($kdifLimit) }}). Остаток не застрахован.</p>
                @endif
                <p class="text-xs faint">Расчёт {{ $best?->effective_rate ? 'по ГЭСВ' : 'с ежемесячной капитализацией' }}, без учёта налогов и бонусов.</p>
            </section>

            @if ($siblings->isNotEmpty())
                <section class="card p-6" data-reveal>
                    <h2 class="font-display font-bold text-xl mb-2">Ещё в {{ $bank->displayName() }}</h2>
                    @foreach ($siblings as $s)
                        <a href="{{ route('deposits.show', $s) }}" class="list-row group">
                            <span class="flex-1 min-w-0"><span class="block font-medium group-hover:underline truncate">{{ $s->name }}</span><span class="text-xs muted">{{ $s->type->label() }}</span></span>
                            <b class="tabular">{{ rate_format($s->currentRates->max('rate')) }}</b>
                        </a>
                    @endforeach
                </section>
            @endif
        </aside>
    </div>

    @if ($similar->isNotEmpty())
        <section class="space-y-3" data-reveal>
            <div class="flex items-center justify-between px-1">
                <h2 class="font-display font-bold text-2xl">Похожие вклады</h2>
                <a href="{{ route('deposits.index', ['type' => [$product->type->value]]) }}" class="text-sm font-semibold muted hover:text-[var(--text)]">Все: {{ mb_strtolower($product->type->label()) }} →</a>
            </div>
            <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
                @foreach ($similar as $o)
                    <a href="{{ route('deposits.show', $o->product) }}" class="card lift p-5 flex flex-col gap-5">
                        <div class="flex items-center gap-3">
                            <span class="bank-mark" style="--c: {{ $o->bank()->brand_color ?? 'var(--brand-1)' }}">{{ \Illuminate\Support\Str::limit($o->bank()->code, 4, '') }}</span>
                            <span class="min-w-0"><span class="block font-semibold truncate">{{ $o->bank()->displayName() }}</span><span class="block text-xs muted truncate">{{ $o->product->name }}</span></span>
                        </div>
                        <p class="font-display font-bold text-4xl tabular">{{ number_format((float) $o->rate->rate, 2, ',', '') }}<span class="text-xl muted">%</span></p>
                        @php $diff = (float) $o->rate->rate - (float) ($best?->rate ?? 0); @endphp
                        <p class="text-xs font-semibold {{ $diff > 0 ? 'text-[var(--ok)]' : 'muted' }}">{{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 2, ',', '') }} п.п. к этому вкладу</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
