{{-- A09 · Макет админки. A24: меню по разделам из ResourceRegistry, поиск ⌘K, мобильное меню. --}}
<!DOCTYPE html>
<html lang="ru" data-theme="{{ settings('theme.default', 'light') }}">
<head>@include('partials.head', ['title' => ($title ?? 'Админка')])</head>
<body x-data="{ nav: false }">
@include('partials.backdrop')
@php
    $registry = app(\App\Admin\ResourceRegistry::class);
    $role = auth()->user()->role;
    $res = fn ($k) => $registry->get($k);
    $sections = [
        '' => [['admin.dashboard', [], 'Обзор', 'home', request()->routeIs('admin.dashboard')]],
        'Данные' => collect(['countries', 'banks', 'deposits', 'rates'])->map(fn ($k) => ['admin.resource.index', [$k], $res($k)->label(), $res($k)->icon(), request()->route('key') === $k])->all(),
        // A32 · раздел «Карты»
        'Карты' => collect(['cards', 'packages', 'payment-systems', 'card-tiers'])->map(fn ($k) => ['admin.resource.index', [$k], $res($k)->label(), $res($k)->icon(), request()->route('key') === $k])->all(),
        'Обмен' => [
            ['admin.exchange.index', [], 'Импорт и экспорт', 'swap', request()->routeIs('admin.exchange.index', 'admin.exchange.check', 'admin.exchange.show')],
            ['admin.exchange.tokens', [], 'Токены API', 'key', request()->routeIs('admin.exchange.tokens')],
            ['admin.import.index', [], 'Матрица Excel', 'upload', request()->routeIs('admin.import.*')],
        ],
        'Сообщество' => array_filter([
            ['admin.ratings.index', [], 'Рейтинги', 'star', request()->routeIs('admin.ratings.*')],
            ['admin.reviews.index', [], 'Отзывы', 'quote', request()->routeIs('admin.reviews.*')],
            $role === 'admin' ? ['admin.resource.index', ['users'], 'Пользователи', 'users', request()->route('key') === 'users'] : null,
        ]),
        'Сайт' => [['admin.settings.edit', [], 'Настройки', 'settings', request()->routeIs('admin.settings.*')]],
    ];
@endphp
<div class="min-h-dvh grid lg:grid-cols-[260px_1fr]">
    {{-- Мобильная шапка --}}
    <div class="lg:hidden flex items-center gap-3 p-4 pb-0">
        <button class="btn btn-ghost !p-2.5" @click="nav = !nav" aria-label="Меню">@include('partials.icon', ['i' => 'menu'])</button>
        @include('partials.logo')
        <button class="btn btn-ghost !p-2.5 ml-auto" @click="$dispatch('palette')" aria-label="Поиск">@include('partials.icon', ['i' => 'search'])</button>
    </div>
    <aside class="lg:sticky lg:top-0 lg:h-dvh p-4 lg:block" :class="nav ? 'block' : 'hidden'">
        <div class="card h-full p-5 flex flex-col gap-6 overflow-y-auto">
            <div class="hidden lg:block">@include('partials.logo')</div>
            <button type="button" class="input !py-2.5 flex items-center gap-2 text-left faint text-sm" @click="$dispatch('palette')">
                @include('partials.icon', ['i' => 'search', 'c' => 'w-4 h-4']) Поиск <kbd class="ml-auto chip !py-0 !px-1.5 mono text-[11px]">⌘K</kbd>
            </button>
            <nav class="grid gap-4 text-sm font-semibold">
                @foreach ($sections as $section => $links)
                    <div class="grid gap-0.5">
                        @if ($section)<p class="label px-3 mb-1">{{ $section }}</p>@endif
                        @foreach ($links as [$route, $params, $label, $icon, $active])
                            <a href="{{ route($route, $params) }}" class="flex items-center gap-3 px-3 py-2 rounded-xl transition {{ $active ? '' : 'muted hover:bg-[var(--card-2)] hover:text-[var(--text)]' }}" @if ($active) style="background: var(--ink); color: var(--ink-text)" aria-current="page" @endif>
                                @include('partials.icon', ['i' => $icon, 'c' => 'w-[18px] h-[18px]'])
                                {{ $label }}
                                {{-- A18 · очередь модерации --}}
                                @if ($route === 'admin.reviews.index' && ($pending = \App\Models\Review::pending()->count()))<span class="ml-auto chip !py-0 !px-2 !bg-[var(--brand-3)] !text-white">{{ $pending }}</span>@endif
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>
            <div class="mt-auto space-y-3 text-sm">
                <a href="{{ url('/') }}" class="muted hover:text-[var(--text)] flex items-center gap-2">@include('partials.icon', ['i' => 'external', 'c' => 'w-4 h-4']) Открыть сайт</a>
                <div class="flex items-center justify-between gap-2">
                    <span class="truncate">{{ auth()->user()->name }} <span class="faint text-xs block">{{ ['admin' => 'администратор', 'editor' => 'редактор'][$role] ?? $role }}</span></span>
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
            <div class="card px-5 py-4 text-sm flex items-center gap-2" role="status" style="border-color: color-mix(in oklab, var(--ok) 40%, transparent)">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4 text-[var(--ok)]']) {{ session('status') }}</div>
        @endif
        @error('bulk')<div class="card px-5 py-4 text-sm error" role="alert">{{ $message }}</div>@enderror
        @yield('content')
    </main>
</div>

{{-- A24 · Палитра поиска ⌘K / Ctrl+K --}}
<div x-data="adminPalette('{{ route('admin.search') }}')" @palette.window="show()" @keydown.window.prevent.meta.k="show()" @keydown.window.prevent.ctrl.k="show()" @keydown.escape.window="open = false"
     x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center pt-[12vh] px-4" style="background: color-mix(in oklab, var(--bg) 60%, transparent); backdrop-filter: blur(6px)">
    <div class="card w-full max-w-xl p-2 shadow-2xl" @click.outside="open = false">
        <label class="flex items-center gap-3 px-3">
            @include('partials.icon', ['i' => 'search', 'c' => 'w-5 h-5 faint'])
            <input x-ref="q" x-model="q" @input.debounce.200ms="search()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()"
                   class="flex-1 bg-transparent py-3 outline-none text-[15px]" placeholder="Банк, код, алиас, вклад, e-mail…" aria-label="Поиск по админке">
        </label>
        <ul class="max-h-[50vh] overflow-y-auto" x-show="items.length" role="listbox">
            <template x-for="(it, i) in items" :key="it.url">
                <li><a :href="it.url" class="flex items-center gap-3 px-3 py-2.5 rounded-xl" :class="i === active ? 'bg-[var(--card-2)]' : ''" @mouseenter="active = i">
                    <span class="chip !py-0 text-[11px]" x-text="it.type"></span>
                    <span class="min-w-0"><span class="font-semibold block truncate" x-text="it.title"></span><span class="faint text-xs block truncate" x-text="it.sub"></span></span>
                </a></li>
            </template>
        </ul>
        <p class="faint text-sm px-3 py-3" x-show="q.length > 1 && !items.length && !loading">Ничего не нашлось</p>
    </div>
</div>
</body>
</html>
