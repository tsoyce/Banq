<a href="{{ url('/') }}" class="flex items-center gap-3 group">
    @if (settings('site.logo'))
        <img src="{{ settings('site.logo') }}" alt="{{ settings('site.name') }}" class="h-9 w-auto">
    @else
        <span class="relative grid place-items-center w-10 h-10 rounded-2xl text-white font-display font-black text-lg"
              style="background: var(--grad); box-shadow: 0 10px 30px -8px var(--a2)">T</span>
        <span class="font-display font-bold text-lg tracking-tight">{{ settings('site.name') }}</span>
    @endif
</a>
