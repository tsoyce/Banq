@extends('layouts.admin', ['title' => 'Импорт Excel'])

@section('content')
<h1 class="font-display font-bold text-3xl">Импорт ставок из Excel</h1>

<form method="POST" action="{{ route('admin.import.preview') }}" enctype="multipart/form-data" class="glass glow-edge p-8"
      x-data="{ name: '', over: false }" @dragover.prevent="over = true" @dragleave="over = false" @drop.prevent="over = false; $refs.file.files = $event.dataTransfer.files; name = $refs.file.files[0]?.name">
    @csrf
    <label class="block rounded-2xl border-2 border-dashed p-10 text-center cursor-pointer transition" :style="{ borderColor: over ? 'var(--a2)' : 'var(--border)' }">
        <input x-ref="file" type="file" name="file" accept=".xlsx,.xls,.csv,.ods" class="hidden" @change="name = $event.target.files[0]?.name" required>
        <p class="font-display font-bold text-xl">Перетащи файл сюда или нажми</p>
        <p class="muted text-sm mt-2" x-text="name || 'xlsx, xls, csv, ods · до 10 МБ'"></p>
    </label>
    @error('file')<p class="error mt-3">{{ $message }}</p>@enderror
    <div class="mt-6 grid md:grid-cols-2 gap-6 text-sm muted">
        <div>
            <p class="font-semibold text-[var(--text)] mb-2">Формат «матрица» (как твой первый файл)</p>
            <p>Банки (коды BCC, FFIN… или названия) по одной оси, по другой — «Снятие и пополнение», «Пополнение», «Табу». Ориентация любая.</p>
        </div>
        <div>
            <p class="font-semibold text-[var(--text)] mb-2">Формат «таблица»</p>
            <p>Колонки: <code>Банк</code>, <code>Ставка</code> и по желанию <code>Тип</code>, <code>Продукт</code>, <code>Срок</code>, <code>Валюта</code>, <code>Мин. сумма</code>, <code>ГЭСВ</code>.</p>
        </div>
    </div>
    <button class="btn btn-primary mt-6">Показать предпросмотр</button>
</form>

<div class="glass p-4 overflow-x-auto">
    <h2 class="font-display font-bold text-xl px-4 py-2">История</h2>
    <table class="table">
        <thead><tr><th>Файл</th><th>Когда</th><th>Всего</th><th>Новых</th><th>Обновлено</th><th>Пропущено</th></tr></thead>
        <tbody>
        @forelse ($imports as $i)
            <tr>
                <td class="font-semibold">{{ $i->filename }}
                    @if ($i->log)<details class="text-xs muted mt-1"><summary class="cursor-pointer">журнал ({{ count($i->log) }})</summary><ul class="mt-1 space-y-1">@foreach ($i->log as $line)<li>{{ $line }}</li>@endforeach</ul></details>@endif
                </td>
                <td class="muted text-sm">{{ $i->created_at->translatedFormat('j M Y, H:i') }}</td>
                <td class="tabular">{{ $i->rows_total }}</td><td class="tabular">{{ $i->rows_created }}</td><td class="tabular">{{ $i->rows_updated }}</td><td class="tabular">{{ $i->rows_skipped }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted text-center py-8">Пока пусто</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $imports->links() }}
</div>
@endsection
