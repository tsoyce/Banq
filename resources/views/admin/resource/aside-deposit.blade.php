{{-- A24 · Справа от формы вклада: добавить ставку (через движок обмена — старая закроется), действующие ставки, история. --}}
@php
    $country = $product->bank?->country;
    $current = $product->rates->whereNull('valid_to');
    $history = $product->rates->whereNotNull('valid_to')->take(30);
@endphp
<section class="card p-5" x-data="{ open: {{ $errors->has('rate') ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between gap-3">
        <h2 class="font-display font-bold text-lg">Действующие ставки <span class="faint tabular">{{ $current->count() }}</span></h2>
        <button type="button" class="btn btn-primary !py-1.5 text-sm" @click="open = !open">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Новая ставка</button>
    </div>
    <form method="POST" action="{{ route('admin.resource.store', 'rates') }}" class="plate p-4 mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3" x-show="open" x-cloak>
        @csrf
        <input type="hidden" name="deposit_product_id" value="{{ $product->id }}">
        <input type="hidden" name="_back" value="{{ url()->current() }}">
        <label class="field"><span>Валюта</span>
            <select name="currency" class="input !bg-[var(--card)]">@foreach ($country?->currencyList() ?? ['KZT'] as $cur)<option @selected(old('currency') === $cur)>{{ $cur }}</option>@endforeach</select></label>
        <label class="field"><span>Срок, мес.</span><input name="term_months" type="number" min="0" class="input !bg-[var(--card)]" value="{{ old('term_months') }}" placeholder="любой"></label>
        <label class="field"><span>Сумма от</span><input name="amount_from" type="number" step="any" min="0" class="input !bg-[var(--card)]" value="{{ old('amount_from') }}" placeholder="любая"></label>
        <label class="field"><span>С даты</span><input name="valid_from" type="date" class="input !bg-[var(--card)]" value="{{ old('valid_from', today()->toDateString()) }}"></label>
        <label class="field"><span>Ставка, %</span><input name="rate" type="number" step="any" min="0" class="input !bg-[var(--card)]" value="{{ old('rate') }}" required></label>
        <label class="field"><span>ГЭСВ, %</span><input name="effective_rate" type="number" step="any" min="0" class="input !bg-[var(--card)]" value="{{ old('effective_rate') }}"></label>
        <div class="col-span-2 flex items-end"><button class="btn btn-primary w-full">Сохранить ставку</button></div>
        @error('rate')<p class="error text-xs col-span-full">{{ $message }}</p>@enderror
        <p class="faint text-xs col-span-full">Если ставка с той же валютой, сроком и порогом суммы уже действует — она закроется этой датой, история сохранится.</p>
    </form>

    <div class="overflow-x-auto mt-3">
        <table class="table text-sm">
            <thead><tr><th>Валюта</th><th>Срок</th><th>Сумма от</th><th>Ставка</th><th>ГЭСВ</th><th>С</th><th>Источник</th></tr></thead>
            <tbody>
            @forelse ($current as $r)
                <tr><td class="font-mono">{{ $r->currency }}</td><td>{{ $r->term_months ? $r->term_months.' мес' : 'любой' }}</td><td class="tabular">{{ $r->amount_from === null ? '—' : money_format($r->amount_from, $country?->symbol()) }}</td>
                    <td><b class="tabular">{{ rate_format($r->rate) }}</b></td><td class="tabular">{{ rate_format($r->effective_rate) }}</td><td class="tabular faint">{{ $r->valid_from->format('d.m.Y') }}</td><td class="faint">{{ $r->source }}</td></tr>
            @empty
                <tr><td colspan="7" class="muted text-center py-6">Ставок нет — вклад не виден в каталоге.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@if ($history->isNotEmpty())
    <section class="card p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-display font-bold text-lg">История</h2>
            <a href="{{ route('admin.resource.index', ['rates', 'product' => $product->id]) }}" class="muted text-sm hover:underline">все записи →</a>
        </div>
        <div class="overflow-x-auto mt-3">
            <table class="table text-sm">
                <thead><tr><th>Валюта</th><th>Срок</th><th>Ставка</th><th>Действовала</th></tr></thead>
                <tbody>
                @foreach ($history as $r)
                    <tr><td class="font-mono">{{ $r->currency }}</td><td>{{ $r->term_months ? $r->term_months.' мес' : 'любой' }}</td><td class="tabular">{{ rate_format($r->rate) }}</td><td class="tabular faint">{{ $r->valid_from->format('d.m.Y') }} → {{ $r->valid_to->format('d.m.Y') }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
