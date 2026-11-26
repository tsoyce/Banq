@extends('layouts.install')

@section('content')
<h1 class="font-display font-bold text-3xl">Привет! Запускаем <span class="text-grad">TSOY.IN</span></h1>
<p class="muted mt-3">Три коротких шага — и портал готов. Сначала проверим, что сервер умеет всё нужное.</p>

<div class="mt-8 space-y-6">
    @foreach ($checks as $group => $rows)
        <div>
            <p class="faint text-xs uppercase tracking-widest font-bold mb-3">{{ $group }}</p>
            <ul class="grid sm:grid-cols-2 gap-2">
                @foreach ($rows as $c)
                    <li class="flex items-start gap-3 rounded-2xl px-4 py-3 bg-[var(--surface)] border border-[var(--border)]" title="{{ $c['ok'] ? '' : $c['hint'] }}">
                        <span class="mt-1.5 w-2 h-2 rounded-full flex-none" style="background: {{ $c['ok'] ? 'var(--ok)' : 'var(--bad)' }}; box-shadow: 0 0 10px {{ $c['ok'] ? 'var(--ok)' : 'var(--bad)' }}"></span>
                        <span class="text-sm">
                            <span class="font-semibold font-mono">{{ $c['name'] }}</span>
                            @unless ($c['ok'])<span class="block error mt-1">{{ $c['hint'] }}</span>@endunless
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>

<div class="mt-10 flex justify-between items-center">
    <a href="{{ route('install.welcome') }}" class="btn btn-ghost">Проверить снова</a>
    <a href="{{ route('install.database') }}" class="btn btn-primary" @unless ($passes) aria-disabled="true" style="opacity:.5;pointer-events:none" @endunless>Дальше →</a>
</div>
@endsection
