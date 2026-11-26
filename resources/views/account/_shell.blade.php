{{-- A28 · Шапка личного кабинета: карточка пользователя + главный факт (вклад в народный рейтинг) + вкладки.
     $user, $stats, $level — из AccountController::shell(), $tab — активная вкладка. --}}
<nav class="text-sm muted flex items-center gap-2 px-1 flex-wrap">
    <a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span>
    <span class="text-[var(--text)]">Личный кабинет</span>
</nav>

<section class="grid grid-cols-6 gap-4 lg:gap-6">
    <div class="card col-span-6 lg:col-span-4 p-6 lg:p-8 flex flex-col sm:flex-row sm:items-center gap-6" data-reveal>
        @include('partials.avatar', ['u' => $user, 'c' => 'avatar-xl'])
        <div class="min-w-0 flex-1 space-y-3">
            <div>
                <p class="kicker">01 / Кабинет</p>
                <h1 class="font-display font-bold text-[30px] sm:text-4xl leading-[1.1] mt-1 break-words">{{ $user->name }}</h1>
                <p class="muted mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    <span class="mono">{{ '@'.$user->username }}</span>
                    @if ($user->location())<span class="inline-flex items-center gap-1">@include('partials.icon', ['i' => 'pin', 'c' => 'w-3.5 h-3.5']) {{ $user->location() }}</span>@endif
                    <span class="inline-flex items-center gap-1">@include('partials.icon', ['i' => 'calendar', 'c' => 'w-3.5 h-3.5']) с {{ $user->memberSince() }}</span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="level"><i>@include('partials.icon', ['i' => 'award', 'c' => 'w-3.5 h-3.5'])</i>{{ $level['label'] }}</span>
                @unless ($user->is_public)<span class="chip">@include('partials.icon', ['i' => 'lock', 'c' => 'w-3.5 h-3.5']) профиль скрыт</span>@endunless
                @if ($user->isStaff())<span class="chip">{{ $user->role === 'admin' ? 'администратор' : 'редактор' }}</span>@endif
            </div>
        </div>
        <div class="flex flex-wrap sm:flex-col gap-2 sm:shrink-0">
            <a href="{{ route('profile.show', $user->username) }}" class="btn btn-ghost flex-1">@include('partials.icon', ['i' => 'user', 'c' => 'w-4 h-4']) Публичный профиль</a>
            <a href="{{ route('account.settings') }}" class="btn btn-outline flex-1">@include('partials.icon', ['i' => 'settings', 'c' => 'w-4 h-4']) Настройки</a>
        </div>
    </div>

    {{-- Главный факт: опубликованные отзывы в народном рейтинге --}}
    <a href="{{ route('account.reviews') }}" class="card card-ink lift col-span-6 lg:col-span-2 p-6 lg:p-7 flex flex-col justify-between gap-6 min-h-[220px]" data-reveal style="--d:.06s">
        <div class="flex items-start justify-between gap-3">
            <span class="text-sm font-semibold opacity-70">Ваш голос в народном рейтинге</span>
            @include('partials.icon', ['i' => 'users', 'c' => 'w-5 h-5 opacity-60'])
        </div>
        <div>
            <p class="num-hero tabular leading-none" x-count="{{ $stats['published'] }}">{{ $stats['published'] }}</p>
            <p class="text-sm opacity-70 mt-2">{{ plural($stats['published'], 'отзыв опубликован', 'отзыва опубликовано', 'отзывов опубликовано') }} · {{ $stats['helpful'] }} «полезно»</p>
            @if ($level['next'])
                <p class="text-xs opacity-60 mt-1">До ступени «{{ $level['next'] }}»@if ($level['need']) — ещё {{ $level['need'] }} {{ plural($level['need'], 'отзыв', 'отзыва', 'отзывов') }}@endif</p>
            @endif
        </div>
    </a>
</section>

<div class="card p-2" data-reveal style="--d:.1s">
    <nav class="tabs" aria-label="Разделы кабинета">
        @foreach ([
            ['index', 'grid', 'Обзор', null],
            ['reviews', 'pen', 'Мои отзывы', $stats['published'] + $stats['pending'] + $stats['rejected']],
            ['favorites', 'heart', 'Избранное', $stats['favorites']],
            ['comparisons', 'scale', 'Сравнения', $stats['comparisons']],
            ['settings', 'settings', 'Настройки', null],
        ] as [$key, $icon, $label, $count])
            <a href="{{ route('account.'.$key) }}" class="{{ $tab === $key ? 'is-active' : '' }}" @if ($tab === $key) aria-current="page" @endif>
                @include('partials.icon', ['i' => $icon, 'c' => 'w-[18px] h-[18px]']) {{ $label }}
                @if ($count)<span class="chip tabular {{ $tab === $key ? '!bg-white/15 !text-current' : '' }}">{{ $count }}</span>@endif
            </a>
        @endforeach
    </nav>
</div>
