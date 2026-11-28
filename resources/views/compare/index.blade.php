{{-- A53 · Раздел «Сравнение»: все виды продуктов. На плитке — что уже выбрано (списки в браузере), кнопка к таблице или в каталог.
     Сравниваются продукты одного вида: вклад с вкладом, карта с картой, банк с банком. --}}
@extends('layouts.app', ['title' => 'Сравнение', 'description' => 'Сравнение вкладов, карт, пакетов услуг, банков и платёжных систем: до четырёх рядом, лучшее значение в каждой строке выделено.'])

@section('content')
<div class="space-y-5" x-data>
    <section class="card p-7" data-reveal>
        <nav class="text-sm muted flex items-center gap-2 mb-5"><a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span><span>Сравнение</span></nav>
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <h1 class="font-display font-bold text-4xl sm:text-[2.75rem] leading-[1.05]">Сравнение <span class="text-grad">всего</span></h1>
                <p class="muted mt-2 max-w-2xl">Вклады, карты, пакеты услуг, банки и платёжные системы — до четырёх рядом. Отмечайте @include('partials.icon', ['i' => 'scale', 'c' => 'w-4 h-4 inline -mt-0.5']) в каталогах: у каждого вида свой список, лучшее значение в каждой строке выделено.</p>
            </div>
            <p class="plate px-4 py-3 text-sm font-semibold tabular" x-show="$store.compareAll.count" x-cloak>Выбрано: <span x-text="$store.compareAll.count"></span></p>
        </div>
        <div class="mt-6">@include('compare._tabs', ['active' => null, 'types' => $types])</div>
    </section>

    <section class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-5">
        @foreach ($types as $i => $t)
            @php $s = '$store.'.$t->store(); @endphp
            <article class="card cmp-tile p-6 flex flex-col gap-5" data-reveal style="--d: {{ 0.04 * $loop->index }}s" :class="{{ $s }}.items.length && 'is-on'">
                <div class="flex items-start justify-between gap-3">
                    <span class="icon-tile">@include('partials.icon', ['i' => $t->icon()])</span>
                    <span class="chip tabular" x-text="{{ $s }}.items.length + ' из ' + {{ $s }}.limit">0 из {{ $t->limit() }}</span>
                </div>
                <div>
                    <h2 class="font-display font-bold text-2xl">{{ $t->label() }}</h2>
                    <p class="text-sm muted mt-1.5">{{ $t->hint() }}</p>
                </div>
                <div class="mt-auto space-y-4">
                    <div class="flex items-center min-h-10" x-show="{{ $s }}.items.length" x-cloak>
                        <template x-for="it in {{ $s }}.items" :key="it.id">
                            <span class="bank-mark !w-10 !h-10 !text-[0.6rem] -ml-2 first:ml-0 ring-2 ring-[var(--card)]" :style="'--c:' + it.color" x-text="it.code" :title="it.name"></span>
                        </template>
                        <button type="button" class="ml-auto text-sm muted hover:text-[var(--text)]" @click="{{ $s }}.clear()">очистить</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="{{ $s }}.url()" class="btn btn-primary" x-show="{{ $s }}.items.length > 1" x-cloak>Сравнить @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</a>
                        <a href="{{ $t->catalogUrl() }}" class="btn" :class="{{ $s }}.items.length > 1 ? 'btn-ghost' : 'btn-outline'">
                            <span x-text="{{ $s }}.items.length ? 'Добавить ещё' : 'Выбрать в каталоге'">Выбрать в каталоге</span>
                        </a>
                    </div>
                </div>
            </article>
        @endforeach
        @foreach ($soon as $key => [$label, $icon])
            <article class="card cmp-tile is-soon p-6 flex flex-col gap-5" data-reveal>
                <div class="flex items-start justify-between gap-3"><span class="icon-tile">@include('partials.icon', ['i' => $icon])</span><span class="chip">скоро</span></div>
                <div>
                    <h2 class="font-display font-bold text-2xl">{{ $label }}</h2>
                    <p class="text-sm muted mt-1.5">Ставки, ГЭСВ, переплата, сроки и требования к заёмщику — появятся вместе с каталогом кредитов.</p>
                </div>
            </article>
        @endforeach
    </section>
</div>
@endsection
