{{-- A33 · Карточка банковской карты: объёмный пластик, «Оценка Banq» с разбивкой, условия, системы и привилегии уровня, рейтинги банка, пакеты, похожие. --}}
@php
    $bank = $card->bank;
    $sign = currency_symbol($bank->country?->currency);
    $money = fn ($v) => $v === null ? null : ((float) $v == 0 ? 'бесплатно' : money_format((float) $v, $sign));
    $cmp = ['id' => $card->id, 'code' => $bank->mark(), 'color' => $card->color ?? $bank->brand_color ?? '#8b5cf6', 'name' => $card->name];
    $isCredit = $card->kind === \App\Enums\CardKind::Credit;
    $networks = $card->networks();
    $features = config('banq.cards.features');
    $conditions = array_filter([
        'Обслуживание' => [
            ['Выпуск', $money($card->issue_fee)],
            ['В год', $card->annual_fee !== null ? $money($card->annual_fee) : null],
            ['В месяц', $card->monthly_fee !== null ? $money($card->monthly_fee) : null],
            ['Когда бесплатно', $card->fee_note],
        ],
        'Кэшбэк и остаток' => [
            ['Кэшбэк до', $card->cashback_max !== null ? percent_format($card->cashback_max) : null],
            ['Кэшбэк на всё', $card->cashback_base !== null ? percent_format($card->cashback_base) : null],
            ['Категории', $card->cashback_note],
            ['Процент на остаток', $card->balance_rate !== null ? rate_format($card->balance_rate).' годовых' : null],
        ],
        'Кредит' => [
            ['Лимит до', $card->credit_limit_max !== null ? money_format((float) $card->credit_limit_max, $sign) : null],
            ['Льготный период', $card->grace_days ? $card->grace_days.' '.plural($card->grace_days, 'день', 'дня', 'дней') : null],
            ['Ставка', $card->credit_rate !== null ? 'от '.rate_format($card->credit_rate).' годовых' : null],
            ['ГЭСВ', $card->credit_rate_effective !== null ? 'от '.rate_format($card->credit_rate_effective) : null],
            ['Минимальный платёж', $card->min_payment !== null ? percent_format($card->min_payment).' долга' : null],
        ],
        'Снятие и переводы' => [
            ['Снятие наличных', $card->cash_fee],
            ['Бесплатно в месяц', $card->free_cash_limit !== null ? 'до '.money_format((float) $card->free_cash_limit, $sign) : null],
            ['Переводы', $card->transfer_fee],
        ],
        'Выпуск' => [
            ['Формат', \App\Models\Card::FORMATS[$card->format] ?? null],
            ['Валюты', $card->currencies ? implode(', ', $card->currencies) : null],
            ['Оформление онлайн', $card->online_opening ? 'да' : 'нет'],
            ['Доставка курьером', $card->delivery ? 'да' : null],
            ['Срок действия', $card->validity_years ? $card->validity_years.' '.plural($card->validity_years, 'год', 'года', 'лет') : null],
            ['Возраст', $card->age_from ? 'с '.$card->age_from.' лет' : null],
        ],
    ], fn ($rows) => collect($rows)->contains(fn ($r) => $r[1] !== null && $r[1] !== ''));
    foreach ((array) $card->conditions as $k => $v) { $conditions['Прочее'][] = [$k, is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE)]; }
@endphp
@extends('layouts.app', [
    'title' => $card->name.' — '.$bank->displayName(),
    'description' => $card->kind->label().' карта '.$card->name.' от '.$bank->displayName().': '.collect($card->keyFigures())->map(fn ($f) => $f[0].' '.$f[1])->implode(', ').'.',
])

