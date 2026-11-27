{{-- A33 · Сравнение до 4 карт: колонки — карты с пластиком, строки — условия; лучшее значение в строке выделено.
     Список — $store.cardCompare (localStorage), URL — /cards/compare?ids=1,2,3. --}}
@extends('layouts.app', ['title' => 'Сравнение карт'])

@section('content')
@php
    $ids = $offers->map(fn ($o) => $o->card->id);
    $storeItems = $offers->map(fn ($o) => ['id' => $o->card->id, 'code' => $o->card->bank->mark(), 'color' => $o->card->color ?? $o->card->bank->brand_color ?? '#8b5cf6', 'name' => $o->card->name])->values();
    $sign = fn ($c) => currency_symbol($c->bank->country?->currency);
    $features = config('banq.cards.features');
    // [подпись, значение, число для «лучшего», 'max'|'min']
    $rows = array_values(array_filter([
        ['Оценка Banq', fn ($o) => score_format($o->score), fn ($o) => $o->score, 'max'],
        ['Вид', fn ($o) => $o->card->kind->label(), null, null],
        ['Платёжные системы', fn ($o) => $o->card->networks()->map(fn ($n) => $n['tier']?->name ?? $n['system']->name)->implode(', ') ?: '—', fn ($o) => $o->card->topLevel() ?: null, 'max'],
        ['Обслуживание в год', fn ($o) => $o->card->yearlyFee() === null ? '—' : ($o->card->yearlyFee() == 0 ? 'бесплатно' : money_format($o->card->yearlyFee(), $sign($o->card))), fn ($o) => $o->card->yearlyFee(), 'min'],
        ['Выпуск', fn ($o) => $o->card->issue_fee === null ? '—' : ((float) $o->card->issue_fee == 0 ? 'бесплатно' : money_format((float) $o->card->issue_fee, $sign($o->card))), fn ($o) => $o->card->issue_fee === null ? null : (float) $o->card->issue_fee, 'min'],
        ['Кэшбэк до', fn ($o) => rate_format($o->card->cashback_max), fn ($o) => $o->card->cashback_max === null ? null : (float) $o->card->cashback_max, 'max'],
        ['Кэшбэк на всё', fn ($o) => rate_format($o->card->cashback_base), fn ($o) => $o->card->cashback_base === null ? null : (float) $o->card->cashback_base, 'max'],
        ['Процент на остаток', fn ($o) => rate_format($o->card->balance_rate), fn ($o) => $o->card->balance_rate === null ? null : (float) $o->card->balance_rate, 'max'],
        $hasCredit ? ['Льготный период', fn ($o) => $o->card->grace_days ? $o->card->grace_days.' дн.' : '—', fn ($o) => $o->card->grace_days, 'max'] : null,
        $hasCredit ? ['Ставка', fn ($o) => $o->card->credit_rate === null ? '—' : 'от '.rate_format($o->card->credit_rate), fn ($o) => $o->card->credit_rate === null ? null : (float) $o->card->credit_rate, 'min'] : null,
        $hasCredit ? ['ГЭСВ', fn ($o) => $o->card->credit_rate_effective === null ? '—' : 'от '.rate_format($o->card->credit_rate_effective), fn ($o) => $o->card->credit_rate_effective === null ? null : (float) $o->card->credit_rate_effective, 'min'] : null,
        $hasCredit ? ['Лимит до', fn ($o) => $o->card->credit_limit_max === null ? '—' : money_format((float) $o->card->credit_limit_max, $sign($o->card)), fn ($o) => $o->card->credit_limit_max === null ? null : (float) $o->card->credit_limit_max, 'max'] : null,
        ['Бесплатное снятие', fn ($o) => $o->card->free_cash_limit === null ? ($o->card->cash_fee ?: '—') : 'до '.money_format((float) $o->card->free_cash_limit, $sign($o->card)), fn ($o) => $o->card->free_cash_limit === null ? null : (float) $o->card->free_cash_limit, 'max'],
        ['Валюты', fn ($o) => implode(', ', $o->card->currencies ?? []) ?: '—', fn ($o) => count($o->card->currencies ?? []) ?: null, 'max'],
        ['Оформление онлайн', fn ($o) => $o->card->online_opening ? '✓' : '—', fn ($o) => $o->card->online_opening ? 1 : 0, 'max'],
        ['Доставка', fn ($o) => $o->card->delivery ? '✓' : '—', fn ($o) => $o->card->delivery ? 1 : 0, 'max'],
        ['Возможности', fn ($o) => collect($o->card->features ?? [])->map(fn ($f) => $features[$f] ?? $f)->implode(', ') ?: '—', fn ($o) => count($o->card->features ?? []) ?: null, 'max'],
        ['Банк: объективный рейтинг', fn ($o) => score_format($o->bankScore?->score(\App\Enums\RatingKind::Objective)), fn ($o) => $o->bankScore?->score(\App\Enums\RatingKind::Objective), 'max'],
        ['Банк: народный рейтинг', fn ($o) => score_format($o->bankScore?->score(\App\Enums\RatingKind::People)), fn ($o) => $o->bankScore?->score(\App\Enums\RatingKind::People), 'max'],
    ]));
