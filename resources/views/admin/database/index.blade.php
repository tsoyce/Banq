{{-- A34 · База данных: текущее подключение, переключение SQLite ⇄ MySQL с переносом данных, откат из копии .env --}}
@extends('layouts.admin', ['title' => 'База данных'])

@php
    $isSqlite = $current['driver'] === 'sqlite';
    $target = $isSqlite ? 'mysql' : 'sqlite';
    $size = $current['size'] ? number_format($current['size'] / 1048576, 1, ',', ' ').' МБ' : null;
    $top = collect($current['tables'])->sortDesc()->take(8);
@endphp

@section('content')
<div class="flex flex-wrap items-end gap-3">
    <div>
        <p class="kicker">Сайт</p>
        <h1 class="font-display font-bold text-3xl">База данных</h1>
    </div>
</div>

@error('database')<div class="card p-4 mt-4 border border-[var(--bad)] error text-sm">{{ $message }}</div>@enderror

<div class="grid xl:grid-cols-[1fr_380px] gap-4 mt-6">
    <div class="space-y-4">
        {{-- Сейчас подключено --}}
        <section class="card p-6">
            <div class="flex flex-wrap items-center gap-3">
                <span class="w-11 h-11 rounded-2xl grid place-items-center text-white font-bold {{ $isSqlite ? 'bg-[#0f80cc]' : 'bg-[#00758f]' }}">{{ $isSqlite ? 'SQ' : 'My' }}</span>
                <div class="min-w-0">
                    <p class="text-sm muted">Сейчас подключено</p>
                    <p class="font-display font-bold text-xl">{{ $isSqlite ? 'SQLite' : 'MySQL' }} @if ($current['version'])<span class="faint text-sm font-normal">· {{ $current['version'] }}</span>@endif</p>
                </div>
                @if ($current['error'])
                    <span class="chip ml-auto text-[var(--bad)]">ошибка подключения</span>
                @else
                    <span class="chip ml-auto"><span class="status-dot status-approved"></span> работает</span>
                @endif
            </div>
            <div class="cells cols-3 mt-5">
                <div><p class="cell-label">{{ $isSqlite ? 'Файл' : 'База' }}</p><p class="font-semibold break-all text-sm mt-1" title="{{ $current['database'] }}">{{ $isSqlite ? basename((string) $current['database']) : ($current['database'] ?: '—') }}</p></div>
                <div><p class="cell-label">{{ $isSqlite ? 'Размер' : 'Сервер' }}</p><p class="font-semibold text-sm mt-1">{{ $isSqlite ? ($size ?? '—') : $current['host'] }}</p></div>
                <div><p class="cell-label">Таблиц · строк</p><p class="font-semibold text-sm mt-1">{{ count($current['tables']) }} · {{ number_format($current['rows'], 0, ',', ' ') }}</p></div>
            </div>
            @if ($isSqlite && $current['database'])<p class="faint text-xs mt-3 mono break-all">{{ $current['database'] }}</p>@endif
            @if ($current['error'])<p class="error text-sm mt-4 break-words">{{ $current['error'] }}</p>@endif
            @if ($top->isNotEmpty())
                <div class="flex flex-wrap gap-2 mt-5">
                    @foreach ($top as $t => $n)<span class="chip text-xs"><span class="mono">{{ $t }}</span> <b>{{ number_format($n, 0, ',', ' ') }}</b></span>@endforeach
                </div>
            @endif
        </section>

        {{-- Переключение --}}
        <form method="POST" action="{{ route('admin.database.switch') }}" class="card p-6 grid sm:grid-cols-2 gap-4"
              x-data="{
                driver: @js(old('driver', $target)), copy: true, state: null, message: '', info: null, busy: false, sending: false,
                async test() {
                    this.busy = true; this.state = null; this.info = null;
                    const r = await fetch(@js(route('admin.database.test')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: new FormData(this.$root) });
                    const j = await r.json().catch(() => ({ ok: false, message: 'Проверь заполнение полей' }));
                    this.state = r.ok && j.ok; this.message = j.message || Object.values(j.errors || {}).flat().join(' ');
                    if (j.version) this.message += ' · ' + j.version;
                    if (this.state) this.info = j;
                    this.busy = false;
                }
              }" @submit="sending = true">
            @csrf
            <div class="sm:col-span-2">
                <h2 class="font-display font-bold text-lg">Переключить базу</h2>
                <p class="muted text-sm mt-1">Сайт создаст таблицы в новой базе, перенесёт все данные, сверит число строк и только потом перепишет <code>.env</code>. Прежняя база не меняется — к ней можно вернуться одной кнопкой.</p>
            </div>

            <div class="seg sm:col-span-2" role="radiogroup" aria-label="Тип базы">
                <label :class="driver === 'mysql' && 'is-active'"><input type="radio" name="driver" value="mysql" x-model="driver" class="sr-only"> MySQL / MariaDB</label>
                <label :class="driver === 'sqlite' && 'is-active'"><input type="radio" name="driver" value="sqlite" x-model="driver" class="sr-only"> SQLite</label>
            </div>

            <template x-if="driver === 'mysql'">
                <div class="contents">
                    <label class="field"><span>Хост</span><input name="host" class="input" value="{{ old('host', '127.0.0.1') }}" required></label>
                    <label class="field"><span>Порт</span><input name="port" type="number" class="input" value="{{ old('port', 3306) }}"></label>
                    <label class="field"><span>База</span><input name="database" class="input" value="{{ old('database', $isSqlite ? '' : $current['database']) }}" placeholder="tsoy_banq" required></label>
                    <label class="field"><span>Префикс таблиц</span><input name="prefix" class="input" value="{{ old('prefix') }}" placeholder="необязательно"></label>
                    <label class="field"><span>Пользователь</span><input name="username" class="input" value="{{ old('username') }}" autocomplete="off" required></label>
                    <label class="field"><span>Пароль</span><input name="password" type="password" class="input" autocomplete="new-password"></label>
                    <p class="faint text-xs sm:col-span-2">В BrainyCP: «Базы данных» → MySQL — создай пустую базу и пользователя с полными правами на неё. Движок — InnoDB.</p>
                </div>
            </template>
            <template x-if="driver === 'sqlite'">
                <label class="field sm:col-span-2"><span>Файл базы</span><input name="database" class="input font-mono" value="{{ old('database', 'database.sqlite') }}">
                    <span class="faint text-xs font-normal">Имя внутри папки <code>database/</code> или полный путь. Файла нет — будет создан.</span></label>
            </template>

            <div class="sm:col-span-2 grid gap-2">
                <label class="switch"><span>Перенести данные текущей базы</span><input type="hidden" name="copy" value="0"><input type="checkbox" name="copy" value="1" class="sr-only" x-model="copy" checked><i></i></label>
                <label class="switch" x-show="copy"><span>Перезаписать данные, если новая база не пустая</span><input type="checkbox" name="overwrite" value="1" class="sr-only" @checked(old('overwrite'))><i></i></label>
            </div>

            <div class="sm:col-span-2 flex flex-wrap items-center gap-3">
                <button type="button" class="btn btn-ghost" @click="test" :disabled="busy">
                    @include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) <span x-text="busy ? 'Проверяю…' : 'Проверить подключение'"></span>
                </button>
                <p class="text-sm" x-show="state !== null" x-cloak :class="state ? 'text-[var(--good)]' : 'error'">
                    <span x-text="message"></span>
                    <template x-if="info && info.same"><span class="block faint">Это и есть текущая база.</span></template>
                    <template x-if="info && !info.same && info.tables"><span class="block faint" x-text="'В базе уже ' + info.tables + ' табл., ключевых строк: ' + info.rows"></span></template>
                </p>
            </div>

            <label class="flex items-start gap-2 text-sm sm:col-span-2"><input type="checkbox" name="confirm" value="1" class="mt-1">
                <span>Понимаю: после переключения сайт сразу начнёт работать с новой базой. Если вход слетит — войди заново тем же логином и паролем.</span></label>
            @error('confirm')<p class="error text-xs sm:col-span-2">{{ $message }}</p>@enderror
            @foreach (['driver', 'host', 'port', 'username', 'prefix'] as $f)@error($f)<p class="error text-xs sm:col-span-2">{{ $message }}</p>@enderror @endforeach

            <div class="sm:col-span-2"><button class="btn btn-primary" :disabled="sending"><span x-text="sending ? 'Переношу данные…' : 'Переключить базу'"></span></button></div>
        </form>
    </div>

    <aside class="space-y-4">
        <section class="card-ink p-6 rounded-[var(--radius,1.5rem)]">
            <p class="kicker !text-white/60">Как это работает</p>
            <ol class="mt-3 space-y-2 text-sm text-white/85 list-decimal pl-4">
                <li>Проверка подключения к новой базе.</li>
                <li>Миграции: все таблицы проекта.</li>
                <li>Перенос таблиц по 500 строк и сверка числа строк. Кеш и очереди не переносятся.</li>
                <li>Копия <code>.env</code> → <code>storage/app/env-backups</code>, запись новых <code>DB_*</code>.</li>
            </ol>
            <p class="text-xs text-white/60 mt-4">Если что-то пошло не так на шагах 1–3, сайт остаётся на прежней базе.</p>
        </section>

        <section class="card p-6">
            <h2 class="font-display font-bold text-lg">Вернуть прежнюю</h2>
            <p class="muted text-sm mt-1">Копии <code>.env</code> до каждого переключения. Возвращаются только строки <code>DB_*</code>.</p>
            <div class="mt-4 space-y-2">
                @forelse ($backups as $b)
                    <form method="POST" action="{{ route('admin.database.restore') }}" class="flex items-center gap-3 rounded-2xl bg-[var(--card-2)] p-3"
                          onsubmit="return confirm('Вернуть настройки базы из этой копии?')">
                        @csrf <input type="hidden" name="file" value="{{ $b['file'] }}">
                        <div class="min-w-0 text-sm">
                            <p class="font-semibold">{{ $b['driver'] === 'mysql' ? 'MySQL' : 'SQLite' }} <span class="faint font-normal">· {{ \Illuminate\Support\Carbon::createFromTimestamp($b['at'])->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</span></p>
                            <p class="faint text-xs truncate">{{ $b['database'] }}</p>
                        </div>
                        <button class="btn btn-ghost !py-1.5 !px-3 text-sm ml-auto">Вернуть</button>
                    </form>
                @empty
                    <p class="faint text-sm">Переключений ещё не было.</p>
                @endforelse
            </div>
        </section>
    </aside>
</div>
@endsection
