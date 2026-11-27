{{-- A22 · Журнал одного импорта. --}}
@extends('layouts.admin', ['title' => 'Импорт #'.$import->id])

@section('content')
<header>
    <a href="{{ route('admin.exchange.index', ['tab' => 'history']) }}" class="muted text-sm inline-flex items-center gap-1 hover:text-[var(--text)]">@include('partials.icon', ['i' => 'back', 'c' => 'w-4 h-4']) История</a>
    <h1 class="font-display font-bold text-3xl mt-1">Импорт #{{ $import->id }} · {{ $import->filename }}</h1>
    <p class="muted text-sm mt-1">{{ $import->created_at->translatedFormat('j F Y, H:i') }} · {{ ['admin' => 'админка', 'cli' => 'консоль', 'api' => 'API'][$import->source] ?? $import->source }}{{ $import->user ? ' · '.$import->user->name : '' }}{{ $import->token ? ' · токен «'.$import->token->name.'»' : '' }} · {{ strtoupper($import->format ?? 'matrix') }}{{ $import->mode ? ' · '.$import->mode : '' }}</p>
</header>

<div class="grid grid-cols-2 md:grid-cols-5 gap-3">
    @foreach (['Всего строк' => $import->rows_total, 'Новых' => $import->rows_created, 'Обновлено' => $import->rows_updated, 'Без изменений / пропущено' => $import->rows_skipped, 'Ошибок' => $import->rows_failed] as $l => $v)
        <div class="card p-4"><p class="label">{{ $l }}</p><p class="font-display font-bold text-3xl tabular mt-1">{{ $v }}</p></div>
    @endforeach
</div>

@if ($import->stats)
    <div class="card p-4 overflow-x-auto">
        <table class="table text-sm">
            <thead><tr><th>Сущность</th><th>Новых</th><th>Обновлено</th><th>Без изменений</th><th>Пропущено</th><th>Ошибок</th></tr></thead>
            <tbody>@foreach ($import->stats as $e => $s)<tr><td class="font-semibold mono">{{ $e }}</td><td class="tabular">{{ $s['created'] }}</td><td class="tabular">{{ $s['updated'] }}</td><td class="tabular">{{ $s['unchanged'] }}</td><td class="tabular">{{ $s['skipped'] }}</td><td class="tabular">{{ $s['error'] }}</td></tr>@endforeach</tbody>
        </table>
    </div>
@endif

<div class="card p-5">
    <h2 class="font-display font-bold text-lg mb-3">Журнал</h2>
    @forelse ($import->log ?? [] as $line)
        <p class="mono text-[12px] py-1 border-t border-[var(--line)] {{ str_contains($line, ' error ') ? 'error' : '' }}">{{ $line }}</p>
    @empty
        <p class="muted text-sm">Все строки без изменений.</p>
    @endforelse
</div>
@endsection
