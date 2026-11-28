{{-- A33 · Строка каталога карт. $o — App\Services\Cards\CardOffer, $first — подсветить лидера. --}}
@php
    $c = $o->card;
    $bank = $c->bank;
    $href = route('cards.show', $c).(($offerFrom ?? null) ? '?from='.$offerFrom : ''); // A54 · откуда пришли — для хлебных крошек
    $cmp = ['id' => $c->id, 'code' => $bank->mark(), 'color' => $c->color ?? $bank->brand_color ?? '#8b5cf6', 'name' => $c->name];
@endphp
<article class="card lift card-offer {{ ($first ?? false) ? 'is-first' : '' }}" @if (($i ?? 0) < 6) data-reveal style="--d: {{ 0.03 * ($i ?? 0) }}s" @endif>
    <a href="{{ $href }}" class="block" aria-label="{{ $c->name }}">@include('partials.plastic', ['card' => $c])</a>

    <div class="min-w-0 space-y-2">
        <a href="{{ $href }}" class="group block">
            <span class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-[1.05rem] group-hover:underline underline-offset-4 decoration-2 decoration-[var(--brand-1)]">{{ $c->name }}</span>
                @if ($first ?? false)<span class="chip !bg-[var(--ink)] !text-[var(--ink-text)] !py-0.5">лидер</span>@endif
            </span>
            <span class="block text-sm muted truncate">{{ $bank->displayName() }} · {{ $c->kind->label() }}@if ($c->isBusiness()) · для бизнеса@endif @if ($c->segmentLabel()) · {{ mb_strtolower($c->segmentLabel()) }}@endif</span>
        </a>
        <div class="flex flex-wrap gap-1.5">
            @foreach ($c->networks() as $n)
                <span class="chip !py-0.5 !pl-1.5 gap-1.5"><span class="ps-tile !w-7 !h-5 !rounded-md !text-[10px]" style="--ps: {{ $n['system']->gradient() }}">@include('partials.ps-mark', ['ps' => $n['system']])</span>{{ $n['tier'] ? \Illuminate\Support\Str::after($n['tier']->name, $n['system']->name.' ') : $n['system']->displayName() }}</span>
            @endforeach
            @if ($c->online_opening)<span class="feat on">@include('partials.icon', ['i' => 'phone', 'c' => 'w-3.5 h-3.5']) онлайн</span>@endif
            @if ($c->delivery)<span class="feat on">@include('partials.icon', ['i' => 'check', 'c' => 'w-3.5 h-3.5']) доставка</span>@endif
        </div>
        <div class="key-nums pt-1">
            @foreach ($c->keyFigures() as [$figLabel, $figValue, $figHi])
                <div><b class="{{ $figHi ? 'text-grad' : '' }}">{{ $figValue }}</b><span>{{ $figLabel }}</span></div>
            @endforeach
        </div>
    </div>


    <div class="col-span-2 min-[900px]:col-span-1 flex min-[900px]:flex-col items-center min-[900px]:items-end gap-3 justify-between">
        <span class="inline-flex items-center gap-2 mr-1" title="Оценка Banq — из данных, относительно рынка">
            <span class="font-display font-bold text-2xl tabular leading-none">{{ score_format($o->score) }}</span>
            <span class="text-[11px] muted leading-tight">оценка<br>Banq</span>
        </span>
        <span class="flex items-center gap-2">
            @include('partials.fav', ['type' => 'card', 'id' => $c->id, 'on' => auth()->user()?->hasFavorite($c), 'wide' => false])
            <button type="button" class="cmp-btn" x-data :class="$store.cardCompare.has({{ $c->id }}) && 'is-on'" @click="$store.cardCompare.toggle(@js($cmp))"
                    :title="$store.cardCompare.has({{ $c->id }}) ? 'Убрать из сравнения' : 'Добавить к сравнению'" aria-label="Сравнить">
                <span x-show="!$store.cardCompare.has({{ $c->id }})">@include('partials.icon', ['i' => 'scale', 'c' => 'w-[18px] h-[18px]'])</span>
                <span x-show="$store.cardCompare.has({{ $c->id }})" x-cloak>@include('partials.icon', ['i' => 'check', 'c' => 'w-[18px] h-[18px]'])</span>
            </button>
            <a href="{{ $href }}" class="btn btn-primary !px-4" aria-label="Подробнее">@include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</a>
        </span>
    </div>
</article>
