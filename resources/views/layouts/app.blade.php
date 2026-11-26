{{-- A13 · Макет витрины v2: тёмная лента-тикер, шапка с пилюлями, левое меню, контент. --}}
<!DOCTYPE html>
<html lang="ru" data-theme="{{ settings('theme.default', 'light') }}">
<head>
    @include('partials.head')
    {!! settings('site.counters') !!}
</head>
<body x-data="{ nav: false }">

{{-- Лента: статус + бегущая строка ставок --}}
<div class="topline text-[13px]">
    <div class="mx-auto max-w-[1440px] px-4 lg:px-6 h-11 flex items-center gap-4">
        <span class="hidden sm:inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 font-semibold whitespace-nowrap">
            <span class="dot"></span> Ставки банков Казахстана
        </span>
        <div class="ticker flex-1 min-w-0">
            <div class="ticker-track mono text-[12px]">
                @if (isset($ticker) && $ticker->isNotEmpty())
                    @foreach ([1, 2] as $_)
                        @foreach ($ticker as $r)
                            <span class="whitespace-nowrap"><span class="text-white/55">{{ $r->product->bank->code }}</span> {{ mb_strtolower($r->product->type->label()) }} <b class="text-white">{{ rate_format($r->rate) }}</b></span>
                        @endforeach
                    @endforeach
                @else
                    <span class="text-white/60">Скоро здесь появятся ставки — загрузите Excel в админке</span>
                @endif
            </div>
        </div>
        <a href="#ratings" class="hidden md:inline-flex items-center gap-1 rounded-full bg-white/10 px-3 py-1 font-semibold whitespace-nowrap" style="color: #fdba74">народный рейтинг →</a>
    </div>
</div>

{{-- Шапка --}}
<header class="sticky top-0 z-40 backdrop-blur-md" style="background: color-mix(in oklab, var(--bg) 85%, transparent)">
    <div class="mx-auto max-w-[1440px] px-4 lg:px-6 h-[72px] flex items-center gap-3">
        <button class="btn btn-ghost !p-2.5 lg:!hidden" @click="nav = !nav" aria-label="Меню">@include('partials.icon', ['i' => 'menu'])</button>
        @include('partials.logo')
        <nav class="hidden md:flex items-center gap-2 ml-6 lg:ml-24">
            <a href="#deposits" class="pill">@include('partials.icon', ['i' => 'piggy', 'c' => 'w-[18px] h-[18px] text-[var(--brand-1)]']) Вклады</a>
            <a href="#top" class="pill">@include('partials.icon', ['i' => 'chart', 'c' => 'w-[18px] h-[18px] text-[var(--ok)]']) Топ ставок</a>
            <a href="#calc" class="pill">@include('partials.icon', ['i' => 'calc', 'c' => 'w-[18px] h-[18px]']) Калькулятор</a>
        </nav>
        <div class="ml-auto flex items-center gap-2">
            <button class="btn btn-ghost !p-2.5" aria-label="Поиск">@include('partials.icon', ['i' => 'search', 'c' => 'w-[18px] h-[18px]'])</button>
            @include('partials.theme-toggle')
            @auth
                <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Админка</a>
            @endauth
        </div>
    </div>
</header>

<div class="mx-auto max-w-[1440px] px-4 lg:px-6 pb-16 grid lg:grid-cols-[230px_minmax(0,1fr)] gap-6">
    {{-- Левое меню --}}
    <aside class="lg:block" :class="nav ? 'block' : 'hidden'">
        <div class="lg:sticky lg:top-[88px] space-y-6 pt-2">
            <nav class="grid gap-0.5 text-[15px]">
                @foreach ([
                    ['piggy', 'Вклады', '#deposits'],
                    ['star', 'Рейтинг банков', '#ratings'],
                    ['scale', 'Сравнение', '#top'],
                    ['calc', 'Калькулятор', '#calc'],
                    ['card', 'Карты', '#soon'],
                    ['loan', 'Кредиты', '#soon'],
                    ['globe', 'Платёжные системы', '#soon'],
                    ['news', 'Новости', '#soon'],
                ] as [$icon, $label, $href])
                    <a href="{{ $href }}" class="flex items-center gap-3 rounded-xl px-2.5 py-2 hover:bg-[var(--card)] transition">
                        @include('partials.icon', ['i' => $icon, 'c' => 'w-[19px] h-[19px] muted'])
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
            <div class="border-t border-[var(--line)] pt-4 px-2.5 text-[13px] faint flex flex-wrap gap-x-4 gap-y-1">
                <a href="#soon">О проекте</a><a href="#soon">Для банков</a><a href="#soon">Рассылка</a>
            </div>
        </div>
    </aside>

    <main class="min-w-0 pt-2">
        @yield('content')
    </main>
</div>

<footer class="border-t border-[var(--line)]">
    <div class="mx-auto max-w-[1440px] px-4 lg:px-6 py-10 flex flex-col md:flex-row gap-6 md:items-center justify-between text-sm">
        <div class="flex items-center gap-4">
            @include('partials.logo')
            <span class="muted max-w-sm">{{ settings('site.tagline') }}</span>
        </div>
        <div class="faint md:text-right space-y-1">
            <p>© {{ project_years() }} {{ settings('site.copyright') ?: settings('site.name') }}@if (settings('site.contact_email')) · <a href="mailto:{{ settings('site.contact_email') }}">{{ settings('site.contact_email') }}</a>@endif</p>
            <p class="max-w-xl">{{ settings('site.footer_text') }}</p>
        </div>
    </div>
</footer>
</body>
</html>
