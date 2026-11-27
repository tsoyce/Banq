{{-- A37 · Поделиться: системное меню (телефон), Telegram, WhatsApp, копировать ссылку с подтверждением. --}}
<div class="share" x-data="share(@js($post->url()), @js($post->title))">
    <button type="button" class="share-btn" x-show="native" x-cloak @click="open()" aria-label="Поделиться">@include('partials.icon', ['i' => 'share', 'c' => 'w-[18px] h-[18px]'])</button>
    <a class="share-btn" href="https://t.me/share/url?url={{ urlencode($post->url()) }}&text={{ urlencode($post->title) }}" target="_blank" rel="noopener" aria-label="Отправить в Telegram" title="Telegram">
        <svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="currentColor" aria-hidden="true"><path d="M21.9 4.3 18.7 19.5c-.2 1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.4-5 9.1-8.2c.4-.4-.1-.6-.6-.2L6.3 13.2 1.5 11.7c-1-.3-1-1 .2-1.5L20.6 3c.9-.3 1.6.2 1.3 1.3z"/></svg>
    </a>
    <a class="share-btn" href="https://wa.me/?text={{ urlencode($post->title.' '.$post->url()) }}" target="_blank" rel="noopener" aria-label="Отправить в WhatsApp" title="WhatsApp">
        <svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.8 14.2c-.2.7-1.4 1.3-2 1.4-.5.1-1.2.1-1.9-.1-.4-.1-1-.3-1.7-.6-3-1.3-4.9-4.3-5.1-4.5-.1-.2-1.2-1.6-1.2-3.1s.8-2.2 1-2.5c.3-.3.6-.4.8-.4h.6c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.3 0 .5l-.3.5-.4.5c-.1.1-.3.3-.1.6.2.3.7 1.2 1.6 2 1.1 1 2 1.3 2.3 1.4.3.1.4.1.6-.1l.9-1c.2-.3.4-.2.6-.1l2 1c.3.1.5.2.5.3.1.2.1.7-.1 1.5z"/></svg>
    </a>
    <button type="button" class="share-btn share-copy" :class="done && 'is-done'" @click="copy()" aria-label="Скопировать ссылку">
        <span x-show="!done" class="inline-flex items-center gap-1.5">@include('partials.icon', ['i' => 'link', 'c' => 'w-[18px] h-[18px]']) <span class="text-sm font-semibold">Ссылка</span></span>
        <span x-show="done" x-cloak class="inline-flex items-center gap-1.5">@include('partials.icon', ['i' => 'check', 'c' => 'w-[18px] h-[18px]']) <span class="text-sm font-semibold">Скопировано</span></span>
    </button>
</div>
