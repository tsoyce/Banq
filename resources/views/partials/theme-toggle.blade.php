<button type="button" x-data @click="$store.theme.toggle()" class="btn btn-ghost !p-2.5" aria-label="Сменить тему">
    <span x-show="$store.theme.value === 'dark'">@include('partials.icon', ['i' => 'sun', 'c' => 'w-[18px] h-[18px]'])</span>
    <span x-show="$store.theme.value !== 'dark'">@include('partials.icon', ['i' => 'moon', 'c' => 'w-[18px] h-[18px]'])</span>
</button>
