{{--
    A51 · Рекламное место. Поставить: @include('ads.slot', ['slot' => 'sidebar']) — одна строка, переносится куда угодно
    (при переделке поверхностей в виджеты место станет виджетом). Нет объявления и кода сети — не выводится ничего.
    Место резервируется пропорциями слота (--ar / --ar-m), картинки — lazy с width/height: вёрстка не сдвигается.
    Показ засчитывает resources/js/ads.js, когда ≥ 50% видно ≥ 1 с; клик — через /ads/c/{метка}.
--}}
@php
    $ad = $adPick ?? app(\App\Services\Ads\AdServer::class)->pick($slot);
@endphp
@if ($ad)
    @php
        $s = $ad['slot'];
        $c = $ad['creative'];
        $ar = $s['width'].' / '.$s['height'];
        $arm = $s['m_width'] ? $s['m_width'].' / '.$s['m_height'] : $ar;
        $label = settings('ads.label', 'Реклама') ?: 'Реклама';
        $href = $c && $c['url'] ? ($ad['token'] ? route('ads.click', $ad['token']) : $c['url']) : null;
        $sponsor = $c['sponsor'] ?? null;
    @endphp
    <div class="ad ad--{{ $c['type'] ?? 'network' }} {{ $class ?? '' }}" data-slot="{{ $s['key'] }}" @if ($ad['token']) data-ad="{{ $ad['token'] }}" @endif style="--ar: {{ $ar }}; --ar-m: {{ $arm }}" role="complementary" aria-label="{{ $label }}">
        <div class="ad-label">
            <span title="{{ $c['disclosure'] ?? '' }}">{{ $label }}@if ($sponsor) · {{ $sponsor }}@endif</span>
            @if ($ad['token'])<button type="button" class="ad-hide" data-ad-hide aria-label="Скрыть это объявление" title="Скрыть">@include('partials.icon', ['i' => 'x', 'c' => 'w-3.5 h-3.5'])</button>@endif
        </div>

        @if (! $c)
            {{-- Код сети (AdSense и т.п.) — вставляется, только когда место подъезжает к экрану --}}
            <div class="ad-box"><template data-ad-code>{!! $ad['fallback'] !!}</template></div>
        @elseif ($c['type'] === 'image')
            <a href="{{ $href }}" class="ad-box ad-link" target="_blank" rel="sponsored noopener">
                <picture>
                    @if ($c['image_mobile'])<source media="(max-width: 640px)" srcset="{{ $c['image_mobile'] }}">@endif
                    <img src="{{ $c['image'] }}" alt="{{ $c['alt'] ?: ($c['title'] ?: $sponsor) }}" width="{{ $s['width'] }}" height="{{ $s['height'] }}" loading="lazy" decoding="async">
                </picture>
            </a>
        @elseif ($c['type'] === 'html')
            <div class="ad-box"><iframe class="ad-frame" title="{{ $label }}" sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox" loading="lazy" referrerpolicy="no-referrer" srcdoc="{{ $c['html'] }}"></iframe></div>
        @else
            <a href="{{ $href }}" class="ad-native ad-link" target="_blank" rel="sponsored noopener">
                @if ($c['image'])<span class="ad-native-media"><img src="{{ $c['image'] }}" alt="{{ $c['alt'] ?: '' }}" width="640" height="400" loading="lazy" decoding="async"></span>@endif
                <span class="ad-native-body">
                    <span class="ad-native-title">{{ $c['title'] }}</span>
                    @if ($c['body'])<span class="ad-native-text">{{ $c['body'] }}</span>@endif
                    <span class="ad-native-cta">{{ $c['cta'] ?: 'Подробнее' }} @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</span>
                </span>
            </a>
        @endif
    </div>
@endif
