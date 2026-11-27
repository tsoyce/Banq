{{-- A36 · Горизонтальная лента без системного скроллбара: затухание по краям, где есть что листать, + стрелки при мыши.
     Логика — Alpine.data('hscroll') в resources/js/app.js. Использование: <x-hscroll label="Сортировка">…чипы…</x-hscroll> --}}
@props(['label' => null])
<div {{ $attributes->merge(['class' => 'hscroll']) }} x-data="hscroll" :data-fade="[start && 'start', end && 'end'].filter(Boolean).join(' ')" role="group" @if ($label) aria-label="{{ $label }}" @endif>
    <button type="button" class="hscroll-btn is-prev" x-show="start" x-cloak @click="by(-1)" tabindex="-1" aria-hidden="true">@include('partials.icon', ['i' => 'down', 'c' => 'w-4 h-4 rotate-90'])</button>
    <div class="hscroll-track" x-ref="track" @scroll.passive="update()">{{ $slot }}</div>
    <button type="button" class="hscroll-btn is-next" x-show="end" x-cloak @click="by(1)" tabindex="-1" aria-hidden="true">@include('partials.icon', ['i' => 'down', 'c' => 'w-4 h-4 -rotate-90'])</button>
</div>
