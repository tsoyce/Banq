{{-- A40 · Админка → Курсы валют: источник по стране, статус обновления, выбор и порядок валют. --}}
@extends('layouts.admin', ['title' => 'Курсы валют'])

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="font-display font-bold text-3xl">Курсы валют</h1>
        <p class="muted mt-1">Официальные курсы центробанков. Обновляются сами: каждый час по расписанию и при первом заходе за день, если курса на сегодня ещё нет.</p>
    </div>
</div>
@error('sync')<p class="error mt-4">{{ $message }}</p>@enderror

<form id="fx-settings" method="POST" action="{{ route('admin.currency.update') }}" class="space-y-4 mt-6">
    @csrf @method('PUT')
    <label class="glass p-4 flex items-center gap-3 text-sm font-semibold cursor-pointer">
        <input type="checkbox" name="home_card" value="1" @checked($homeCard) class="w-4 h-4 accent-[var(--ink)]"> Показывать карточку «Курсы валют» на главной
    </label>

    @foreach ($countries as $row)
        @php($c = $row['country'])
        <section class="glass p-6 space-y-5">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="font-display font-bold text-xl flex items-center gap-2">{{ $c->flag }} {{ $c->name }}</h2>
                @if ($row['provider'])
                    <a href="{{ $row['provider']->url() }}" target="_blank" rel="noopener" class="chip">{{ $row['provider']->name() }} @include('partials.icon', ['i' => 'external', 'c' => 'w-3 h-3'])</a>
                @else
                    <span class="chip">Источника курсов для страны пока нет</span>
                @endif
            </div>

            @if ($row['provider'])
                @php($st = $row['status'])
                <div class="grid sm:grid-cols-3 gap-3 text-sm">
                    <div class="plate p-4"><p class="muted text-xs">Курс на дату</p><p class="font-display font-semibold text-2xl mt-1">{{ $row['date']?->translatedFormat('j F Y') ?? '—' }}</p></div>
                    <div class="plate p-4"><p class="muted text-xs">Последняя попытка</p>
                        <p class="font-semibold mt-1">@if ($st){{ \Illuminate\Support\Carbon::parse($st['at'])->setTimezone($c->timezone ?: config('app.timezone'))->translatedFormat('j F, H:i') }}@else ещё не было @endif</p>
                        @if ($st)<p class="text-xs mt-0.5" style="color: var({{ $st['ok'] ? '--ok' : '--bad' }})">{{ $st['ok'] ? 'успешно · '.$st['count'].' валют' : 'ошибка: '.$st['error'] }}</p>@endif
                    </div>
                    <div class="plate p-4"><p class="muted text-xs">История в базе</p><p class="font-display font-semibold text-2xl mt-1 tabular">{{ $row['days'] }} <span class="text-sm muted font-sans">{{ plural($row['days'], 'день', 'дня', 'дней') }}</span></p></div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button form="sync-{{ $c->id }}" class="btn btn-primary">@include('partials.icon', ['i' => 'refill', 'c' => 'w-4 h-4']) Обновить сейчас</button>
                    <button form="sync-{{ $c->id }}" name="days" value="30" class="btn btn-ghost">Загрузить историю за 30 дней</button>
                </div>

                {{-- Выбор валют: клик по чипу добавляет/убирает, стрелки — порядок в карточке и ленте --}}
                <div x-data="{ all: @js($row['available']), picked: @js($row['codes']),
                               toggle(k) { this.picked = this.picked.includes(k) ? this.picked.filter(x => x !== k) : [...this.picked, k]; },
                               move(i, d) { const j = i + d; if (j < 0 || j >= this.picked.length) return; [this.picked[i], this.picked[j]] = [this.picked[j], this.picked[i]]; } }" class="space-y-3">
                    <p class="text-sm font-semibold">Валюты в карточке и ленте <span class="muted font-normal">— по порядку</span></p>
                    <ol class="flex flex-wrap gap-2">
                        <template x-for="(k, i) in picked" :key="k">
                            <li class="chip !py-1.5 !pl-3 !pr-1.5 gap-1" style="background: var(--ink); color: var(--ink-text)">
                                <b x-text="k"></b>
                                <button type="button" class="px-1 opacity-60 hover:opacity-100" @click="move(i, -1)" aria-label="Левее">←</button>
                                <button type="button" class="px-1 opacity-60 hover:opacity-100" @click="move(i, 1)" aria-label="Правее">→</button>
                                <button type="button" class="px-1 opacity-60 hover:opacity-100" @click="toggle(k)" aria-label="Убрать">✕</button>
                                <input type="hidden" name="codes[{{ $c->code }}][]" :value="k">
                            </li>
                        </template>
                        <li x-show="!picked.length" class="text-sm muted">Ничего не выбрано — будут показаны валюты по умолчанию ({{ implode(', ', config('banq.fx.defaults')[$c->code] ?? []) }}).</li>
                    </ol>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="(name, k) in all" :key="k">
                            <button type="button" class="chip !py-1 hover:!bg-[var(--card-2)]" :class="picked.includes(k) && 'opacity-40'" @click="toggle(k)" :title="name"><span x-text="k"></span></button>
                        </template>
                    </div>
                </div>

                @if ($row['rows']->isNotEmpty())
                    <details class="plate p-4">
                        <summary class="cursor-pointer text-sm font-semibold">Все курсы на {{ $row['date']->translatedFormat('j F') }} · {{ $row['rows']->count() }}</summary>
                        <div class="overflow-x-auto mt-3">
                            <table class="table text-sm">
                                <thead><tr><th>Код</th><th>Валюта</th><th class="!text-right">За</th><th class="!text-right">Курс, {{ $c->symbol() }}</th><th class="!text-right">Изм.</th></tr></thead>
                                <tbody>
                                @foreach ($row['rows'] as $r)
                                    <tr><td class="font-semibold">{{ \App\Support\CurrencyInfo::flag($r->currency) }} {{ $r->currency }}</td><td class="muted">{{ \App\Support\CurrencyInfo::name($r->currency, $r->name) }}</td>
                                        <td class="text-right tabular muted">{{ $r->nominal }}</td><td class="text-right tabular font-semibold">{{ number_format($r->rate, 2, ',', ' ') }}</td>
                                        <td class="text-right tabular" style="color: var({{ $r->change > 0 ? '--ok' : ($r->change < 0 ? '--bad' : '--muted') }})">{{ $r->change === null ? '—' : ($r->change > 0 ? '+' : '').number_format($r->change, 2, ',', ' ') }}</td></tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            @endif
        </section>
    @endforeach

    <button class="btn btn-primary">Сохранить</button>
</form>

@foreach ($countries as $row)
    @if ($row['provider'])
        <form id="sync-{{ $row['country']->id }}" method="POST" action="{{ route('admin.currency.sync', $row['country']) }}" class="hidden">@csrf</form>
    @endif
@endforeach
@endsection
