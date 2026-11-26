@extends('layouts.admin', ['title' => 'Обзор'])

@section('content')
<h1 class="font-display font-bold text-3xl">Обзор</h1>
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach ($stats as $label => $value)
        <div class="glass glow-edge p-6" data-reveal style="--d: {{ $loop->index * .06 }}s">
            <p class="faint text-xs uppercase tracking-widest font-bold">{{ $label }}</p>
            <p class="font-display font-black text-4xl mt-2 tabular" x-data x-count="{{ $value }}">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="grid lg:grid-cols-[1.5fr_1fr] gap-4">
    <div class="glass p-4 overflow-x-auto">
        <div class="flex items-center justify-between px-4 py-2">
            <h2 class="font-display font-bold text-xl">Последние импорты</h2>
            <a href="{{ route('admin.import.index') }}" class="btn btn-ghost !py-2 text-sm">Загрузить Excel</a>
        </div>
        <table class="table">
            <thead><tr><th>Файл</th><th>Когда</th><th>Новых</th><th>Обновлено</th></tr></thead>
            <tbody>
            @forelse ($imports as $i)
                <tr><td class="font-semibold">{{ $i->filename }}</td><td class="muted text-sm">{{ $i->created_at->diffForHumans() }}</td><td class="tabular">{{ $i->rows_created }}</td><td class="tabular">{{ $i->rows_updated }}</td></tr>
            @empty
                <tr><td colspan="4" class="muted text-center py-8">Импортов ещё не было</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="glass p-6 space-y-3 text-sm">
        <h2 class="font-display font-bold text-xl">Система</h2>
        <p class="muted">Версия: <b class="text-[var(--text)]">{{ config('banq.version') }}</b></p>
        <p class="muted">Установлено: <b class="text-[var(--text)]">{{ isset($install['installed_at']) ? \Illuminate\Support\Carbon::parse($install['installed_at'])->translatedFormat('j F Y, H:i') : '—' }}</b></p>
        <p class="muted">Годы жизни проекта: <b class="text-[var(--text)]">{{ project_years() }}</b></p>
        <p class="muted">PHP {{ PHP_VERSION }} · Laravel {{ app()->version() }} · БД {{ config('database.default') }}</p>
    </div>
</div>
@endsection
