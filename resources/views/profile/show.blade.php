{{-- A28 · Публичная карточка автора /u/{username}: кто стоит за оценками народного рейтинга. --}}
@extends('layouts.app', [
    'title' => $user->name.' — отзывы о банках',
    'description' => $user->name.': '.$stats['published'].' '.plural($stats['published'], 'отзыв', 'отзыва', 'отзывов').' о банках'.($stats['avg'] ? ', средняя оценка '.score_format($stats['avg']) : '').'.',
])

@section('content')
@php($max = max(1, (int) $dist->max()))
<div class="space-y-6">
    <nav class="text-sm muted flex items-center gap-2 px-1 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span>
        <a href="{{ route('ratings', ['by' => 'people']) }}" class="hover:text-[var(--text)]">Народный рейтинг</a><span class="faint">/</span>
        <span class="text-[var(--text)]">{{ $user->name }}</span>
    </nav>

    @if ($own && ! $user->is_public)
        <div class="card card-soft p-4 text-sm flex items-center gap-3">@include('partials.icon', ['i' => 'lock', 'c' => 'w-4 h-4']) Профиль скрыт — его видите только вы. <a href="{{ route('account.settings') }}#profile" class="font-semibold underline">Открыть</a></div>
    @endif

    <section class="grid grid-cols-6 gap-4 lg:gap-6">
        <div class="card col-span-6 lg:col-span-4 p-6 lg:p-8 flex flex-col gap-6" data-reveal>
            <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                @include('partials.avatar', ['u' => $user, 'c' => 'avatar-xl'])
                <div class="min-w-0 flex-1">
                    <p class="kicker">Автор народного рейтинга</p>
                    <h1 class="font-display font-bold text-[30px] sm:text-4xl leading-[1.1] mt-1 break-words">{{ $user->name }}</h1>
                    <p class="muted mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        @if ($user->location())<span class="inline-flex items-center gap-1">@include('partials.icon', ['i' => 'pin', 'c' => 'w-3.5 h-3.5']) {{ $user->location() }}</span>@endif
                        <span class="inline-flex items-center gap-1">@include('partials.icon', ['i' => 'calendar', 'c' => 'w-3.5 h-3.5']) на портале с {{ $user->memberSince() }}</span>
                    </p>
                </div>
                @if ($own)<a href="{{ route('account.settings') }}#profile" class="btn btn-ghost shrink-0">@include('partials.icon', ['i' => 'pen', 'c' => 'w-4 h-4']) Редактировать</a>@endif
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="level"><i>@include('partials.icon', ['i' => 'award', 'c' => 'w-3.5 h-3.5'])</i>{{ $level['label'] }}</span>
                @if (app(\App\Support\Founder::class)->is($user))<a href="{{ route('author') }}" class="chip hover:!bg-[var(--card-2)]">@include('partials.icon', ['i' => 'pen', 'c' => 'w-3.5 h-3.5']) Страница автора →</a>@endif {{-- A55 --}}
                @if ($user->isStaff())<span class="chip">@include('partials.icon', ['i' => 'shield', 'c' => 'w-3.5 h-3.5']) команда портала</span>@endif
            </div>
            @if ($user->bio)<p class="text-[15px] leading-[1.55] max-w-2xl">{{ $user->bio }}</p>@endif
            <div class="cells cols-3 plate !p-0">
                <div><p class="cell-label">Отзывов</p><p class="num-card mt-1.5 !text-[1.9rem]">{{ $stats['published'] }}</p></div>
                <div><p class="cell-label">Банков</p><p class="num-card mt-1.5 !text-[1.9rem]">{{ $stats['banks'] }}</p></div>
                <div><p class="cell-label">«Полезно»</p><p class="num-card mt-1.5 !text-[1.9rem]">{{ $stats['helpful'] }}</p></div>
            </div>
        </div>

        {{-- Главный факт: средняя оценка автора --}}
        <div class="card card-ink col-span-6 lg:col-span-2 p-6 lg:p-7 flex flex-col justify-between gap-6 min-h-[240px]" data-reveal style="--d:.06s">
            <div class="flex items-start justify-between gap-3">
                <span class="text-sm font-semibold opacity-70">Средняя оценка банкам</span>
                @include('partials.icon', ['i' => 'star', 'c' => 'w-5 h-5 opacity-60'])
            </div>
            <div>
                @if ($stats['avg'] !== null)
                    <p class="num-hero tabular leading-none">{{ score_format($stats['avg']) }}<span class="text-[0.35em] opacity-60 ml-1">/ 5</span></p>
                @else
                    {{-- A36 · без оценок — фраза вместо «—/5» --}}
                    <p class="font-display font-bold text-3xl leading-tight">Оценок пока нет</p>
                    <p class="text-sm opacity-60 mt-1">Распределение появится после первого отзыва.</p>
                @endif
                <div class="space-y-1.5 mt-5">
                    @for ($s = 5; $s >= 1; $s--)
                        <div class="dist-row"><span class="opacity-70">{{ $s }}</span><span class="meter !bg-current/15"><i style="width: {{ round(($dist[$s] ?? 0) / $max * 100) }}%"></i></span><span class="text-right opacity-70">{{ $dist[$s] ?? 0 }}</span></div>
                    @endfor
                </div>
            </div>
        </div>
    </section>

    <section class="card p-6 lg:p-7" data-reveal>
        <div class="flex items-center justify-between gap-3">
            <div><p class="kicker">01 / Отзывы</p><h2 class="font-display font-bold text-xl mt-1">Отзывы о банках</h2></div>
        </div>
        @forelse ($reviews as $r)
            @include('reviews._item', ['r' => $r, 'showBank' => true])
        @empty
            <div class="empty">
                <span class="icon-tile">@include('partials.icon', ['i' => 'quote'])</span>
                <p class="muted">Опубликованных отзывов пока нет.</p>
            </div>
        @endforelse
        @if ($reviews->hasPages())<div class="pt-4">{{ $reviews->links() }}</div>@endif
    </section>
</div>
@endsection
