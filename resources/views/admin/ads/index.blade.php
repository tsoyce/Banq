{{-- A51 · Админка → Реклама → Обзор и настройки. A50 · ads.txt. --}}
@extends('layouts.admin', ['title' => 'Реклама'])

@php
    $max = max(1, $days->max('v'));
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $ctr = fn ($v, $c) => $v ? number_format($c / $v * 100, 2, ',', '').'%' : '—';
@endphp

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="font-display font-bold text-3xl">Реклама</h1>
        <p class="muted mt-1 max-w-2xl">Свои рекламные места без посредников. Показ засчитывается, когда объявление видно наполовину хотя бы секунду; сотрудники и боты не считаются.</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.resource.create', 'ad-campaigns') }}" class="btn btn-primary">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Кампания</a>
        <a href="{{ route('admin.resource.index', 'ad-slots') }}" class="btn btn-ghost">Места</a>
    </div>
</div>

<section class="grid sm:grid-cols-3 gap-4">
    @foreach ([['Показов за 30 дней', $fmt($total['v'])], ['Кликов', $fmt($total['c'])], ['CTR', $ctr($total['v'], $total['c'])]] as [$k, $v])
        <div class="card p-5"><p class="label">{{ $k }}</p><p class="num-hero text-4xl tabular mt-2">{{ $v }}</p></div>
    @endforeach
</section>

<section class="card p-6">
    <div class="flex items-center justify-between gap-3 mb-4"><h2 class="font-display font-bold text-lg">По дням</h2><span class="text-xs faint">показы · клики</span></div>
    <div class="flex items-end gap-1 h-32" role="img" aria-label="Показы по дням за 30 дней">
        @foreach ($days as $d)
            <div class="flex-1 rounded-t-md min-h-[2px]" style="height: {{ max(2, round($d['v'] / $max * 100)) }}%; background: {{ $d['v'] ? 'linear-gradient(180deg, var(--brand-1), var(--brand-2))' : 'var(--card-2)' }}" title="{{ \Illuminate\Support\Carbon::parse($d['date'])->format('d.m') }}: {{ $fmt($d['v']) }} показов, {{ $d['c'] }} кликов"></div>
        @endforeach
    </div>
    <div class="flex justify-between text-xs faint mt-2 tabular"><span>{{ \Illuminate\Support\Carbon::parse($days->first()['date'])->format('d.m') }}</span><span>сегодня</span></div>
</section>

<div class="grid xl:grid-cols-2 gap-4 items-start">
    <section class="card p-6">
        <h2 class="font-display font-bold text-lg mb-3">Кампании</h2>
        @forelse ($campaigns as $c)
            <a href="{{ route('admin.resource.edit', ['ad-campaigns', $c->id]) }}" class="flex items-center gap-3 py-2.5 border-t border-[var(--line)] first:border-0 hover:opacity-80">
                <span class="min-w-0 flex-1"><b class="block truncate">{{ $c->name }}</b><span class="text-xs muted">{{ $c->stateLabel() }} · {{ $c->creatives_count }} {{ plural($c->creatives_count, 'объявление', 'объявления', 'объявлений') }}</span></span>
                <span class="text-sm tabular text-right">{{ $fmt($c->impressions) }}<span class="block text-xs faint">{{ $c->clicks }} · {{ $ctr($c->impressions, $c->clicks) }}</span></span>
            </a>
        @empty
            <p class="muted text-sm">Кампаний пока нет. Создайте кампанию, добавьте в неё объявление — и оно появится в подходящих местах.</p>
        @endforelse
    </section>

    <section class="card p-6">
        <h2 class="font-display font-bold text-lg mb-3">Места</h2>
        @foreach ($slots as $s)
            @php($st = $bySlot[$s->key] ?? null)
            <a href="{{ route('admin.resource.edit', ['ad-slots', $s->id]) }}" class="flex items-center gap-3 py-2.5 border-t border-[var(--line)] first:border-0 hover:opacity-80 {{ $s->is_active ? '' : 'opacity-50' }}">
                <span class="min-w-0 flex-1"><b class="block truncate">{{ $s->name }}</b><span class="text-xs muted"><span class="font-mono">{{ $s->key }}</span> · {{ $s->width }}×{{ $s->height }}{{ $s->is_active ? '' : ' · выключено' }}{{ filled($s->fallback_code) ? ' · есть код сети' : '' }}</span></span>
                <span class="text-sm tabular text-right">{{ $fmt($st->v ?? 0) }}<span class="block text-xs faint">{{ (int) ($st->c ?? 0) }} кликов</span></span>
            </a>
        @endforeach
    </section>
</div>

<form method="POST" action="{{ route('admin.ads.update') }}" class="card p-6 grid sm:grid-cols-2 gap-4">
    @csrf @method('PUT')
    <h2 class="sm:col-span-2 font-display font-bold text-lg">Как показывать</h2>
    <label class="switch sm:col-span-2"><span>Реклама на сайте включена<span class="block text-xs muted font-normal">Выключите — пропадут и свои объявления, и коды сетей в местах</span></span>
        <input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" class="sr-only" @checked(settings('ads.enabled', true))><i></i></label>
    <label class="field"><span>Мест с рекламой на странице, не больше</span><input name="max_per_page" type="number" min="0" max="6" class="input" value="{{ old('max_per_page', settings('ads.max_per_page', 3)) }}"><span class="faint text-xs">2–3 — заметно, но не раздражает</span></label>
    <label class="field"><span>Карточка в ленте журнала — после публикации №</span><input name="feed_position" type="number" min="0" max="30" class="input" value="{{ old('feed_position', settings('ads.feed_position', 6)) }}"><span class="faint text-xs">0 — не показывать в ленте</span></label>
    <label class="field"><span>Метка</span><input name="label" class="input" value="{{ old('label', settings('ads.label', 'Реклама')) }}" maxlength="40"></label>
    <label class="switch self-end py-2"><span>Не показывать рекламу сотрудникам</span>
        <input type="hidden" name="hide_for_staff" value="0"><input type="checkbox" name="hide_for_staff" value="1" class="sr-only" @checked(settings('ads.hide_for_staff', false))><i></i></label>
    @if (auth()->user()->role === 'admin')
        <label class="field sm:col-span-2"><span>ads.txt</span>
            <textarea name="ads_txt" rows="4" class="input font-mono text-xs" spellcheck="false" placeholder="google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0">{{ old('ads_txt', settings('ads.txt')) }}</textarea>
            <span class="faint text-xs">Открывается по адресу <a href="{{ url('/ads.txt') }}" target="_blank" class="underline underline-offset-4">{{ url('/ads.txt') }}</a>.@if ($autoAdsTxt) Из «Кодов и счётчиков» добавится само: <span class="font-mono">{{ implode(' · ', $autoAdsTxt) }}</span>@endif</span></label>
    @endif
    @if ($errors->any())<ul class="sm:col-span-2 error space-y-1 text-sm">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
    <div class="sm:col-span-2"><button class="btn btn-primary">Сохранить</button></div>
</form>
@endsection
