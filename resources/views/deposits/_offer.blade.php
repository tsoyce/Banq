{{-- A14 · Карточка предложения в каталоге. $o — App\Services\Deposits\DepositOffer, $first — подсветить лидера. --}}
@php
    $p = $o->product;
    $bank = $o->bank();
    $cmp = ['id' => $p->id, 'code' => \Illuminate\Support\Str::limit($bank->code, 4, ''), 'color' => $bank->brand_color ?? '#8b5cf6', 'name' => $bank->displayName()];
@endphp
<article class="card lift offer {{ ($first ?? false) ? 'is-first' : '' }}" @if (($i ?? 0) < 6) data-reveal style="--d: {{ 0.03 * ($i ?? 0) }}s" @endif>
    {{-- Банк и вклад --}}
    <a href="{{ route('deposits.show', $p) }}" class="flex items-center gap-4 min-w-0 group">
        <span class="bank-mark !w-12 !h-12 !text-[0.7rem]" style="--c: {{ $bank->brand_color ?? 'var(--brand-1)' }}">{{ $cmp['code'] }}</span>
        <span class="min-w-0">
            <span class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-[1.05rem] group-hover:underline underline-offset-4 decoration-2 decoration-[var(--brand-1)]">{{ $p->name }}</span>
                @if ($first ?? false)<span class="chip !bg-[var(--ink)] !text-[var(--ink-text)] !py-0.5">лидер</span>@endif
            </span>
            <span class="block text-sm muted truncate">{{ $bank->displayName() }} · {{ $p->type->label() }}</span>
        </span>
    </a>

    {{-- Условия --}}
    <div class="col-span-2 min-[900px]:col-span-1 flex flex-wrap gap-x-4 gap-y-1.5 order-last min-[900px]:order-none">
        <span class="feat {{ $p->can_replenish ? 'on' : 'off' }}">@include('partials.icon', ['i' => $p->can_replenish ? 'check' : 'x', 'c' => 'w-3.5 h-3.5']) пополнение</span>
        <span class="feat {{ $p->can_withdraw ? 'on' : 'off' }}">@include('partials.icon', ['i' => $p->can_withdraw ? 'check' : 'x', 'c' => 'w-3.5 h-3.5']) снятие</span>
        @if ($p->online_opening)<span class="feat on">@include('partials.icon', ['i' => 'phone', 'c' => 'w-3.5 h-3.5']) онлайн</span>@endif
        @if ($o->rate->term_months)<span class="feat">@include('partials.icon', ['i' => 'clock', 'c' => 'w-3.5 h-3.5']) {{ $o->rate->term_months }} мес</span>@endif
        @if ($p->min_amount)<span class="feat">от {{ number_format((float) $p->min_amount, 0, ',', ' ') }} {{ currency_symbol() }}</span>@endif
        @if ($o->income)<span class="feat on w-full">@include('partials.icon', ['i' => 'trend', 'c' => 'w-3.5 h-3.5']) +{{ number_format($o->income, 0, ',', ' ') }} {{ currency_symbol($o->rate->currency) }} <span class="faint font-medium">{{ $filters->amount ? '' : 'с 1 млн за год' }}</span></span>@endif
    </div>

    {{-- Ставка --}}
    <div class="text-right">
        <p class="rate-big text-[2.4rem] sm:text-[2.8rem]">{{ number_format((float) $o->rate->rate, 2, ',', '') }}<span class="text-[0.5em] muted align-top ml-0.5">%</span></p>
        <p class="text-xs muted mt-1">ГЭСВ <b class="tabular">{{ rate_format($o->rate->effective_rate) }}</b>@if ($o->ratesCount > 1) · {{ $o->ratesCount }} {{ plural($o->ratesCount, 'ставка', 'ставки', 'ставок') }}@endif</p>
    </div>

    {{-- Действия --}}
    <div class="hidden min-[900px]:flex items-center gap-2">
        @include('partials.fav', ['type' => 'deposit', 'id' => $p->id, 'on' => auth()->user()?->hasFavorite($p)])
        <button type="button" class="cmp-btn" x-data :class="$store.compare.has({{ $p->id }}) && 'is-on'" @click="$store.compare.toggle(@js($cmp))"
                :title="$store.compare.has({{ $p->id }}) ? 'Убрать из сравнения' : 'Добавить к сравнению'" aria-label="Сравнить">
            <span x-show="!$store.compare.has({{ $p->id }})">@include('partials.icon', ['i' => 'scale', 'c' => 'w-[18px] h-[18px]'])</span>
            <span x-show="$store.compare.has({{ $p->id }})" x-cloak>@include('partials.icon', ['i' => 'check', 'c' => 'w-[18px] h-[18px]'])</span>
        </button>
        <a href="{{ route('deposits.show', $p) }}" class="btn btn-primary !px-4" aria-label="Подробнее">@include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</a>
    </div>
    <div class="col-span-2 flex gap-2 min-[900px]:hidden order-last">
        <a href="{{ route('deposits.show', $p) }}" class="btn btn-primary flex-1 min-w-0">Подробнее</a>
        @include('partials.fav', ['type' => 'deposit', 'id' => $p->id, 'on' => auth()->user()?->hasFavorite($p)])
        <button type="button" class="btn btn-ghost" x-data :class="$store.compare.has({{ $p->id }}) && '!bg-[var(--ink)] !text-[var(--ink-text)]'" @click="$store.compare.toggle(@js($cmp))">
            @include('partials.icon', ['i' => 'scale', 'c' => 'w-4 h-4']) <span x-text="$store.compare.has({{ $p->id }}) ? 'В сравнении' : 'Сравнить'">Сравнить</span>
        </button>
    </div>
</article>
