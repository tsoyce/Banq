{{-- A22 · Обмен данными: импорт (файл или вставка → проверка), экспорт, формат и шаблоны, история. --}}
@extends('layouts.admin', ['title' => 'Обмен данными'])

@section('content')
<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="label">Обмен</p>
        <h1 class="font-display font-bold text-3xl">Импорт и экспорт данных</h1>
        <p class="muted text-sm mt-1 max-w-2xl">Один формат для всего: страны, банки, вклады, ставки. JSON, XML, YAML, NDJSON, CSV, TSV или XLSX — через эту страницу, <a href="{{ route('admin.exchange.grid.index') }}" class="underline">таблицу «как Excel»</a>, командой <code class="mono">php artisan banq:import</code> или API. Повторная заливка не плодит дублей, новая ставка закрывает старую.</p>
    </div>
    <nav class="seg">
        @foreach (['import' => 'Импорт', 'export' => 'Экспорт', 'format' => 'Формат и API', 'history' => 'История'] as $k => $l)
            <a href="{{ route('admin.exchange.index', ['tab' => $k]) }}" class="{{ $tab === $k ? 'is-active' : 'muted' }}">{{ $l }}</a>
        @endforeach
    </nav>
</header>

@if ($tab === 'import')
<form method="POST" action="{{ route('admin.exchange.check') }}" enctype="multipart/form-data" class="grid xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] gap-4 items-start"
      x-data="{ name: '', over: false, src: '{{ old('text') ? 'text' : 'file' }}' }">
    @csrf
    <div class="card p-6 space-y-4">
        <div class="seg w-fit">
            <label><input type="radio" class="sr-only" x-model="src" value="file">Файл</label>
            <label><input type="radio" class="sr-only" x-model="src" value="text">Вставить текст</label>
        </div>
        <label x-show="src === 'file'" class="block rounded-2xl border-2 border-dashed p-10 text-center cursor-pointer transition" :style="{ borderColor: over ? 'var(--ink)' : 'var(--border)' }"
               @dragover.prevent="over = true" @dragleave="over = false" @drop.prevent="over = false; $refs.file.files = $event.dataTransfer.files; name = $refs.file.files[0]?.name">
            <input x-ref="file" type="file" name="file" accept=".json,.jsonl,.ndjson,.xml,.yaml,.yml,.csv,.tsv,.txt,.xlsx,.xls,.ods" class="hidden" @change="name = $event.target.files[0]?.name">
            @include('partials.icon', ['i' => 'upload', 'c' => 'w-8 h-8 mx-auto faint'])
            <p class="font-display font-bold text-xl mt-3">Перетащи файл или нажми</p>
            <p class="muted text-sm mt-1" x-text="name || 'JSON, XML, YAML, NDJSON, CSV, TSV, XLSX · до 20 МБ'"></p>
        </label>
        <div x-show="src === 'text'" x-cloak class="field">
            <label for="text" class="text-[0.85rem] font-semibold">JSON, XML, YAML, CSV или ячейки из Excel</label>
            <textarea id="text" name="text" rows="14" class="input font-mono text-[13px]" spellcheck="false" placeholder='{"banks": [{"code": "BCC", "name": "Банк ЦентрКредит", "deposit_products": [{"name": "Срочный", "type": "term", "rates": [{"term_months": 12, "rate": 14.5}]}]}]}'>{{ old('text') }}</textarea>
        </div>
        @error('file')<p class="error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="card p-6 space-y-4">
        <p class="font-display font-bold text-lg">Как читать</p>
        <label class="field"><span>Какие это данные</span>
            <select name="entity" class="input">
                <option value="">Определить само (пакет JSON, листы XLSX)</option>
                @foreach ($entities as $key => $e)<option value="{{ $key }}" @selected(old('entity') === $key)>{{ $e->label() }} · {{ $key }}</option>@endforeach
            </select>
            <span class="faint text-xs font-normal">нужно для плоского CSV или массива строк</span>
        </label>
        <div class="grid grid-cols-2 gap-3">
            <label class="field"><span>Формат</span>
                <select name="format" class="input"><option value="">По файлу</option>@foreach (\App\Services\Exchange\DataExchange::FORMAT_LABELS as $v => $l)<option value="{{ $v }}" @selected(old('format') === $v)>{{ $l }}</option>@endforeach</select></label>
            <label class="field"><span>Страна по умолчанию</span>
                <select name="country" class="input">@foreach ($countries as $c)<option value="{{ $c->code }}" @selected(old('country', country()?->code) === $c->code)>{{ $c->flag }} {{ $c->name }}</option>@endforeach</select></label>
        </div>
        <fieldset class="field"><legend class="text-[0.85rem] font-semibold mb-2">Режим</legend>
            <div class="grid gap-2">
                @foreach ($modes as $v => $l)
                    <label class="check-pill !justify-start"><input type="radio" name="mode" value="{{ $v }}" class="sr-only" @checked(old('mode', 'upsert') === $v)>{{ $l }}</label>
                @endforeach
            </div>
        </fieldset>
        <button class="btn btn-primary w-full">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) Проверить — ничего не запишется</button>
        <p class="faint text-xs">Сначала покажем, что будет создано и изменено (с диффом по полям), потом — кнопка «Применить». Старая матрица Excel — в разделе <a href="{{ route('admin.import.index') }}" class="underline">«Матрица Excel»</a>.</p>
    </div>
