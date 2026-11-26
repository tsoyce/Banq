{{-- A13 · Логотип: чёрная «пилюля» + фирменный градиентный знак. --}}
<a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 shrink-0" aria-label="{{ settings('site.name') }}">
    @if (settings('site.logo'))
        <img src="{{ settings('site.logo') }}" alt="{{ settings('site.name') }}" class="h-9 w-auto">
    @else
        <span class="inline-flex items-center gap-2 rounded-xl px-3 py-2 font-bold text-[1.05rem] tracking-tight" style="background: var(--ink); color: var(--ink-text)">
            <span class="grid place-items-center w-5 h-5 rounded-md text-[11px] font-extrabold text-white" style="background: var(--grad)">T</span>
            {{ settings('site.name') }}
        </span>
    @endif
</a>
