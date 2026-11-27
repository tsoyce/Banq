{{-- A28 · Кабинет — избранное: вклады с текущей лучшей ставкой (строки каталога) и банки с рейтингом. --}}
@extends('layouts.app', ['title' => 'Избранное'])

@section('content')
<div class="space-y-6">
    @include('account._shell', ['tab' => 'favorites'])

    <section class="space-y-3">
        <div class="flex items-end justify-between gap-3 px-1">
            <div><p class="kicker">02 / Вклады</p><h2 class="font-display font-bold text-xl mt-1">Избранные вклады <span class="muted tabular">{{ $deposits->count() }}</span></h2></div>
            @if ($deposits->count() > 1)
                <button type="button" class="btn btn-ghost !py-2 text-sm" x-data
                        @click="$store.compare.set(@js($deposits->take(4)->map(fn ($o) => ['id' => $o->product->id, 'code' => \Illuminate\Support\Str::limit($o->bank()->code, 4, ''), 'color' => $o->bank()->brand_color ?? '#8b5cf6', 'name' => $o->bank()->displayName()])->values())); location.href = $store.compare.url()">
                    @include('partials.icon', ['i' => 'scale', 'c' => 'w-4 h-4']) Сравнить {{ min(4, $deposits->count()) }}
                </button>
            @endif
        </div>
        @forelse ($deposits as $i => $o)
            @include('deposits._offer', ['o' => $o, 'i' => $i, 'first' => false])
        @empty
            <div class="card empty" data-reveal>
                <span class="icon-tile">@include('partials.icon', ['i' => 'heart'])</span>
                <div><p class="font-semibold">Пока нет избранных вкладов</p><p class="text-sm muted mt-1">Нажмите сердце у вклада — здесь будет его текущая ставка.</p></div>
                <a href="{{ route('deposits.index') }}" class="btn btn-primary">В каталог вкладов</a>
            </div>
        @endforelse
        <p class="text-xs muted px-1">Ставки — лучшие действующие в тенге, по данным банков. Обновляются при каждом импорте.</p>
    </section>

    {{-- A33 · Избранные карты --}}
    @if ($cards->isNotEmpty())
        <section class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-3 px-1">
                <div><p class="kicker">Карты</p><h2 class="font-display font-bold text-xl mt-1">Избранные карты <span class="muted tabular">{{ $cards->count() }}</span></h2></div>
                @if ($cards->count() > 1)<a href="{{ route('cards.compare', ['ids' => $cards->take(4)->map(fn ($o) => $o->card->id)->join(',')]) }}" class="btn btn-ghost">@include('partials.icon', ['i' => 'scale', 'c' => 'w-4 h-4']) Сравнить разом</a>@endif
            </div>
            @foreach ($cards as $i => $o)@include('cards._offer', ['o' => $o, 'i' => $i])@endforeach
        </section>
    @endif

    <section class="space-y-3">
        <div class="px-1"><p class="kicker">03 / Банки</p><h2 class="font-display font-bold text-xl mt-1">Избранные банки <span class="muted tabular">{{ $banks->count() }}</span></h2></div>
        @if ($banks->isEmpty())
            <div class="card empty" data-reveal>
                <span class="icon-tile">@include('partials.icon', ['i' => 'bank'])</span>
                <div><p class="font-semibold">Пока нет избранных банков</p><p class="text-sm muted mt-1">Сердце есть на странице каждого банка.</p></div>
                <a href="{{ route('banks.index') }}" class="btn btn-ghost">Все банки</a>
            </div>
        @else
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
                @foreach ($banks as $i => ['bank' => $b, 'score' => $score, 'stats' => $st])
                    <article class="card lift bank-card" data-reveal style="--d: {{ 0.04 * $i }}s">
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ route('banks.show', $b) }}" class="flex items-center gap-3 min-w-0 group">
                                <span class="bank-mark" style="--c: {{ $b->brand_color ?? 'var(--brand-1)' }}">{{ $b->mark() }}</span>
                                <span class="min-w-0"><span class="block font-semibold text-lg leading-tight group-hover:underline truncate">{{ $b->displayName() }}</span>
                                    <span class="block text-xs muted">{{ $st['offers'] }} {{ plural($st['offers'], 'вклад', 'вклада', 'вкладов') }}</span></span>
                            </a>
                            @include('partials.fav', ['type' => 'bank', 'id' => $b->id, 'on' => true])
                        </div>
                        <div class="cells cols-3 plate !p-0">
                            <div><p class="cell-label">Ставка до</p><p class="font-bold tabular text-lg mt-1">{{ $st['best'] ? rate_format($st['best']->rate) : '—' }}</p></div>
                            <div><p class="cell-label">Рейтинг</p><p class="font-bold tabular text-lg mt-1">{{ score_format($score?->score(\App\Enums\RatingKind::Objective)) }}</p></div>
                            <div><p class="cell-label">Народный</p><p class="font-bold tabular text-lg mt-1">{{ $score?->people ? score_format($score->people['score']) : '—' }}</p></div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