</form>
@endif

@if ($tab === 'export')
<form method="GET" action="{{ route('admin.exchange.export') }}" class="card p-6 grid md:grid-cols-2 gap-6 max-w-4xl">
    <fieldset>
        <legend class="font-display font-bold text-lg mb-3">Что выгрузить</legend>
        <div class="grid gap-2">
            @foreach ($entities as $key => $e)
                <label class="check-pill !justify-start"><input type="checkbox" name="entities[]" value="{{ $key }}" class="sr-only" checked>{{ $e->label() }} <span class="count mono">{{ $key }}</span></label>
            @endforeach
        </div>
    </fieldset>
    <div class="space-y-4">
        <label class="field"><span>Страна</span>
            <select name="country" class="input"><option value="">Все страны</option>@foreach ($countries as $c)<option value="{{ $c->code }}">{{ $c->flag }} {{ $c->name }}</option>@endforeach</select></label>
        <fieldset class="field"><legend class="text-[0.85rem] font-semibold mb-2">Формат</legend>
            <div class="seg flex-wrap">@foreach (\App\Services\Exchange\DataExchange::FORMAT_LABELS as $v => $l)<label><input type="radio" name="format" value="{{ $v }}" class="sr-only" @checked($v === 'json')>{{ $l }}</label>@endforeach</div>
        </fieldset>
        <label class="switch"><span>Только действующие ставки</span><input type="hidden" name="current" value="0"><input type="checkbox" name="current" value="1" class="sr-only"><i></i></label>
        <button class="btn btn-primary w-full">@include('partials.icon', ['i' => 'download', 'c' => 'w-4 h-4']) Скачать</button>
        <p class="faint text-xs">Выгрузка — в том же формате, что принимает импорт: её можно поправить и залить обратно. В CSV несколько сущностей разделяет колонка <code class="mono">_entity</code>, в XLSX — листы.</p>
    </div>
</form>
@endif

