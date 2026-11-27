{{-- A40 · Админка → Бегущая строка: что показывать в верхней ленте витрины и в каком порядке. --}}
@extends('layouts.admin', ['title' => 'Бегущая строка'])

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="font-display font-bold text-3xl">Бегущая строка</h1>
        <p class="muted mt-1">Чёрная лента над шапкой сайта. Выберите типы контента — они пойдут друг за другом в этом порядке.</p>
    </div>
</div>

{{-- Предпросмотр: как лента выглядит сейчас (по сохранённым настройкам, для страны по умолчанию) --}}
<div class="topline rounded-2xl overflow-hidden mt-6">
    <div class="px-4 h-11 flex items-center gap-4 text-[13px]">
        <span class="mono text-[11px] uppercase tracking-widest text-white/40 shrink-0">Сейчас</span>
        @include('partials.ticker', ['ticker' => $preview, 'tickerDuration' => $previewDuration])
    </div>
</div>

<form method="POST" action="{{ route('admin.ticker.update') }}" class="grid xl:grid-cols-[1fr_380px] gap-4 mt-4"
      x-data="{ all: @js($sources), order: @js(array_values(array_unique([...$selected, ...array_keys($sources)]))), on: @js($selected), speed: {{ $speed }},
                move(k, d) { const i = this.order.indexOf(k), j = i + d; if (j < 0 || j >= this.order.length) return; [this.order[i], this.order[j]] = [this.order[j], this.order[i]]; },
                toggle(k) { this.on = this.on.includes(k) ? this.on.filter(x => x !== k) : [...this.on, k]; } }">
    @csrf @method('PUT')
    <div class="space-y-4">
        <section class="glass p-6 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-display font-bold text-lg">Типы контента</h2>
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="enabled" value="1" @checked($enabled) class="w-4 h-4 accent-[var(--ink)]"> Лента включена</label>
            </div>
            <ol class="space-y-2">
                <template x-for="(k, i) in order" :key="k">
                    <li class="plate p-3 flex items-center gap-3 transition" :class="on.includes(k) ? '' : 'opacity-60'">
                        <label class="flex items-start gap-3 flex-1 min-w-0 cursor-pointer">
                            <input type="checkbox" class="w-4 h-4 mt-1 accent-[var(--ink)]" :checked="on.includes(k)" @change="toggle(k)">
                            <span class="min-w-0">
                                <b class="block" x-text="all[k].label"></b>
                                <span class="block text-xs muted" x-text="all[k].hint"></span>
                            </span>
                        </label>
                        <span class="chip tabular" x-show="on.includes(k)" x-text="on.filter(x => order.indexOf(x) <= i).length"></span>
                        <button type="button" class="btn btn-ghost !p-2" @click="move(k, -1)" :disabled="i === 0" aria-label="Выше">↑</button>
                        <button type="button" class="btn btn-ghost !p-2" @click="move(k, 1)" :disabled="i === order.length - 1" aria-label="Ниже">↓</button>
                    </li>
                </template>
            </ol>
            <template x-for="k in order.filter(x => on.includes(x))" :key="'h' + k"><input type="hidden" name="sources[]" :value="k"></template>
            <p class="text-xs faint">Пустой тип (например, курсов ещё нет) пропускается. Если пусто всё — в ленте будет «Скоро здесь побегут ставки банков».</p>
        </section>

        <section class="glass p-6 grid gap-4">
            <h2 class="font-display font-bold text-lg">Свой текст и скорость</h2>
            <label class="field"><span>Свой текст — каждая строка отдельным сообщением (тип «Свой текст»)</span>
                <textarea name="text" rows="4" class="input" placeholder="Новый рейтинг банков уже на сайте&#10;Ставки обновлены 27 ноября">{{ old('text', $text) }}</textarea></label>
            <label class="field"><span>Скорость: <b x-text="speed"></b> из 10</span>
                <input type="range" name="speed" min="1" max="10" x-model.number="speed" class="range"></label>
        </section>

        @if ($errors->any())<ul class="error space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
        <button class="btn btn-primary">Сохранить</button>
    </div>

    <aside class="glass p-6 space-y-3 self-start text-sm">
        <h2 class="font-display font-bold text-lg">Как это работает</h2>
        <p class="muted"><b class="text-[var(--text)]">Курсы валют</b> — официальный курс центробанка страны посетителя (Казахстан — НБРК). Валюты выбираются в разделе <a href="{{ route('admin.currency.index') }}" class="underline">Курсы валют</a>.</p>
        <p class="muted"><b class="text-[var(--text)]">Ставки вкладов</b> — лучшая ставка каждого банка по типу вклада.</p>
        <p class="muted">Наведите курсор на ленту — она остановится. Новые типы (новости блога, карты) добавляются в коде одной строкой в <code class="mono text-xs">config/banq.php → ticker.sources</code>.</p>
    </aside>
</form>
@endsection
