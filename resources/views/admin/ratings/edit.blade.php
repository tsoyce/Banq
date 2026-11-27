{{-- A18 · Авторская оценка банка + данные для объективного рейтинга. --}}
@extends('layouts.admin', ['title' => 'Оценка · '.$bank->displayName()])
@php use App\Enums\RatingKind; $a = $score?->author; @endphp

@section('content')
<div class="flex items-center gap-4">
    <span class="bank-mark !w-12 !h-12" style="--c: {{ $bank->brand_color ?? 'var(--ink)' }}">{{ $bank->mark() }}</span>
    <div><h1 class="font-display font-bold text-3xl">{{ $bank->displayName() }}</h1><a href="{{ route('banks.show', $bank) }}" class="text-sm muted hover:underline" target="_blank">страница банка ↗</a></div>
</div>
<form method="POST" action="{{ route('admin.ratings.update', $bank) }}" class="grid xl:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start" x-data="{ score: {{ (float) old('score', $a?->score ?? 4) }} }">
    @csrf @method('PUT')
    <div class="card p-6 space-y-5">
        <h2 class="font-display font-bold text-xl">Авторская оценка</h2>
        <label class="field"><span class="flex justify-between">Оценка <b class="tabular text-2xl" x-text="score.toFixed(1).replace('.', ',') + ' / 5'"></b></span>
            <input type="range" name="score" min="0" max="5" step="0.1" x-model.number="score" class="range"></label>
        <label class="field"><span>Вывод</span><textarea name="verdict" class="input" maxlength="2000" placeholder="2–3 предложения: кому подходит банк и почему">{{ old('verdict', $a?->verdict) }}</textarea></label>
        <div class="grid sm:grid-cols-2 gap-4">
            <label class="field"><span>Плюсы <span class="muted font-normal">— по строке</span></span><textarea name="pros" class="input">{{ old('pros', implode("\n", $a?->pros ?? [])) }}</textarea></label>
            <label class="field"><span>Минусы <span class="muted font-normal">— по строке</span></span><textarea name="cons" class="input">{{ old('cons', implode("\n", $a?->cons ?? [])) }}</textarea></label>
        </div>
        <p class="text-xs muted">Оценка сохраняется на сегодняшнюю дату. Правка в другой день создаёт новую запись — старые остаются в истории и дают ▲▼ на сайте.</p>
    </div>
    <aside class="space-y-6">
        <div class="card p-6 space-y-4">
            <h2 class="font-display font-bold text-xl">Данные для объективного</h2>
            <div class="grid grid-cols-2 gap-3">
                <label class="field"><span>Активы, млрд ₸</span><input name="assets_bn" type="number" step="0.1" min="0" class="input tabular" value="{{ old('assets_bn', $bank->assets_bn) }}"></label>
                <label class="field"><span>Капитал, млрд ₸</span><input name="capital_bn" type="number" step="0.1" min="0" class="input tabular" value="{{ old('capital_bn', $bank->capital_bn) }}"></label>
                <label class="field"><span>Данные на дату</span><input name="financials_at" type="date" class="input" value="{{ old('financials_at', $bank->financials_at?->toDateString()) }}"></label>
                <label class="field"><span>Год основания</span><input name="founded_year" type="number" min="1850" max="{{ date('Y') }}" class="input tabular" value="{{ old('founded_year', $bank->founded_year) }}"></label>
            </div>
            <label class="field"><span>Лицензия {{ $bank->country?->regulatorName() ?? 'регулятора' }}</span><input name="license" class="input" value="{{ old('license', $bank->license) }}" placeholder="№ 1.2.47/193/38 от 03.02.2020"></label>
            <label class="switch"><span>Участник {{ $bank->country?->guaranteeName() ?? 'системы гарантирования вкладов' }}</span><input type="hidden" name="deposit_insured" value="0"><input type="checkbox" name="deposit_insured" value="1" class="sr-only" @checked(old('deposit_insured', $bank->deposit_insured))><i></i></label>
            @if ($score)
                <div class="plate p-4 space-y-2 text-sm">
                    <p class="font-semibold">Сейчас: {{ $score->objective['has_data'] ? score_format($score->objective['score'], 2) : 'нет ставок' }}</p>
                    @foreach ($criteria as $key => [$label])<div class="flex justify-between"><span class="muted">{{ $label }}</span><b class="tabular">{{ round(($score->objective['criteria'][$key] ?? 0) * 100) }}</b></div>@endforeach
                </div>
            @endif
        </div>
        @if ($history->isNotEmpty())
            <div class="card p-6">
                <h2 class="font-display font-bold text-lg mb-2">История авторской</h2>
                @foreach ($history as $h)<div class="list-row !py-2 justify-between text-sm"><span class="muted">{{ $h->period->format('d.m.Y') }}</span><b class="tabular">{{ score_format($h->score) }}</b></div>@endforeach
            </div>
        @endif
    </aside>
    @if ($errors->any())<ul class="error xl:col-span-2">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
    <div class="xl:col-span-2 flex gap-3"><a href="{{ route('admin.ratings.index') }}" class="btn btn-ghost">Назад</a><button class="btn btn-primary">Сохранить</button></div>
</form>
@endsection
