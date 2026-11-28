{{-- A33 · Каталог платёжных систем: международные / региональные / национальные, уровни карт, сколько карт в стране. --}}
@extends('layouts.app', ['title' => 'Платёжные системы', 'description' => 'Visa, Mastercard, UnionPay, Мир, American Express, JCB и национальные системы: уровни карт, привилегии, банки '.(country()?->genitive() ?? '').' и их карты.'])

@section('content')
<div class="space-y-5">
    <section class="grid md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] gap-5">
        <div class="card p-7 flex flex-col justify-between gap-6" data-reveal>
            <nav class="text-sm muted flex items-center gap-2"><a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span><a href="{{ route('cards.index') }}" class="hover:text-[var(--text)]">Карты</a><span class="faint">/</span><span>Платёжные системы</span></nav>
            <div>
                <h1 class="font-display font-bold text-4xl sm:text-[2.75rem] leading-[1.05]">Платёжные <span class="text-grad">системы</span></h1>
                <p class="muted mt-3 max-w-xl">Кто стоит за картой: охват, уровни от Classic до Infinite, привилегии каждого уровня и карты банков {{ country()?->genitive() }} в каждой системе.</p>
            </div>
        </div>
        <div class="card card-ink p-7 flex flex-col justify-between gap-4" data-reveal style="--d:.05s">
            <span class="text-sm font-semibold opacity-70">В справочнике</span>
            <p class="font-display font-semibold tabular leading-none text-6xl"><span x-data x-count="{{ $systems->count() }}">{{ $systems->count() }}</span></p>
            <p class="text-sm opacity-60">{{ plural($systems->count(), 'система', 'системы', 'систем') }} · {{ $systems->sum(fn ($s) => $s->tiers->count()) }} уровней карт · {{ $total }} {{ plural($total, 'карта', 'карты', 'карт') }} в каталоге</p>
        </div>
    </section>

    @php $n = 0; @endphp
    @foreach (['international' => 'международные', 'regional' => 'региональные', 'national' => 'национальные'] as $scope => $label)
        @continue(! isset($scopes[$scope]))
        <section class="space-y-3">
            <p class="kicker px-1"><b>{{ str_pad((string) ++$n, 2, '0', STR_PAD_LEFT) }}</b> / {{ $label }}</p>
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($scopes[$scope] as $i => $ps)
                    <div class="relative">{{-- A53 · обёртка: кнопка сравнения не может лежать внутри ссылки --}}
                    <a href="{{ route('payment-systems.show', $ps) }}" class="card ps-card lift p-6 flex flex-col gap-6 min-h-[230px] h-full" style="--ps-grad: {{ $ps->gradient() }}; --d: {{ 0.04 * $i }}s" data-reveal>
                        <div class="flex items-start justify-between gap-3 relative z-10 pr-12">
                            <span class="text-[34px] leading-none">@include('partials.ps-mark', ['ps' => $ps])</span>
                            @if ($ps->cards_here)<span class="chip !bg-white/15 !text-white">{{ $ps->cards_here }} {{ plural($ps->cards_here, 'карта', 'карты', 'карт') }}</span>@endif
                        </div>
                        <div class="relative z-10 mt-auto">
                            <p class="font-display font-bold text-2xl">{{ $ps->name }}</p>
                            <p class="text-sm opacity-75 mt-1">{{ $ps->scopeLabel() }}@if ($ps->headquarters) · {{ $ps->headquarters }}@endif @if ($ps->founded_year) · с {{ $ps->founded_year }}@endif</p>
                            <div class="flex flex-wrap gap-1.5 mt-4">
                                @foreach ($ps->tiers->take(6) as $t)<span class="chip !bg-white/12 !text-white !py-0.5 !text-[0.75rem]">{{ trim(\Illuminate\Support\Str::after($t->name, $ps->name), " -") ?: $t->name }}</span>@endforeach
                            </div>
                        </div>
                    </a>
                    @include('partials.compare-toggle', ['type' => 'payment-systems', 'item' => $ps, 'class' => 'absolute top-5 right-5 z-20 ps-cmp'])
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
@endsection
