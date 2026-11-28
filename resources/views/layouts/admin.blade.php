{{-- A09 · Макет админки. A24: поиск ⌘K, мобильное меню. A70: меню из App\Admin\Navigation — группы сворачиваются,
     режим «только иконки» (запоминается в браузере), хлебные крошки, бейджи очередей; палитра ⌘K ищет и страницы, и записи. --}}
<!DOCTYPE html>
<html lang="ru" data-theme="{{ settings('theme.default', 'light') }}">
<head>@include('partials.head', ['title' => ($title ?? 'Админка')])</head>
@php
    $nav = app(\App\Admin\Navigation::class);
    $role = auth()->user()->role;
    $navGroups = $nav->groups();
    $crumb = $nav->current();
    $onDashboard = request()->routeIs('admin.dashboard');
@endphp
<body x-data="adminNav(@js($crumb[0]['key'] ?? null))">
@include('partials.backdrop')
<div class="admin-shell min-h-dvh grid" :class="compact ? 'is-compact' : ''">
    {{-- Мобильная шапка --}}
    <div class="lg:hidden flex items-center gap-3 p-4 pb-0">
        <button class="btn btn-ghost !p-2.5" @click="mobile = !mobile" :aria-expanded="mobile" aria-label="Меню">@include('partials.icon', ['i' => 'menu'])</button>
        @include('partials.logo')
        <button class="btn btn-ghost !p-2.5 ml-auto" @click="$dispatch('palette')" aria-label="Поиск">@include('partials.icon', ['i' => 'search'])</button>
    </div>
    <aside class="admin-side lg:sticky lg:top-0 lg:h-dvh p-4 lg:block" :class="mobile ? 'block' : 'hidden'">
        <div class="card h-full p-3 flex flex-col gap-4 overflow-y-auto overflow-x-hidden">
            <div class="hidden lg:flex items-center justify-between gap-2 px-2 pt-2">
                <span class="admin-side-logo">@include('partials.logo')</span>
                <a href="{{ url('/') }}" class="admin-side-mark" aria-label="{{ settings('site.name') }}" title="{{ settings('site.name') }}">T</a>
                <button type="button" class="btn btn-ghost !p-2 admin-side-toggle" @click="toggleCompact()" :aria-pressed="compact" :title="compact ? 'Развернуть меню' : 'Только иконки'" aria-label="Режим меню">
                    @include('partials.icon', ['i' => 'menu', 'c' => 'w-[18px] h-[18px]'])
                </button>
            </div>
            <button type="button" class="admin-side-search input !py-2.5 flex items-center gap-2 text-left faint text-sm" @click="$dispatch('palette')" title="Поиск и переход · ⌘K">
                @include('partials.icon', ['i' => 'search', 'c' => 'w-4 h-4 shrink-0']) <span class="admin-side-text">Поиск и переход</span> <kbd class="admin-side-text ml-auto chip !py-0 !px-1.5 mono text-[11px]">⌘K</kbd>
            </button>
            <nav class="grid gap-1 text-sm font-semibold" aria-label="Разделы админки">
                <a href="{{ route('admin.dashboard') }}" class="admin-link {{ $onDashboard ? 'is-on' : '' }}" title="Обзор" @if ($onDashboard) aria-current="page" @endif>
                    @include('partials.icon', ['i' => 'home', 'c' => 'w-[18px] h-[18px] shrink-0']) <span class="admin-side-text">Обзор</span>
                </a>
                @foreach ($navGroups as $g)
                    @php $groupBadge = collect($g['items'])->sum('badge'); @endphp
                    <div class="admin-group" data-group="{{ $g['key'] }}">
                        <button type="button" class="admin-group-head" @click="toggle('{{ $g['key'] }}')" :aria-expanded="isOpen('{{ $g['key'] }}')" title="{{ $g['label'] }}">
                            <span class="admin-side-text label !text-[0.68rem] mr-auto">{{ $g['label'] }}</span>
                            @if ($groupBadge)<span class="admin-side-text admin-badge" x-show="!isOpen('{{ $g['key'] }}')">{{ $groupBadge }}</span>@endif
                            <svg class="admin-side-text w-3.5 h-3.5 faint transition-transform" :class="isOpen('{{ $g['key'] }}') ? 'rotate-90' : ''" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 4 4 4-4 4"/></svg>
                            <span class="admin-group-rule" aria-hidden="true"></span>
                        </button>
                        <div class="admin-group-items grid gap-0.5" x-show="compact || isOpen('{{ $g['key'] }}')">
                            @foreach ($g['items'] as $it)
                                @if ($it['soon'])
                                    <span class="admin-link is-soon" title="{{ $it['label'] }} — скоро" aria-disabled="true">
                                        @include('partials.icon', ['i' => $it['icon'], 'c' => 'w-[18px] h-[18px] shrink-0']) <span class="admin-side-text">{{ $it['label'] }}</span>
                                        <span class="admin-side-text ml-auto chip !py-0 !px-1.5 text-[10px] faint">скоро</span>
                                    </span>
                                @else
                                    <a href="{{ $it['url'] }}" class="admin-link {{ $it['active'] ? 'is-on' : '' }}" title="{{ $it['label'] }}" @if ($it['active']) aria-current="page" @endif>
                                        @include('partials.icon', ['i' => $it['icon'], 'c' => 'w-[18px] h-[18px] shrink-0']) <span class="admin-side-text truncate">{{ $it['label'] }}</span>
                                        @if ($it['badge'])<span class="admin-badge ml-auto">{{ $it['badge'] }}</span>@endif
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
            <div class="mt-auto space-y-3 text-sm px-2 pb-1">
                <a href="{{ url('/') }}" class="muted hover:text-[var(--text)] flex items-center gap-2" title="Открыть сайт">@include('partials.icon', ['i' => 'external', 'c' => 'w-4 h-4 shrink-0']) <span class="admin-side-text">Открыть сайт</span></a>
                <div class="admin-side-user flex items-center justify-between gap-2">
                    <span class="admin-side-text truncate">{{ auth()->user()->name }} <span class="faint text-xs block">{{ ['admin' => 'администратор', 'editor' => 'редактор'][$role] ?? $role }}</span></span>
                    <div class="flex gap-1">
                        @include('partials.theme-toggle')
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost !p-2.5" aria-label="Выйти" title="Выйти">@include('partials.icon', ['i' => 'logout', 'c' => 'w-[18px] h-[18px]'])</button></form>
                    </div>
                </div>
            </div>
        </div>
    </aside>
    <main class="p-4 lg:p-8 space-y-6 min-w-0">
        {{-- A70 · Хлебные крошки: Админка › группа › раздел (› запись — у страниц правки) --}}
        @unless ($onDashboard)
            <nav class="admin-crumbs" aria-label="Хлебные крошки">
                <a href="{{ route('admin.dashboard') }}">Админка</a>
                @if ($crumb)
                    <span aria-hidden="true">›</span><span>{{ $crumb[0]['label'] }}</span>
                    <span aria-hidden="true">›</span>
                    @if (isset($crumbTail))<a href="{{ $crumb[1]['url'] }}">{{ $crumb[1]['label'] }}</a><span aria-hidden="true">›</span><span class="truncate" aria-current="page">{{ $crumbTail }}</span>
                    @else<span aria-current="page">{{ $crumb[1]['label'] }}</span>@endif
                @elseif (isset($title))
                    <span aria-hidden="true">›</span><span aria-current="page">{{ $title }}</span>
                @endif
            </nav>
        @endunless
        {{-- A46 · Страна в плашке: сужает списки, таблицу и выгрузки; сотрудник видит только свои страны (App\Support\AdminScope) --}}
        @php $scope = app(\App\Support\AdminScope::class); $scopeCountries = $scope->countries(); @endphp
        @if ($scopeCountries->count() > 1 || $scope->isRestricted())
            @php $scopeSel = $scope->selected(); @endphp
            <form method="POST" action="{{ route('admin.country') }}" class="admin-scope" aria-label="Страна в админке">
                @csrf <input type="hidden" name="_back" value="{{ request()->fullUrl() }}">
                <span class="label shrink-0 hidden sm:inline">Страна</span>
                @if ($scopeCountries->count() > 1)
                    <button name="country" value="" class="admin-scope-pill {{ $scopeSel ? '' : 'is-on' }}" @unless ($scopeSel) aria-current="true" @endunless>
                        @include('partials.icon', ['i' => 'globe', 'c' => 'w-4 h-4']) {{ $scope->isRestricted() ? 'Все мои' : 'Все страны' }}
                    </button>
                @endif
                @foreach ($scopeCountries as $sc)
                    <button name="country" value="{{ $sc->id }}" class="admin-scope-pill {{ $scopeSel?->is($sc) ? 'is-on' : '' }}" @if ($scopeSel?->is($sc)) aria-current="true" @endif>
                        {!! $sc->flagImg('h-4', true) !!} {{ $sc->name }}
                    </button>
                @endforeach
                @if ($scope->isRestricted())<span class="faint text-xs ml-auto shrink-0">ваши страны</span>@endif
            </form>
        @endif
        @if (session('status'))
            <div class="card px-5 py-4 text-sm flex items-center gap-2" role="status" style="border-color: color-mix(in oklab, var(--ok) 40%, transparent)">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4 text-[var(--ok)]']) {{ session('status') }}</div>
        @endif
        @error('bulk')<div class="card px-5 py-4 text-sm error" role="alert">{{ $message }}</div>@enderror
        @yield('content')
    </main>
