{{-- A53 · Вкладки видов сравнения: на каждой — сколько выбрано (из хранилища в браузере); ссылка ведёт сразу к выбранному.
     $active — ключ вида (deposits, cards…) или null на странице раздела. --}}
@php $types ??= app(\App\Services\Compare\CompareRegistry::class)->all(); @endphp
<nav class="cmp-tabs" aria-label="Что сравнивать" x-data>
    <a href="{{ route('compare.index') }}" class="{{ ($active ?? null) === null ? 'is-active' : '' }}">@include('partials.icon', ['i' => 'grid', 'c' => 'w-4 h-4']) Все</a>
    @foreach ($types as $t)
        <a href="{{ $t->url() }}" :href="$store.{{ $t->store() }}.items.length ? $store.{{ $t->store() }}.url() : @js($t->url())"
           class="{{ ($active ?? null) === $t->key() ? 'is-active' : '' }}" @if (($active ?? null) === $t->key()) aria-current="page" @endif>
            @include('partials.icon', ['i' => $t->icon(), 'c' => 'w-4 h-4'])
            {{ $t->label() }}
            <span class="cmp-count" x-show="$store.{{ $t->store() }}.items.length" x-text="$store.{{ $t->store() }}.items.length" x-cloak></span>
        </a>
    @endforeach
    @foreach (\App\Services\Compare\CompareRegistry::SOON as [$label, $icon])
        <span class="is-soon" title="Скоро">@include('partials.icon', ['i' => $icon, 'c' => 'w-4 h-4']) {{ $label }} <span class="cmp-count">скоро</span></span>
    @endforeach
</nav>
