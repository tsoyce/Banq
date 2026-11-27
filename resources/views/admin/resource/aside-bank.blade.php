{{-- A24 · Справа от формы банка: вклады банка и быстрые ссылки. --}}
@php($products = $bank->depositProducts()->withCount('currentRates')->withMax('currentRates as best', 'rate')->orderBy('name')->get())
<section class="card p-5">
    <div class="flex items-center justify-between gap-3 mb-3">
        <h2 class="font-display font-bold text-lg">Вклады банка <span class="faint tabular">{{ $products->count() }}</span></h2>
        <a href="{{ route('admin.resource.create', ['deposits', 'bank' => $bank->id]) }}" class="btn btn-ghost !py-1.5 text-sm">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Вклад</a>
    </div>
    <div class="divide-y divide-[var(--line)]">
        @forelse ($products as $p)
            <a href="{{ route('admin.resource.edit', ['deposits', $p->id]) }}" class="flex items-center gap-3 py-2.5 hover:bg-[var(--card-2)] -mx-2 px-2 rounded-lg">
                <span class="flex-1 min-w-0"><span class="font-semibold block truncate">{{ $p->name }}</span><span class="faint text-xs">{{ $p->type->label() }} · ставок {{ $p->current_rates_count }}{{ $p->is_active ? '' : ' · скрыт' }}</span></span>
                <b class="tabular">{{ rate_format($p->best) }}</b>
            </a>
        @empty
            <p class="muted text-sm py-4">Вкладов пока нет.</p>
        @endforelse
    </div>
</section>
<section class="card p-5 text-sm space-y-2">
    <a class="flex items-center gap-2 hover:underline" href="{{ route('admin.ratings.edit', $bank) }}">@include('partials.icon', ['i' => 'star', 'c' => 'w-4 h-4']) Авторская оценка и данные для рейтинга</a>
    <a class="flex items-center gap-2 hover:underline" href="{{ route('admin.exchange.export', ['entities' => ['banks', 'deposit_products', 'deposit_rates'], 'country' => $bank->country?->code, 'format' => 'json']) }}">@include('partials.icon', ['i' => 'download', 'c' => 'w-4 h-4']) Выгрузить данные страны банка (JSON)</a>
</section>
