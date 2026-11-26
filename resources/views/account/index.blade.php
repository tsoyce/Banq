{{-- A28 · Кабинет — обзор: счётчики, последние отзывы, избранные вклады, сравнения, подсказки по заполнению профиля. --}}
@extends('layouts.app', ['title' => 'Личный кабинет'])

@section('content')
@php
    $todo = collect([
        ['done' => (bool) $user->avatar, 'label' => 'Добавить фото', 'href' => route('account.settings').'#profile'],
        ['done' => (bool) ($user->city || $user->country_code), 'label' => 'Указать город и страну', 'href' => route('account.settings').'#profile'],
        ['done' => (bool) $user->bio, 'label' => 'Пара слов о себе', 'href' => route('account.settings').'#profile'],
        ['done' => $stats['published'] + $stats['pending'] > 0, 'label' => 'Первый отзыв о банке', 'href' => route('account.reviews')],
        ['done' => $stats['favorites'] > 0, 'label' => 'Сохранить вклад в избранное', 'href' => route('deposits.index')],
    ]);
    $progress = (int) round($todo->where('done', true)->count() / $todo->count() * 100);
@endphp
<div class="space-y-6">
    @include('account._shell', ['tab' => 'index'])

    {{-- Ячейки-счётчики на волосках --}}
    <section class="card p-2" data-reveal>
        <div class="cells cols-4">
            @foreach ([
                ['Опубликовано', $stats['published'], route('account.reviews', ['status' => 'approved'])],
                ['На модерации', $stats['pending'], route('account.reviews', ['status' => 'pending'])],
                ['В избранном', $stats['favorites'], route('account.favorites')],
                ['Сравнений', $stats['comparisons'], route('account.comparisons')],
            ] as [$label, $value, $href])
                <a href="{{ $href }}" class="!p-5 hover:bg-[var(--card-2)] transition">
                    <p class="cell-label">{{ $label }}</p>
                    <p class="num-card mt-2">{{ $value }}</p>
                </a>
            @endforeach
        </div>
    </section>

    <section class="grid grid-cols-6 gap-4 lg:gap-6 items-start">
        {{-- Последние отзывы --}}
        <div class="card col-span-6 lg:col-span-4 p-6 lg:p-7" data-reveal>
            <div class="flex items-center justify-between gap-3">
                <div><p class="kicker">02 / Отзывы</p><h2 class="font-display font-bold text-xl mt-1">Последние отзывы</h2></div>
                <a href="{{ route('account.reviews') }}" class="btn btn-ghost !py-2 text-sm">Все @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</a>
            </div>
            @forelse ($recentReviews as $r)
                @include('account._review-row', ['r' => $r])
            @empty
                <div class="empty">
                    <span class="icon-tile">@include('partials.icon', ['i' => 'pen'])</span>
                    <div><p class="font-semibold">Вы ещё не писали отзывов</p><p class="text-sm muted mt-1">Оцените свой банк — оценка войдёт в народный рейтинг после модерации.</p></div>
                    <a href="{{ route('banks.index') }}" class="btn btn-primary">Выбрать банк</a>
                </div>
            @endforelse
        </div>

        {{-- Профиль заполнен на … --}}
        <div class="card card-soft col-span-6 lg:col-span-2 p-6 lg:p-7 space-y-4" data-reveal style="--d:.06s">
            <div class="flex items-end justify-between gap-3">
                <div><p class="kicker">Профиль</p><h2 class="font-display font-bold text-xl mt-1">Заполнен</h2></div>
                <p class="num-card tabular">{{ $progress }}<span class="text-[0.45em] muted ml-0.5">%</span></p>
            </div>
            <div class="meter"><i style="width: {{ $progress }}%"></i></div>
            <ul class="space-y-1">
                @foreach ($todo as $t)
                    <li><a href="{{ $t['href'] }}" class="flex items-center gap-2.5 py-1.5 text-sm {{ $t['done'] ? 'muted line-through' : 'font-semibold hover:underline' }}">
                        <span class="grid place-items-center w-5 h-5 rounded-full {{ $t['done'] ? 'bg-[var(--ok)] text-white' : 'bg-[var(--card)]' }}">@include('partials.icon', ['i' => $t['done'] ? 'check' : 'plus', 'c' => 'w-3 h-3'])</span>
                        {{ $t['label'] }}</a></li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="grid grid-cols-6 gap-4 lg:gap-6 items-start">
        <div class="card col-span-6 lg:col-span-3 p-6 lg:p-7" data-reveal>
            <div class="flex items-center justify-between gap-3 mb-3">
                <div><p class="kicker">03 / Избранное</p><h2 class="font-display font-bold text-xl mt-1">Избранные вклады</h2></div>
                <a href="{{ route('account.favorites') }}" class="btn btn-ghost !py-2 text-sm">Все @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</a>
            </div>
            @forelse ($deposits as $o)
                <a href="{{ route('deposits.show', $o->product) }}" class="list-row">
                    <span class="bank-mark" style="--c: {{ $o->bank()->brand_color ?? 'var(--brand-1)' }}">{{ $o->bank()->mark() }}</span>
                    <span class="min-w-0 flex-1"><span class="block font-semibold truncate">{{ $o->product->name }}</span><span class="block text-xs muted truncate">{{ $o->bank()->displayName() }} · {{ $o->product->type->label() }}</span></span>
                    <span class="font-bold tabular text-lg">{{ rate_format($o->rate->rate) }}</span>
                </a>
            @empty
                <p class="text-sm muted py-4">Нажмите @include('partials.icon', ['i' => 'heart', 'c' => 'w-4 h-4 inline -mt-0.5']) у вклада в каталоге — он появится здесь вместе с текущей ставкой.</p>
                <a href="{{ route('deposits.index') }}" class="btn btn-primary">В каталог вкладов</a>
            @endforelse
        </div>

        <div class="card col-span-6 lg:col-span-3 p-6 lg:p-7" data-reveal style="--d:.06s">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div><p class="kicker">04 / Сравнения</p><h2 class="font-display font-bold text-xl mt-1">Сохранённые сравнения</h2></div>
                <a href="{{ route('account.comparisons') }}" class="btn btn-ghost !py-2 text-sm">Все @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</a>
            </div>
            @forelse ($comparisons as $c)
                <a href="{{ $c->url() }}" class="list-row">
                    <span class="icon-tile">@include('partials.icon', ['i' => 'scale', 'c' => 'w-5 h-5'])</span>
                    <span class="min-w-0 flex-1"><span class="block font-semibold truncate">{{ $c->title }}</span>
                        <span class="block text-xs muted">{{ count($c->product_ids) }} {{ plural(count($c->product_ids), 'вклад', 'вклада', 'вкладов') }}@if ($c->amount) · {{ money_format($c->amount) }}@endif @if ($c->term_months) · {{ $c->term_months }} мес@endif · {{ $c->created_at->translatedFormat('j M') }}</span></span>
                    @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4 muted'])
                </a>
            @empty
                <p class="text-sm muted py-4">На странице сравнения нажмите «Сохранить» — подборка останется здесь, со своей суммой и сроком.</p>
                <a href="{{ route('deposits.compare') }}" class="btn btn-ghost">К сравнению</a>
            @endforelse
        </div>
    </section>
</div>
@endsection
