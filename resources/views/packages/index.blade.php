{{-- A33 · Пакеты услуг банков: фильтр по сегменту, карточки со стоимостью, условием бесплатности, привилегиями и картами. --}}
@extends('layouts.app', ['title' => 'Пакеты услуг банков '.(country()?->genitive() ?? ''), 'description' => 'Пакеты (тарифы) обслуживания банков '.(country()?->genitive() ?? '').': стоимость, когда бесплатно, что входит и какие карты.'])

@section('content')
<div class="space-y-5">
    <section class="grid md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] gap-5">
        <div class="card p-7 flex flex-col justify-between gap-6" data-reveal>
            <nav class="text-sm muted flex items-center gap-2"><a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span><a href="{{ route('cards.index') }}" class="hover:text-[var(--text)]">Карты</a><span class="faint">/</span><span>Пакеты услуг</span></nav>
            <div>
                <h1 class="font-display font-bold text-4xl sm:text-[2.75rem] leading-[1.05]">Пакеты <span class="text-grad">услуг</span></h1>
                <p class="muted mt-3 max-w-xl">Тарифы обслуживания: сколько стоят, при каких условиях бесплатны, что входит и какие карты дают.</p>
            </div>
            @if ($total)<div class="flex flex-wrap gap-2">{{-- A36: «Все 0» не показываем --}}
                <a href="{{ route('packages.index') }}" class="pill {{ $segment ? '' : 'is-active' }}">Все <span class="faint tabular">{{ $total }}</span></a>
                @foreach (\App\Models\ServicePackage::SEGMENTS as $k => $l)
                    @continue(! ($counts[$k] ?? 0))
                    <a href="{{ route('packages.index', ['segment' => $k]) }}" class="pill {{ $segment === $k ? 'is-active' : '' }}">{{ $l }} <span class="faint tabular">{{ $counts[$k] }}</span></a>
                @endforeach
            </div>@endif
        </div>
        @if ($total === 0)
            {{-- A36 · пустой каталог: вместо «0 пакетов · 0 банков» --}}
            @include('partials.catalog-soon', [
                'class' => 'min-w-0',
                'title' => 'Пакеты услуг скоро будут здесь',
                'text' => 'Собираем тарифы банков: что входит, сколько стоит и когда обслуживание бесплатно.',
                'items' => [['wallet', 'Стоимость', 'в месяц и в год'], ['check', 'Бесплатно, если…', 'остаток или обороты'], ['card', 'Карты пакета', 'уровень и система'], ['star', 'Привилегии', 'залы, страховка, кэшбэк']],
                'links' => [['Карты', route('cards.index'), 'card'], ['Платёжные системы', route('payment-systems.index'), 'globe']],
            ])
        @else
            <div class="card card-brand p-7 flex flex-col justify-between gap-4" data-reveal style="--d:.05s">
                <span class="text-sm font-semibold opacity-80">В каталоге</span>
                <p class="font-display font-semibold tabular leading-none text-6xl"><span x-data x-count="{{ $total }}">{{ $total }}</span></p>
                <p class="text-sm opacity-80">{{ plural($total, 'пакет', 'пакета', 'пакетов') }} · {{ $packages->pluck('bank_id')->unique()->count() }} {{ plural($packages->pluck('bank_id')->unique()->count(), 'банк', 'банка', 'банков') }}</p>
            </div>
        @endif
    </section>

    @if ($total > 0)
    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse ($packages as $i => $p)
            @include('packages._card', ['p' => $p, 'i' => $i])
        @empty
            <div class="card p-12 text-center space-y-3 md:col-span-2 xl:col-span-3">
                <span class="icon-tile mx-auto">@include('partials.icon', ['i' => 'gift'])</span>
                <p class="font-display font-bold text-xl">Пакеты скоро появятся</p>
                <p class="muted">Мы собираем тарифы банков {{ country()?->genitive() }}.</p>
            </div>
        @endforelse
    </div>
    @endif
</div>
@endsection
