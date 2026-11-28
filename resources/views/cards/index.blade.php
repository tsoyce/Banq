{{-- A33 · Каталог карт: бенто-шапка с крупными цифрами, фильтры слева, список справа (fetch без перезагрузки). --}}
@php
    $kindTitle = count($filters->kinds) === 1 ? $filters->kinds[0]->plural().' карты' : 'Банковские карты';
    $kindTitle .= $filters->audience ? ' '.$filters->audience->forWhom() : ''; // A54
    $aud = $filters->audienceParam(); // A54 · текущая аудитория в ссылках
@endphp
@extends('layouts.app', ['title' => $kindTitle.' '.(country()?->genitive() ? 'банков '.country()->genitive() : ''), 'description' => 'Дебетовые и кредитные карты банков '.(country()?->genitive() ?? '').': кэшбэк, стоимость обслуживания, льготный период, платёжные системы и уровни карт, сравнение.'])

@section('content')
@php $catalogEmpty = ! array_sum($audienceCounts) && ! $filters->activeCount(); @endphp {{-- A36, A54: пусто во всей стране, а не в выбранной вкладке --}}
<div x-data="cardFilters" class="space-y-5">

    <section class="grid grid-cols-2 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,1fr)] gap-3 sm:gap-5">{{-- A36: цифры на телефоне — в ряд --}}
        <div class="card p-7 flex flex-col justify-between gap-6 relative overflow-hidden col-span-2 md:col-span-1" data-reveal>
            <nav class="text-sm muted flex items-center gap-2"><a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span><span>Карты</span></nav>
            <div>
                <h1 class="font-display font-bold text-4xl sm:text-[2.75rem] leading-[1.05]">Карты @if ($filters->audience){{ $filters->audience->forWhom() }} @endif<span class="text-grad">банков {{ country()?->genitive() }}</span></h1>
                <p class="muted mt-3">{{ $filters->audience === \App\Enums\CardAudience::Business ? 'Корпоративные и бизнес-карты для юрлиц и ИП: обслуживание, кэшбэк, лимиты и уровень карты.' : 'Дебетовые, кредитные и виртуальные: кэшбэк, стоимость, льготный период и уровень карты.' }} «Оценка Banq» считается из данных — относительно лучших карт рынка.</p>
                {{-- A54 · физлица / бизнес — первая развилка каталога, вид карты — вторая --}}
                <nav class="aud-tabs mt-5" aria-label="Для кого">
                    @foreach (\App\Enums\CardAudience::cases() as $a)
                        <a href="{{ route('cards.index', array_filter(['audience' => $filters->audienceParam($a), 'kind' => array_map(fn ($k) => $k->value, $filters->kinds)])) }}" @class(['is-active' => $filters->audience === $a]) title="{{ $a->hint() }}"
                           @if ($filters->audience === $a) aria-current="page" @endif>@include('partials.icon', ['i' => $a->icon(), 'c' => 'w-4 h-4']) {{ $a->label() }} <span class="count">{{ $audienceCounts[$a->value] ?? 0 }}</span></a>
                    @endforeach
                    <a href="{{ route('cards.index', array_filter(['audience' => 'all', 'kind' => array_map(fn ($k) => $k->value, $filters->kinds)])) }}" @class(['is-active' => $filters->audience === null])>Все <span class="count">{{ array_sum($audienceCounts) }}</span></a>
                </nav>
                <div class="flex flex-wrap gap-2 mt-3">
                    <a href="{{ route('cards.index', array_filter(['audience' => $aud])) }}" class="pill {{ ! $filters->kinds ? 'is-active' : '' }}">Все виды <span class="faint tabular">{{ array_sum($kindCounts) }}</span></a>
                    @foreach (\App\Enums\CardKind::cases() as $k)
                        @continue(! ($kindCounts[$k->value] ?? 0))
                        <a href="{{ route('cards.index', array_filter(['kind' => [$k->value], 'audience' => $aud])) }}" class="pill {{ $filters->kinds === [$k] ? 'is-active' : '' }}">{{ $k->plural() }} <span class="faint tabular">{{ $kindCounts[$k->value] }}</span></a>
                    @endforeach
                    <a href="{{ route('payment-systems.index') }}" class="pill">@include('partials.icon', ['i' => 'globe', 'c' => 'w-4 h-4 text-[var(--brand-2)]']) Платёжные системы</a>
                </div>
            </div>
        </div>
        @if ($catalogEmpty)
            {{-- A36 · пустой каталог: вместо «Кэшбэк до —» и «0 карт · 0 банков» --}}
            @include('partials.catalog-soon', [
                'class' => 'col-span-2',
                'title' => 'Карты банков скоро будут здесь',
                'text' => 'Собираем дебетовые и кредитные карты: условия, тарифы и честную оценку по данным.',
                'items' => [['star', 'Кэшбэк', 'по категориям и на всё'], ['wallet', 'Обслуживание', 'сколько стоит в год'], ['clock', 'Льготный период', 'и ставка по кредитке'], ['globe', 'Уровни карт', 'Gold, Platinum, Infinite']],
                'links' => [['Платёжные системы', route('payment-systems.index'), 'globe'], ['Вклады', route('deposits.index'), 'piggy']],
            ])
        @else
            <div class="card card-ink p-5 sm:p-7 flex flex-col justify-between gap-4 min-w-0" data-reveal style="--d:.05s">
                <span class="text-sm font-semibold opacity-70">Кэшбэк до</span>
                @php $cb = $stats['cashback']; @endphp
                <p class="font-display font-semibold tabular leading-none text-[2.6rem] sm:text-6xl">
                    @if ($cb && $cb->cashback_max)<span x-data x-count="{{ rtrim(rtrim(number_format((float) $cb->cashback_max, 2, '.', ''), '0'), '.') }}">{{ rtrim(rtrim(number_format((float) $cb->cashback_max, 2, ',', ''), '0'), ',') }}</span><span class="text-[0.45em] align-top opacity-60">%</span>@else—@endif
                </p>
                <p class="text-sm opacity-60 truncate">{{ $cb && $cb->cashback_max ? $cb->bank->displayName().' · '.$cb->name : 'нет данных' }}</p>
            </div>
            <div class="card card-soft p-5 sm:p-7 flex flex-col justify-between gap-4 min-w-0" data-reveal style="--d:.1s">
                <span class="text-sm font-semibold muted">В каталоге</span>
                <p class="font-display font-semibold tabular leading-none text-[2.6rem] sm:text-6xl"><span x-data x-count="{{ $stats['count'] }}">{{ $stats['count'] }}</span></p>
                <p class="text-sm muted">{{ plural($stats['count'], 'карта', 'карты', 'карт') }} · {{ $stats['banks'] }} {{ plural($stats['banks'], 'банк', 'банка', 'банков') }}@if ($stats['free']) · {{ $stats['free'] }} бесплатно@endif</p>
            </div>
        @endif
    </section>

    @unless ($catalogEmpty){{-- A36 · фильтры по пустому каталогу не показываем --}}
    <div class="grid lg:grid-cols-[290px_minmax(0,1fr)] gap-5 items-start">
        <aside class="lg:sticky lg:top-[88px]">
            <button type="button" class="btn btn-ghost w-full lg:!hidden mb-3" @click="open = !open">
                @include('partials.icon', ['i' => 'filter', 'c' => 'w-4 h-4']) Фильтры
                @if ($filters->activeCount())<span class="chip !bg-[var(--ink)] !text-[var(--ink-text)]">{{ $filters->activeCount() }}</span>@endif
            </button>
            <form x-ref="form" action="{{ route('cards.index') }}" method="get" class="card p-5 space-y-6 lg:!block" :class="open ? 'block' : 'hidden'"
                  @change="changed()" @input.debounce.450ms="$event.target.matches('[data-live]') && changed()" @submit.prevent="changed()">
                <label class="relative block">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 faint">@include('partials.icon', ['i' => 'search', 'c' => 'w-4 h-4'])</span>
                    <input type="search" name="q" value="{{ $filters->q }}" placeholder="Банк или карта" class="input !pl-10" data-live>
                </label>

                <fieldset>
                    <legend class="label mb-2.5">Вид карты</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Enums\CardKind::cases() as $k)
                            <label class="check-pill" title="{{ $k->hint() }}">
                                <input type="checkbox" name="kind[]" value="{{ $k->value }}" class="sr-only" @checked($filters->hasKind($k))>
                                {{ $k->label() }} <span class="count">{{ $kindCounts[$k->value] ?? 0 }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @if ($systems->isNotEmpty())
                    <fieldset>
                        <legend class="label mb-2.5">Платёжная система</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($systems as $ps)
                                <label class="check-pill">
                                    <input type="checkbox" name="system[]" value="{{ $ps->slug }}" class="sr-only" @checked(in_array($ps->slug, $filters->systems, true))>
                                    {{ $ps->displayName() }} <span class="count">{{ $ps->cards_here }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <fieldset class="space-y-1">
                    <legend class="label mb-1.5">Условия</legend>
                    <label class="switch">Бесплатное обслуживание<input type="checkbox" name="free" value="1" class="sr-only" @checked($filters->free)><i></i></label>
                    <label class="switch">С кэшбэком<input type="checkbox" name="cashback" value="1" class="sr-only" @checked($filters->cashback)><i></i></label>
                    <label class="switch">Оформить онлайн<input type="checkbox" name="online" value="1" class="sr-only" @checked($filters->online)><i></i></label>
                </fieldset>

                <fieldset>
                    <legend class="label mb-2.5">Возможности</legend>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (array_slice(config('banq.cards.features'), 0, 10, true) as $key => $label)
                            <label class="check-pill !text-[0.8rem] !py-1.5"><input type="checkbox" name="feature[]" value="{{ $key }}" class="sr-only" @checked(in_array($key, $filters->features, true))>{{ $label }}</label>
                        @endforeach
                    </div>
                </fieldset>

                @if (count($currencies) > 1)
                    <fieldset>
                        <legend class="label mb-2.5">Валюта счёта</legend>
                        <div class="seg flex-wrap">
                            <label><input type="radio" name="currency" value="" class="sr-only" @checked(! $filters->currency) data-default>Любая</label>
                            @foreach ($currencies as $cur)
                                <label><input type="radio" name="currency" value="{{ $cur }}" class="sr-only" @checked($filters->currency === $cur)>{{ $cur }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <fieldset>
                    <legend class="label mb-2.5">Сегмент</legend>
                    <select name="segment" class="input">
                        <option value="">Любой</option>
                        @foreach (\App\Models\Card::SEGMENTS as $v => $l)<option value="{{ $v }}" @selected($filters->segment === $v)>{{ $l }}</option>@endforeach
                    </select>
                </fieldset>

                @if ($banks->isNotEmpty())
                    <fieldset x-data="{ all: false }">
                        <legend class="label mb-2.5">Банк</legend>
                        <div class="grid gap-0.5">
                            @foreach ($banks as $i => $b)
                                <label class="flex items-center gap-3 rounded-xl px-1.5 py-1.5 cursor-pointer hover:bg-[var(--card-2)] transition" @if ($i >= 6 && ! in_array($b->slug, $filters->banks, true)) x-show="all" x-cloak @endif>
                                    <input type="checkbox" name="bank[]" value="{{ $b->slug }}" class="w-4 h-4 accent-[var(--ink)]" @checked(in_array($b->slug, $filters->banks, true))>
                                    <span class="bank-mark !w-6 !h-6 !text-[0.42rem]" style="--c: {{ $b->brand_color ?? 'var(--brand-1)' }}">{{ $b->mark() }}</span>
                                    <span class="text-sm font-medium flex-1 truncate">{{ $b->displayName() }}</span>
                                    <span class="text-xs faint tabular">{{ $b->cards_here }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if ($banks->count() > 6)
                            <button type="button" class="text-sm font-semibold mt-2 px-1.5 muted hover:text-[var(--text)]" @click="all = !all" x-text="all ? 'Свернуть' : 'Все банки ({{ $banks->count() }})'">Все банки</button>
                        @endif
                    </fieldset>
                @endif

                <input type="hidden" name="sort" value="{{ $filters->sort }}" x-ref="sort">
                @if ($aud)<input type="hidden" name="audience" value="{{ $aud }}">@endif {{-- A54 --}}
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-primary flex-1">Показать</button>
                    <a href="{{ route('cards.index', array_filter(['audience' => $aud])) }}" class="btn btn-ghost" @click.prevent="reset()">Сбросить</a>
                </div>
            </form>
        </aside>

        <div id="card-results" class="min-w-0" :class="loading && 'is-loading'">
            @include('cards._results')
        </div>
    </div>
    @endunless
</div>
@endsection
