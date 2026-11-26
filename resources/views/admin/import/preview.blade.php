@extends('layouts.admin', ['title' => 'Предпросмотр импорта'])

@section('content')
<h1 class="font-display font-bold text-3xl">Предпросмотр: {{ $filename }}</h1>
<p class="muted">Найдено ставок: <b>{{ count($preview['records']) }}</b>. Нули и пустые ячейки — «нет данных». Если листы противоречат друг другу, побеждает последний — сними галочки с черновых листов.</p>

@if ($preview['warnings'])
    <div class="glass p-5 text-sm space-y-1" style="border-color: color-mix(in oklab, var(--a4) 50%, transparent)">
        @foreach ($preview['warnings'] as $w)<p>⚠ {{ $w }}</p>@endforeach
    </div>
@endif

<div class="glass p-4 overflow-x-auto">
    <table class="table">
        <thead><tr><th>Банк в файле</th><th>Банк в базе</th><th>Тип</th><th>Лист</th><th>Валюта</th><th class="!text-right">Ставка</th><th class="!text-right">ГЭСВ</th></tr></thead>
        <tbody>
        @forelse ($preview['records'] as $r)
            <tr>
                <td class="font-mono text-sm">{{ $r['bank'] }}</td>
                <td>@if ($preview['banks'][$r['bank']] ?? null){{ $preview['banks'][$r['bank']] }}@else<span class="error">не найден</span>@endif</td>
                <td class="muted text-sm">{{ $r['type']->label() }}@if ($r['product']) · {{ $r['product'] }}@endif</td>
                <td class="muted text-sm">{{ $r['sheet'] }}@if ($r['term_months']) · {{ $r['term_months'] }} мес.@endif</td>
                <td class="muted text-sm">{{ $r['currency'] }}</td>
                <td class="text-right tabular font-bold">{{ rate_format($r['rate']) }}</td>
                <td class="text-right tabular muted">{{ rate_format($r['effective_rate']) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted text-center py-8">Ставок не найдено. Пустые ячейки и нули считаются «нет данных».</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<form method="POST" action="{{ route('admin.import.store') }}" class="flex flex-wrap items-center gap-4">
    @csrf
    <fieldset class="flex flex-wrap gap-3 w-full">
        <legend class="text-sm font-semibold mb-2">Какие листы записать</legend>
        @foreach ($preview['sheets'] as $sheet => $n)
            <label class="chip cursor-pointer"><input type="checkbox" name="sheets[]" value="{{ $sheet }}" checked class="accent-[var(--a2)]"> {{ $sheet }} · {{ $n }}</label>
        @endforeach
    </fieldset>
    @if ($preview['unknown'])
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="create_banks" value="1" class="accent-[var(--a2)]"> Создать неизвестные банки: {{ implode(', ', $preview['unknown']) }}</label>
    @endif
    <a href="{{ route('admin.import.index') }}" class="btn btn-ghost">Отмена</a>
    <button class="btn btn-primary" @disabled(empty($preview['records']))>Записать в базу</button>
</form>
@endsection
