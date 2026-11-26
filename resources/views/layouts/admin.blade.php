{{-- A09 · Макет админки. --}}
<!DOCTYPE html>
<html lang="ru" data-theme="{{ settings('theme.default', 'light') }}">
<head>@include('partials.head', ['title' => ($title ?? 'Админка')])</head>
<body>
@include('partials.backdrop')
<div class="min-h-dvh grid lg:grid-cols-[260px_1fr]">
    <aside class="lg:sticky lg:top-0 lg:h-dvh p-4">
        <div class="card h-full p-5 flex flex-col gap-8">
            @include('partials.logo')
            <nav class="grid gap-1 text-sm font-semibold">
                @foreach ([
                    ['admin.dashboard', 'Обзор', 'M3 12l9-9 9 9M5 10v10h14V10'],
                    ['admin.banks.index', 'Банки', 'M3 21h18M5 21V10M19 21V10M9 21v-6h6v6M12 3l9 5H3z'],
                    ['admin.import.index', 'Импорт Excel', 'M12 3v12m0 0l-4-4m4 4l4-4M4 17v3h16v-3'],
                    ['admin.ratings.index', 'Рейтинги', 'M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z'],
                    ['admin.reviews.index', 'Отзывы', 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
                    ['admin.settings.edit', 'Настройки сайта', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3 15H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.2-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 9 4.6V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0 1.2 2.9H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z'],
                ] as [$route, $label, $icon])
                    @php($active = request()->routeIs(str_replace('.index', '.*', str_replace('.edit', '.*', $route))))
                    <a href="{{ route($route) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition {{ $active ? '' : 'muted hover:bg-[var(--card-2)]' }}" @if ($active) style="background: var(--ink); color: var(--ink-text)" @endif>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        {{ $label }}
                        {{-- A18 · очередь модерации --}}
                        @if ($route === 'admin.reviews.index' && ($pending = \App\Models\Review::pending()->count()))<span class="ml-auto chip !py-0 !px-2 !bg-[var(--brand-3)] !text-white">{{ $pending }}</span>@endif
                    </a>
                @endforeach
            </nav>
            <div class="mt-auto space-y-3 text-sm">
                <a href="{{ url('/') }}" class="muted hover:text-[var(--text)] block">↗ Открыть сайт</a>
                <div class="flex items-center justify-between gap-2">
                    <span class="truncate">{{ auth()->user()->name }}</span>
                    <div class="flex gap-1">
                        @include('partials.theme-toggle')
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost !p-2.5" aria-label="Выйти" title="Выйти">@include('partials.icon', ['i' => 'logout', 'c' => 'w-[18px] h-[18px]'])</button></form>
                    </div>
                </div>
            </div>
        </div>
    </aside>
    <main class="p-4 lg:p-8 space-y-6 min-w-0">
        @if (session('status'))
            <div class="glass px-5 py-4 text-sm" style="border-color: color-mix(in oklab, var(--ok) 40%, transparent)">✓ {{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
