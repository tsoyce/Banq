{{-- A14 · Каталог вкладов: фильтры слева, список справа. Фильтры — GET-форма; с JS список обновляется без перезагрузки. --}}
@extends('layouts.app', ['title' => 'Вклады банков Казахстана', 'description' => 'Каталог депозитов банков Казахстана: ставки, ГЭСВ, условия пополнения и снятия, сравнение и калькулятор дохода.'])

@section('content')
<div x-data="depositFilters" class="space-y-5">

    {{-- Шапка раздела: бенто с крупными цифрами --}}
    <section class="grid md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,1fr)] gap-5">
        <div class="card p-7 flex flex-col justify-between gap-6" data-reveal>
            <nav class="text-sm muted flex items-center gap-2"><a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span><span>Вклады</span></nav>
            <div>
                <h1 class="font-display font-bold text-4xl sm:text-[2.75rem] leading-[1.05]">Вклады <span class="text-grad">банков Казахстана</span></h1>
                <p class="muted mt-3 max-w-xl">Действующие ставки и ГЭСВ по всем типам вкладов. Отметьте до {{ \App\Http\Controllers\DepositController::COMPARE_LIMIT }} вкладов — и сравните их рядом.</p>
            </div>
        </div>
        <div class="card card-ink p-7 flex flex-col justify-between gap-4" data-reveal style="--d:.05s">
            <span class="text-sm font-semibold opacity-70">Лучшая ставка</span>
            @php $top = $offers->sortByDesc(fn ($o) => (float) $o->rate->rate)->first(); @endphp
            <p class="font-display font-semibold tabular leading-none text-6xl">
                @if ($top)<span x-data x-count="{{ number_format((float) $top->rate->rate, 2, '.', '') }}">{{ number_format((float) $top->rate->rate, 2, ',', '') }}</span><span class="text-[0.45em] align-top opacity-60">%</span>@else—@endif
            </p>
            <p class="text-sm opacity-60 truncate">{{ $top ? $top->bank()->displayName().' · '.mb_strtolower($top->product->type->label()) : 'нет данных' }}</p>
        </div>
        <div class="card card-soft p-7 flex flex-col justify-between gap-4" data-reveal style="--d:.1s">
            <span class="text-sm font-semibold muted">В каталоге</span>
            <p class="font-display font-semibold tabular leading-none text-6xl"><span x-data x-count="{{ $typeCounts ? array_sum($typeCounts) : 0 }}">{{ array_sum($typeCounts) }}</span></p>
            <p class="text-sm muted">{{ plural(array_sum($typeCounts), 'вклад', 'вклада', 'вкладов') }} · {{ $banks->count() }} {{ plural($banks->count(), 'банк', 'банка', 'банков') }}</p>
        </div>
    </section>

    <div class="grid lg:grid-cols-[290px_minmax(0,1fr)] gap-5 items-start">
        {{-- ===== Фильтры ===== --}}
        <aside class="lg:sticky lg:top-[88px]">
            <button type="button" class="btn btn-ghost w-full lg:!hidden mb-3" @click="open = !open">
                @include('partials.icon', ['i' => 'filter', 'c' => 'w-4 h-4']) Фильтры
                @if ($filters->activeCount())<span class="chip !bg-[var(--ink)] !text-[var(--ink-text)]">{{ $filters->activeCount() }}</span>@endif
            </button>
            <form x-ref="form" action="{{ route('deposits.index') }}" method="get" class="card p-5 space-y-6 lg:!block" :class="open ? 'block' : 'hidden'"
                  @change="changed()" @input.debounce.450ms="$event.target.matches('[data-live]') && changed()" @submit.prevent="changed()">
                <label class="relative block">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 faint">@include('partials.icon', ['i' => 'search', 'c' => 'w-4 h-4'])</span>
                    <input type="search" name="q" value="{{ $filters->q }}" placeholder="Банк или название" class="input !pl-10" data-live>
                </label>

                <fieldset class="space-y-2.5">
                    <legend class="label mb-2.5">Тип вклада</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Enums\DepositType::cases() as $t)
                            <label class="check-pill" title="{{ $t->hint() }}">
                                <input type="checkbox" name="type[]" value="{{ $t->value }}" class="sr-only" @checked($filters->hasType($t))>
                                {{ $t->label() }} <span class="count">{{ $typeCounts[$t->value] ?? 0 }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @if (count($currencies) > 1)
                    <fieldset>
                        <legend class="label mb-2.5">Валюта</legend>
                        <div class="seg">
                            @foreach ($currencies as $cur)
                                <label><input type="radio" name="currency" value="{{ $cur }}" class="sr-only" @checked($filters->currency === $cur) @if ($cur === 'KZT') data-default @endif>{{ $cur }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <fieldset>
                    <legend class="label mb-2.5">Сумма</legend>
                    <input type="text" inputmode="numeric" name="amount" value="{{ $filters->amount ? number_format($filters->amount, 0, ',', ' ') : '' }}" placeholder="например, 1 000 000 ₸"
                           class="input tabular font-semibold" data-live
                           @blur="$el.value = parseMoney($el.value) ? parseMoney($el.value).toLocaleString('ru-RU') : ''">
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @foreach ([500_000 => '500 тыс', 1_000_000 => '1 млн', 5_000_000 => '5 млн', 15_000_000 => '15 млн'] as $v => $l)
                            <button type="button" class="btn btn-ghost !py-1 !px-2.5 !text-xs" @click="$refs.form.amount.value = '{{ number_format($v, 0, ',', ' ') }}'; changed()">{{ $l }}</button>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="label mb-2.5">Срок</legend>
                    <div class="seg flex-wrap">
                        <label><input type="radio" name="term" value="" class="sr-only" @checked(! $filters->term) data-default>Любой</label>
                        @foreach ([3, 6, 12, 24] as $m)
                            <label><input type="radio" name="term" value="{{ $m }}" class="sr-only" @checked($filters->term === $m)>{{ $m }} мес</label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="space-y-1">
                    <legend class="label mb-1.5">Условия</legend>
                    <label class="switch">Пополнение<input type="checkbox" name="replenish" value="1" class="sr-only" @checked($filters->replenish)><i></i></label>
                    <label class="switch">Частичное снятие<input type="checkbox" name="withdraw" value="1" class="sr-only" @checked($filters->withdraw)><i></i></label>
                    <label class="switch">Открыть онлайн<input type="checkbox" name="online" value="1" class="sr-only" @checked($filters->online)><i></i></label>
                </fieldset>

                @if ($banks->isNotEmpty())
                    <fieldset x-data="{ all: false }">
                        <legend class="label mb-2.5">Банк</legend>
                        <div class="grid gap-0.5">
                            @foreach ($banks as $i => $b)
                                <label class="flex items-center gap-3 rounded-xl px-1.5 py-1.5 cursor-pointer hover:bg-[var(--card-2)] transition" @if ($i >= 6 && ! in_array($b->slug, $filters->banks, true)) x-show="all" x-cloak @endif>
                                    <input type="checkbox" name="bank[]" value="{{ $b->slug }}" class="w-4 h-4 accent-[var(--ink)]" @checked(in_array($b->slug, $filters->banks, true))>
                                    <span class="bank-mark !w-6 !h-6 !text-[0.42rem]" style="--c: {{ $b->brand_color ?? 'var(--brand-1)' }}">{{ \Illuminate\Support\Str::limit($b->code, 4, '') }}</span>
                                    <span class="text-sm font-medium flex-1 truncate">{{ $b->displayName() }}</span>
                                    <span class="text-xs faint tabular">{{ $b->offers_count }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if ($banks->count() > 6)
                            <button type="button" class="text-sm font-semibold mt-2 px-1.5 muted hover:text-[var(--text)]" @click="all = !all" x-text="all ? 'Свернуть' : 'Все банки ({{ $banks->count() }})'">Все банки</button>
                        @endif
                    </fieldset>
                @endif

                <input type="hidden" name="sort" value="{{ $filters->sort }}" x-ref="sort">

                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-primary flex-1">Показать</button>
                    <a href="{{ route('deposits.index') }}" class="btn btn-ghost" @click.prevent="reset()">Сбросить</a>
                </div>
            </form>
        </aside>

        {{-- ===== Результаты ===== --}}
        <div id="deposit-results" class="min-w-0" :class="loading && 'is-loading'">
            @include('deposits._results')
        </div>
    </div>
</div>
@endsection
