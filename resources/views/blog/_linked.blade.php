{{-- A37 · «О ком материал»: связанные банки, вклады, карты, пакеты, платёжные системы и уровни.
     Отмеченные редактором — первыми, каскадные (карта → банк, пакет, система, уровень) — следом. --}}
@php
    $routes = [
        'bank' => fn ($m) => route('banks.show', $m), 'deposit' => fn ($m) => route('deposits.show', $m), 'card' => fn ($m) => route('cards.show', $m),
        'package' => fn ($m) => route('packages.show', $m), 'payment_system' => fn ($m) => route('payment-systems.show', $m),
        'card_tier' => fn ($m) => route('payment-systems.show', $m->paymentSystem).'#'.$m->slug,
    ];
    $items = collect($linked)->flatMap(fn ($list, $type) => $list->map(fn ($m) => ['type' => $type, 'm' => $m, 'primary' => (bool) $m->pivot->is_primary]))
        ->filter(fn ($x) => match ($x['type']) {
            'bank' => $x['m']->status === 'active',
            'deposit', 'card', 'package' => $x['m']->is_active && $x['m']->bank?->status === 'active',
            'payment_system' => $x['m']->is_active,
            'card_tier' => (bool) $x['m']->paymentSystem?->is_active,
            default => true,
        })
        ->sortBy(fn ($x) => ($x['primary'] ? 0 : 10) + array_search($x['type'], array_keys(\App\Models\Post::LINK_TYPES), true))->values();
@endphp
@if ($items->isNotEmpty())
<section class="card p-5 sm:p-6" data-reveal>
    <div class="flex items-center justify-between gap-3 mb-4">
        <h2 class="font-display font-bold text-xl">В материале</h2>
        <span class="text-xs muted">{{ $items->count() }} {{ plural($items->count(), 'связь', 'связи', 'связей') }}</span>
    </div>
    <div class="linked-grid">
        @foreach ($items as $x)
            @php($m = $x['m'])
            <a href="{{ $routes[$x['type']]($m) }}" class="linked-item {{ $x['primary'] ? 'is-primary' : '' }}">
                @switch($x['type'])
                    @case('bank')<span class="bank-mark !w-10 !h-10" style="--c: {{ $m->brand_color ?? 'var(--brand-1)' }}">{{ $m->mark() }}</span>@break
                    @case('card')<span class="linked-plastic" style="background: {{ $m->face() }}"></span>@break
                    @case('payment_system')<span class="linked-ps" style="background: {{ $m->gradient() }}">{{ $m->mark() }}</span>@break
                    @case('card_tier')<span class="linked-ps" style="background: {{ $m->paymentSystem?->gradient() }}">{{ \Illuminate\Support\Str::limit(trim(\Illuminate\Support\Str::after($m->name, $m->paymentSystem?->name ?? '')) ?: $m->name, 4, '') }}</span>@break
                    @default<span class="icon-tile !w-10 !h-10">@include('partials.icon', ['i' => ['deposit' => 'piggy', 'package' => 'gift'][$x['type']] ?? 'box', 'c' => 'w-[18px] h-[18px]'])</span>
                @endswitch
                <span class="min-w-0">
                    <span class="text-[0.7rem] font-semibold uppercase tracking-wider muted block">{{ \App\Models\Post::LINK_LABELS[$x['type']] }}</span>
                    <b class="block truncate">{{ in_array($x['type'], ['bank', 'payment_system'], true) ? $m->displayName() : $m->name }}</b>
                    @if (in_array($x['type'], ['deposit', 'card', 'package'], true))<span class="text-xs muted block truncate">{{ $m->bank?->displayName() }}</span>@endif
                </span>
                @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4 muted ml-auto shrink-0'])
            </a>
        @endforeach
    </div>
</section>
@endif
