{{-- A53 · Сравнение любого вида продуктов (пакеты, банки, платёжные системы…): колонки — продукты, строки — CompareType::rows();
     лучшее значение в строке выделено, «Только различия» прячет строки, где у всех одинаково. Список — $store.<type->store()>. --}}
@extends('layouts.app', ['title' => 'Сравнение '.$type->genitive()])

@section('content')
@php
    $ids = $heads->pluck('id');
    $limit = $type->limit();
    $store = '$store.'.$type->store();
    $storeItems = $heads->map(fn ($h) => \Illuminate\Support\Arr::only($h, ['id', 'code', 'color', 'name']))->values();
    $one = $type->forms()[0];
@endphp
<div class="space-y-5" x-data="{ diff: false }" x-init="{{ $store }}.set(@js($storeItems))">
    <section class="card p-7" data-reveal>
        <nav class="text-sm muted flex items-center gap-2 mb-5"><a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span><a href="{{ route('compare.index') }}" class="hover:text-[var(--text)]">Сравнение</a><span class="faint">/</span><span>{{ $type->label() }}</span></nav>
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <h1 class="font-display font-bold text-4xl sm:text-[2.75rem] leading-[1.05]">Сравнение <span class="text-grad">{{ $type->genitive() }}</span></h1>
                <p class="muted mt-2">До {{ $limit }} рядом. Лучшее значение в каждой строке выделено.</p>
            </div>
            @if ($items->count() > 1)
                <div class="plate p-2 flex flex-wrap items-center gap-2 text-[15px]">
                    <label class="switch !py-1 px-2 gap-3"><span>Только различия</span><input type="checkbox" class="sr-only" x-model="diff"><i></i></label>
                    @auth
                        <form method="POST" action="{{ route('comparisons.store') }}" class="contents">
                            @csrf
                            <input type="hidden" name="type" value="{{ $type->key() }}">
                            <input type="hidden" name="ids" value="{{ $ids->join(',') }}">
                            <button class="btn btn-primary !py-2">@include('partials.icon', ['i' => 'heart', 'c' => 'w-4 h-4']) Сохранить</button>
                        </form>
                    @else
                        <a href="{{ route('login', ['to' => request()->getRequestUri()]) }}" class="btn btn-ghost !py-2 !bg-[var(--card)]" title="Войдите, чтобы сохранить сравнение">@include('partials.icon', ['i' => 'heart', 'c' => 'w-4 h-4']) Сохранить</a>
                    @endauth
                </div>
            @endif
        </div>
        @error('ids')<p class="error mt-3">{{ $message }}</p>@enderror
        <div class="mt-6">@include('compare._tabs', ['active' => $type->key(), 'types' => $types])</div>
    </section>

    @if ($items->isEmpty())
        <section class="card p-12 text-center space-y-4" data-reveal>
            <span class="icon-tile mx-auto">@include('partials.icon', ['i' => $type->icon()])</span>
            <div>
                <p class="font-display font-bold text-xl">Пока нечего сравнивать</p>
                <p class="muted mt-1">Нажмите @include('partials.icon', ['i' => 'scale', 'c' => 'w-4 h-4 inline -mt-0.5']) в каталоге — {{ $type->forms()[2] }} появятся здесь.</p>
            </div>
            <a href="{{ $type->catalogUrl() }}" class="btn btn-primary">В каталог: {{ mb_strtolower($type->label()) }}</a>
        </section>
    @else
        <section class="card p-3 sm:p-5 overflow-x-auto cmp-scroll" data-reveal x-data="{ s: false }" @scroll.passive="s = $el.scrollLeft > 4" :class="s && 'is-scrolled'">
            <table class="table cmp-table tabular min-w-[640px]" style="table-layout: fixed">
                <colgroup><col class="w-[128px] sm:w-[200px]">@foreach ($heads as $h)<col>@endforeach @if ($items->count() < $limit)<col class="w-[160px]">@endif</colgroup>
                <thead>
                <tr>
                    <th></th>
                    @foreach ($heads as $h)
                        <th class="!normal-case !tracking-normal align-top !pb-4">
                            <div class="flex items-start justify-between gap-2">
                                <span class="bank-mark !w-11 !h-11 !text-[0.65rem]" style="--c: {{ $h['color'] }}">{{ $h['code'] }}</span>
                                <a href="{{ $type->url($ids->reject(fn ($id) => $id === $h['id'])->all()) }}"
                                   class="cmp-btn !w-8 !h-8" title="Убрать" aria-label="Убрать {{ $h['title'] }}" @click="{{ $store }}.remove({{ $h['id'] }})">@include('partials.icon', ['i' => 'x', 'c' => 'w-4 h-4'])</a>
                            </div>
                            <a href="{{ $h['url'] }}" class="block mt-3 text-[var(--text)] text-base font-semibold leading-snug hover:underline">{{ $h['title'] }}</a>
                            @if ($h['sub'])<span class="block text-sm muted font-medium">{{ $h['sub'] }}</span>@endif
                        </th>
                    @endforeach
                    @if ($items->count() < $limit)
                        <th class="align-top"><a href="{{ $type->catalogUrl() }}" class="plate grid place-items-center gap-2 p-5 text-center !normal-case !tracking-normal hover:bg-[var(--line)] transition">
                            @include('partials.icon', ['i' => 'plus', 'c' => 'w-5 h-5']) <span class="text-sm font-semibold text-[var(--text)]">Добавить: {{ $one }}</span></a></th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    @if (isset($row['group']))
                        <tr class="cmp-group"><th scope="row" colspan="{{ $items->count() + 1 + ($items->count() < $limit ? 1 : 0) }}">{{ $row['group'] }}</th></tr>
                        @continue
                    @endif
                    @php
                        $vals = $items->map(fn ($m) => (string) $row['value']($m));
                        $num = $row['num'] ?? null;
                        $nums = $num ? $items->map(fn ($m) => $num($m)) : collect();
                        $known = $nums->filter(fn ($v) => $v !== null);
                        $best = $known->count() > 1 && $known->unique()->count() > 1 ? (($row['dir'] ?? 'max') === 'min' ? $known->min() : $known->max()) : null;
                        $same = $items->count() > 1 && $vals->unique()->count() === 1;
                        if ($same) { $best = null; } // числа разные, а на экране одинаково (4,68 и 4,71 → «4,7») — не выделяем
                    @endphp
                    <tr @if ($same) x-show="!diff" @endif class="{{ ($row['small'] ?? false) ? 'cmp-sub' : '' }}">
                        <th scope="row">{{ $row['label'] }}</th>
                        @foreach ($items as $k => $m)
                            @php $v = $nums[$k] ?? null; $href = isset($row['href']) ? $row['href']($m) : null; @endphp
                            <td class="{{ $best !== null && $v !== null && (float) $v === (float) $best ? 'win' : '' }} {{ ($row['big'] ?? false) ? 'text-xl font-bold' : '' }}">
                                @if ($href)<a href="{{ $href }}" target="_blank" rel="noopener nofollow" class="hover:underline"><span>{{ $vals[$k] }}</span></a>@else<span>{{ $vals[$k] }}</span>@endif
                            </td>
                        @endforeach
                        @if ($items->count() < $limit)<td></td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
        @if ($type->note())<p class="text-xs faint px-1">{{ $type->note() }}</p>@endif
    @endif
</div>
@endsection