@if ($tab === 'format')
<div class="grid xl:grid-cols-2 gap-4 items-start">
    <section class="card p-6 space-y-4 text-sm">
        <h2 class="font-display font-bold text-xl">Формат пакета</h2>
        <p class="muted">Ключи верхнего уровня — сущности. Банк можно вложить в страну (<code class="mono">banks</code>), вклад — в банк (<code class="mono">deposit_products</code>), ставки — во вклад (<code class="mono">rates</code>). Ссылки — по естественным ключам: страна — ISO-код, банк — код/имя/алиас, вклад — slug или название.</p>
        <pre class="plate p-4 overflow-x-auto mono text-[12px] leading-relaxed">{{ json_encode(app(\App\Services\Exchange\EntityRegistry::class)->schema()['example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.exchange.schema') }}" target="_blank" class="btn btn-ghost !py-2 text-sm">@include('partials.icon', ['i' => 'code', 'c' => 'w-4 h-4']) Полная схема JSON</a>
        </div>
    </section>
    <section class="card p-6 space-y-4 text-sm">
        <h2 class="font-display font-bold text-xl">Поля и шаблоны</h2>
        @foreach ($entities as $key => $e)
            <details class="plate p-4" @if ($loop->first) open @endif>
                <summary class="cursor-pointer font-semibold flex items-center gap-2">{{ $e->label() }} <span class="faint mono text-xs">{{ $key }}</span>
                    <span class="ml-auto flex gap-1 text-xs font-normal"><a href="{{ route('admin.exchange.grid', $key) }}" class="chip hover:bg-[var(--line)]">таблица</a>@foreach (['csv', 'xlsx', 'json'] as $f)<a href="{{ route('admin.exchange.template', [$key, $f]) }}" class="chip hover:bg-[var(--line)]">шаблон {{ $f }}</a>@endforeach</span></summary>
                <p class="muted mt-2">{{ $e->description() }}</p>
                <table class="table text-[13px] mt-2">
                    <tbody>
                    @foreach ($e->fields() as $name => $f)
                        <tr><td class="mono whitespace-nowrap">{{ $name }}@if ($f['required'] ?? false)<span class="text-[var(--bad)]">*</span>@endif</td><td class="faint whitespace-nowrap">{{ $f[0] }}</td><td class="muted">{{ $f['desc'] ?? '' }}@if (! empty($f['values'])) <span class="mono">({{ implode(' | ', $f['values']) }})</span>@endif</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </details>
        @endforeach
    </section>
    <section class="card p-6 space-y-3 text-sm xl:col-span-2">
        <h2 class="font-display font-bold text-xl">Командная строка и API</h2>
        <pre class="plate p-4 overflow-x-auto mono text-[12px] leading-relaxed">php artisan banq:schema                              # схема формата
php artisan banq:import data.json --dry-run          # проверить, ничего не записывая
php artisan banq:import rates.csv --entity=rates --country=KZ
php artisan banq:export banks deposit_rates --format=xlsx --current --output=kz.xlsx
php artisan banq:token "Claude" --write              # токен для API

curl -H "Authorization: Bearer $TOKEN" {{ url('/api/v1/schema') }}
curl -H "Authorization: Bearer $TOKEN" "{{ url('/api/v1/deposit_rates') }}?country=KZ&amp;current=1"
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
     --data @data.json "{{ url('/api/v1/import') }}?dry_run=1"</pre>
        <p class="muted">Токены — в разделе <a href="{{ route('admin.exchange.tokens') }}" class="underline">«Токены API»</a>. Подробности — <span class="mono">docs/API.md</span>.</p>
    </section>
    {{-- A39 · Инструкция для ИИ-агента: скопировать в промпт вместе с токеном --}}
    @php($agentPrompt = "Ты наполняешь базу портала о банках через API ".url('/api/v1').". Токен: Authorization: Bearer <ТОКЕН>.\n"
        ."1) GET /api/v1 и GET /api/v1/schema — прочитай порядок работы, поля, типы и примеры (OpenAPI — /api/v1/openapi.json).\n"
        ."2) GET /api/v1/{сущность}?country=KZ — посмотри, что уже есть (постранично: limit/offset, next_offset).\n"
        ."3) Собери пакет JSON {\"banks\": [...], \"deposit_rates\": [...]} — ссылки по кодам банков и slug вкладов, ставки в % годовых, источник — официальный сайт банка.\n"
        ."4) POST /api/v1/import?dry_run=1 — исправь все строки с action=error и повтори проверку.\n"
        ."5) POST /api/v1/import с заголовком Idempotency-Key — запиши. Пришли мне отчёт totals.")
    <section class="card p-6 space-y-3 text-sm xl:col-span-2" x-data="{ copied: false }">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-display font-bold text-xl mr-auto">Для ИИ-агентов</h2>
            <a href="{{ url('/api/v1') }}" target="_blank" class="btn btn-ghost !py-2 text-sm">@include('partials.icon', ['i' => 'external', 'c' => 'w-4 h-4']) /api/v1</a>
            <a href="{{ url('/api/v1/openapi.json') }}" target="_blank" class="btn btn-ghost !py-2 text-sm">@include('partials.icon', ['i' => 'code', 'c' => 'w-4 h-4']) OpenAPI</a>
            <button type="button" class="btn btn-primary !py-2 text-sm" @click="navigator.clipboard.writeText($refs.prompt.textContent); copied = true; setTimeout(() => copied = false, 2000)">
                @include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) <span x-text="copied ? 'Скопировано' : 'Копировать инструкцию'"></span></button>
        </div>
        <p class="muted">Агент (Claude и другие) сам читает описание API без токена, проверяет пакет на dry-run и пишет идемпотентно: повтор того же пакета ничего не меняет, а с <code class="mono">Idempotency-Key</code> повтор запроса вернёт прежний отчёт. Удаление — только токеном с правом «удаление»; ставки при этом закрываются, история остаётся.</p>
        <pre x-ref="prompt" class="plate p-4 overflow-x-auto mono text-[12px] leading-relaxed whitespace-pre-wrap">{{ $agentPrompt }}</pre>
    </section>
</div>
@endif

@if ($tab === 'history')
<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>#</th><th>Файл</th><th>Откуда</th><th>Когда</th><th>Новых</th><th>Обновлено</th><th>Без изменений</th><th>Ошибок</th><th></th></tr></thead>
        <tbody>
        @forelse ($imports as $i)
            <tr>
                <td class="faint tabular">{{ $i->id }}</td>
                <td><p class="font-semibold">{{ $i->filename }}</p><p class="faint text-xs">{{ $i->kind }} · {{ $i->format ?? 'matrix' }}</p></td>
                <td class="text-sm">{{ ['admin' => 'админка', 'cli' => 'консоль', 'api' => 'API'][$i->source] ?? $i->source }}<span class="faint block text-xs">{{ $i->user?->name ?? $i->token?->name }}</span></td>
                <td class="muted text-sm whitespace-nowrap">{{ $i->created_at->translatedFormat('j M Y, H:i') }}</td>
                <td class="tabular">{{ $i->rows_created }}</td><td class="tabular">{{ $i->rows_updated }}</td><td class="tabular faint">{{ $i->rows_skipped }}</td>
                <td class="tabular {{ $i->rows_failed ? 'text-[var(--bad)] font-semibold' : 'faint' }}">{{ $i->rows_failed }}</td>
                <td class="text-right"><a href="{{ route('admin.exchange.show', $i) }}" class="btn btn-ghost !py-1.5 text-sm">Журнал</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted text-center py-10">Импортов ещё не было</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $imports->links() }}
@endif
@endsection
