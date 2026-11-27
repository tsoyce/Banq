{{-- A18 · Рейтинги банков: баннер народного рейтинга, лидеры трёх видов, сводная таблица, методика, свежие отзывы. --}}
@php
    use App\Enums\RatingKind;
    $objLeader = $tops['objective']->first();
@endphp
@extends('layouts.app', [
    'title' => 'Рейтинг банков '.(country()?->genitive() ?? '').' — народный, объективный, авторский',
    'description' => 'Три рейтинга банков '.(country()?->genitive() ?? '').' рядом: народный по отзывам клиентов, объективный по ставкам и надёжности, авторский от редакции TSOY.IN.',
])

@section('content')
<div class="space-y-6">
    <header class="px-1 pt-2 space-y-3" data-reveal>
        <p class="kicker"><b>01</b> / Рейтинги</p>
        <h1 class="font-display font-bold text-[30px] sm:text-4xl leading-[1.1]">Рейтинг банков. <span class="muted">Три взгляда: клиенты, цифры и редакция.</span></h1>
    </header>

    {{-- Бенто: народный рейтинг (бренд) + лидер объективного (чернила) --}}
    <section class="grid grid-cols-6 gap-4 lg:gap-6">
        <article class="card card-brand col-span-6 md:col-span-4 p-6 lg:p-8 min-h-[260px] flex flex-col justify-between gap-6" data-reveal>
            <div class="orb-soft"></div>
            <span class="chip !bg-white/15 !text-white w-fit relative">@include('partials.icon', ['i' => 'users', 'c' => 'w-3.5 h-3.5']) народный рейтинг</span>
            <div class="relative flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="font-display font-bold text-[56px] sm:text-[72px] leading-none tracking-[-.05em] tabular"><span x-data x-count="{{ $reviewsCount }}">{{ $reviewsCount }}</span></p>
                    <p class="mt-2 text-white/80 max-w-sm">{{ plural($reviewsCount, 'отзыв', 'отзыва', 'отзывов') }} клиентов прошли модерацию. Оцените свой банк — это займёт две минуты.</p>
                </div>
                <a href="{{ route('banks.index', ['sort' => 'people']) }}" class="btn bg-white text-[#0a0a0b] hover:opacity-90">Оценить банк @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</a>
            </div>
        </article>
        <a href="{{ $objLeader ? route('banks.show', $objLeader->bank) : route('banks.index') }}" class="card card-ink lift col-span-6 md:col-span-2 p-6 lg:p-7 flex flex-col justify-between gap-6" data-reveal style="--d:.06s">
            <div class="flex items-start justify-between gap-3">
                <span class="text-sm font-semibold opacity-70">Лидер по цифрам</span>
                @if ($objLeader)<span class="bank-mark" style="--c: {{ $objLeader->bank->brand_color ?? 'var(--brand-1)' }}">{{ $objLeader->bank->mark() }}</span>@endif
            </div>
            @if ($objLeader)
                <p class="num-hero">{{ score_format($objLeader->score(RatingKind::Objective)) }}<sup class="text-[.32em] font-semibold align-[1.4em] ml-1 opacity-60">/ 5</sup></p>
                <p class="font-semibold">{{ $objLeader->bank->displayName() }} <span class="opacity-60 font-normal">· объективный</span></p>
            @else
                <p class="opacity-70">Появится вместе со ставками банков.</p>
            @endif
        </a>
    </section>

    {{-- Лидеры трёх видов --}}
    <section class="grid lg:grid-cols-3 gap-4 lg:gap-6">
        @foreach (RatingKind::cases() as $k => $kind)
            <article class="card p-6 flex flex-col" data-reveal style="--d: {{ 0.05 * $k }}s">
                <div class="flex items-center gap-3">
                    <span class="icon-tile" style="color: {{ $kind->color() }}">@include('partials.icon', ['i' => $kind->icon()])</span>
                    <div><h2 class="font-display font-bold text-xl">{{ $kind->label() }}</h2><p class="text-[13px] muted">{{ $kind->hint() }}</p></div>
                </div>
                <ol class="mt-4 flex-1">
                    @forelse ($tops[$kind->value] as $n => $s)
                        <li>
                            <a href="{{ route('banks.show', $s->bank) }}" class="list-row group">
                                <span class="w-5 text-sm font-bold tabular {{ $n ? 'muted' : '' }}">{{ $n + 1 }}</span>
                                <span class="bank-mark" style="--c: {{ $s->bank->brand_color ?? 'var(--brand-1)' }}">{{ $s->bank->mark() }}</span>
                                <span class="flex-1 min-w-0 font-medium truncate group-hover:underline">{{ $s->bank->displayName() }}</span>
                                @include('partials.delta', ['d' => $s->delta($kind)])
                                <b class="tabular text-[17px] w-9 text-right">{{ score_format($s->score($kind)) }}</b>
                            </a>
                        </li>
                    @empty
                        <li class="plate p-5 text-sm text-center muted">
                            @switch($kind)
                                @case(RatingKind::Author) Редакция ещё выставляет оценки. @break
                                @case(RatingKind::People) Ждём первые отзывы — <a href="{{ route('banks.index') }}" class="underline">выберите банк</a>. @break
                                @default Нет данных о ставках.
                            @endswitch
                        </li>
                    @endforelse
                </ol>
                <a href="{{ route('ratings', ['by' => $kind->value]) }}#table" class="text-sm font-semibold muted hover:text-[var(--text)] mt-4">Вся таблица →</a>
            </article>
        @endforeach
    </section>

    {{-- Сводная таблица --}}
    <section id="table" class="card p-3 sm:p-5 scroll-mt-32" data-reveal>
        <div class="flex items-end justify-between gap-3 px-3 pt-2 pb-4 flex-wrap">
            <div>
                <p class="kicker"><b>02</b> / Сводная таблица</p>
                <h2 class="font-display font-bold text-[22px] sm:text-2xl mt-1">Все банки, три оценки</h2>
            </div>
            <nav class="seg" aria-label="Сортировать по">
                @foreach (RatingKind::cases() as $kind)
                    <a href="{{ route('ratings', ['by' => $kind->value]) }}#table" class="{{ $by === $kind ? 'is-active' : 'muted' }}">{{ $kind->label() }}</a>
                @endforeach
            </nav>
        </div>

        {{-- Десктоп: таблица --}}
        <div class="hidden md:block">
            <table class="table tabular">
                <thead><tr>
                    <th class="w-10">#</th><th>Банк</th>
                    @foreach (RatingKind::cases() as $kind)<th class="!text-right {{ $by === $kind ? 'is-sorted' : '' }}">{{ $kind->label() }}@if ($by === $kind) ↓@endif</th>@endforeach
                    <th class="!text-right">Ставка до</th>
                </tr></thead>
                <tbody>
                @foreach ($table as $s)
                    @php $b = $s->bank; $st = $stats[$b->id] ?? null; @endphp
                    <tr>
                        <td class="muted text-sm">{{ $s->rank($by) ?? '—' }}</td>
                        <td><a href="{{ route('banks.show', $b) }}" class="flex items-center gap-3 font-semibold hover:underline"><span class="bank-mark" style="--c: {{ $b->brand_color ?? 'var(--brand-1)' }}">{{ $b->mark() }}</span>{{ $b->displayName() }}</a></td>
                        @foreach (RatingKind::cases() as $kind)
                            @php $v = $kind === RatingKind::Objective && ! $s->objective['has_data'] ? null : $s->score($kind); @endphp
                            <td class="text-right {{ $by === $kind ? 'is-sorted' : '' }}">
                                @if ($v !== null)
                                    <span class="inline-flex items-center gap-2 justify-end">@include('partials.delta', ['d' => $s->delta($kind)])<span class="text-[16px] {{ $by === $kind ? '' : 'font-semibold' }}">{{ score_format($v) }}</span></span>
                                    @if ($kind === RatingKind::People)<span class="block text-xs muted">{{ $s->people['count'] }} {{ plural($s->people['count'], 'отзыв', 'отзыва', 'отзывов') }}</span>@endif
                                @else<span class="faint">—</span>@endif
                            </td>
                        @endforeach
                        <td class="text-right font-semibold">{{ $st && $st['best'] ? rate_format($st['best']->rate) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- Мобильный: карточки-строки (DESIGN §6.5) --}}
        <div class="md:hidden px-2">
            @foreach ($table as $s)
                @php $v = $by === RatingKind::Objective && ! $s->objective['has_data'] ? null : $s->score($by); @endphp
                <a href="{{ route('banks.show', $s->bank) }}" class="list-row">
                    <span class="w-5 text-sm muted tabular">{{ $s->rank($by) ?? '—' }}</span>
                    <span class="bank-mark" style="--c: {{ $s->bank->brand_color ?? 'var(--brand-1)' }}">{{ $s->bank->mark() }}</span>
                    <span class="flex-1 min-w-0">
                        <span class="block font-semibold truncate">{{ $s->bank->displayName() }}</span>
                        <span class="block text-xs muted tabular">{{ collect(RatingKind::cases())->reject(fn ($k) => $k === $by)->map(fn ($k) => $k->label().' '.score_format($k === RatingKind::Objective && ! $s->objective['has_data'] ? null : $s->score($k)))->implode(' · ') }}</span>
                    </span>
                    <b class="tabular text-lg">{{ score_format($v) }}</b>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Методика --}}
    <section id="method" class="card p-6 lg:p-7 scroll-mt-32" data-reveal>
        <p class="kicker"><b>03</b> / Методика</p>
        <h2 class="font-display font-bold text-[22px] sm:text-2xl mt-1">Как считаем</h2>
        <div class="grid lg:grid-cols-3 gap-4 mt-5">
            <div class="plate p-5 space-y-2">
                <p class="font-semibold flex items-center gap-2" style="color: {{ RatingKind::Objective->color() }}">@include('partials.icon', ['i' => 'chart', 'c' => 'w-4 h-4']) <span class="text-[var(--text)]">Объективный</span></p>
                <p class="text-sm muted">Только из данных портала, без мнений. Каждый критерий — от 0 до 100, итог = сумма × вес, переведённая в шкалу 0–5.</p>
                <div class="space-y-2.5 pt-2">
                    @foreach ($criteria as $key => [$label, $hint])
                        <div>
                            <div class="flex justify-between text-sm"><span class="font-medium">{{ $label }}</span><b class="tabular">{{ round(($weights[$key] ?? 0) * 100) }}%</b></div>
                            <div class="meter mt-1"><i style="width: {{ round(($weights[$key] ?? 0) * 100 / max($weights) ) }}%; background: var(--s-objective)"></i></div>
                            <p class="text-xs muted mt-1">{{ $hint }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="plate p-5 space-y-2">
                <p class="font-semibold flex items-center gap-2" style="color: {{ RatingKind::People->color() }}">@include('partials.icon', ['i' => 'users', 'c' => 'w-4 h-4']) <span class="text-[var(--text)]">Народный</span></p>
                <p class="text-sm muted">Оценки 1–5 из отзывов зарегистрированных клиентов. Каждый отзыв проверяет модератор; один отзыв на банк от человека.</p>
                <p class="text-sm muted">Чтобы один восторженный отзыв не делал банк лидером, считаем взвешенное среднее: к оценкам добавляется {{ $prior['C'] }} «виртуальных» оценок {{ score_format($prior['m']) }}. Чем больше отзывов, тем меньше их влияние.</p>
            </div>
            <div class="plate p-5 space-y-2">
                <p class="font-semibold flex items-center gap-2" style="color: {{ RatingKind::Author->color() }}">@include('partials.icon', ['i' => 'pen', 'c' => 'w-4 h-4']) <span class="text-[var(--text)]">Авторский</span></p>
                <p class="text-sm muted">Субъективная оценка редакции TSOY.IN: опыт обслуживания, приложение, прозрачность условий. У каждой оценки — вывод, плюсы и минусы и дата.</p>
                <p class="text-sm muted">Ни один рейтинг не продаётся. История оценок хранится — изменения видны значками ▲▼.</p>
            </div>
        </div>
    </section>

    {{-- Свежие отзывы --}}
    @if ($latest->isNotEmpty())
        <section class="card p-6 lg:p-7" data-reveal>
            <p class="kicker"><b>04</b> / Свежие отзывы</p>
            <div class="grid lg:grid-cols-2 gap-x-8 mt-2">
                @foreach ($latest as $r)
                    @include('reviews._item', ['r' => $r, 'showBank' => true, 'clamp' => true])
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
