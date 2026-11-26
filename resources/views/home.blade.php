{{-- A10 · Главная-дашборд: герой, пульс рынка, топ ставок, калькулятор, анонсы разделов. --}}
@extends('layouts.app')

@section('content')
@php($best = $pulse->max('rate'))

{{-- Герой --}}
<section class="relative mx-auto max-w-7xl px-4 sm:px-6 pt-16 md:pt-24 pb-10 grid lg:grid-cols-[1.25fr_1fr] gap-12 items-center">
    <div class="space-y-8">
        <span class="chip" data-reveal><span class="dot"></span> Ставки банков Казахстана · {{ $updatedAt ? 'обновлено '.\Illuminate\Support\Carbon::parse($updatedAt)->translatedFormat('j F') : 'скоро первые данные' }}</span>
        <h1 class="font-display font-black text-4xl sm:text-6xl xl:text-7xl leading-[1.02]" data-reveal style="--d:.08s">
            Деньги, которые<br><span class="text-grad">работают ярче</span>
        </h1>
        <p class="muted text-lg max-w-xl leading-relaxed" data-reveal style="--d:.16s">
            {{ settings('site.description') }}
        </p>
        <div class="flex flex-wrap gap-3" data-reveal style="--d:.24s">
            <a href="#deposits" class="btn btn-primary">Подобрать вклад
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
            <a href="#calc" class="btn btn-ghost">Посчитать доход</a>
        </div>
        <dl class="grid grid-cols-3 gap-4 max-w-lg pt-4" data-reveal style="--d:.32s">
            <div><dt class="faint text-xs uppercase tracking-widest font-bold">Банков</dt><dd class="font-display text-3xl font-bold mt-1" x-data x-count="{{ $banksCount }}">{{ $banksCount }}</dd></div>
            <div><dt class="faint text-xs uppercase tracking-widest font-bold">Ставок</dt><dd class="font-display text-3xl font-bold mt-1" x-data x-count="{{ $ratesCount }}">{{ $ratesCount }}</dd></div>
            <div><dt class="faint text-xs uppercase tracking-widest font-bold">Максимум</dt><dd class="font-display text-3xl font-bold mt-1 text-grad">{{ $best ? rate_format($best) : '—' }}</dd></div>
        </dl>
    </div>

    <div class="relative grid place-items-center" data-reveal style="--d:.2s">
        <div class="orb"></div>
        <div class="glass glow-edge absolute inset-0 m-auto h-fit w-[min(280px,64vw)] p-6 text-center">
            <p class="faint text-xs uppercase tracking-[0.2em] font-bold">Лучшая ставка сейчас</p>
            <p class="font-display font-black text-6xl mt-3 text-grad tabular">
                @if ($best)<span x-data x-count="{{ number_format((float) $best, 2, '.', '') }}">{{ $best }}</span>%@else—@endif
            </p>
            @php($leader = $pulse->firstWhere('rate', $best))
            <p class="muted mt-3 text-sm">{{ $leader['bank']?->displayName() ?? 'Загрузите ставки в админке' }}@if ($leader && $leader['rate']) · {{ mb_strtolower($leader['type']->label()) }}@endif</p>
        </div>
    </div>
</section>

{{-- Бегущая строка --}}
@if ($top->isNotEmpty())
<section class="ticker py-6 border-y border-[var(--border)] my-8" aria-label="Ставки">
    <div class="ticker-track">
        @foreach ([1, 2] as $_)
            @foreach ($top as $r)
                <span class="flex items-center gap-3 whitespace-nowrap text-sm">
                    <span class="font-bold">{{ $r->product->bank->displayName() }}</span>
                    <span class="faint">{{ $r->product->type->label() }}</span>
                    <span class="tabular font-bold" style="color: var(--ok)">{{ rate_format($r->rate) }}</span>
                </span>
            @endforeach
        @endforeach
    </div>
</section>
@endif

{{-- Пульс рынка --}}
<section id="deposits" class="scroll-mt-28 mx-auto max-w-7xl px-4 sm:px-6 pt-16 space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4" data-reveal>
        <div>
            <p class="faint text-xs uppercase tracking-[0.2em] font-bold">Пульс рынка</p>
            <h2 class="font-display font-bold text-3xl sm:text-4xl mt-2">Максимальные ставки по типам вкладов</h2>
        </div>
        <p class="muted text-sm max-w-sm">Классификация КФГД: от того, можно ли пополнять и снимать, зависит предельная ставка.</p>
    </div>
    <div class="grid md:grid-cols-3 gap-5">
        @foreach ($pulse as $i => $p)
            <article class="glass glow-edge lift p-7 space-y-6" data-reveal style="--d: {{ $i * 0.08 }}s">
                <div class="flex items-center justify-between">
                    <span class="chip">{{ $p['type']->hint() }}</span>
                    <span class="faint text-xs font-mono">0{{ $i + 1 }}</span>
                </div>
                <div>
                    <h3 class="font-display font-bold text-xl">{{ $p['type']->label() }}</h3>
                    <p class="font-display font-black text-5xl mt-3 tabular {{ $p['rate'] ? 'text-grad' : 'faint' }}">
                        @if ($p['rate'])<span x-data x-count="{{ number_format((float) $p['rate'], 2, '.', '') }}">{{ $p['rate'] }}</span>%@else—@endif
                    </p>
                </div>
                <div class="flex items-center gap-3 pt-2 border-t border-[var(--border)]">
                    @if ($p['bank'])
                        <span class="bank-mark" style="--c: {{ $p['bank']->brand_color ?? 'var(--a2)' }}">{{ \Illuminate\Support\Str::limit($p['bank']->code, 4, '') }}</span>
                        <span class="text-sm font-semibold">{{ $p['bank']->displayName() }}</span>
                    @else
                        <span class="text-sm faint">Данных пока нет</span>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>

