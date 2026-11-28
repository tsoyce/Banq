{{-- A70 · группа раздела — в хлебных крошках макета. A24 · Список ресурса: сводка, поиск, фильтры, сортировка по колонкам, выбор строк и массовые действия, экспорт. --}}
@extends('layouts.admin', ['title' => $resource->label()])

@php
    $filters = $resource->filters();
    $actions = $resource->bulkActions();
    [$sortCol, $sortDir] = $sort;
    $base = request()->except(['page']);
@endphp

@section('content')
<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="font-display font-bold text-3xl flex items-center gap-3">{{ $resource->label() }} <span class="faint text-xl tabular">{{ $items->total() }}</span></h1>
        @if ($resource->description())<p class="muted text-sm mt-1 max-w-2xl">{{ $resource->description() }}</p>@endif
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($resource->exchangeEntity())
            {{-- A46 · «Экспорт» с выбором формата (не привязан к XLSX) и «Импорт» — в общий обмен данными; страна — из плашки --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" class="btn btn-ghost !py-2.5 text-sm" @click="open = !open" :aria-expanded="open" aria-haspopup="menu">@include('partials.icon', ['i' => 'download', 'c' => 'w-4 h-4']) Экспорт</button>
                <div x-show="open" x-cloak x-transition.origin.top.right class="absolute right-0 top-full mt-2 z-30 card p-1.5 min-w-52 shadow-xl" role="menu">
                    <p class="label px-3 pt-1.5 pb-1">Всё в формате</p>
                    @foreach (\App\Services\Exchange\DataExchange::FORMAT_LABELS as $fk => $fl)
                        <a role="menuitem" href="{{ route('admin.exchange.export', array_filter(['entities' => [$resource->exchangeEntity()], 'format' => $fk, 'country' => app(\App\Support\AdminScope::class)->code()])) }}" class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl text-sm font-semibold hover:bg-[var(--card-2)]">{{ $fl }} <span class="faint mono text-xs">.{{ $fk === 'yaml' ? 'yaml' : $fk }}</span></a>
                    @endforeach
                </div>
            </div>
            <a href="{{ route('admin.exchange.index') }}" class="btn btn-ghost !py-2.5 text-sm">@include('partials.icon', ['i' => 'upload', 'c' => 'w-4 h-4']) Импорт</a>
        @endif
        @if ($resource->canCreate())
            <a href="{{ route('admin.resource.create', $resource->key()) }}" class="btn btn-primary !py-2.5">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Добавить {{ $resource->singular() }}</a>
        @endif
    </div>
</header>

@if ($summary = $resource->summary())
    <div class="grid grid-cols-2 {{ ['', 'sm:grid-cols-1', 'sm:grid-cols-2', 'sm:grid-cols-3', 'sm:grid-cols-4'][min(4, count($summary))] }} gap-3">
        @foreach ($summary as $label => $value)
            <div class="card p-4"><p class="label">{{ $label }}</p><p class="font-display font-bold text-2xl tabular mt-1">{{ number_format($value, 0, ',', ' ') }}</p></div>
        @endforeach
    </div>
@endif

{{-- Поиск и фильтры: обычная GET-форма, состояние в URL --}}
{{-- A36 · на телефоне фильтры — ровной сеткой 2 колонки, а не лесенкой --}}
<form method="GET" class="card p-3 grid grid-cols-2 sm:flex sm:flex-wrap items-center gap-2" x-data @change="$el.requestSubmit()">
    <label class="relative col-span-2 sm:flex-1 sm:min-w-56">
        <span class="sr-only">Поиск</span>
        @include('partials.icon', ['i' => 'search', 'c' => 'w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 faint'])
        <input name="q" value="{{ request('q') }}" class="input !pl-10 !py-2.5" placeholder="Поиск…" @change.stop>
    </label>
    @foreach ($filters as $name => $f)
        @if (isset($f['options']))
            <select name="{{ $name }}" class="input sm:!w-auto !py-2.5 text-sm min-w-0" aria-label="{{ $f['label'] }}">
                <option value="">{{ $f['label'] }}: все</option>
                @foreach ($resource->options($f['options']) as $v => $l)<option value="{{ $v }}" @selected((string) request($name) === (string) $v)>{{ $l }}</option>@endforeach
            </select>
        @elseif (request()->filled($name))
            <input type="hidden" name="{{ $name }}" value="{{ request($name) }}">
            <span class="chip">{{ $f['label'] }}: {{ request($name) }}</span>
        @endif
    @endforeach
    <select name="per" class="input sm:!w-auto !py-2.5 text-sm min-w-0" aria-label="На странице">
        @foreach ([25, 50, 100, 200] as $n)<option value="{{ $n }}" @selected($perPage === $n)>по {{ $n }}</option>@endforeach
    </select>
    @if (request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="dir" value="{{ request('dir') }}">@endif
    <button class="btn btn-primary !py-2.5 text-sm">Найти</button>
    @if ($activeFilters)<a href="{{ route('admin.resource.index', $resource->key()) }}" class="btn btn-ghost !py-2.5 text-sm">Сбросить · {{ $activeFilters }}</a>@endif
</form>

<form method="POST" action="{{ route('admin.resource.bulk', $resource->key()) }}" class="card overflow-hidden" x-data="bulkSelect({{ $items->total() }})" @submit="confirmRun($event)">
    @csrf
    @foreach (request()->only(['q', ...array_keys($filters)]) as $k => $v)<input type="hidden" name="filters[{{ $k }}]" value="{{ $v }}">@endforeach
    <template x-for="id in ids" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
    <input type="hidden" name="all" :value="all ? 1 : 0">

    {{-- Панель массовых действий --}}
    @if ($actions || $resource->exchangeEntity())
        <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-[var(--line)]" :class="count ? 'bg-[var(--card-2)]' : ''">
            <p class="text-sm font-semibold mr-auto" x-show="!count">Отметь строки, чтобы изменить их разом</p>
            <p class="text-sm font-semibold mr-auto" x-show="count" x-cloak>
                Выбрано: <span class="tabular" x-text="count"></span>
                <button type="button" class="underline ml-2 font-normal muted" x-show="!all && ids.length && ids.length < total" @click="all = true">выбрать все {{ $items->total() }} по фильтру</button>
                <button type="button" class="underline ml-2 font-normal muted" @click="clear()">снять</button>
            </p>
            <select name="action" class="input !w-auto !py-2 text-sm" :disabled="!count" aria-label="Действие">
                @foreach ($actions as $k => $a)<option value="{{ $k }}" @if (! empty($a['confirm'])) data-confirm="{{ $a['confirm'] }}" @endif>{{ $a['label'] }}</option>@endforeach
                @if ($resource->exchangeEntity())
                    <option value="export">Экспорт выбранного</option>
                @endif
            </select>
            @if ($resource->exchangeEntity())
                <select name="format" class="input !w-auto !py-2 text-sm" :disabled="!count" aria-label="Формат экспорта">@foreach (\App\Services\Exchange\DataExchange::FORMAT_LABELS as $fk => $fl)<option value="{{ $fk }}">{{ $fl }}</option>@endforeach</select>
            @endif
            <button class="btn btn-primary !py-2 text-sm" :disabled="!count">Применить</button>
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="table">
            <thead>
            <tr>
                <th class="w-10"><input type="checkbox" class="accent-[var(--text)] w-4 h-4" @change="toggleAll($event)" :checked="ids.length && ids.length === {{ $items->count() }}" aria-label="Выбрать все на странице"></th>
                @foreach ($resource->columns() as $col)
                    <th>
                        @if (! empty($col['sort']))
                            @php($isSorted = $sortCol === $col['sort'])
                            <a href="{{ request()->fullUrlWithQuery(['sort' => $col['sort'], 'dir' => $isSorted && $sortDir === 'asc' ? 'desc' : 'asc', 'page' => null]) }}" class="inline-flex items-center gap-1 hover:text-[var(--text)] {{ $isSorted ? 'text-[var(--text)]' : '' }}">
                                {{ $col['label'] }} <span class="text-[10px]">{{ $isSorted ? ($sortDir === 'asc' ? '▲' : '▼') : '' }}</span>
                            </a>
                        @else
                            {{ $col['label'] }}
                        @endif
                    </th>
                @endforeach
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($items as $m)
                <tr :class="ids.includes('{{ $m->getKey() }}') || all ? 'bg-[var(--card-2)]' : ''">
                    <td><input type="checkbox" data-row value="{{ $m->getKey() }}" x-model="ids" class="accent-[var(--text)] w-4 h-4" aria-label="Выбрать"></td>
                    @foreach ($resource->columns() as $col)
                        <td class="{{ $col['class'] ?? '' }}">
                            @if (isset($col['render']))
                                {{ $col['render']($m) }}
                            @else
                                {{ data_get($m, $col['key']) }}
                            @endif
                        </td>
                    @endforeach
                    <td class="text-right whitespace-nowrap">
                        @if ($url = $resource->publicUrl($m))<a href="{{ $url }}" target="_blank" class="btn btn-ghost !p-2" title="На сайте" aria-label="На сайте">@include('partials.icon', ['i' => 'external', 'c' => 'w-4 h-4'])</a>@endif
                        @if ($resource->canEdit($m))<a href="{{ route('admin.resource.edit', [$resource->key(), $m->getKey()]) }}" class="btn btn-ghost !py-1.5 text-sm">Изменить</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($resource->columns()) + 2 }}" class="muted text-center py-12">
                    {{ $activeFilters ? 'Под фильтр ничего не попало.' : 'Пока пусто.' }}
                    @if ($resource->canCreate())<a href="{{ route('admin.resource.create', $resource->key()) }}" class="underline">Добавить</a> или <a href="{{ route('admin.exchange.index') }}" class="underline">загрузить данные</a>.@endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</form>
{{ $items->links() }}
@endsection
