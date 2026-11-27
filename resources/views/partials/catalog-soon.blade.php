{{-- A36 · Пустой каталог (данных ещё нет): вместо «—» и «0» — честная карточка «наполняется» с тем, что здесь будет.
     $title, $text, $items — [[иконка, заголовок, подпись], …], $links — [[подпись, url, иконка], …]. Занимает место цифровых карточек шапки. --}}
<div class="card card-ink p-7 {{ $class ?? 'md:col-span-2' }} flex flex-col justify-between gap-6 overflow-hidden @container" data-reveal style="--d:.05s">
    <div class="flex items-start justify-between gap-4">
        <span class="chip !bg-[color-mix(in_oklab,var(--ink-text)_12%,transparent)] !text-[var(--ink-text)]"><span class="dot !bg-[var(--warn)]"></span>каталог наполняется</span>
    </div>
    <div>
        <h2 class="font-display font-bold text-2xl sm:text-3xl leading-tight">{{ $title }}</h2>
        <p class="opacity-60 mt-2 max-w-lg">{{ $text }}</p>
    </div>
    <div class="grid grid-cols-2 @lg:grid-cols-4 gap-2">
        @foreach ($items as [$icon, $name, $hint])
            <div class="ink-plate !p-3">
                <span class="opacity-60">@include('partials.icon', ['i' => $icon, 'c' => 'w-[18px] h-[18px]'])</span>
                <p class="font-semibold text-sm mt-2 leading-tight">{{ $name }}</p>
                <p class="text-xs opacity-60 mt-0.5 leading-snug">{{ $hint }}</p>
            </div>
        @endforeach
    </div>
    @if (! empty($links))
        <div class="flex flex-wrap gap-2">
            @foreach ($links as [$label, $url, $icon])
                <a href="{{ $url }}" class="btn {{ $loop->first ? 'btn-brand' : 'ink-plate !py-2.5 !px-4 !rounded-full' }}">@include('partials.icon', ['i' => $icon, 'c' => 'w-4 h-4']) {{ $label }}</a>
            @endforeach
        </div>
    @endif
</div>
