{{-- A39 · Табличный редактор «как Excel»: любая сущность обмена данными, вставка диапазонов из Excel, горячие клавиши.
     Клиент — resources/js/sheet.js (Alpine.data('sheet')), сервер — Admin\GridController. --}}
@extends('layouts.admin', ['title' => 'Таблица · '.$entity->label(), 'crumbTail' => $entity->label()]) {{-- A70 --}}

@php($mod = str_contains(request()->userAgent() ?? '', 'Mac') ? '⌘' : 'Ctrl')
@section('content')
<div x-data="sheet(@js($config + ['country' => $country]))" class="space-y-4" @keydown.escape.window="help = false; colsOpen = false">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="font-display font-bold text-3xl">{{ $entity->label() }} <span class="faint mono text-base font-normal">{{ $entity->key() }}</span></h1>
            <p class="muted text-sm mt-1 max-w-3xl">Правьте как в Excel: стрелки, Enter, Tab, {{ $mod }}+C / {{ $mod }}+V — диапазоны прямо из Excel и Google Таблиц. Строка заголовков в буфере — колонки сопоставятся сами. Записывается только при сохранении; сначала можно проверить.</p>
        </div>
        <x-hscroll>
            @foreach ($entities as $key => $e)
                <a href="{{ route('admin.exchange.grid', $key) }}" class="chip whitespace-nowrap {{ $key === $entity->key() ? 'is-active' : '' }}" @if ($key === $entity->key()) aria-current="page" style="background: var(--ink); color: var(--ink-text)" @endif>{{ $e->label() }}</a>
            @endforeach
        </x-hscroll>
    </header>

    {{-- Панель: фильтры, правка, запись --}}
    <div class="card p-3 flex flex-wrap items-center gap-2">
        @if (isset($entity->fields()['country']) || $entity->key() === 'countries')
            <select x-model="country" @change="bank = ''; load()" class="input !py-2 !w-auto text-sm" aria-label="Страна">
                <option value="">Все страны</option>
                @foreach ($countries as $c)<option value="{{ $c->code }}">{{ $c->flag }} {{ $c->name }}</option>@endforeach
            </select>
        @endif
        @if (isset($entity->fields()['bank']))
            <select x-model="bank" @change="load()" class="input !py-2 !w-auto text-sm max-w-48" aria-label="Банк">
                <option value="">Все банки</option>
                <template x-for="b in banks" :key="b.code"><option :value="b.code" x-text="b.name + ' · ' + b.code"></option></template>
            </select>
        @endif
        @if ($config['hasRates'])
            <label class="switch text-sm"><span>Только действующие</span><input type="checkbox" class="sr-only" x-model="current" @change="load()"><i></i></label>
        @endif
        <label class="relative">
            <span class="sr-only">Поиск</span>
            @include('partials.icon', ['i' => 'search', 'c' => 'w-4 h-4 faint absolute left-3 top-1/2 -translate-y-1/2'])
            <input x-ref="search" x-model="q" @input.debounce.150ms="grid.setFilter(q)" @keydown.escape="q = ''; grid.setFilter(''); grid.scroll.focus()" @keydown.enter="grid.scroll.focus()"
                   class="input !py-2 !pl-9 !w-44 text-sm" placeholder="Поиск ({{ $mod }}+F)">
        </label>

        <span class="w-px h-6 bg-[var(--line)] mx-1 hidden md:block"></span>
        <button type="button" class="btn btn-ghost !py-2 text-sm" @click="grid.insertRows(true); grid.scroll.focus()" title="{{ $mod }}+Enter">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Строка</button>
        <button type="button" class="btn btn-ghost !py-2 text-sm" @click="grid.deleteRows(); grid.scroll.focus()" title="{{ $mod }}+−">@include('partials.icon', ['i' => 'trash', 'c' => 'w-4 h-4']) Удалить</button>
        <button type="button" class="btn btn-ghost !p-2.5" @click="grid.undo(); grid.scroll.focus()" :disabled="!canUndo" :class="!canUndo && 'opacity-40'" title="Отменить · {{ $mod }}+Z" aria-label="Отменить"><span class="text-base leading-none">↶</span></button>
        <button type="button" class="btn btn-ghost !p-2.5" @click="grid.redo(); grid.scroll.focus()" :disabled="!canRedo" :class="!canRedo && 'opacity-40'" title="Вернуть · {{ $mod }}+Y" aria-label="Вернуть"><span class="text-base leading-none">↷</span></button>

        <div class="relative" @click.outside="colsOpen = false">
            <button type="button" class="btn btn-ghost !py-2 text-sm" @click="colsOpen = !colsOpen" :aria-expanded="colsOpen">@include('partials.icon', ['i' => 'layers', 'c' => 'w-4 h-4']) Колонки</button>
            <div x-show="colsOpen" x-cloak x-transition.opacity class="card absolute z-20 mt-2 p-2 w-64 max-h-80 overflow-y-auto shadow-2xl">
                <template x-for="c in columns" :key="c.key">
                    <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-[var(--card-2)] text-sm cursor-pointer">
                        <input type="checkbox" :checked="!hiddenKeys.includes(c.key)" @change="toggleCol(c.key)">
                        <span class="mono text-xs" x-text="c.key"></span><span class="faint text-xs truncate" x-text="c.ru || ''"></span>
                    </label>
                </template>
            </div>
        </div>
        <div class="relative">
            <button type="button" class="btn btn-ghost !py-2 text-sm" @click="help = !help" :aria-expanded="help">@include('partials.icon', ['i' => 'info', 'c' => 'w-4 h-4']) Клавиши</button>
        </div>

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" class="btn btn-ghost !py-2 text-sm" @click="open = !open">@include('partials.icon', ['i' => 'download', 'c' => 'w-4 h-4']) Файл</button>
                <div x-show="open" x-cloak x-transition.opacity class="card absolute right-0 z-20 mt-2 p-2 w-48 shadow-2xl grid">
                    @foreach (\App\Services\Exchange\DataExchange::FORMAT_LABELS as $f => $l)
                        <a :href="exportHref('{{ $f }}')" class="px-3 py-1.5 rounded-lg hover:bg-[var(--card-2)] text-sm">Скачать {{ $l }}</a>
                    @endforeach
                    <a href="{{ route('admin.exchange.index') }}" class="px-3 py-1.5 rounded-lg hover:bg-[var(--card-2)] text-sm muted">Залить файл…</a>
                </div>
            </div>
            <button type="button" class="btn btn-ghost !py-2 text-sm" @click="save(true)" :disabled="busy || !dirty" :class="(busy || !dirty) && 'opacity-50'" title="Проверить без записи · {{ $mod }}+Shift+S">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) Проверить</button>
            <button type="button" class="btn btn-primary !py-2 text-sm" @click="save(false)" :disabled="busy || !dirty" :class="(busy || !dirty) && 'opacity-50'" title="{{ $mod }}+S">
                <span x-text="busy ? 'Сохраняю…' : (dirty ? 'Сохранить · ' + dirty : 'Сохранено')"></span>
            </button>
        </div>
    </div>

    {{-- Подсказка по клавишам --}}
    <div x-show="help" x-cloak x-transition.opacity class="card p-5 grid sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-2 text-sm">
        @foreach ([
            ['← ↑ → ↓', 'перемещение'], ['Shift + стрелки', 'выделить диапазон'], [$mod.' + стрелки', 'к краю таблицы'], [$mod.' + A', 'выделить всё'],
            ['Enter · F2 · двойной клик', 'править ячейку'], ['Любая буква/цифра', 'заменить значение'], ['Enter / Tab', 'записать и вниз / вправо'], ['Esc', 'отменить правку'],
            ['Delete · Backspace', 'очистить выделенное'], [$mod.' + C / X / V', 'копировать / вырезать / вставить'], [$mod.' + D', 'заполнить вниз'], [$mod.' + Z / Y', 'отменить / вернуть'],
            [$mod.' + Enter · '.$mod.' + =', 'вставить строку ниже'], [$mod.' + −', 'удалить / вернуть строки'], [$mod.' + S', 'сохранить'], [$mod.' + Shift + S', 'проверить без записи'],
        ] as [$k, $l])
            <p class="flex items-center justify-between gap-3"><kbd class="k">{{ $k }}</kbd><span class="muted text-right">{{ $l }}</span></p>
        @endforeach
        <p class="faint text-xs sm:col-span-2 xl:col-span-4 pt-2">Вставка из Excel: скопируйте диапазон вместе со строкой заголовков (code, name… или русские «Банк», «Ставка», «Срок») — строки добавятся новыми, а совпавшие с базой по ключу обновятся при сохранении. Без заголовков — значения лягут от активной ячейки, таблица растянется вниз. Списки — через «|», да/нет — словами.</p>
    </div>

    {{-- Таблица --}}
    <div class="sheet">
        <div x-ref="grid" :class="loading && 'opacity-50 pointer-events-none'"></div>
        <div x-show="loading" class="absolute inset-0 grid place-items-center muted text-sm">Загружаю строки…</div>
    </div>

    {{-- Строка состояния --}}
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[13px]">
        <span class="mono muted" x-text="info"></span>
        <span class="sheet-legend muted"><i style="background: color-mix(in oklab, var(--brand-2) 45%, transparent)"></i><span x-text="counts.changed"></span> изменено</span>
        <span class="sheet-legend muted"><i style="background: var(--ok)"></i><span x-text="counts.added"></span> новых</span>
        <span class="sheet-legend muted"><i style="background: var(--bad)"></i><span x-text="counts.deleted"></span> к удалению</span>
        <span class="sheet-legend" x-show="counts.errors" x-cloak style="color: var(--bad)"><i style="background: var(--bad)"></i><span x-text="counts.errors"></span> с ошибкой — наведите на «!»</span>
        <span class="faint ml-auto"><span x-text="counts.total"></span> в базе · <span class="mono">*</span> — обязательное поле</span>
    </div>

    <div x-show="toastText" x-cloak x-transition.opacity class="sheet-toast" role="status" x-text="toastText"></div>
</div>
@endsection
