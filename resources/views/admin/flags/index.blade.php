{{-- A46 · Админка → Флаги: библиотека SVG (flag-icons, MIT) с поиском, свои флаги — загрузка, замена, ручной ввод кода и названия. --}}
@extends('layouts.admin', ['title' => 'Флаги'])

@section('content')
<div x-data="{ q: @js(request('q', '')), code: @js(old('code', '')), name: @js(old('name', '')),
               pick(k, n) { this.code = k; this.name = n; $refs.form.scrollIntoView({ behavior: 'smooth', block: 'center' }); } }" class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display font-bold text-3xl">Флаги</h1>
            <p class="muted mt-1 max-w-2xl">Флаги стран и валют на сайте — SVG из встроенной библиотеки ({{ count(\App\Support\Flags::NAMES) }} шт., лежат на нашем сервере). Любой можно заменить своим или добавить новый код: регион, организацию, старый вариант флага.</p>
        </div>
    </div>

    <form x-ref="form" method="POST" action="{{ route('admin.flags.store') }}" enctype="multipart/form-data" class="glass p-6 grid sm:grid-cols-[160px_1fr_1fr_auto] gap-3 items-end">
        @csrf
        <label class="grid gap-1.5"><span class="label">Код</span>
            <input name="code" x-model="code" required maxlength="16" class="input font-mono lowercase" placeholder="kz, eu, gb-sct"></label>
        <label class="grid gap-1.5"><span class="label">Название</span>
            <input name="name" x-model="name" maxlength="80" class="input" placeholder="как подписать флаг"></label>
        <label class="grid gap-1.5"><span class="label">Картинка</span>
            <input type="file" name="file" accept=".svg,.png,.jpg,.jpeg,.webp" class="input !py-2 text-sm"></label>
        <button class="btn btn-primary">@include('partials.icon', ['i' => 'upload', 'c' => 'w-4 h-4']) Сохранить</button>
        <p class="faint text-xs sm:col-span-4">SVG без скриптов и внешних ссылок, PNG, JPG или WebP до 512 КБ. Пропорции 4:3 — в кружках флаг обрезается по центру. Клик по флагу ниже подставит его код.</p>
        @if ($errors->any())<p class="error text-sm sm:col-span-4">{{ $errors->first() }}</p>@endif
    </form>

    <section class="glass p-6 space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <input type="search" x-model="q" class="input !w-auto flex-1 min-w-48" placeholder="Поиск: название или код" aria-label="Поиск флага">
            <span class="chip">своих: {{ collect($flags)->where('custom', true)->count() }}</span>
        </div>
        <ul class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2">
            @foreach ($flags as $code => $f)
                <li x-show="!q || @js(mb_strtolower($f['name'].' '.$code)).includes(q.toLowerCase())" class="plate p-3 flex flex-col gap-2 relative min-w-0">
                    <button type="button" class="text-left grid gap-2 min-w-0" @click="pick(@js($code), @js($f['name']))" title="Заменить или переименовать">
                        @if ($f['url'])<img src="{{ $f['url'] }}" alt="" loading="lazy" class="flag !h-auto w-full !aspect-[4/3] object-cover rounded-md">@else<span class="flag flag-none !h-auto w-full aspect-[4/3]">{{ strtoupper($code) }}</span>@endif
                        <span class="text-xs leading-tight"><b class="block truncate">{{ $f['name'] }}</b><span class="faint mono">{{ $code }}</span>@isset($used[$code]) <span class="chip !py-0 !px-1.5 !text-[10px]">{{ $used[$code] }}</span>@endisset</span>
                    </button>
                    @if ($f['custom'] || ! $f['library'])
                        <form method="POST" action="{{ route('admin.flags.destroy', $code) }}" class="absolute top-1.5 right-1.5">@csrf @method('DELETE')
                            <button class="chip !py-0 !px-1.5 !text-[10px]" style="background: var(--ink); color: var(--ink-text)" title="{{ $f['library'] ? 'Вернуть флаг из библиотеки' : 'Удалить свой флаг' }}">{{ $f['library'] ? 'свой ✕' : '✕' }}</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        <p class="faint text-xs">Библиотека — <a class="underline" href="https://github.com/lipis/flag-icons" target="_blank" rel="noopener">flag-icons</a> (MIT). Флаг страны берётся по её ISO-коду; другой можно выбрать в карточке страны.</p>
    </section>
</div>
@endsection
