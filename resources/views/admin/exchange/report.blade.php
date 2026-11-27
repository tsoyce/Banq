{{-- A22 · Результат проверки (dry-run): счётчики по сущностям, построчный дифф, «Применить». --}}
@extends('layouts.admin', ['title' => 'Проверка данных'])

@php($t = $report['totals'])
@section('content')
<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <a href="{{ route('admin.exchange.index') }}" class="muted text-sm inline-flex items-center gap-1 hover:text-[var(--text)]">@include('partials.icon', ['i' => 'back', 'c' => 'w-4 h-4']) Обмен данными</a>
        <h1 class="font-display font-bold text-3xl mt-1">Проверка: {{ $filename }}</h1>
        <p class="muted text-sm mt-1">Формат {{ strtoupper($format) }} · режим «{{ \App\Services\Exchange\ImportOptions::MODES[$mode] }}» · в базу пока ничего не записано</p>
    </div>
</header>

<div class="grid grid-cols-2 md:grid-cols-5 gap-3">
    @foreach (['created' => ['Новых', 'var(--ok)'], 'updated' => ['Изменится', 'var(--brand-1)'], 'unchanged' => ['Без изменений', 'var(--muted)'], 'skipped' => ['Пропущено', 'var(--warn)'], 'error' => ['Ошибок', 'var(--bad)']] as $k => [$l, $c])
        <div class="card p-4"><p class="label">{{ $l }}</p><p class="font-display font-bold text-3xl tabular mt-1" style="color: {{ $t[$k] ? $c : 'var(--faint)' }}">{{ $t[$k] }}</p></div>
    @endforeach
</div>

<div class="card p-4 overflow-x-auto">
    <table class="table text-sm">
        <thead><tr><th>Сущность</th><th>Строк в файле</th><th>Новых</th><th>Изменится</th><th>Без изменений</th><th>Пропущено</th><th>Ошибок</th></tr></thead>
        <tbody>
        @foreach ($report['stats'] as $entity => $s)
            <tr><td class="font-semibold">{{ $entities[$entity]->label() }} <span class="faint mono text-xs">{{ $entity }}</span></td><td class="tabular">{{ $counts[$entity] ?? 0 }}</td>
                <td class="tabular">{{ $s['created'] }}</td><td class="tabular">{{ $s['updated'] }}</td><td class="tabular faint">{{ $s['unchanged'] }}</td><td class="tabular">{{ $s['skipped'] }}</td><td class="tabular {{ $s['error'] ? 'text-[var(--bad)] font-semibold' : '' }}">{{ $s['error'] }}</td></tr>
        @endforeach
        </tbody>
    </table>
    @foreach ($report['warnings'] as $w)<p class="text-sm text-[var(--warn)] px-3 pt-2">{{ $w }}</p>@endforeach
</div>

<form method="POST" action="{{ route('admin.exchange.apply') }}" class="card p-5 flex flex-wrap items-center gap-4 sticky top-4 z-10 shadow-lg">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <p class="text-sm flex-1 min-w-60">
        @if ($t['created'] + $t['updated'] > 0)
            Применить: <b>{{ $t['created'] }}</b> новых и <b>{{ $t['updated'] }}</b> изменений.@if ($t['error']) Строки с ошибками ({{ $t['error'] }}) будут пропущены.@endif
        @else
            Менять нечего@if ($t['error']) — исправь ошибки и загрузи снова@endif.
        @endif
    </p>
    <a href="{{ route('admin.exchange.index') }}" class="btn btn-ghost">Отмена</a>
    <button class="btn btn-primary" @disabled($t['created'] + $t['updated'] === 0)>@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) Применить</button>
</form>

@include('admin.exchange._rows', ['rows' => $report['rows'], 'entities' => $entities])
@if ($report['truncated'])<p class="faint text-sm">Показаны первые {{ \App\Services\Exchange\ImportReport::MAX_ROWS }} строк — счётчики полные.</p>@endif
@endsection
