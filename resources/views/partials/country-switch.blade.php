{{-- A21 · Переключатель страны в ленте. Выбор — ссылка ?country=xx (запоминается в cookie, см. ResolveCountry). --}}
@php($cc = app(\App\Support\CurrentCountry::class))
@php($current = $cc->get())
<div class="relative hidden sm:block" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
    <button type="button" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 font-semibold whitespace-nowrap {{ $cc->hasChoice() ? 'hover:bg-white/20 transition' : 'cursor-default' }}"
            @if ($cc->hasChoice()) @click="open = !open" :aria-expanded="open" aria-haspopup="listbox" @endif>
        <span class="dot"></span>
        @if ($current)<span aria-hidden="true">{{ $current->flag }}</span> Ставки банков {{ $current->genitive() }}@else Ставки банков @endif
        @if ($cc->hasChoice())<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="opacity-70" :class="open && 'rotate-180'"><path d="m6 9 6 6 6-6"/></svg>@endif
    </button>
    @if ($cc->hasChoice())
        <div x-show="open" x-cloak x-transition.origin.top.left class="absolute left-0 top-full mt-2 z-50 min-w-56 card p-1.5 text-[var(--text)] shadow-xl" role="listbox">
            @foreach ($cc->all() as $c)
                <a href="{{ request()->fullUrlWithQuery(['country' => $c->slug, 'bank' => null, 'currency' => null, 'page' => null]) }}" role="option" @if ($current?->is($c)) aria-selected="true" @endif
                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-semibold {{ $current?->is($c) ? 'bg-[var(--card-2)]' : 'hover:bg-[var(--card-2)]' }}">
                    <span class="text-lg leading-none" aria-hidden="true">{{ $c->flag }}</span>
                    <span class="flex-1">{{ $c->name }}</span>
                    <span class="faint mono text-xs">{{ $c->currency }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
