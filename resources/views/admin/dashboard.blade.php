{{-- A09 · Обзор. A24: страны, здоровье данных, импорты. A70: приветствие и быстрые действия, бенто по группам меню,
     «Требует внимания», последние правки публикаций; числа — в пределах страны из плашки. --}}
@extends('layouts.admin', ['title' => 'Обзор'])

@php($fmt = fn ($v) => is_numeric($v) ? number_format((float) $v, 0, ',', ' ') : $v)

@section('content')
<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="label">{{ now()->translatedFormat('l, j F') }}</p>
        <h1 class="font-display font-bold text-3xl mt-1">{{ $greeting }}, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
    </div>
    <button type="button" class="input !w-auto !py-2.5 flex items-center gap-2 faint text-sm min-w-64" @click="$dispatch('palette')">
        @include('partials.icon', ['i' => 'search', 'c' => 'w-4 h-4']) Найти раздел или запись <kbd class="ml-auto chip !py-0 !px-1.5 mono text-[11px]">⌘K</kbd>
    </button>
</header>

{{-- Быстрые действия --}}
<div class="flex flex-wrap gap-2" aria-label="Быстрые действия">
    @foreach ($actions as $a)
        <a href="{{ $a['url'] }}" class="btn {{ $loop->last ? 'btn-primary' : 'btn-ghost' }} !py-2.5 text-sm whitespace-nowrap shrink-0">
            @include('partials.icon', ['i' => $loop->last ? $a['icon'] : 'plus', 'c' => 'w-4 h-4']) {{ $loop->last ? $a['label'] : \Illuminate\Support\Str::after($a['label'], ' ') }}
        </a>
    @endforeach
</div>

{{-- Бенто по группам --}}
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach ($bento as $b)
        <section class="card dash-tile dash-{{ $b['tone'] }} p-6 flex flex-col gap-4" data-reveal style="--d: {{ $loop->index * .06 }}s">
            <div class="flex items-center justify-between gap-2">
                <p class="label flex items-center gap-2">@include('partials.icon', ['i' => $b['icon'], 'c' => 'w-4 h-4']) {{ $b['group'] }}</p>
                <a href="{{ $b['url'] }}" class="dash-go" aria-label="{{ $b['group'] }}: открыть">→</a>
            </div>
            <a href="{{ $b['url'] }}" class="block">
                <p class="font-display font-black text-5xl tabular leading-none" x-data x-count="{{ (int) $b['value'] }}">{{ $fmt($b['value'] ?? 0) }}</p>
                <p class="text-sm mt-2 dash-sub">{{ $b['label'] }}</p>
            </a>
            @if (! empty($b['spark']) && max($b['spark']) > 0)
                @php($mx = max($b['spark']))
                <div class="flex items-end gap-1 h-10" aria-label="Показы по дням за неделю">
                    @foreach ($b['spark'] as $v)<span class="flex-1 rounded-sm" style="height: {{ max(6, round($v / $mx * 100)) }}%; background: var(--grad); opacity: {{ $loop->last ? 1 : .55 }}" title="{{ $fmt($v) }}"></span>@endforeach
                </div>
            @endif
            <dl class="mt-auto grid gap-1 text-sm">
                @foreach ($b['rows'] as [$l, $v, $u])
                    @continue($v === null)
                    <a href="{{ $u }}" class="dash-row flex items-center justify-between gap-3 -mx-2 px-2 py-1 rounded-lg"><dt class="dash-sub">{{ $l }}</dt><dd class="font-semibold tabular">{{ $fmt($v) }}</dd></a>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>

<div class="grid xl:grid-cols-[1.4fr_1fr] gap-4 items-start">
    {{-- Последние правки публикаций --}}
    <section class="card p-4">
        <div class="flex items-center justify-between px-3 py-2">
            <h2 class="font-display font-bold text-xl">Последние правки</h2>
            <a href="{{ route('admin.resource.index', 'posts') }}" class="muted text-sm hover:underline">все публикации →</a>
        </div>
        <ul class="divide-y divide-[var(--line)]">
            @forelse ($posts as $p)
                <li class="flex items-center gap-3 px-3 py-2.5">
                    @if ($p->coverUrl())<img src="{{ $p->coverUrl() }}" alt="" class="w-12 h-12 rounded-xl object-cover shrink-0" loading="lazy">@else<span class="w-12 h-12 rounded-xl shrink-0 grid place-items-center" style="background: var(--card-2)">@include('partials.icon', ['i' => 'news', 'c' => 'w-5 h-5 faint'])</span>@endif
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('admin.resource.edit', ['posts', $p->id]) }}" class="font-semibold block truncate hover:underline">{{ $p->title }}</a>
                        <p class="faint text-xs truncate">{{ $p->category?->name ?? 'Без рубрики' }} · правка {{ $p->updated_at?->diffForHumans() }}</p>
                    </div>
                    @php([$st, $stc] = $p->isPublished() ? ['на сайте', 'var(--ok)'] : ($p->isScheduled() ? ['запланирована', 'var(--brand-1)'] : ['черновик', 'var(--faint)']))
                    <span class="chip !py-0 text-xs shrink-0" style="color: {{ $stc }}">{{ $st }}</span>
                </li>
            @empty
                <li class="muted text-center py-8 text-sm">Публикаций пока нет — <a href="{{ route('admin.resource.create', 'posts') }}" class="underline">написать первую</a></li>
            @endforelse
        </ul>
    </section>

    {{-- Требует внимания: модерация и пробелы в данных --}}
    <section class="card p-5">
        <h2 class="font-display font-bold text-xl mb-2">Требует внимания</h2>
        <div class="divide-y divide-[var(--line)]">
            @foreach ($attention as $a)
                <a href="{{ $a['url'] }}" class="flex items-center gap-3 py-2.5 hover:bg-[var(--card-2)] -mx-2 px-2 rounded-lg">
                    @include('partials.icon', ['i' => $a['icon'], 'c' => 'w-4 h-4 shrink-0 '.($a['n'] ? 'text-[var(--warn)]' : 'faint')])
                    <span class="text-sm flex-1 {{ $a['n'] ? '' : 'faint' }}">{{ $a['label'] }}</span>
                    <span class="chip !py-0 tabular {{ $a['n'] ? '!text-[var(--warn)]' : 'faint' }}">{{ $a['n'] ?: '✓' }}</span>
                </a>
            @endforeach
        </div>
    </section>
