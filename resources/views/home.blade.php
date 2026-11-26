{{-- A13 · Главная v2: бенто-сетка по референсам (vibecoding / shadcn / bento) + правая колонка. --}}
@extends('layouts.app')

@section('content')
@php
    $leader = $best;
    $mark = fn ($bank) => \Illuminate\Support\Str::limit($bank->code, 4, '');
    $maxRate = (float) ($best?->rate ?? 20);
    $minRate = (float) ($top->min('rate') ?? 0) * 0.92; // шкала полосок: от чуть ниже минимума топа до максимума
@endphp

<div class="grid xl:grid-cols-[minmax(0,1fr)_340px] gap-5">
    {{-- ===== Центральная колонка ===== --}}
    <div class="space-y-5 min-w-0">

        {{-- Бенто: герой + лучшая ставка --}}
        <section id="deposits" class="grid md:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)] gap-5 scroll-mt-32">
            <div class="card p-7 md:p-9 flex flex-col justify-between gap-10" data-reveal>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="chip"><span class="dot"></span>{{ $updatedAt ? 'обновлено '.\Illuminate\Support\Carbon::parse($updatedAt)->translatedFormat('j F') : 'скоро первые данные' }}</span>
                    <span class="chip">{{ $banksCount }} банков</span>
                    <span class="chip">{{ $ratesCount }} ставок</span>
                </div>
                <h1 class="font-display font-bold text-[2.2rem] sm:text-5xl xl:text-[3.25rem] leading-[1.04]">
                    Все вклады Казахстана.<br><span class="muted">Одна таблица — <span class="text-grad">лучшая ставка.</span></span>
                </h1>
                {{-- A14 · Быстрый подбор → каталог вкладов --}}
                <form action="{{ route('deposits.index') }}" method="get" class="plate p-2 flex flex-wrap items-center gap-2 text-[15px]">
                    <span class="px-3 muted">Хочу вклад</span>
                    <select name="type[]" class="input !w-auto !py-2 !bg-[var(--card)] font-semibold" aria-label="Тип вклада"><option value="term">с пополнением</option><option value="demand">со снятием</option><option value="savings">без операций</option><option value="">любой</option></select>
                    <span class="muted">на</span>
                    <select name="term" class="input !w-auto !py-2 !bg-[var(--card)] font-semibold" aria-label="Срок"><option value="12">12 месяцев</option><option value="6">6 месяцев</option><option value="3">3 месяца</option><option value="24">24 месяца</option><option value="">любой срок</option></select>
                    <button class="btn btn-primary ml-auto">Подобрать @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</button>
                </form>
            </div>

            <div class="card card-ink p-7 md:p-9 flex flex-col justify-between gap-6 overflow-hidden" data-reveal style="--d:.06s">
                <div class="flex items-start justify-between gap-4">
                    <span class="text-sm font-semibold opacity-70">Лучшая ставка<br>сегодня</span>
                    @if ($leader)<span class="bank-mark" style="--c: {{ $leader->product->bank->brand_color ?? 'var(--brand-1)' }}">{{ $mark($leader->product->bank) }}</span>@endif
                </div>
                <p class="font-display font-semibold tabular leading-none text-[5.5rem] sm:text-[7rem]">
                    @if ($leader)<span x-data x-count="{{ number_format((float) $leader->rate, 1, '.', '') }}">{{ number_format((float) $leader->rate, 1, ',', '') }}</span><span class="text-[0.4em] align-top opacity-60">%</span>@else—@endif
                </p>
                <div class="text-sm">
                    <p class="font-semibold">{{ $leader?->product->bank->displayName() ?? 'Загрузите ставки' }}</p>
                    <p class="opacity-60">@if ($leader){{ $leader->product->type->label() }} · ГЭСВ {{ rate_format($leader->effective_rate) }}@else в админке → Импорт Excel @endif</p>
                </div>
            </div>
        </section>

        {{-- Лидеры по типам --}}
        <section class="grid sm:grid-cols-3 gap-5">
            @foreach ($leaders as $i => $l)
                <article class="card lift p-6 space-y-5" data-reveal style="--d: {{ 0.04 * $i }}s">
                    <div>
                        <p class="font-semibold">{{ $l['type']->label() }}</p>
                        <p class="text-xs muted mt-0.5">{{ $l['type']->hint() }}</p>
                    </div>
                    <p class="font-display font-semibold text-5xl tabular">
                        @if ($l['rate'])<span x-data x-count="{{ number_format((float) $l['rate']->rate, 2, '.', '') }}">{{ number_format((float) $l['rate']->rate, 2, ',', '') }}</span><span class="text-2xl muted">%</span>@else<span class="faint">—</span>@endif
                    </p>
                    <div class="flex items-center gap-3 pt-4 border-t border-[var(--line)]">
                        @if ($l['rate'])
                            <span class="bank-mark !w-8 !h-8 !text-[0.55rem]" style="--c: {{ $l['rate']->product->bank->brand_color ?? 'var(--brand-1)' }}">{{ $mark($l['rate']->product->bank) }}</span>
                            <span class="text-sm font-semibold">{{ $l['rate']->product->bank->displayName() }}</span>
                            <span class="text-xs muted ml-auto">из {{ $l['count'] }}</span>
                        @else
                            <span class="text-sm faint">Данных пока нет</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>

        {{-- Топ ставок --}}
        <section id="top" class="card p-3 sm:p-5 scroll-mt-32" data-reveal>
            <div class="flex items-center justify-between px-3 pt-2 pb-4">
                <h2 class="font-display font-bold text-2xl flex items-center gap-3">
                    <span class="icon-tile !w-9 !h-9" style="background: var(--ink); color: var(--ink-text)">@include('partials.icon', ['i' => 'chart', 'c' => 'w-[18px] h-[18px]'])</span>
                    Топ ставок
                </h2>
                <a href="{{ route('deposits.index') }}" class="btn btn-ghost !py-1.5 text-sm shrink-0">Все вклады @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th class="w-8">#</th><th>Банк</th><th class="hidden md:table-cell">Тип</th><th class="hidden sm:table-cell w-[22%]"></th><th class="!text-right">Ставка</th><th class="!text-right hidden sm:table-cell">ГЭСВ</th></tr></thead>
                    <tbody>
                    @forelse ($top as $i => $r)
                        <tr class="cursor-pointer" onclick="location.href='{{ route('deposits.show', $r->product) }}'">
                            <td class="muted tabular">{{ $i + 1 }}</td>
                            <td><div class="flex items-center gap-3">
                                <span class="bank-mark" style="--c: {{ $r->product->bank->brand_color ?? 'var(--brand-1)' }}">{{ $mark($r->product->bank) }}</span>
                                <div><a href="{{ route('deposits.show', $r->product) }}" class="font-semibold leading-tight block">{{ $r->product->bank->displayName() }}</a><p class="text-xs muted md:hidden">{{ $r->product->type->label() }}</p></div>
                            </div></td>
                            <td class="muted text-sm hidden md:table-cell">{{ $r->product->type->label() }}</td>
                            <td class="hidden sm:table-cell"><div class="meter"><i style="width: {{ max(4, round(((float) $r->rate - $minRate) / max(0.01, $maxRate - $minRate) * 100)) }}%"></i></div></td>
                            <td class="text-right tabular font-bold text-lg">{{ rate_format($r->rate) }}</td>
                            <td class="text-right tabular muted text-sm hidden sm:table-cell">{{ rate_format($r->effective_rate) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-12 muted">Ставок пока нет. Загрузите Excel в админке → Импорт.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Бренд-карточки: рейтинги и будущие разделы --}}
        <section id="ratings" class="grid md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] gap-5 scroll-mt-32">
            <article class="card card-brand p-8 min-h-[260px] flex flex-col justify-between" data-reveal>
                <div class="orb-soft"></div>
                <span class="chip !bg-white/15 !text-white w-fit">@include('partials.icon', ['i' => 'star', 'c' => 'w-3.5 h-3.5']) скоро</span>
                <div class="relative">
                    <h3 class="font-display font-bold text-3xl sm:text-4xl leading-tight">Народный рейтинг<br>банков</h3>
                    <p class="mt-3 text-white/75 max-w-md">Оценки и отзывы клиентов, авторский рейтинг TSOY.IN и объективный расчёт — рядом, в одном месте.</p>
                </div>
            </article>
            <div id="soon" class="grid grid-cols-2 gap-5 scroll-mt-32">
                @foreach ([['card', 'Карты', 'Кешбэк и лимиты'], ['loan', 'Кредиты', 'Ставки и переплата'], ['globe', 'Платёжные системы', 'Visa, Mastercard, UnionPay'], ['news', 'Новости', 'Рынок и решения НБРК']] as $i => [$icon, $name, $text])
                    <article class="card lift p-5 flex flex-col justify-between gap-6 {{ $i === 0 ? 'card-soft' : '' }}" data-reveal style="--d: {{ 0.04 * $i }}s">
                        <span class="icon-tile {{ $i === 0 ? '!bg-white/60' : '' }}">@include('partials.icon', ['i' => $icon])</span>
                        <div><h4 class="font-semibold">{{ $name }}</h4><p class="text-sm muted mt-0.5">{{ $text }}</p></div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>

    {{-- ===== Правая колонка ===== --}}
    <aside class="space-y-5">
        <section class="card p-6" data-reveal>
            <h2 class="font-display font-bold text-xl mb-2">Что можно у нас</h2>
            <div>
                @foreach ([['scale', 'Сравнить вклады банков', route('deposits.compare')], ['calc', 'Посчитать доход', '#calc'], ['shield', 'Проверить гарантию КФГД', '#soon'], ['star', 'Оценить свой банк', '#ratings'], ['mail', 'Получать ставки на почту', '#soon']] as [$icon, $label, $href])
                    <a href="{{ $href }}" class="list-row group">
                        <span class="icon-tile group-hover:bg-[var(--ink)] group-hover:text-[var(--ink-text)] transition">@include('partials.icon', ['i' => $icon, 'c' => 'w-[19px] h-[19px]'])</span>
                        <span class="font-medium">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section id="calc" class="card p-6 space-y-5 scroll-mt-32" x-data="calc({{ (float) ($best?->rate ?? 15) }})" data-reveal style="--d:.06s">
            <div>
                <h2 class="font-display font-bold text-xl">Калькулятор дохода</h2>
                <p class="text-sm muted mt-1">Сколько принесёт вклад за срок</p>
            </div>
            <label class="field"><span>Сумма</span>
                <input type="text" class="input tabular font-semibold" :value="money(amount)" @input="amount = +($event.target.value.replace(/\D/g, '')) || 0"></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="field"><span>Срок, мес.</span><input type="number" min="1" max="60" x-model.number="months" class="input tabular"></label>
                <label class="field"><span>Ставка, %</span><input type="number" step="0.01" x-model.number="rate" class="input tabular"></label>
            </div>
            <input type="range" min="1" max="36" x-model.number="months" class="range" aria-label="Срок">
            <label class="flex items-center justify-between text-sm font-medium">Капитализация ежемесячно
                <input type="checkbox" x-model="cap" class="w-4 h-4 accent-[var(--ink)]"></label>
            <div class="plate p-4 space-y-2 text-sm">
                <div class="flex justify-between"><span class="muted">Доход</span><b class="tabular text-lg" x-text="'+' + money(income)"></b></div>
                <div class="flex justify-between border-t border-[var(--line)] pt-2"><span class="muted">Через <span x-text="months"></span> мес.</span><b class="tabular" x-text="money(amount + income)"></b></div>
            </div>
            <a :href="'{{ route('deposits.index') }}?amount=' + Math.round(amount) + '&sort=income'" class="btn btn-primary w-full">Найти вклад с этой ставкой</a>
        </section>

        <section class="card p-6" data-reveal style="--d:.1s">
            <h2 class="font-display font-bold text-xl mb-4">Сейчас ищут</h2>
            <div class="flex flex-wrap gap-2">
                @foreach (['вклад Kaspi', 'Halyk депозит', 'ставка НБРК', 'ГЭСВ что это', 'сберегательный вклад', 'КФГД 20 млн', 'вклад в долларах'] as $tag)
                    <a href="#top" class="btn btn-ghost !py-1.5 !px-3 text-sm !font-medium">{{ $tag }}</a>
                @endforeach
            </div>
        </section>
    </aside>
</div>
@endsection
