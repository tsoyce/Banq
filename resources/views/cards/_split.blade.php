{{-- A54 · Карты с вкладками «Физлицам / Бизнесу» и «Дебетовые / Кредитные / …» — переключение без перезагрузки.
     $offers — Collection<CardOffer>, $offerFrom — откуда ссылка (ps:visa | bank) для хлебных крошек карты, $limit — сколько карт максимум. --}}
@php
    $list = $offers->take($limit ?? 40)->values();
    $counts = [];
    foreach ($list as $o) {
        $counts[$o->card->audience->value][$o->card->kind->value] = ($counts[$o->card->audience->value][$o->card->kind->value] ?? 0) + 1;
    }
    $auds = collect(\App\Enums\CardAudience::cases())->filter(fn ($a) => isset($counts[$a->value]))->values();
    $start = $auds->first()?->value ?? 'personal';
@endphp
<div x-data="{ aud: @js($start), kind: '', counts: @js($counts), n(a, k) { const c = this.counts[a] || {}; return k ? (c[k] || 0) : Object.values(c).reduce((s, v) => s + v, 0) } }" class="space-y-3">
    <div class="flex flex-wrap items-center gap-2 px-1">
        @if ($auds->count() > 1)
            <div class="aud-tabs" role="tablist" aria-label="Для кого">
                @foreach ($auds as $a)
                    <button type="button" role="tab" :class="aud === '{{ $a->value }}' && 'is-active'" :aria-selected="aud === '{{ $a->value }}'" @click="aud = '{{ $a->value }}'; if (kind && !n(aud, kind)) kind = ''">
                        @include('partials.icon', ['i' => $a->icon(), 'c' => 'w-4 h-4']) {{ $a->label() }} <span class="count">{{ array_sum($counts[$a->value]) }}</span>
                    </button>
                @endforeach
            </div>
        @endif
        <button type="button" class="pill !py-1.5 !text-sm" :class="!kind && 'is-active'" @click="kind = ''">Все <span class="faint tabular" x-text="n(aud)"></span></button>
        @foreach (\App\Enums\CardKind::cases() as $k)
            <button type="button" class="pill !py-1.5 !text-sm" x-show="n(aud, '{{ $k->value }}')" x-cloak :class="kind === '{{ $k->value }}' && 'is-active'" @click="kind = '{{ $k->value }}'">{{ $k->plural() }} <span class="faint tabular" x-text="n(aud, '{{ $k->value }}')"></span></button>
        @endforeach
    </div>
    @foreach ($list as $i => $o)
        <div data-audience="{{ $o->card->audience->value }}" data-kind="{{ $o->card->kind->value }}"
             x-show="aud === '{{ $o->card->audience->value }}' && (!kind || kind === '{{ $o->card->kind->value }}')" @if ($o->card->audience->value !== $start) x-cloak @endif>
            @include('cards._offer', ['o' => $o, 'i' => 99, 'offerFrom' => $offerFrom ?? null])
        </div>
    @endforeach
</div>
