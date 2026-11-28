{{-- A53 · Кнопка «к сравнению» для любого вида продукта. $type — ключ (packages, banks, payment-systems…), $item — модель,
     $wide — широкая кнопка с подписью (на странице продукта), иначе круглая. Список — $store.<store> (до 4, localStorage). --}}
@php
    $cmpType = app(\App\Services\Compare\CompareRegistry::class)->get($type);
    $cmpItem = \Illuminate\Support\Arr::only($cmpType->head($item), ['id', 'code', 'color', 'name']);
    $cmpS = '$store.'.$cmpType->store();
@endphp
@if ($wide ?? false)
    <button type="button" x-data class="btn" :class="{{ $cmpS }}.has({{ $item->getKey() }}) ? 'btn-primary' : 'btn-ghost'" @click="{{ $cmpS }}.toggle(@js($cmpItem))">
        @include('partials.icon', ['i' => 'scale', 'c' => 'w-4 h-4'])
        <span x-text="{{ $cmpS }}.has({{ $item->getKey() }}) ? 'В сравнении' : 'Сравнить'">Сравнить</span>
    </button>
@else
    <button type="button" class="cmp-btn {{ $class ?? '' }}" x-data :class="{{ $cmpS }}.has({{ $item->getKey() }}) && 'is-on'" @click.prevent.stop="{{ $cmpS }}.toggle(@js($cmpItem))"
            :title="{{ $cmpS }}.has({{ $item->getKey() }}) ? 'Убрать из сравнения' : 'Добавить к сравнению'" aria-label="Сравнить">
        <span x-show="!{{ $cmpS }}.has({{ $item->getKey() }})">@include('partials.icon', ['i' => 'scale', 'c' => 'w-[18px] h-[18px]'])</span>
        <span x-show="{{ $cmpS }}.has({{ $item->getKey() }})" x-cloak>@include('partials.icon', ['i' => 'check', 'c' => 'w-[18px] h-[18px]'])</span>
    </button>
@endif
