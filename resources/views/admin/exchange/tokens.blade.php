{{-- A23 · Токены API: выпуск (показывается один раз), список, отзыв. --}}
@extends('layouts.admin', ['title' => 'Токены API'])

@section('content')
<header>
    <h1 class="font-display font-bold text-3xl">Токены API</h1>
    <p class="muted text-sm mt-1 max-w-2xl">Токен даёт доступ к <span class="mono">/api/v1</span>: чтение — схема и выгрузки, запись — импорт. Так скрипт или Claude может собрать данные и залить их сам. В базе хранится только хеш.</p>
</header>

@if (session('plain_token'))
    <div class="card-ink p-6 rounded-[var(--radius)] space-y-3" x-data="{ copied: false }">
        <p class="font-semibold">Скопируй токен сейчас — больше он не покажется:</p>
        <div class="flex flex-wrap gap-2 items-center">
            <code class="mono text-sm bg-white/10 rounded-xl px-4 py-3 break-all flex-1" x-ref="t">{{ session('plain_token') }}</code>
            <button type="button" class="btn btn-brand" @click="navigator.clipboard.writeText($refs.t.textContent.trim()); copied = true" x-text="copied ? 'Скопировано' : 'Копировать'">Копировать</button>
        </div>
    </div>
@endif

<div class="grid xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] gap-4 items-start">
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Название</th><th>Права</th><th>Последний раз</th><th>Истекает</th><th></th></tr></thead>
            <tbody>
            @forelse ($tokens as $t)
                <tr>
                    <td><p class="font-semibold">{{ $t->name }}</p><p class="faint text-xs mono">{{ $t->prefix }}… · {{ $t->user?->name }}</p></td>
                    <td>@foreach ($t->abilities as $a)<span class="chip mr-1">{{ ['write' => 'запись', 'delete' => 'удаление'][$a] ?? 'чтение' }}</span>@endforeach</td>
                    <td class="text-sm muted">{{ $t->last_used_at?->diffForHumans() ?? 'не использовался' }}@if ($t->last_ip)<span class="faint block text-xs">{{ $t->last_ip }}</span>@endif</td>
                    <td class="text-sm {{ $t->expires_at?->isPast() ? 'text-[var(--bad)]' : 'muted' }}">{{ $t->expires_at?->format('d.m.Y') ?? 'бессрочно' }}</td>
                    <td class="text-right">@if (auth()->user()->role === 'admin')
                        <form method="POST" action="{{ route('admin.exchange.tokens.destroy', $t) }}" onsubmit="return confirm('Отозвать токен? Скрипты с ним перестанут работать.')">@csrf @method('DELETE')<button class="btn btn-outline !py-1.5 text-sm text-[var(--bad)]">Отозвать</button></form>
                    @endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted text-center py-10">Токенов нет</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if (auth()->user()->role === 'admin')
        <form method="POST" action="{{ route('admin.exchange.tokens.store') }}" class="card p-6 space-y-4">
            @csrf
            <p class="font-display font-bold text-lg">Новый токен</p>
            <label class="field"><span>Для кого / зачем</span><input name="name" class="input" required maxlength="120" placeholder="Claude — сбор ставок" value="{{ old('name') }}"></label>
            <fieldset class="grid gap-2">
                @foreach ($abilities as $k => $l)
                    <label class="check-pill !justify-start"><input type="checkbox" name="abilities[]" value="{{ $k }}" class="sr-only" @checked(in_array($k, old('abilities', ['read', 'write']), true))>{{ $l }}</label>
                @endforeach
            </fieldset>
            <label class="field"><span>Срок, дней</span><input name="days" type="number" min="1" max="3650" class="input" placeholder="бессрочно" value="{{ old('days') }}"></label>
            @if ($errors->any())<ul class="error text-sm">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
            <button class="btn btn-primary w-full">@include('partials.icon', ['i' => 'key', 'c' => 'w-4 h-4']) Выпустить</button>
        </form>
    @endif
</div>
@endsection
