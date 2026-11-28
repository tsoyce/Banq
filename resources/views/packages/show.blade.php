{{-- A33 · Пакет услуг: стоимость и условие бесплатности, состав, карты пакета строками каталога, другие пакеты. --}}
@php $bank = $package->bank; $sign = currency_symbol($bank->country?->currency); @endphp
@extends('layouts.app', ['title' => $package->name.' — пакет услуг '.$bank->displayName(), 'description' => 'Пакет услуг '.$package->name.' в '.$bank->displayName().': стоимость, условия, что входит.'])

@section('content')
<div class="space-y-5">
    <nav class="text-sm muted flex items-center gap-2 px-1 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span>
        <a href="{{ route('packages.index') }}" class="hover:text-[var(--text)]">Пакеты услуг</a><span class="faint">/</span>
        <a href="{{ route('banks.show', $bank) }}" class="hover:text-[var(--text)]">{{ $bank->displayName() }}</a>
    </nav>

    <section class="grid lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] gap-5">
        <div class="card p-7 md:p-9 flex flex-col justify-between gap-8" data-reveal>
            <a href="{{ route('banks.show', $bank) }}" class="flex items-center gap-4 w-fit group">
                <span class="bank-mark !w-14 !h-14 !text-[0.8rem]" style="--c: {{ $bank->brand_color ?? 'var(--brand-1)' }}">{{ $bank->mark() }}</span>
                <span class="font-semibold group-hover:underline underline-offset-4">{{ $bank->displayName() }}</span>
            </a>
            <div>
                <span class="chip mb-4">{{ $package->segmentLabel() }} пакет</span>
                <h1 class="font-display font-bold text-4xl sm:text-5xl leading-[1.04]">{{ $package->name }}</h1>
                @if ($package->description)<p class="muted mt-4 max-w-2xl">{{ $package->description }}</p>@endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($package->source_url || $bank->website)
                    <a href="{{ $package->source_url ?: $bank->website }}" target="_blank" rel="noopener nofollow" class="btn btn-outline w-fit">Подключить на сайте банка @include('partials.icon', ['i' => 'external', 'c' => 'w-4 h-4'])</a>
                @endif
                @include('partials.compare-toggle', ['type' => 'packages', 'item' => $package, 'wide' => true]){{-- A53 --}}
            </div>
        </div>
        <div class="card card-ink p-7 md:p-9 flex flex-col justify-between gap-6" data-reveal style="--d:.06s">
            <span class="text-sm font-semibold opacity-70">Стоимость</span>
            <p class="font-display font-semibold tabular leading-none text-[4.5rem]">
                @if ($package->monthly_fee !== null){{ number_format((float) $package->monthly_fee, 0, ',', "\u{202F}") }}<span class="text-[0.35em] opacity-60"> {{ $sign }}/мес</span>
                @elseif ($package->annual_fee !== null){{ number_format((float) $package->annual_fee, 0, ',', "\u{202F}") }}<span class="text-[0.35em] opacity-60"> {{ $sign }}/год</span>
                @else—@endif
            </p>
            <div class="text-sm space-y-1">
                @if ($package->monthly_fee !== null && $package->annual_fee !== null)<p class="opacity-70">или {{ money_format((float) $package->annual_fee, $sign) }} в год</p>@endif
                @if ($package->fee_note)<p class="font-semibold" style="color: #86efac">{{ $package->fee_note }}</p>@endif
            </div>
        </div>
    </section>

    <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-5 items-start">
        <section class="card p-6 md:p-7" data-reveal>
            <p class="kicker mb-1"><b>01</b> / состав</p>
            <h2 class="font-display font-bold text-2xl">Что входит</h2>
            @if ($package->perks)
                <ul class="grid gap-3 mt-5">@foreach ($package->perks as $perk)<li class="perk">@include('partials.icon', ['i' => 'check', 'c' => 'w-[18px] h-[18px]']) {{ $perk }}</li>@endforeach</ul>
            @else<p class="muted mt-4">Состав пакета не указан.</p>@endif
            @if ($package->conditions)
                <dl class="divide-y divide-[var(--line)] mt-6 pt-2 border-t border-[var(--line)]">
                    @foreach ($package->conditions as $k => $v)<div class="flex justify-between gap-4 py-2 text-sm"><dt class="muted">{{ $k }}</dt><dd class="font-semibold text-right">{{ is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE) }}</dd></div>@endforeach
                </dl>
            @endif
        </section>
        <section class="space-y-3" data-reveal>
            <p class="kicker px-1"><b>02</b> / карты пакета</p>
            @forelse ($offers as $i => $o)
                @include('cards._offer', ['o' => $o, 'i' => $i])
            @empty
                <div class="card p-10 text-center muted">Карты пакета не указаны.</div>
            @endforelse
        </section>
    </div>

    @include('blog._about', ['about' => $package, 'title' => 'О пакете в журнале', 'limit' => 3]) {{-- A37 --}}

    @if ($others->isNotEmpty())
        <section class="space-y-3" data-reveal>
            <h2 class="font-display font-bold text-2xl px-1">Другие пакеты</h2>
            <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($others->take(3) as $i => $p)@include('packages._card', ['p' => $p->loadMissing('cards'), 'i' => $i])@endforeach
            </div>
        </section>
    @endif
</div>
@endsection
