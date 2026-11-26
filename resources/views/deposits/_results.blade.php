{{-- A14 · Список вкладов (отдаётся и целиком, и как частичный HTML на fetch с заголовком X-Partial). --}}
<div class="flex flex-wrap items-center justify-between gap-3 px-1 pb-4">
    <p class="font-semibold">
        <span class="font-display text-2xl tabular">{{ $offers->count() }}</span>
        <span class="muted">{{ plural($offers->count(), 'вклад', 'вклада', 'вкладов') }}@if ($filters->amount) · доход за {{ $filters->term ?? 12 }} мес. с {{ number_format($filters->amount, 0, ',', ' ') }} ₸@endif</span>
    </p>
    <div class="flex gap-1.5 overflow-x-auto -mx-1 px-1">
        @foreach (\App\Services\Deposits\DepositFilters::SORTS as $key => $label)
            <a href="{{ route('deposits.index', $filters->query(['sort' => $key === 'rate' ? null : $key])) }}"
               class="pill !py-1.5 !text-sm whitespace-nowrap {{ $filters->sort === $key ? 'is-active' : '' }}"
               @click.prevent="$refs.sort.value = '{{ $key }}'; changed()">{{ $label }}</a>
        @endforeach
    </div>
</div>

<div class="grid gap-3">
    @forelse ($offers as $i => $o)
        @include('deposits._offer', ['o' => $o, 'first' => $i === 0 && $filters->sort !== 'bank'])
    @empty
        <div class="card p-12 text-center space-y-4">
            <span class="icon-tile mx-auto">@include('partials.icon', ['i' => 'search'])</span>
            <div>
                <p class="font-display font-bold text-xl">Ничего не нашлось</p>
                <p class="muted mt-1">Попробуйте убрать часть фильтров — например, срок или банк.</p>
            </div>
            <a href="{{ route('deposits.index') }}" class="btn btn-primary">Сбросить фильтры</a>
        </div>
    @endforelse
</div>

@if ($offers->isNotEmpty())
    <p class="text-xs faint px-1 pt-4 max-w-2xl">Доход — оценка по ГЭСВ (если банк её публикует) или по номинальной ставке с ежемесячной капитализацией. Точные условия смотрите на сайте банка.</p>
@endif