</div>

<div class="grid xl:grid-cols-[1.4fr_1fr] gap-4 items-start">
    <section class="card p-4 overflow-x-auto">
        <div class="flex items-center justify-between px-3 py-2">
            <h2 class="font-display font-bold text-xl">Страны</h2>
            <a href="{{ route('admin.resource.create', 'countries') }}" class="btn btn-ghost !py-1.5 text-sm">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Страна</a>
        </div>
        <table class="table">
            <thead><tr><th>Страна</th><th>Валюта</th><th>Банков</th><th>Вкладов</th><th>Ставок</th><th>Публикаций</th><th></th></tr></thead>
            <tbody>
            @foreach ($countries as $row)
                @php($c = $row['country'])
                <tr>
                    <td class="whitespace-nowrap">{!! $c->flagImg('mr-1.5 h-[1.1em]') !!}<b>{{ $c->name }}</b> @unless ($c->is_active)<span class="chip faint !py-0">скрыта</span>@endunless</td>
                    <td class="mono text-sm">{{ $c->currency }}</td>
                    <td class="tabular"><a class="hover:underline" href="{{ route('admin.resource.index', ['banks', 'country' => $c->id]) }}">{{ $c->active_banks_count }}<span class="faint"> / {{ $c->banks_count }}</span></a></td>
                    <td class="tabular"><a class="hover:underline" href="{{ route('admin.resource.index', ['deposits', 'country' => $c->id]) }}">{{ $row['products'] }}</a></td>
                    <td class="tabular"><a class="hover:underline" href="{{ route('admin.resource.index', ['rates', 'country' => $c->id, 'state' => 'current']) }}">{{ $row['rates'] }}</a></td>
                    <td class="tabular"><a class="hover:underline" href="{{ route('admin.resource.index', ['posts', 'country' => $c->id]) }}">{{ $row['posts'] }}</a></td>
                    <td class="text-right"><a href="{{ route('admin.resource.edit', ['countries', $c->id]) }}" class="btn btn-ghost !py-1.5 text-sm">Изменить</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <div class="grid gap-4">
        <section class="card p-4 overflow-x-auto">
            <div class="flex items-center justify-between px-3 py-2">
                <h2 class="font-display font-bold text-xl">Последние импорты</h2>
                <a href="{{ route('admin.exchange.index', ['tab' => 'history']) }}" class="muted text-sm hover:underline">вся история →</a>
            </div>
            <ul class="divide-y divide-[var(--line)] text-sm">
                @forelse ($imports as $i)
                    <li class="flex items-center gap-3 px-3 py-2.5">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('admin.exchange.show', $i) }}" class="font-semibold block truncate hover:underline">{{ $i->filename }}</a>
                            <p class="faint text-xs">{{ ['admin' => 'админка', 'cli' => 'консоль', 'api' => 'API'][$i->source] ?? $i->source }} · {{ $i->created_at->diffForHumans() }}</p>
                        </div>
                        <span class="tabular text-xs shrink-0"><span class="text-[var(--ok)]">+{{ $i->rows_created }}</span> · {{ $i->rows_updated }}@if ($i->rows_failed) · <span class="text-[var(--bad)]">{{ $i->rows_failed }} ошиб.</span>@endif</span>
                    </li>
                @empty
                    <li class="muted text-center py-6">Импортов ещё не было — <a href="{{ route('admin.exchange.index') }}" class="underline">залить данные</a></li>
                @endforelse
            </ul>
        </section>
        <section class="card p-6 space-y-2 text-sm">
            <h2 class="font-display font-bold text-xl mb-1">Система</h2>
            <p class="muted">Версия: <b class="text-[var(--text)]">{{ config('banq.version') }}</b> · годы жизни: <b class="text-[var(--text)]">{{ project_years() }}</b></p>
            <p class="muted">Установлено: <b class="text-[var(--text)]">{{ isset($install['installed_at']) ? \Illuminate\Support\Carbon::parse($install['installed_at'])->translatedFormat('j F Y, H:i') : '—' }}</b></p>
            <p class="muted">PHP {{ PHP_VERSION }} · Laravel {{ app()->version() }} · БД {{ config('database.default') }}</p>
        </section>
    </div>
</div>
@endsection