@section('content')
<div class="space-y-5">
    <nav class="text-sm muted flex items-center gap-2 px-1 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span>
        <a href="{{ route('cards.index') }}" class="hover:text-[var(--text)]">Карты</a><span class="faint">/</span>
        <a href="{{ route('cards.index', ['kind' => [$card->kind->value]]) }}" class="hover:text-[var(--text)]">{{ $card->kind->plural() }}</a><span class="faint">/</span>
        <a href="{{ route('cards.index', ['bank' => [$bank->slug]]) }}" class="hover:text-[var(--text)]">{{ $bank->displayName() }}</a>
    </nav>

    <section class="grid lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] gap-5">
        <div class="card p-7 md:p-9 flex flex-col justify-between gap-8" data-reveal>
            <a href="{{ route('banks.show', $bank) }}" class="flex items-center gap-4 group w-fit">
                <span class="bank-mark !w-14 !h-14 !text-[0.8rem]" style="--c: {{ $bank->brand_color ?? 'var(--brand-1)' }}">{{ $bank->mark() }}</span>
                <span>
                    <span class="font-semibold block group-hover:underline underline-offset-4">{{ $bank->displayName() }}</span>
                    <span class="text-sm muted">{{ $siblings->count() + 1 }} {{ plural($siblings->count() + 1, 'карта', 'карты', 'карт') }} банка в каталоге</span>
                </span>
            </a>
            <div>
                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="chip">{{ $card->kind->label() }}</span>
                    @if ($card->segmentLabel())<span class="chip">{{ $card->segmentLabel() }}</span>@endif
                    @if ($card->format !== 'physical')<span class="chip">{{ \App\Models\Card::FORMATS[$card->format] }}</span>@endif
                </div>
                <h1 class="font-display font-bold text-4xl sm:text-5xl leading-[1.04]">{{ $card->name }}</h1>
                <p class="muted mt-4 max-w-2xl">{{ $card->description ?: $card->kind->hint().'.' }}</p>
            </div>
            <div class="cells cols-3 plate rounded-2xl">
                @foreach ($card->keyFigures() as [$label, $value, $hi])
                    <div><p class="cell-label">{{ $label }}</p><p class="font-display font-bold text-base sm:text-[1.6rem] leading-tight tabular mt-1 break-words {{ $hi ? 'text-grad' : '' }}">{{ $value }}</p></div>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" x-data class="btn" :class="$store.cardCompare.has({{ $card->id }}) ? 'btn-primary' : 'btn-ghost'" @click="$store.cardCompare.toggle(@js($cmp))">
                    @include('partials.icon', ['i' => 'scale', 'c' => 'w-4 h-4'])
                    <span x-text="$store.cardCompare.has({{ $card->id }}) ? 'В сравнении' : 'Добавить к сравнению'">Добавить к сравнению</span>
                </button>
                @include('partials.fav', ['type' => 'card', 'id' => $card->id, 'on' => auth()->user()?->hasFavorite($card), 'label' => true])
                @if ($card->source_url || $bank->website)
                    <a href="{{ $card->source_url ?: $bank->website }}" target="_blank" rel="noopener nofollow" class="btn btn-outline">Оформить на сайте банка @include('partials.icon', ['i' => 'external', 'c' => 'w-4 h-4'])</a>
                @endif
            </div>
        </div>

        <div class="card card-ink p-7 md:p-9 flex flex-col gap-8 overflow-hidden relative" data-reveal style="--d:.06s">
            <div class="absolute inset-0 opacity-60 pointer-events-none" style="background: radial-gradient(60% 60% at 70% 30%, color-mix(in oklab, {{ $card->color ?? $bank->brand_color ?? '#8b5cf6' }} 55%, transparent), transparent 70%)" aria-hidden="true"></div>
            <div class="relative mx-auto w-full max-w-[400px] pt-2">@include('partials.plastic', ['card' => $card, 'tilt' => true])</div>
            <div class="relative flex items-center gap-5">
                @include('partials.gauge', ['score' => $offer->score, 'color' => 'var(--brand-2)', 'size' => 112, 'sub' => 'из 5'])
                <div class="min-w-0">
                    <p class="font-semibold">@if ($rank)<span class="text-grad font-display text-2xl">{{ $rank }}</span> место из {{ $sameKind }}@else Оценка Banq @endif</p>
                    <p class="text-sm opacity-60 mt-1">среди {{ mb_strtolower($card->kind->plural()) }} карт {{ country()?->genitive() }} — по данным, без рекламы</p>
                    <a href="#score" class="text-sm font-semibold mt-2 inline-flex items-center gap-1 opacity-80 hover:opacity-100">Как считаем @include('partials.icon', ['i' => 'arrow', 'c' => 'w-3.5 h-3.5'])</a>
                </div>
            </div>
        </div>
    </section>

    <div class="grid lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] gap-5 items-start">
        <div class="space-y-5 min-w-0">
            {{-- Условия --}}
            <section class="card p-6 md:p-7" data-reveal>
                <p class="kicker mb-1"><b>01</b> / условия</p>
                <h2 class="font-display font-bold text-2xl">Условия карты</h2>
                <div class="grid sm:grid-cols-2 gap-x-8 gap-y-6 mt-6">
                    @foreach ($conditions as $group => $rows)
                        <div>
                            <p class="label mb-2">{{ $group }}</p>
                            <dl class="divide-y divide-[var(--line)]">
                                @foreach ($rows as [$k, $v])
                                    @continue($v === null || $v === '')
                                    <div class="flex justify-between gap-4 py-2 text-sm"><dt class="muted">{{ $k }}</dt><dd class="font-semibold text-right">{{ $v }}</dd></div>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                </div>
                @if ($card->features)
                    <div class="flex flex-wrap gap-2 mt-7 pt-6 border-t border-[var(--line)]">
                        @foreach ($card->features as $f)<span class="chip">@include('partials.icon', ['i' => 'check', 'c' => 'w-3.5 h-3.5 text-[var(--ok)]']) {{ $features[$f] ?? $f }}</span>@endforeach
                    </div>
                @endif
            </section>

            {{-- Платёжные системы и уровни --}}
            @if ($networks->isNotEmpty())
                <section class="card p-6 md:p-7" data-reveal>
                    <p class="kicker mb-1"><b>02</b> / платёжные системы</p>
                    <h2 class="font-display font-bold text-2xl">{{ $networks->count() > 1 ? 'Выпускается в '.$networks->count().' системах' : 'Платёжная система и уровень' }}</h2>
                    <div class="grid gap-4 mt-6 {{ $networks->count() > 1 ? 'md:grid-cols-2' : '' }}">
                        @foreach ($networks as $n)
                            <div class="plate rounded-2xl p-5 space-y-4">
                                <a href="{{ route('payment-systems.show', $n['system']) }}" class="flex items-center gap-3 group">
                                    <span class="ps-tile" style="--ps: {{ $n['system']->gradient() }}">@include('partials.ps-mark', ['ps' => $n['system']])</span>
                                    <span>
                                        <span class="font-semibold block group-hover:underline underline-offset-4">{{ $n['tier']?->name ?? $n['system']->name }}</span>
                                        <span class="text-xs muted">{{ $n['system']->scopeLabel() }} система@if ($n['tier']) · {{ mb_strtolower($n['tier']->segmentLabel()) }} уровень@endif</span>
                                    </span>
                                    @if ($n['tier'])<span class="level-dots ml-auto">@for ($l = 1; $l <= 6; $l++)<i @class(['on' => $l <= $n['tier']->level])></i>@endfor</span>@endif
                                </a>
                                @if ($n['tier']?->privileges)
                                    <ul class="grid gap-2">
                                        @foreach (array_slice($n['tier']->privileges, 0, 6) as $p)<li class="perk">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) {{ $p }}</li>@endforeach
                                    </ul>
                                    @if (count($n['tier']->privileges) > 6)<a href="{{ route('payment-systems.show', $n['system']) }}#tier-{{ $n['tier']->code }}" class="text-sm font-semibold muted hover:text-[var(--text)]">ещё {{ count($n['tier']->privileges) - 6 }} →</a>@endif
                                    <p class="text-xs faint">Привилегии уровня — типичный набор системы; точный перечень задаёт банк.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($card->packages->isNotEmpty())
                <section class="card p-6 md:p-7" data-reveal>
                    <p class="kicker mb-1"><b>03</b> / пакеты</p>
                    <h2 class="font-display font-bold text-2xl">Входит в пакеты услуг</h2>
                    <div class="grid sm:grid-cols-2 gap-3 mt-5">
                        @foreach ($card->packages as $pkg)
                            <a href="{{ route('packages.show', $pkg) }}" class="plate rounded-2xl p-4 lift flex items-center gap-3">
                                <span class="icon-tile">@include('partials.icon', ['i' => 'gift'])</span>
                                <span class="min-w-0"><span class="font-semibold block truncate">{{ $pkg->name }}</span><span class="text-sm muted">{{ $pkg->segmentLabel() }}@if ($pkg->monthly_fee !== null) · {{ (float) $pkg->monthly_fee ? money_format((float) $pkg->monthly_fee, $sign).'/мес' : 'бесплатно' }}@endif</span></span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <aside class="space-y-5 min-w-0">
            {{-- Разбивка оценки --}}
            <section id="score" class="card p-6 scroll-mt-24" data-reveal>
                <p class="kicker mb-1">методика</p>
                <h2 class="font-display font-bold text-xl">Из чего оценка {{ score_format($offer->score) }}</h2>
                <div class="grid gap-4 mt-5">
                    @foreach ($offer->parts as $part)
                        <div>
                            <div class="flex justify-between text-sm mb-1.5"><span class="font-semibold">{{ $part['label'] }}</span><span class="muted tabular">{{ round($part['weight'] * 100) }}% веса · {{ round($part['value'] * 100) }}/100</span></div>
                            <div class="score-bar"><i style="--v: {{ $part['value'] }}"></i></div>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs faint mt-5">Каждый критерий — доля от лучшего значения среди карт {{ country()?->genitive() }}; оценка = 1 + 4 × взвешенная сумма. Пересчитывается при каждом обновлении данных.</p>
            </section>

            {{-- Рейтинги банка --}}
            @if ($offer->bankScore)
                <section class="card p-6" data-reveal>
                    <p class="kicker mb-1">банк</p>
                    <h2 class="font-display font-bold text-xl">Рейтинги {{ $bank->displayName() }}</h2>
                    <div class="grid grid-cols-3 gap-2 mt-5 text-center">
                        @foreach (\App\Enums\RatingKind::cases() as $k)
                            <a href="{{ route('ratings', ['by' => $k->value]) }}" class="grid justify-items-center gap-1 rounded-2xl py-2 hover:bg-[var(--card-2)] transition">
                                @include('partials.gauge', ['score' => $offer->bankScore->score($k), 'color' => $k->color(), 'size' => 78, 'sub' => $offer->bankScore->rank($k) ? $offer->bankScore->rank($k).' место' : '—'])
                                <span class="text-xs font-semibold muted">{{ $k->label() }}</span>
                            </a>
                        @endforeach
                    </div>
                    <a href="{{ route('banks.show', $bank) }}" class="btn btn-ghost w-full mt-4">Страница банка и отзывы @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</a>
                </section>
            @endif

            @if ($siblings->isNotEmpty())
                <section class="card p-6" data-reveal>
                    <h2 class="font-display font-bold text-xl">Ещё карты {{ $bank->displayName() }}</h2>
                    <div class="grid gap-2 mt-4">
                        @foreach ($siblings->take(6) as $s)
                            <a href="{{ route('cards.show', $s) }}" class="list-row !px-2 !py-2 rounded-xl hover:bg-[var(--card-2)] transition">
                                <span class="mini-card" style="background: {{ $s->face() }}"></span>
                                <span class="flex-1 min-w-0"><span class="font-semibold block truncate text-sm">{{ $s->name }}</span><span class="text-xs muted">{{ $s->kind->label() }} · {{ $s->feeLabel() }}</span></span>
                                @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4 faint'])
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>

    @if ($similar->isNotEmpty())
        <section class="space-y-3" data-reveal>
            <div class="flex items-end justify-between gap-3 px-1">
                <h2 class="font-display font-bold text-2xl">Похожие {{ mb_strtolower($card->kind->plural()) }} карты в других банках</h2>
                <a href="{{ route('cards.index', ['kind' => [$card->kind->value]]) }}" class="text-sm font-semibold muted hover:text-[var(--text)] whitespace-nowrap">Все →</a>
            </div>
            <div class="grid gap-3">
                @foreach ($similar as $i => $o)@include('cards._offer', ['o' => $o, 'i' => $i])@endforeach
            </div>
        </section>
    @endif
</div>
@endsection
