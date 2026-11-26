{{-- A14 · Сравнение до 4 вкладов: колонки — вклады, строки — параметры; лучшее в строке подсвечено.
     Список хранится в $store.compare (localStorage), URL — /deposits/compare?ids=1,2,3 (можно отправить ссылкой). --}}
@extends('layouts.app', ['title' => 'Сравнение вкладов'])

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₸';
    $ids = $columns->map(fn ($col) => $col['product']->id);
    $storeItems = $columns->map(fn ($col) => ['id' => $col['product']->id, 'code' => \Illuminate\Support\Str::limit($col['product']->bank->code, 4, ''), 'color' => $col['product']->bank->brand_color ?? '#8b5cf6', 'name' => $col['product']->bank->displayName()])->values();
@endphp
<div class="space-y-5" x-data="depositCalc({ amount: {{ (int) ($filters->amount ?? 1_000_000) }}, months: {{ $filters->term ?? 12 }} })"
     x-init="$store.compare.set(@js($storeItems))">
    <section class="card p-7 flex flex-wrap items-end justify-between gap-6" data-reveal>
        <div>
            <nav class="text-sm muted flex items-center gap-2 mb-5"><a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span><a href="{{ route('deposits.index') }}" class="hover:text-[var(--text)]">Вклады</a><span class="faint">/</span><span>Сравнение</span></nav>
            <h1 class="font-display font-bold text-4xl sm:text-[2.75rem] leading-[1.05]">Сравнение <span class="text-grad">вкладов</span></h1>
            <p class="muted mt-2">До {{ $limit }} вкладов рядом. Лучшее значение в каждой строке выделено.</p>
        </div>
        @if ($columns->isNotEmpty())
            <div class="plate p-2 flex flex-wrap items-center gap-2 text-[15px]">
                <span class="px-2 muted">Сумма</span>
                <input type="text" inputmode="numeric" class="input !w-40 !py-2 !bg-[var(--card)] font-semibold tabular" :value="money(amount)" @input="setAmount($event)" aria-label="Сумма">
                <span class="muted">на</span>
                <select x-model.number="months" class="input !w-auto !py-2 !bg-[var(--card)] font-semibold" aria-label="Срок">
                    @foreach (config('banq.terms') as $m)<option value="{{ $m }}">{{ $m }} мес</option>@endforeach
                </select>
            </div>
        @endif
    </section>

    @if ($columns->isEmpty())
        <section class="card p-12 text-center space-y-4" data-reveal>
            <span class="icon-tile mx-auto">@include('partials.icon', ['i' => 'scale'])</span>
            <div>
                <p class="font-display font-bold text-xl">Пока нечего сравнивать</p>
                <p class="muted mt-1">Нажмите @include('partials.icon', ['i' => 'scale', 'c' => 'w-4 h-4 inline -mt-0.5']) у вкладов в каталоге — они появятся здесь.</p>
            </div>
            <a href="{{ route('deposits.index') }}" class="btn btn-primary">В каталог вкладов</a>
        </section>
    @else
        <section class="card p-3 sm:p-5 overflow-x-auto" data-reveal>
            <table class="table cmp-table tabular min-w-[640px]">
                <colgroup><col class="w-[180px]">@foreach ($columns as $col)<col>@endforeach @if ($columns->count() < $limit)<col class="w-[160px]">@endif</colgroup>
                <thead>
                <tr>
                    <th></th>
                    @foreach ($columns as $col)
                        @php $p = $col['product']; @endphp
                        <th class="!normal-case !tracking-normal align-top !pb-4">
                            <div class="flex items-start justify-between gap-2">
                                <span class="bank-mark !w-11 !h-11 !text-[0.65rem]" style="--c: {{ $p->bank->brand_color ?? 'var(--brand-1)' }}">{{ \Illuminate\Support\Str::limit($p->bank->code, 4, '') }}</span>
                                <a href="{{ route('deposits.compare', ['ids' => $ids->reject(fn ($id) => $id === $p->id)->join(',')] + $filters->query()) }}"
                                   class="cmp-btn !w-8 !h-8" title="Убрать" aria-label="Убрать {{ $p->name }}" @click="$store.compare.remove({{ $p->id }})">@include('partials.icon', ['i' => 'x', 'c' => 'w-4 h-4'])</a>
                            </div>
                            <a href="{{ route('deposits.show', $p) }}" class="block mt-3 text-[var(--text)] text-base font-semibold leading-snug hover:underline">{{ $p->name }}</a>
                            <span class="block text-sm muted font-medium">{{ $p->bank->displayName() }}</span>
                        </th>
                    @endforeach
                    @if ($columns->count() < $limit)
                        <th class="align-top"><a href="{{ route('deposits.index') }}" class="plate grid place-items-center gap-2 p-5 text-center !normal-case !tracking-normal hover:bg-[var(--line)] transition">
                            @include('partials.icon', ['i' => 'plus', 'c' => 'w-5 h-5']) <span class="text-sm font-semibold text-[var(--text)]">Добавить вклад</span></a></th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @php
                    $rows = [
                        ['Ставка', fn ($col) => rate_format($col['rate']?->rate), fn ($col) => $col['rate'] && (float) $col['rate']->rate === (float) $bestRate],
                        ['ГЭСВ', fn ($col) => rate_format($col['rate']?->effective_rate), fn ($col) => $col['rate']?->effective_rate !== null && (float) $col['rate']->effective_rate === (float) $bestEffective],
                        ['Тип', fn ($col) => $col['product']->type->label(), null],
                        ['Пополнение', fn ($col) => $col['product']->can_replenish ? '✓' : '—', fn ($col) => $col['product']->can_replenish],
                        ['Частичное снятие', fn ($col) => $col['product']->can_withdraw ? '✓' : '—', fn ($col) => $col['product']->can_withdraw],
                        ['Открытие онлайн', fn ($col) => $col['product']->online_opening ? '✓' : '—', fn ($col) => $col['product']->online_opening],
                        ['Мин. сумма', fn ($col) => $col['product']->min_amount ? $money($col['product']->min_amount) : 'не указана', null],
                        ['Срок ставки', fn ($col) => $col['rate']?->term_months ? $col['rate']->term_months.' мес' : 'любой', null],
                        // A21 · гарантия — по системе страны банка
                        ['Гарантия вклада', function ($col) {
                            $c = $col['product']->bank->country;
                            $limit = $col['product']->bank->deposit_insured ? $c?->guaranteeLimit($col['product']->type->value, $col['rate']?->currency) : null;

                            return $limit ? ($c->guaranteeName() ? $c->guaranteeName().', ' : '').'до '.number_format($limit / 1e6, 0, ',', ' ').' млн '.$c->symbol() : 'нет';
                        }, null],
                    ];
                    $incomes = $columns->map(fn ($col) => $col['rate'] ? ['rate' => (float) $col['rate']->rate, 'eff' => $col['rate']->effective_rate === null ? null : (float) $col['rate']->effective_rate] : null)->values();
                @endphp
                {{-- Доход: считается в браузере под выбранные сумму и срок --}}
                <tr x-data="{ incomes: @js($incomes) }">
                    <th scope="row">Доход за <span x-text="months"></span> мес</th>
                    @foreach ($columns as $k => $col)
                        <td :class="incomes[{{ $k }}] && income(incomes[{{ $k }}].rate, incomes[{{ $k }}].eff) === Math.max(...incomes.filter(Boolean).map(i => income(i.rate, i.eff))) && incomes.filter(Boolean).length > 1 && 'win'">
                            @if ($col['rate'])<span class="text-xl font-bold" x-text="'+' + money(income(incomes[{{ $k }}].rate, incomes[{{ $k }}].eff))"></span>@else<span class="faint">нет ставки</span>@endif
                        </td>
                    @endforeach
                    @if ($columns->count() < $limit)<td></td>@endif
                </tr>
                @foreach ($rows as [$label, $value, $win])
                    <tr>
                        <th scope="row">{{ $label }}</th>
                        @foreach ($columns as $col)
                            @php $isWin = $win && $columns->count() > 1 && $win($col) && ! $columns->every(fn ($x) => $win($x)); @endphp
                            <td class="{{ $isWin ? 'win' : '' }} {{ $loop->parent->index < 2 ? 'text-lg font-bold' : '' }}"><span>{{ $value($col) }}</span></td>
                        @endforeach
                        @if ($columns->count() < $limit)<td></td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
        <p class="text-xs faint px-1">Ставки — лучшие действующие под выбранные сумму и срок. Доход оценивается по ГЭСВ или по номинальной ставке с ежемесячной капитализацией.</p>
    @endif
</div>
@endsection
