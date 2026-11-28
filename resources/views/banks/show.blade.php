{{-- A18 · Страница банка: о банке, лучшая ставка, три рейтинга, все вклады, разбор объективного, отзывы. --}}
@php
    use App\Enums\RatingKind;
    $color = $bank->brand_color ?? 'var(--brand-1)';
    $best = $stats['best'];
    $bestOffer = $offers->first();
    $people = $score?->people;
@endphp
@extends('layouts.app', [
    'title' => $bank->displayName().' — вклады, рейтинг и отзывы',
    'description' => $bank->displayName().': '.($best ? 'вклады до '.rate_format($best->rate).', ' : '').'объективный рейтинг '.score_format($score?->score(RatingKind::Objective)).' из 5'.($people ? ', народный '.score_format($people['score']).' по '.$people['count'].' '.plural($people['count'], 'отзыву', 'отзывам', 'отзывам') : '').'.',
])

@section('content')
<div class="space-y-6">
    <nav class="text-sm muted flex items-center gap-2 px-1 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span>
        <a href="{{ route('banks.index') }}" class="hover:text-[var(--text)]">Банки</a><span class="faint">/</span>
        <span class="text-[var(--text)]">{{ $bank->displayName() }}</span>
    </nav>

    {{-- Бенто: о банке + лучшая ставка --}}
    <section class="grid grid-cols-6 gap-4 lg:gap-6">
        <div class="card col-span-6 lg:col-span-4 p-6 lg:p-8 flex flex-col justify-between gap-7" data-reveal>
            <div class="flex items-start gap-4">
                <span class="bank-mark !w-14 !h-14 !text-[0.8rem]" style="--c: {{ $color }}">{{ $bank->mark() }}</span>
                <div class="min-w-0">
                    <h1 class="font-display font-bold text-[30px] sm:text-4xl leading-[1.1]">{{ $bank->displayName() }}</h1>
                    <p class="muted mt-1">{{ $bank->name }}@if ($bank->name_kz && $bank->name_kz !== $bank->name) · {{ $bank->name_kz }}@endif</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="chip">@include('partials.icon', ['i' => 'shield', 'c' => 'w-3.5 h-3.5 '.($bank->deposit_insured ? 'text-[var(--ok)]' : '')]) {{ $bank->deposit_insured ? 'участник '.($bank->country?->guaranteeName() ?? 'гарантирования вкладов') : 'вклады не застрахованы' }}</span>
                @if ($bank->license)<span class="chip">лицензия {{ $bank->license }}</span>@endif
                @if ($bank->founded_year)<span class="chip">с {{ $bank->founded_year }} года</span>@endif
                <span class="chip mono">{{ $bank->code }}</span>
            </div>
            @if ($bank->description)<p class="text-[15px] leading-[1.55] max-w-2xl">{{ $bank->description }}</p>@endif
            <div class="flex flex-wrap gap-2">
                <a href="#deposits" class="btn btn-primary">Вклады банка @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</a>
                <a href="#review-form" class="btn btn-ghost">@include('partials.icon', ['i' => 'star', 'c' => 'w-4 h-4']) Оставить отзыв</a>
                @include('partials.fav', ['type' => 'bank', 'id' => $bank->id, 'on' => auth()->user()?->hasFavorite($bank), 'wide' => true]){{-- A28 --}}
                @include('partials.compare-toggle', ['type' => 'banks', 'item' => $bank, 'wide' => true]){{-- A53 --}}
                @if ($bank->website)<a href="{{ $bank->website }}" target="_blank" rel="noopener nofollow" class="btn btn-outline">Сайт банка @include('partials.icon', ['i' => 'external', 'c' => 'w-4 h-4'])</a>@endif
            </div>
        </div>

        <a href="{{ $bestOffer ? route('deposits.show', $bestOffer->product) : '#deposits' }}" class="card card-ink lift col-span-6 lg:col-span-2 p-6 lg:p-7 flex flex-col justify-between gap-6 min-h-[240px]" data-reveal style="--d:.06s">
            <div class="flex items-start justify-between gap-3">
                <span class="text-sm font-semibold opacity-70">Лучшая ставка</span>
                @if ($bestOffer)<span class="chip !bg-white/10 !text-current">{{ $bestOffer->product->type->label() }}</span>@endif
            </div>
            @if ($bestOffer)
                <p class="num-hero"><span x-data x-count="{{ number_format((float) $bestOffer->rate->rate, 2, '.', '') }}">{{ number_format((float) $bestOffer->rate->rate, 2, ',', '') }}</span><sup class="text-[.45em] font-semibold align-[.9em] ml-0.5 opacity-60">%</sup></p>
                <div class="text-sm">
                    <p class="font-semibold truncate">{{ $bestOffer->product->name }}</p>
                    <p class="opacity-60">ГЭСВ {{ rate_format($bestOffer->rate->effective_rate) }} · с {{ $bestOffer->rate->valid_from->translatedFormat('j F Y') }}</p>
                </div>
            @else
                <p class="text-lg opacity-70">Ставок по вкладам пока нет в базе.</p>
            @endif
        </a>
    </section>

    {{-- Три рейтинга рядом --}}
    <section class="card p-2" data-reveal>
        <div class="grid md:grid-cols-3">
            @foreach (RatingKind::cases() as $k => $kind)
                @php
                    $val = $score?->score($kind);
                    if ($kind === RatingKind::Objective && ! ($score?->objective['has_data'] ?? false)) { $val = null; }
                    $rank = $score?->rank($kind);
                @endphp
                <div class="p-5 lg:p-6 flex items-center gap-5 {{ $k ? 'border-t md:border-t-0 md:border-l border-[var(--line)]' : '' }}">
                    @include('partials.gauge', ['score' => $val, 'color' => $kind->color(), 'size' => 112])
                    <div class="min-w-0 space-y-1.5">
                        <p class="font-semibold flex items-center gap-2">@include('partials.icon', ['i' => $kind->icon(), 'c' => 'w-4 h-4', ]) {{ $kind->label() }}</p>
                        <p class="text-[13px] muted">{{ $kind->hint() }}</p>
                        <p class="text-[13px] flex items-center gap-2 flex-wrap">
                            @if ($rank)<span class="kicker"><b>#{{ $rank }}</b> из {{ $totalRanked[$kind->value] }}</span>@endif
                            @include('partials.delta', ['d' => $score?->delta($kind)])
                        </p>
                        <p class="text-[13px]">
                            @switch($kind)
                                @case(RatingKind::Author) @if ($score?->author) <a href="#verdict" class="font-semibold hover:underline">вывод редакции →</a> @else <span class="faint">оценки пока нет</span> @endif @break
                                @case(RatingKind::Objective) <a href="#breakdown" class="font-semibold hover:underline">из чего сложился →</a> @break
                                @case(RatingKind::People) @if ($people) <a href="#reviews" class="font-semibold hover:underline">{{ $people['count'] }} {{ plural($people['count'], 'отзыв', 'отзыва', 'отзывов') }} →</a> @else <a href="#review-form" class="font-semibold hover:underline">станьте первым →</a> @endif @break
                            @endswitch
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Вклады — на всю ширину: карточке предложения нужна широкая сетка --}}
    <section id="deposits" class="offers space-y-3 scroll-mt-32">{{-- A36: .offers — контейнер для раскладки карточки вклада --}}
        <div class="flex items-end justify-between gap-3 px-1">
            <div>
                <p class="kicker"><b>01</b> / Вклады</p>
                <h2 class="font-display font-bold text-[22px] sm:text-2xl mt-1">Все вклады {{ $bank->displayName() }}</h2>
            </div>
            @if ($offers->isNotEmpty())<a href="{{ route('deposits.index', ['bank' => [$bank->slug]]) }}" class="text-sm font-semibold muted hover:text-[var(--text)] whitespace-nowrap">в каталоге →</a>@endif
        </div>
        @forelse ($offers as $i => $o)
            @include('deposits._offer', ['o' => $o, 'first' => $i === 0 && $offers->count() > 1, 'i' => $i])
        @empty
            <div class="card p-8 text-center"><p class="font-semibold">Вкладов пока нет</p><p class="text-sm muted mt-1">Ставки появятся после загрузки данных банка.</p></div>
        @endforelse
    </section>

    {{-- A33 · Карты и пакеты услуг банка --}}
    @if ($cardOffers->isNotEmpty() || $packages->isNotEmpty())
        <section id="cards" class="space-y-3 scroll-mt-32">
            <div class="flex items-end justify-between gap-3 px-1">
                <div>
                    <p class="kicker">Карты и пакеты</p>
                    <h2 class="font-display font-bold text-[22px] sm:text-2xl mt-1">Карты {{ $bank->displayName() }}</h2>
                </div>
                @if ($cardOffers->isNotEmpty())<a href="{{ route('cards.index', ['bank' => [$bank->slug]]) }}" class="text-sm font-semibold muted hover:text-[var(--text)] whitespace-nowrap">в каталоге →</a>@endif
            </div>
            @include('cards._split', ['offers' => $cardOffers, 'offerFrom' => 'bank', 'limit' => 24]) {{-- A54 · физлицам / бизнесу, по видам --}}
            @if ($packages->isNotEmpty())
                <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4 pt-2">
                    @foreach ($packages as $i => $p)@include('packages._card', ['p' => $p, 'i' => $i])@endforeach
                </div>
            @endif
        </section>
    @endif

    <div class="grid xl:grid-cols-[minmax(0,1fr)_360px] gap-4 lg:gap-6 items-start">
        <div class="space-y-4 lg:space-y-6 min-w-0">
            {{-- Разбор объективного рейтинга --}}
            <section id="breakdown" class="card p-6 lg:p-7 scroll-mt-32" data-reveal>
                <div class="flex items-start justify-between gap-3 mb-5">
                    <div>
                        <p class="kicker"><b>02</b> / Объективный рейтинг</p>
                        <h2 class="font-display font-bold text-[22px] mt-1">Из чего сложилась оценка</h2>
                    </div>
                    <a href="{{ route('ratings') }}#method" class="text-sm font-semibold muted hover:text-[var(--text)] whitespace-nowrap">методика →</a>
                </div>
                <div class="grid sm:grid-cols-2 gap-x-8">
                    @foreach ($criteria as $key => [$label, $hint])
                        @php $v = $score?->objective['criteria'][$key] ?? 0; @endphp
                        <div class="list-row !block sm:[&:nth-child(2)]:border-t-0 !py-4">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="font-semibold">{{ $label }} <span class="text-xs muted font-normal tabular">вес {{ round(($weights[$key] ?? 0) * 100) }}%</span></p>
                                <b class="tabular">{{ round($v * 100) }}<span class="text-xs muted">/100</span></b>
                            </div>
                            <div class="meter mt-2"><i style="width: {{ round($v * 100) }}%; background: var(--s-objective)"></i></div>
                            <p class="text-xs muted mt-1.5">{{ $hint }}</p>
                        </div>
                    @endforeach
                </div>
                @if (! $bank->assets_bn || ! $bank->founded_year)
                    <p class="text-xs faint mt-4 flex gap-2">@include('partials.icon', ['i' => 'info', 'c' => 'w-4 h-4 flex-none']) Нет данных об {{ ! $bank->assets_bn ? 'активах' : '' }}{{ ! $bank->assets_bn && ! $bank->founded_year ? ' и ' : '' }}{{ ! $bank->founded_year ? 'годе основания' : '' }} — эти части надёжности не учтены и не штрафуют банк.</p>
                @endif
            </section>

            {{-- Авторский вывод --}}
            @if ($score?->author && ($score->author->verdict || $score->author->pros || $score->author->cons))
                @php $a = $score->author; @endphp
                <section id="verdict" class="card p-6 lg:p-7 scroll-mt-32" data-reveal>
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div>
                            <p class="kicker"><b>03</b> / Мнение редакции</p>
                            <h2 class="font-display font-bold text-[22px] mt-1">Авторская оценка <span class="muted tabular">{{ score_format($a->score) }} / 5</span></h2>
                        </div>
                        <span class="text-xs muted whitespace-nowrap">{{ $a->period->translatedFormat('j F Y') }}</span>
                    </div>
                    @if ($a->verdict)<p class="text-[15px] leading-[1.6] whitespace-pre-line">{{ $a->verdict }}</p>@endif
                    @if ($a->pros || $a->cons)
                        <div class="grid sm:grid-cols-2 gap-4 mt-5">
                            @foreach ([['Плюсы', $a->pros ?? [], 'plus', 'var(--ok)'], ['Минусы', $a->cons ?? [], 'minus', 'var(--bad)']] as [$title, $items, $icon, $c])
                                @if ($items)
                                    <div class="plate p-4">
                                        <p class="label mb-2">{{ $title }}</p>
                                        <ul class="space-y-1.5 text-sm">@foreach ($items as $it)<li class="flex gap-2">@include('partials.icon', ['i' => $icon, 'c' => 'w-4 h-4 mt-0.5 flex-none']) <span>{{ $it }}</span></li>@endforeach</ul>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            {{-- Отзывы --}}
            <section id="reviews" class="card p-6 lg:p-7 scroll-mt-32" data-reveal>
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <p class="kicker"><b>04</b> / Народный рейтинг</p>
                        <h2 class="font-display font-bold text-[22px] mt-1">Отзывы клиентов</h2>
                    </div>
                    @if ($reviews->total() > 1)
                        <nav class="seg" aria-label="Сортировка отзывов">
                            <a href="{{ route('banks.show', $bank) }}#reviews" class="{{ request('reviews') !== 'helpful' ? 'is-active' : 'muted' }}">Новые</a>
                            <a href="{{ route('banks.show', [$bank, 'reviews' => 'helpful']) }}#reviews" class="{{ request('reviews') === 'helpful' ? 'is-active' : 'muted' }}">Полезные</a>
                        </nav>
                    @endif
                </div>
                @if ($people)
                    <div class="grid sm:grid-cols-[auto_minmax(0,1fr)] gap-6 items-center mt-5 plate p-5">
                        <div class="text-center sm:pr-6 sm:border-r border-[var(--line)]">
                            <p class="num-card">{{ score_format($people['avg']) }}</p>
                            <div class="mt-2">@include('partials.stars', ['score' => $people['avg']])</div>
                            <p class="text-xs muted mt-1">{{ $people['count'] }} {{ plural($people['count'], 'оценка', 'оценки', 'оценок') }}</p>
                        </div>
                        <div class="space-y-1.5">
                            @foreach ($people['dist'] as $n => $c)
                                <div class="dist-row"><span class="muted">{{ $n }}★</span><div class="meter"><i style="width: {{ $people['count'] ? round($c / $people['count'] * 100) : 0 }}%"></i></div><span class="text-right muted">{{ $c }}</span></div>
                            @endforeach
                        </div>
                    </div>
                    <p class="text-xs faint mt-2">В рейтинг идёт взвешенная оценка {{ score_format($people['score'], 2) }}: пока отзывов мало, она тянется к средней по рынку.</p>
                @endif
                <div class="mt-2">
                    @forelse ($reviews as $r)
                        @include('reviews._item', ['r' => $r])
                    @empty
                        <div class="text-center py-10">
                            <span class="icon-tile mx-auto">@include('partials.icon', ['i' => 'star'])</span>
                            <p class="font-semibold mt-3">Отзывов пока нет</p>
                            <p class="text-sm muted mt-1">Расскажите о своём опыте — это поможет другим выбрать банк.</p>
                        </div>
                    @endforelse
                </div>
                @if ($reviews->hasPages())<div class="pt-4 border-t border-[var(--line)]">{{ $reviews->links() }}</div>@endif
            </section>
        </div>

        {{-- Правая колонка --}}
        <aside class="space-y-4 lg:space-y-6 xl:sticky xl:top-[88px]">
            <section class="card p-6" data-reveal>
                <h2 class="font-display font-bold text-xl mb-2">О банке</h2>
                <dl>
                    @foreach (array_filter([
                        ['Код', $bank->code],
                        $bank->bin ? ['БИН', $bank->bin] : null,
                        $bank->swift ? ['SWIFT', $bank->swift] : null,
                        $bank->license ? ['Лицензия', $bank->license] : null,
                        $bank->founded_year ? ['Основан', $bank->founded_year.' г.'] : null,
                        $bank->assets_bn ? ['Активы', number_format((float) $bank->assets_bn, 0, ',', ' ').' млрд '.currency_symbol()] : null,
                        $bank->capital_bn ? ['Капитал', number_format((float) $bank->capital_bn, 0, ',', ' ').' млрд '.currency_symbol()] : null,
                        [$bank->country?->guaranteeName() ?? 'Гарантия вкладов', $bank->deposit_insured ? 'участник' : 'нет'],
                        $bank->phone ? ['Телефон', $bank->phone] : null,
                        $bank->website ? ['Сайт', preg_replace('#^https?://(www\.)?#', '', rtrim($bank->website, '/'))] : null,
                    ]) as [$k, $v])
                        <div class="list-row !py-2.5 justify-between"><dt class="muted text-sm">{{ $k }}</dt><dd class="font-semibold text-right tabular text-sm">{{ $v }}</dd></div>
                    @endforeach
                </dl>
                @if ($bank->financials_at)<p class="text-xs faint mt-3">Финансы — на {{ $bank->financials_at->translatedFormat('j F Y') }}, источник {{ $bank->country?->regulatorName() ?? 'регулятор' }}.</p>@endif
            </section>

            @include('reviews._form', ['bank' => $bank, 'mine' => $mine, 'products' => $products])
            @include('blog._about', ['about' => $bank, 'title' => 'О банке в журнале']) {{-- A37 --}}
        </aside>
    </div>
</div>
@endsection
