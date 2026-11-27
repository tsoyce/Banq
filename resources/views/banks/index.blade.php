{{-- A18 · Каталог банков: лидер объективного рейтинга, поиск, сортировка, карточки с тремя цифрами. --}}
@php use App\Enums\RatingKind; @endphp
@extends('layouts.app', [
    'title' => 'Банки '.(country()?->genitive() ?? '').' — ставки, рейтинги, отзывы',
    'description' => 'Все банки '.(country()?->genitive() ?? '').': лучшие ставки по вкладам, объективный, народный и авторский рейтинг, отзывы клиентов.',
])

@section('content')
<div class="space-y-6">
    <header class="px-1 pt-2 space-y-3" data-reveal>
        <p class="kicker"><b>01</b> / Банки</p>
        <h1 class="font-display font-bold text-[30px] sm:text-4xl leading-[1.1]">Банки {{ country()?->genitive() }}. <span class="muted">Ставки, рейтинги и отзывы — на одной странице.</span></h1>
    </header>

    {{-- Бенто: лидер (чернила) + цифры справочника --}}
    <section class="grid grid-cols-6 gap-4 lg:gap-6">
        <a href="{{ $leader ? route('banks.show', $leader->bank) : route('ratings') }}" class="card card-ink lift col-span-6 md:col-span-4 p-6 lg:p-7 flex flex-col justify-between gap-8 min-h-[240px]" data-reveal>
            <div class="flex items-start justify-between gap-4">
                <span class="text-sm font-semibold opacity-70">Лидер объективного рейтинга</span>
                @if ($leader)<span class="bank-mark !w-12 !h-12 !text-[0.7rem]" style="--c: {{ $leader->bank->brand_color ?? 'var(--brand-1)' }}">{{ $leader->bank->mark() }}</span>@endif
            </div>
            @if ($leader)
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <p class="num-hero"><span x-data x-count="{{ number_format($leader->score(RatingKind::Objective), 1, '.', '') }}">{{ score_format($leader->score(RatingKind::Objective)) }}</span><sup class="text-[.32em] font-semibold align-[1.4em] ml-1 opacity-60">/ 5</sup></p>
                    <div class="text-right">
                        <p class="font-display font-bold text-2xl">{{ $leader->bank->displayName() }}</p>
                        <p class="text-sm opacity-60">ставки · надёжность · линейка · удобство</p>
                    </div>
                </div>
            @else
                <p class="text-lg opacity-70">Рейтинг появится, когда в базе будут ставки банков.</p>
            @endif
        </a>
        <div class="card col-span-6 md:col-span-2 p-6 lg:p-7 flex flex-col justify-between gap-6" data-reveal style="--d:.06s">
            <div class="flex items-center justify-between"><span class="icon-tile">@include('partials.icon', ['i' => 'bank'])</span><span class="chip">справочник</span></div>
            <div>
                <p class="num-card">{{ $total }}</p>
                <p class="text-sm muted mt-1">{{ plural($total, 'банк', 'банка', 'банков') }} второго уровня</p>
            </div>
            <div class="list-row !pb-0 text-sm justify-between"><span class="flex items-center gap-2">@include('partials.icon', ['i' => 'shield', 'c' => 'w-4 h-4 text-[var(--ok)]']) участники {{ country()?->guaranteeName() ?? 'гарантирования вкладов' }}</span><b class="tabular">{{ $kdifCount }}</b></div>
        </div>
    </section>

    {{-- Поиск и сортировка --}}
    <form method="GET" action="{{ route('banks.index') }}" class="card p-3 flex flex-col md:flex-row gap-3 md:items-center" data-reveal>
        <label class="relative flex-1">
            <span class="sr-only">Поиск банка</span>
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 muted">@include('partials.icon', ['i' => 'search', 'c' => 'w-[18px] h-[18px]'])</span>
            <input type="search" name="q" value="{{ $q }}" placeholder="Halyk, Kaspi, BCC…" class="input !pl-11">
        </label>
        <input type="hidden" name="sort" value="{{ $sort }}">
        <nav class="seg max-w-full max-sm:grid max-sm:grid-cols-2" aria-label="Сортировка">{{-- A36: на телефоне 2×2 вместо обрезанной ленты --}}
            @foreach (\App\Http\Controllers\BankController::SORTS as $key => $label)
                <a href="{{ route('banks.index', array_filter(['sort' => $key, 'q' => $q])) }}" class="{{ $sort === $key ? 'is-active' : 'muted' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </form>

    {{-- Карточки банков --}}
    <section class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-4 lg:gap-6">
        @forelse ($scores as $i => $s)
            @php
                $b = $s->bank; $st = $stats[$b->id] ?? ['best' => null, 'offers' => 0, 'types' => []];
                $rank = $s->rank(RatingKind::Objective);
            @endphp
            <a href="{{ route('banks.show', $b) }}" class="card lift bank-card group min-w-0" @if ($i < 9) data-reveal style="--d: {{ 0.03 * $i }}s" @endif>
                <div class="flex items-center gap-4">
                    <span class="bank-mark" style="--c: {{ $b->brand_color ?? 'var(--brand-1)' }}">{{ $b->mark() }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-display font-bold text-[19px] tracking-tight truncate group-hover:underline underline-offset-4 decoration-2 decoration-[var(--brand-1)]">{{ $b->displayName() }}</p>
                        <p class="text-[13px] muted truncate">{{ $b->name }}</p>
                    </div>
                    @if ($rank && $sort !== 'name')<span class="kicker tabular whitespace-nowrap" title="Место в объективном рейтинге"><b>#{{ $rank }}</b></span>@endif
                </div>
                <div class="flex flex-wrap gap-1.5">
                    @if ($b->deposit_insured)<span class="chip">@include('partials.icon', ['i' => 'shield', 'c' => 'w-3.5 h-3.5 text-[var(--ok)]']) {{ country()?->guaranteeName() ?? 'гарантия' }}</span>@endif
                    @foreach ($st['types'] as $t)<span class="chip">{{ mb_strtolower($t->label()) }}</span>@endforeach
                </div>
                <div class="plate cells cols-3 mt-auto !rounded-[var(--radius-sm)]">
                    <div>
                        <p class="cell-label">Ставка до</p>
                        <p class="font-display font-bold text-[22px] tabular mt-1">{{ $st['best'] ? rate_format($st['best']->rate) : '—' }}</p>
                    </div>
                    <div>
                        <p class="cell-label">Рейтинг</p>
                        <p class="font-display font-bold text-[22px] tabular mt-1">{{ $s->objective['has_data'] ? score_format($s->score(RatingKind::Objective)) : '—' }}</p>
                    </div>
                    <div>
                        <p class="cell-label">Народный</p>
                        <p class="font-display font-bold text-[22px] tabular mt-1 flex items-center gap-1">
                            @if ($s->people)@include('partials.icon', ['i' => 'star', 'c' => 'w-4 h-4 text-[var(--s-people)]']){{ score_format($s->people['score']) }}@else<span class="faint">—</span>@endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="muted">{{ $st['offers'] ? $st['offers'].' '.plural($st['offers'], 'вклад', 'вклада', 'вкладов') : 'вкладов пока нет' }}@if ($s->people) · {{ $s->people['count'] }} {{ plural($s->people['count'], 'отзыв', 'отзыва', 'отзывов') }}@endif</span>
                    <span class="font-semibold flex items-center gap-1">Подробнее @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4 transition group-hover:translate-x-0.5'])</span>
                </div>
            </a>
        @empty
            <div class="card p-10 text-center sm:col-span-2 2xl:col-span-3">
                <p class="font-semibold text-lg">Ничего не нашлось</p>
                <p class="muted mt-1">Попробуйте код банка (BCC, FFIN) или его короткое имя. <a href="{{ route('banks.index') }}" class="underline">Сбросить поиск</a></p>
            </div>
        @endforelse
    </section>

    <p class="text-xs faint px-1">@if (country()?->regulatorName())Справочник сверяется с реестром {{ country()->regulatorName() }}.@endif Ставки — действующие, из базы портала. Методика рейтингов — <a href="{{ route('ratings') }}#method" class="underline">на странице рейтингов</a>.</p>
</div>
@endsection
