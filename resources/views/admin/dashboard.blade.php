{{-- A09 · Обзор. A24: счётчики-ссылки, страны, здоровье данных, последние импорты, быстрые действия. --}}
@extends('layouts.admin', ['title' => 'Обзор'])

@section('content')
<header class="flex flex-wrap items-end justify-between gap-4">
    <h1 class="font-display font-bold text-3xl">Обзор</h1>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.exchange.index') }}" class="btn btn-primary !py-2.5">@include('partials.icon', ['i' => 'upload', 'c' => 'w-4 h-4']) Залить данные</a>
        <a href="{{ route('admin.resource.create', 'banks') }}" class="btn btn-ghost !py-2.5">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Банк</a>
        <a href="{{ route('admin.resource.create', 'deposits') }}" class="btn btn-ghost !py-2.5">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Вклад</a>
    </div>
</header>

<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach ($stats as [$label, $value, $url])
        <a href="{{ $url }}" class="card lift p-6 block" data-reveal style="--d: {{ $loop->index * .06 }}s">
            <p class="label">{{ $label }}</p>
            <p class="font-display font-black text-4xl mt-2 tabular" x-data x-count="{{ $value }}">{{ $value }}</p>
        </a>
    @endforeach
</div>

<div class="grid xl:grid-cols-[1.4fr_1fr] gap-4 items-start">
    <section class="card p-4 overflow-x-auto">
        <div class="flex items-center justify-between px-3 py-2">
            <h2 class="font-display font-bold text-xl">Страны</h2>
            <a href="{{ route('admin.resource.create', 'countries') }}" class="btn btn-ghost !py-1.5 text-sm">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Страна</a>
        </div>
        <table class="table">
            <thead><tr><th>Страна</th><th>Валюта</th><th>Банков</th><th>Вкладов</th><th>Ставок</th><th></th></tr></thead>
            <tbody>
            @foreach ($countries as $row)
                @php($c = $row['country'])
                <tr>
                    <td><span class="text-lg mr-1.5">{{ $c->flag }}</span><b>{{ $c->name }}</b> @unless ($c->is_active)<span class="chip faint !py-0">скрыта</span>@endunless</td>
                    <td class="mono text-sm">{{ $c->currency }}</td>
                    <td class="tabular"><a class="hover:underline" href="{{ route('admin.resource.index', ['banks', 'country' => $c->id]) }}">{{ $c->active_banks_count }}<span class="faint"> / {{ $c->banks_count }}</span></a></td>
                    <td class="tabular"><a class="hover:underline" href="{{ route('admin.resource.index', ['deposits', 'country' => $c->id]) }}">{{ $row['products'] }}</a></td>
                    <td class="tabular"><a class="hover:underline" href="{{ route('admin.resource.index', ['rates', 'country' => $c->id, 'state' => 'current']) }}">{{ $row['rates'] }}</a></td>
                    <td class="text-right"><a href="{{ route('admin.resource.edit', ['countries', $c->id]) }}" class="btn btn-ghost !py-1.5 text-sm">Изменить</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section class="card p-5">
        <h2 class="font-display font-bold text-xl mb-2">Что заполнить</h2>
        <div class="divide-y divide-[var(--line)]">
            @foreach ($health as [$label, $n, $url])
                <a href="{{ $url }}" class="flex items-center justify-between gap-3 py-2.5 hover:bg-[var(--card-2)] -mx-2 px-2 rounded-lg">
                    <span class="text-sm {{ $n ? '' : 'faint' }}">{{ $label }}</span>
                    <span class="chip !py-0 tabular {{ $n ? '!text-[var(--warn)]' : 'faint' }}">{{ $n ?: '✓' }}</span>
                </a>
            @endforeach
        </div>
    </section>
</div>

<div class="grid xl:grid-cols-[1.4fr_1fr] gap-4 items-start">
    <section class="card p-4 overflow-x-auto">
        <div class="flex items-center justify-between px-3 py-2">
            <h2 class="font-display font-bold text-xl">Последние импорты</h2>
            <a href="{{ route('admin.exchange.index', ['tab' => 'history']) }}" class="muted text-sm hover:underline">вся история →</a>
        </div>
        <table class="table">
            <thead><tr><th>Файл</th><th>Откуда</th><th>Когда</th><th>Новых</th><th>Обновлено</th><th>Ошибок</th></tr></thead>
            <tbody>
            @forelse ($imports as $i)
                <tr>
                    <td class="font-semibold"><a href="{{ route('admin.exchange.show', $i) }}" class="hover:underline">{{ $i->filename }}</a></td>
                    <td class="text-sm muted">{{ ['admin' => 'админка', 'cli' => 'консоль', 'api' => 'API'][$i->source] ?? $i->source }}</td>
                    <td class="muted text-sm">{{ $i->created_at->diffForHumans() }}</td>
                    <td class="tabular">{{ $i->rows_created }}</td><td class="tabular">{{ $i->rows_updated }}</td>
                    <td class="tabular {{ $i->rows_failed ? 'text-[var(--bad)]' : 'faint' }}">{{ $i->rows_failed }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted text-center py-8">Импортов ещё не было — <a href="{{ route('admin.exchange.index') }}" class="underline">залить данные</a></td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <section class="card p-6 space-y-3 text-sm">
        <h2 class="font-display font-bold text-xl">Система</h2>
        <p class="muted">Версия: <b class="text-[var(--text)]">{{ config('banq.version') }}</b></p>
        <p class="muted">Установлено: <b class="text-[var(--text)]">{{ isset($install['installed_at']) ? \Illuminate\Support\Carbon::parse($install['installed_at'])->translatedFormat('j F Y, H:i') : '—' }}</b></p>
        <p class="muted">Годы жизни проекта: <b class="text-[var(--text)]">{{ project_years() }}</b></p>
        <p class="muted">PHP {{ PHP_VERSION }} · Laravel {{ app()->version() }} · БД {{ config('database.default') }}</p>
    </section>
</div>
@endsection