@endphp
<div class="space-y-5" x-data x-init="$store.cardCompare.set(@js($storeItems))">
    <section class="card p-7" data-reveal>
        <nav class="text-sm muted flex items-center gap-2 mb-5"><a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span><a href="{{ route('cards.index') }}" class="hover:text-[var(--text)]">Карты</a><span class="faint">/</span><span>Сравнение</span></nav>
        <h1 class="font-display font-bold text-4xl sm:text-[2.75rem] leading-[1.05]">Сравнение <span class="text-grad">карт</span></h1>
        <p class="muted mt-2">До {{ $limit }} карт рядом — дебетовые и кредитные вместе. Лучшее значение в каждой строке выделено.</p>
    </section>

    @if ($offers->isEmpty())
        <section class="card p-12 text-center space-y-4" data-reveal>
            <span class="icon-tile mx-auto">@include('partials.icon', ['i' => 'scale'])</span>
            <div>
                <p class="font-display font-bold text-xl">Пока нечего сравнивать</p>
                <p class="muted mt-1">Нажмите @include('partials.icon', ['i' => 'scale', 'c' => 'w-4 h-4 inline -mt-0.5']) у карт в каталоге — они появятся здесь.</p>
            </div>
            <a href="{{ route('cards.index') }}" class="btn btn-primary">В каталог карт</a>
        </section>
    @else
        <section class="card p-3 sm:p-5 overflow-x-auto" data-reveal>
            <table class="table cmp-table tabular min-w-[680px]" style="table-layout: fixed">
                <colgroup><col class="w-[190px]">@foreach ($offers as $o)<col>@endforeach @if ($offers->count() < $limit)<col class="w-[170px]">@endif</colgroup>
                <thead>
                <tr>
                    <th></th>
                    @foreach ($offers as $o)
                        @php $c = $o->card; @endphp
                        <th class="!normal-case !tracking-normal align-top !pb-4">
                            <div class="relative">
                                <a href="{{ route('cards.show', $c) }}" class="block max-w-[220px]">@include('partials.plastic', ['card' => $c])</a>
                                <a href="{{ route('cards.compare', ['ids' => $ids->reject(fn ($id) => $id === $c->id)->join(',')]) }}"
                                   class="cmp-btn !w-8 !h-8 absolute -top-2 -right-1 shadow" title="Убрать" aria-label="Убрать {{ $c->name }}" @click="$store.cardCompare.remove({{ $c->id }})">@include('partials.icon', ['i' => 'x', 'c' => 'w-4 h-4'])</a>
                            </div>
                            <a href="{{ route('cards.show', $c) }}" class="block mt-3 text-[var(--text)] text-base font-semibold leading-snug hover:underline">{{ $c->name }}</a>
                            <span class="block text-sm muted font-medium">{{ $c->bank->displayName() }}</span>
                        </th>
                    @endforeach
                    @if ($offers->count() < $limit)
                        <th class="align-top"><a href="{{ route('cards.index') }}" class="plate grid place-items-center gap-2 p-5 aspect-[1.586] text-center !normal-case !tracking-normal hover:bg-[var(--line)] transition">
                            @include('partials.icon', ['i' => 'plus', 'c' => 'w-5 h-5']) <span class="text-sm font-semibold text-[var(--text)]">Добавить карту</span></a></th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $r => [$label, $value, $num, $dir])
                    @php
                        $nums = $num ? $offers->map(fn ($o) => $num($o))->filter(fn ($v) => $v !== null) : collect();
                        $best = $nums->count() > 1 && $nums->unique()->count() > 1 ? ($dir === 'min' ? $nums->min() : $nums->max()) : null;
                    @endphp
                    <tr>
                        <th scope="row">{{ $label }}</th>
                        @foreach ($offers as $o)
                            @php $v = $num ? $num($o) : null; @endphp
                            <td class="{{ $best !== null && $v !== null && (float) $v === (float) $best ? 'win' : '' }} {{ $r === 0 ? 'text-2xl font-bold' : '' }}"><span>{{ $value($o) }}</span></td>
                        @endforeach
                        @if ($offers->count() < $limit)<td></td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
        <p class="text-xs faint px-1">Условия — из опубликованных тарифов банков. «Оценка Banq» и рейтинги банков пересчитываются при обновлении данных.</p>
    @endif
</div>
@endsection
