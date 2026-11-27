{{-- A33 · Список карт (целиком и как частичный HTML для fetch с X-Partial). --}}
<div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 px-1 pb-4">
    <p class="font-semibold shrink-0">
        <span class="font-display text-2xl tabular">{{ $offers->count() }}</span>
        <span class="muted">{{ plural($offers->count(), 'карта', 'карты', 'карт') }}</span>
    </p>
    {{-- A36 · лента сортировок без системного скроллбара --}}
    <x-hscroll label="Сортировка" class="flex-1 min-w-[12rem] hscroll-end">
        @foreach (\App\Services\Cards\CardFilters::SORTS as $key => $label)
            <a href="{{ route('cards.index', $filters->query(['sort' => $key === 'score' ? null : $key])) }}"
               class="pill !py-1.5 !text-sm whitespace-nowrap {{ $filters->sort === $key ? 'is-active' : '' }}"
               @click.prevent="$refs.sort.value = '{{ $key }}'; changed()">{{ $label }}</a>
        @endforeach
    </x-hscroll>
</div>

<div class="grid gap-3">
    @forelse ($offers as $i => $o)
        @include('cards._offer', ['o' => $o, 'first' => $i === 0 && $filters->sort === 'score', 'i' => $i])
    @empty
        <div class="card p-12 text-center space-y-4">
            <span class="icon-tile mx-auto">@include('partials.icon', ['i' => 'card'])</span>
            <div>
                @if ($filters->activeCount())
                    <p class="font-display font-bold text-xl">Ничего не нашлось</p>
                    <p class="muted mt-1">Попробуйте убрать часть фильтров — например, систему или банк.</p>
                @else
                    <p class="font-display font-bold text-xl">Карты скоро появятся</p>
                    <p class="muted mt-1">Мы оцифровываем карты банков {{ country()?->genitive() }}. Пока можно посмотреть <a class="underline underline-offset-4" href="{{ route('payment-systems.index') }}">платёжные системы и уровни карт</a>.</p>
                @endif
            </div>
            @if ($filters->activeCount())<a href="{{ route('cards.index') }}" class="btn btn-primary">Сбросить фильтры</a>@endif
        </div>
    @endforelse
</div>

@if ($offers->isNotEmpty())
    <p class="text-xs faint px-1 pt-4 max-w-2xl">«Оценка Banq» 1–5 считается из опубликованных условий относительно лучших карт рынка: для дебетовых — кэшбэк, стоимость, процент на остаток и возможности; для кредитных — льготный период, ставка, стоимость и кэшбэк. Точные условия — на сайте банка.</p>
@endif
