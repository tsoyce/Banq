{{-- A33 · Платёжная система: шапка в фирменном градиенте, лестница уровней с привилегиями, банки и карты страны в этой системе. --}}
@extends('layouts.app', ['title' => $system->name.' — уровни карт и банки', 'description' => $system->name.': '.($system->description ?? '').' Уровни карт и привилегии, карты банков '.(country()?->genitive() ?? '').'.'])

@section('content')
<div class="space-y-5">
    <nav class="text-sm muted flex items-center gap-2 px-1 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span>
        <a href="{{ route('payment-systems.index') }}" class="hover:text-[var(--text)]">Платёжные системы</a><span class="faint">/</span><span>{{ $system->name }}</span>
    </nav>

    <section class="grid lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] gap-5">
        <div class="card ps-card p-7 md:p-9 flex flex-col justify-between gap-10 min-h-[320px]" style="--ps-grad: {{ $system->gradient() }}" data-reveal>
            <span class="text-[52px] leading-none relative z-10">@include('partials.ps-mark', ['ps' => $system])</span>
            <div class="relative z-10">
                <span class="chip !bg-white/15 !text-white mb-4">{{ $system->scopeLabel() }} система</span>
                <h1 class="font-display font-bold text-5xl sm:text-6xl leading-[1.02]">{{ $system->name }}</h1>
                @if ($system->description)<p class="mt-4 max-w-2xl opacity-85">{{ $system->description }}</p>@endif
            </div>
        </div>
        <div class="card p-0 overflow-hidden" data-reveal style="--d:.06s">
            <div class="cells cols-2 h-full">
                <div class="!p-6"><p class="cell-label">Уровней</p><p class="font-display font-bold text-4xl tabular mt-2">{{ $system->tiers->count() }}</p></div>
                <div class="!p-6"><p class="cell-label">Карт в каталоге</p><p class="font-display font-bold text-4xl tabular mt-2">{{ $offers->count() }}</p></div>
                <div class="!p-6"><p class="cell-label">Банков</p><p class="font-display font-bold text-4xl tabular mt-2">{{ $banks->count() }}</p></div>
                <div class="!p-6"><p class="cell-label">Основана</p><p class="font-display font-bold text-4xl tabular mt-2">{{ $system->founded_year ?? '—' }}</p></div>
                <div class="!p-6 col-span-2"><p class="cell-label">Штаб-квартира · кошельки</p>
                    <p class="font-semibold mt-2">{{ $system->headquarters ?? '—' }}</p>
                    @if ($system->wallets)<div class="flex flex-wrap gap-1.5 mt-3">@foreach ($system->wallets as $w)<span class="chip">{{ config('banq.cards.wallets.'.$w, $w) }}</span>@endforeach</div>@endif
                    @if ($system->website)<a href="{{ $system->website }}" target="_blank" rel="noopener nofollow" class="text-sm font-semibold mt-3 inline-flex items-center gap-1 muted hover:text-[var(--text)]">{{ parse_url($system->website, PHP_URL_HOST) }} @include('partials.icon', ['i' => 'external', 'c' => 'w-3.5 h-3.5'])</a>@endif
                </div>
            </div>
        </div>
    </section>

    @if ($system->tiers->isNotEmpty())
        <section class="card p-6 md:p-8" data-reveal>
            <p class="kicker mb-1"><b>01</b> / уровни карт</p>
            <h2 class="font-display font-bold text-2xl">От базового до топового</h2>
            <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4 mt-6">
                @foreach ($system->tiers->sortBy('level') as $t)
                    <div id="tier-{{ $t->code }}" class="plate rounded-2xl p-5 tier-step scroll-mt-24" style="--lvl: {{ $t->level }}">
                        <div class="flex items-center gap-3">
                            <span class="mini-card !w-12 !h-8" style="background: {{ $system->gradient() }}; filter: saturate({{ 0.55 + $t->level * 0.1 }}) brightness({{ 1.2 - $t->level * 0.08 }})"></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold truncate">{{ $t->name }}</p>
                                <p class="text-xs muted">{{ $t->segmentLabel() }} · ступень {{ $t->level }} из 6 @if ($byTier[$t->id] ?? 0) · {{ $byTier[$t->id] }} {{ plural($byTier[$t->id], 'карта', 'карты', 'карт') }}@endif</p>
                            </div>
                            <span class="level-dots">@for ($l = 1; $l <= 6; $l++)<i @class(['on' => $l <= $t->level])></i>@endfor</span>
                        </div>
                        @if ($t->description)<p class="text-sm muted mt-3">{{ $t->description }}</p>@endif
                        @if ($t->privileges)
                            <ul class="grid gap-1.5 mt-4">@foreach ($t->privileges as $p)<li class="perk !text-[0.85rem]">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) {{ $p }}</li>@endforeach</ul>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="text-xs faint mt-5">Привилегии — типичный набор уровня у платёжной системы; что именно даёт ваша карта, определяет банк-эмитент.</p>
        </section>
    @endif

    <section class="space-y-3" data-reveal>
        <div class="flex items-end justify-between gap-3 px-1">
            <h2 class="font-display font-bold text-2xl">Карты {{ $system->displayName() }} в банках {{ country()?->genitive() }}</h2>
            @if ($offers->isNotEmpty())<a href="{{ route('cards.index', ['system' => [$system->slug]]) }}" class="text-sm font-semibold muted hover:text-[var(--text)] whitespace-nowrap">В каталоге →</a>@endif
        </div>
        @forelse ($offers->take(10) as $i => $o)
            @include('cards._offer', ['o' => $o, 'i' => $i])
        @empty
            <div class="card p-10 text-center muted">Карт {{ $system->displayName() }} в каталоге пока нет.</div>
        @endforelse
    </section>

    @include('blog._about', ['about' => $system, 'title' => $system->displayName().' в журнале', 'limit' => 3]) {{-- A37 --}}

    @if ($others->isNotEmpty())
        <section class="flex flex-wrap gap-2 px-1" data-reveal>
            <span class="label w-full mb-1">Другие системы</span>
            @foreach ($others as $o)
                <a href="{{ route('payment-systems.show', $o) }}" class="pill"><span class="ps-tile !w-8 !h-6 !text-[11px]" style="--ps: {{ $o->gradient() }}">@include('partials.ps-mark', ['ps' => $o])</span> {{ $o->name }}</a>
            @endforeach
        </section>
    @endif
</div>
@endsection