</div>

{{-- A24 · Палитра поиска ⌘K / Ctrl+K. A70: разделы и действия — сразу, записи — с сервера. --}}
@php
    $palettePages = collect($nav->items())->reject(fn ($it) => $it['soon'])->map(fn ($it) => ['title' => $it['label'], 'sub' => $it['group'], 'url' => $it['url'], 'keywords' => $it['keywords']])
        ->prepend(['title' => 'Обзор', 'sub' => 'Админка', 'url' => route('admin.dashboard'), 'keywords' => 'дашборд главная статистика'])->values();
    $paletteActions = collect($nav->quickActions())->map(fn ($a) => ['title' => $a['label'], 'sub' => 'Создать', 'url' => $a['url'], 'keywords' => 'создать добавить новый'])->values();
@endphp
<div x-data="adminPalette('{{ route('admin.search') }}', @js($palettePages), @js($paletteActions))" @palette.window="show()" @keydown.window.prevent.meta.k="show()" @keydown.window.prevent.ctrl.k="show()" @keydown.escape.window="open = false"
     x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center pt-[12vh] px-4" style="background: color-mix(in oklab, var(--bg) 60%, transparent); backdrop-filter: blur(6px)">
    <div class="card w-full max-w-xl p-2 shadow-2xl" @click.outside="open = false" role="dialog" aria-label="Поиск по админке">
        <label class="flex items-center gap-3 px-3">
            @include('partials.icon', ['i' => 'search', 'c' => 'w-5 h-5 faint'])
            <input x-ref="q" x-model="q" @input.debounce.200ms="search()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()"
                   class="flex-1 bg-transparent py-3 outline-none text-[15px]" placeholder="Раздел, банк, публикация, карта, e-mail…" aria-label="Поиск по админке">
            <kbd class="chip !py-0 !px-1.5 mono text-[11px] faint">Esc</kbd>
        </label>
        <ul class="max-h-[55vh] overflow-y-auto border-t border-[var(--line)] pt-1" x-show="items.length" role="listbox">
            <template x-for="(it, i) in items" :key="it.type + it.url">
                <li><a :href="it.url" class="flex items-center gap-3 px-3 py-2.5 rounded-xl" :class="i === active ? 'bg-[var(--card-2)]' : ''" :data-active="i === active ? '' : null" @mouseenter="active = i">
                    <span class="chip !py-0 text-[11px] shrink-0 min-w-[5.5rem] justify-center" :class="it.type === 'Действие' ? '!text-[var(--brand-1)]' : ''" x-text="it.type"></span>
                    <span class="min-w-0"><span class="font-semibold block truncate" x-text="it.title"></span><span class="faint text-xs block truncate" x-text="it.sub"></span></span>
                </a></li>
            </template>
        </ul>
        <p class="faint text-sm px-3 py-3" x-show="q.length > 1 && !items.length && !loading">Ничего не нашлось</p>
        <p class="faint text-xs px-3 pt-2 pb-1 flex gap-4"><span>↑↓ — выбрать</span><span>Enter — открыть</span><span class="ml-auto">⌘K / Ctrl+K — отовсюду</span></p>
    </div>
</div>
</body>
</html>