{{-- Топ ставок + калькулятор --}}
<section id="top" class="mx-auto max-w-7xl px-4 sm:px-6 pt-20 grid lg:grid-cols-[1.5fr_1fr] gap-6 scroll-mt-28">
    <div class="glass p-2 sm:p-4 overflow-x-auto" data-reveal>
        <div class="px-4 pt-3 pb-4 flex items-center justify-between">
            <h2 class="font-display font-bold text-2xl">Топ ставок</h2>
            <span class="chip">KZT</span>
        </div>
        <table class="table">
            <thead><tr><th>Банк</th><th>Тип</th><th class="!text-right">Ставка</th></tr></thead>
            <tbody>
            @forelse ($top as $r)
                <tr>
                    <td><div class="flex items-center gap-3">
                        <span class="bank-mark" style="--c: {{ $r->product->bank->brand_color ?? 'var(--a2)' }}">{{ \Illuminate\Support\Str::limit($r->product->bank->code, 4, '') }}</span>
                        <span class="font-semibold">{{ $r->product->bank->displayName() }}</span>
                    </div></td>
                    <td class="muted text-sm">{{ $r->product->type->label() }}</td>
                    <td class="text-right tabular font-bold text-lg">{{ rate_format($r->rate) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center py-12 muted">Ставок пока нет. Загрузите Excel в админке → Импорт.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div id="calc" class="glass glow-edge p-7 space-y-6" x-data="calc({{ (float) ($best ?: 15) }})" data-reveal style="--d:.1s">
        <div>
            <p class="faint text-xs uppercase tracking-[0.2em] font-bold">Калькулятор</p>
            <h2 class="font-display font-bold text-2xl mt-2">Сколько принесёт вклад</h2>
        </div>
        <label class="field"><span>Сумма, ₸ — <b class="tabular" x-text="money(amount)"></b></span>
            <input type="range" min="50000" max="50000000" step="50000" x-model.number="amount" class="w-full accent-[var(--a2)]"></label>
        <label class="field"><span>Срок — <b x-text="months + ' мес.'"></b></span>
            <input type="range" min="1" max="36" x-model.number="months" class="w-full accent-[var(--a2)]"></label>
        <label class="field"><span>Ставка, % годовых</span>
            <input type="number" step="0.01" x-model.number="rate" class="input tabular"></label>
        <label class="flex items-center gap-3 text-sm font-semibold"><input type="checkbox" x-model="cap" class="accent-[var(--a2)] w-4 h-4"> Ежемесячная капитализация</label>
        <div class="pt-5 border-t border-[var(--border)]">
            <p class="muted text-sm">Доход за срок</p>
            <p class="font-display font-black text-4xl text-grad tabular mt-1" x-text="money(income)"></p>
            <p class="faint text-xs mt-2">Без учёта налогов. Расчёт ориентировочный.</p>
        </div>
    </div>
</section>

{{-- Анонсы будущих разделов --}}
<section id="ratings" class="scroll-mt-28 mx-auto max-w-7xl px-4 sm:px-6 pt-20">
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
        @foreach ([
            ['Народный рейтинг', 'Оценки и отзывы клиентов банков', 'var(--a3)'],
            ['Рейтинг TSOY.IN', 'Авторский взгляд на банки и продукты', 'var(--a2)'],
            ['Карты и кредиты', 'Дебетовые, кредитные, пакеты услуг', 'var(--a1)'],
            ['Платёжные системы', 'Visa, Mastercard, UnionPay и другие', 'var(--a4)'],
        ] as $i => [$name, $text, $color])
            <article id="{{ $i === 2 ? 'soon' : '' }}" class="glass glow-edge lift p-6 space-y-3" data-reveal style="--d: {{ $i * 0.06 }}s">
                <span class="block w-10 h-1.5 rounded-full" style="background: {{ $color }}; box-shadow: 0 0 20px {{ $color }}"></span>
                <h3 class="font-display font-bold text-lg">{{ $name }}</h3>
                <p class="muted text-sm">{{ $text }}</p>
                <span class="chip">скоро</span>
            </article>
        @endforeach
    </div>
</section>
@endsection
