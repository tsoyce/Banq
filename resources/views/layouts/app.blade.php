{{-- A04 · Макет витрины. --}}
<!DOCTYPE html>
<html lang="ru" data-theme="{{ settings('theme.default', 'dark') }}">
<head>
    @include('partials.head')
    {!! settings('site.counters') !!}
</head>
<body>
@include('partials.backdrop')

<header class="sticky top-0 z-40">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 pt-4">
        <nav class="glass !rounded-full flex items-center justify-between gap-4 px-4 py-2.5">
            @include('partials.logo')
            <ul class="hidden md:flex items-center gap-1 text-sm font-semibold">
                @foreach (['Депозиты' => '#deposits', 'Топ ставок' => '#top', 'Калькулятор' => '#calc', 'Рейтинги' => '#ratings', 'Карты и кредиты' => '#soon'] as $label => $href)
                    <li><a href="{{ $href }}" class="px-3 py-2 rounded-full muted hover:text-[var(--text)] hover:bg-[var(--surface)] transition">{{ $label }}</a></li>
                @endforeach
            </ul>
            <div class="flex items-center gap-2">
                @include('partials.theme-toggle')
                @auth
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost !py-2 text-sm">Админка</a>
                @endauth
            </div>
        </nav>
    </div>
</header>

<main>
    {{ $slot ?? '' }}
    @yield('content')
</main>

<footer class="mt-32 border-t border-[var(--border)]">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 py-12 grid gap-10 md:grid-cols-[1.4fr_1fr_1fr]">
        <div class="space-y-4">
            @include('partials.logo')
            <p class="muted max-w-md text-sm leading-relaxed">{{ settings('site.tagline') }}</p>
        </div>
        <div class="text-sm space-y-2">
            <p class="faint uppercase tracking-widest text-xs font-bold mb-3">Разделы</p>
            <p><a href="#deposits" class="muted hover:text-[var(--text)]">Депозиты</a></p>
            <p><a href="#top" class="muted hover:text-[var(--text)]">Топ ставок</a></p>
            <p><a href="#ratings" class="muted hover:text-[var(--text)]">Рейтинги</a></p>
        </div>
        <div class="text-sm space-y-2">
            <p class="faint uppercase tracking-widest text-xs font-bold mb-3">Связь</p>
            @if (settings('site.contact_email'))
                <p><a href="mailto:{{ settings('site.contact_email') }}" class="muted hover:text-[var(--text)]">{{ settings('site.contact_email') }}</a></p>
            @endif
        </div>
    </div>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 pb-10 flex flex-col md:flex-row gap-3 justify-between text-xs faint">
        <p>© {{ project_years() }} {{ settings('site.copyright') ?: settings('site.name') }}</p>
        <p class="max-w-2xl md:text-right">{{ settings('site.footer_text') }}</p>
    </div>
</footer>
</body>
</html>
