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
        @include('partials.country-switch')
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
        <a href="{{ route('ratings') }}" class="hidden md:inline-flex items-center gap-1 rounded-full bg-white/10 px-3 py-1 font-semibold whitespace-nowrap" style="color: #fdba74">народный рейтинг →</a>
    </div>
</div>

{{-- Шапка --}}
<header class="sticky top-0 z-40 backdrop-blur-md" style="background: color-mix(in oklab, var(--bg) 85%, transparent)">
    <div class="mx-auto max-w-[1440px] px-4 lg:px-6 h-[72px] flex items-center gap-3">
        <button class="btn btn-ghost !p-2.5 lg:!hidden" @click="nav = !nav" aria-label="Меню">@include('partials.icon', ['i' => 'menu'])</button>
        @include('partials.logo')
        <nav class="hidden md:flex items-center gap-2 ml-6 lg:ml-24">
            <a href="{{ route('deposits.index') }}" class="pill {{ request()->routeIs('deposits.index', 'deposits.show') ? 'is-active' : '' }}">@include('partials.icon', ['i' => 'piggy', 'c' => 'w-[18px] h-[18px] '.(request()->routeIs('deposits.index', 'deposits.show') ? '' : 'text-[var(--brand-1)]')]) Вклады</a>
            {{-- A18 · Банки и рейтинги --}}
            <a href="{{ route('banks.index') }}" class="pill {{ request()->routeIs('banks.*') ? 'is-active' : '' }}">@include('partials.icon', ['i' => 'bank', 'c' => 'w-[18px] h-[18px] '.(request()->routeIs('banks.*') ? '' : 'text-[var(--brand-2)]')]) Банки</a>
            <a href="{{ route('ratings') }}" class="pill {{ request()->routeIs('ratings') ? 'is-active' : '' }}">@include('partials.icon', ['i' => 'star', 'c' => 'w-[18px] h-[18px] '.(request()->routeIs('ratings') ? '' : 'text-[var(--brand-3)]')]) Рейтинги</a>
            <a href="{{ route('deposits.compare') }}" class="pill hidden xl:inline-flex {{ request()->routeIs('deposits.compare') ? 'is-active' : '' }}" x-data>@include('partials.icon', ['i' => 'scale', 'c' => 'w-[18px] h-[18px] '.(request()->routeIs('deposits.compare') ? '' : 'text-[var(--ok)]')]) Сравнение <span class="chip !py-0 !px-1.5 !text-[0.7rem]" x-show="$store.compare.items.length" x-text="$store.compare.items.length" x-cloak></span></a>
        </nav>
        <div class="ml-auto flex items-center gap-2">
            <button class="btn btn-ghost !p-2.5" aria-label="Поиск">@include('partials.icon', ['i' => 'search', 'c' => 'w-[18px] h-[18px]'])</button>
            @include('partials.theme-toggle')
            @auth
                @if (auth()->user()->isStaff())
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Админка</a>
                @else
                    {{-- A18 · посетитель: имя и выход --}}
                    <span class="hidden sm:flex items-center gap-2 text-sm font-semibold"><span class="avatar !w-9 !h-9 !bg-[var(--card)]">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="max-w-[10rem] truncate">{{ auth()->user()->name }}</span></span>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost !p-2.5" aria-label="Выйти" title="Выйти">@include('partials.icon', ['i' => 'logout', 'c' => 'w-[18px] h-[18px]'])</button></form>
                @endif
            @else
                <a href="{{ route('login', ['to' => request()->getRequestUri()]) }}" class="btn btn-ghost">Войти</a>
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
                    ['piggy', 'Вклады', route('deposits.index')],
                    ['bank', 'Банки', route('banks.index')],
                    ['star', 'Рейтинг банков', route('ratings')],
                    ['scale', 'Сравнение', route('deposits.compare')],
                    ['calc', 'Калькулятор', route('home').'#calc'],
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

{{-- A14 · Плавающая панель сравнения (данные — $store.compare в resources/js/app.js) --}}
@unless (request()->routeIs('deposits.compare'))
<div x-data x-show="$store.compare.items.length" x-cloak x-transition.opacity.duration.300ms class="compare-bar">
    <div class="flex items-center">
        <template x-for="i in $store.compare.items" :key="i.id">
            <span class="bank-mark" :style="'--c:' + i.color" x-text="i.code" :title="i.name"></span>
        </template>
    </div>
    <span class="text-sm font-semibold whitespace-nowrap" x-text="$store.compare.full ? 'Максимум ' + $store.compare.limit + ' вклада' : 'К сравнению: ' + $store.compare.items.length + ' из ' + $store.compare.limit"></span>
    <button type="button" class="text-sm opacity-60 hover:opacity-100 px-1" @click="$store.compare.clear()">очистить</button>
    <a :href="$store.compare.url()" class="btn btn-brand !py-2">Сравнить @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</a>
</div>
@endunless

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
