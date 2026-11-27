{{-- A32 · Справа от формы платёжной системы: уровни карт по ступеням, ссылки на правку и «добавить уровень». --}}
<section class="card p-5">
    <div class="flex items-center justify-between gap-3">
        <h2 class="font-display font-bold text-lg">Уровни карт <span class="faint tabular">{{ $system->tiers->count() }}</span></h2>
        <a href="{{ route('admin.resource.create', ['card-tiers', 'system' => $system->id]) }}" class="btn btn-primary !py-1.5 text-sm">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Уровень</a>
    </div>
    <div class="mt-4 grid gap-2">
        @forelse ($system->tiers as $t)
            <a href="{{ route('admin.resource.edit', ['card-tiers', $t->id]) }}" class="list-row !px-3 !py-2.5 rounded-xl hover:bg-[var(--card-2)] transition">
                <span class="mini-card" style="background: {{ $system->gradient() }}; filter: saturate({{ 0.5 + $t->level * 0.12 }}) brightness({{ 1.15 - $t->level * 0.07 }})"></span>
                <span class="flex-1 min-w-0">
                    <span class="font-semibold block truncate">{{ $t->name }}</span>
                    <span class="faint text-xs">{{ $t->segmentLabel() }} · {{ count($t->privileges ?? []) }} {{ plural(count($t->privileges ?? []), 'привилегия', 'привилегии', 'привилегий') }} · {{ $t->cards_count }} {{ plural($t->cards_count, 'карта', 'карты', 'карт') }}</span>
                </span>
                <span class="level-dots" aria-label="ступень {{ $t->level }}">@for ($i = 1; $i <= 6; $i++)<i @class(['on' => $i <= $t->level])></i>@endfor</span>
            </a>
        @empty
            <p class="faint text-sm">Уровней пока нет.</p>
        @endforelse
    </div>
</section>
