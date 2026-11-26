@extends('layouts.install')

@section('content')
<div x-data="{
        driver: @js(old('driver', $db['driver'])),
        state: null, message: '', busy: false,
        async test() {
            this.busy = true; this.state = null;
            const r = await fetch(@js(route('install.database.test')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                body: new FormData(this.$refs.form),
            });
            const j = await r.json().catch(() => ({ ok: false, message: 'Проверь заполнение полей' }));
            this.state = r.ok && j.ok; this.message = j.message || Object.values(j.errors || {}).flat().join(' ');
            if (j.version) this.message += ' · ' + j.version;
            this.busy = false;
        }
    }">
<h1 class="font-display font-bold text-3xl">База данных</h1>
<p class="muted mt-3">В BrainyCP: «Базы данных» → создай базу MySQL и пользователя, затем впиши их сюда. Пароль хранится только в файле <code>.env</code> на сервере.</p>

<form x-ref="form" method="POST" action="{{ route('install.database.save') }}" class="mt-8 grid sm:grid-cols-2 gap-4">
    @csrf
    <label class="field sm:col-span-2"><span>Тип базы</span>
        <select name="driver" x-model="driver" class="input">
            <option value="mysql">MySQL / MariaDB (боевой сервер)</option>
            <option value="sqlite">SQLite (локально, для пробы)</option>
        </select>
    </label>
    <template x-if="driver === 'mysql'">
        <div class="contents">
            <label class="field"><span>Хост</span><input name="host" class="input" value="{{ old('host', $db['host']) }}"></label>
            <label class="field"><span>Порт</span><input name="port" type="number" class="input" value="{{ old('port', $db['port']) }}"></label>
            <label class="field"><span>Имя базы</span><input name="database" class="input" value="{{ old('database', $db['database']) }}" required></label>
            <label class="field"><span>Префикс таблиц (необязательно)</span><input name="prefix" class="input" value="{{ old('prefix', $db['prefix']) }}" placeholder="banq_"></label>
            <label class="field"><span>Пользователь</span><input name="username" class="input" value="{{ old('username', $db['username']) }}" autocomplete="off" required></label>
            <label class="field"><span>Пароль</span><input name="password" type="password" class="input" autocomplete="new-password"></label>
        </div>
    </template>
    <template x-if="driver === 'sqlite'">
        <label class="field sm:col-span-2"><span>Файл базы (в папке database/)</span><input name="database" class="input" value="database.sqlite"></label>
    </template>

    @error('database')<p class="error sm:col-span-2">{{ $message }}</p>@enderror
    <p class="sm:col-span-2 text-sm" x-show="state !== null" x-cloak :style="{ color: state ? 'var(--ok)' : 'var(--bad)' }" x-text="message"></p>

    <div class="sm:col-span-2 mt-4 flex flex-wrap justify-between gap-3">
        <a href="{{ route('install.welcome') }}" class="btn btn-ghost">← Назад</a>
        <div class="flex gap-3">
            <button type="button" class="btn btn-ghost" @click="test" :disabled="busy"><span x-text="busy ? 'Проверяю…' : 'Проверить подключение'"></span></button>
            <button class="btn btn-primary">Создать таблицы →</button>
        </div>
    </div>
</form>
</div>
@endsection
