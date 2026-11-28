{{-- A46 · Соцсети TSOY.IN. $variant: grid — карточки с числом подписчиков (страница автора), row — значки в строку (подвал, карточка автора).
     Данные — App\Services\Social\SocialStats::profiles() (число по API, иначе ручное из админки). --}}
@php($social = app(\App\Services\Social\SocialStats::class))
@php($profiles = $profiles ?? (settings('social.enabled', true) ? $social->profiles() : []))
@if ($profiles)
    @if (($variant ?? 'row') === 'grid')
        <div class="social-grid">
            @foreach ($profiles as $i => $p)
                <a href="{{ $p['url'] }}" target="_blank" rel="noopener me" class="social-card lift" style="--c: {{ $p['color'] }}; --d: {{ 0.04 * $i }}s" data-reveal>
                    <span class="social-mark">@include('partials.social-icon', ['n' => $p['network'], 'c' => 'w-[22px] h-[22px]'])</span>
                    <span class="min-w-0">
                        <b class="block">{{ $p['label'] }}</b>
                        <span class="block text-xs muted truncate">{{ $p['handle'] }}</span>
                    </span>
                    @if ($p['count'] !== null)
                        <span class="social-count">
                            <b class="tabular" @if ($p['count'] < 10000) x-count="{{ $p['count'] }}" @endif>{{ \App\Services\Social\SocialStats::compact($p['count']) }}</b>
                            <span>{{ plural($p['count'], ...$p['noun']) }}</span>
                        </span>
                    @else
                        <span class="social-count"><span class="inline-flex items-center gap-1 font-semibold text-[var(--text)]">Подписаться @include('partials.icon', ['i' => 'arrow', 'c' => 'w-3.5 h-3.5'])</span></span>
                    @endif
                </a>
            @endforeach
        </div>
    @else
        <div class="social-row {{ $class ?? '' }}">
            @foreach ($profiles as $p)
                <a href="{{ $p['url'] }}" target="_blank" rel="noopener me" style="--c: {{ $p['color'] }}" aria-label="{{ $p['label'] }}{{ $p['count'] !== null ? ' — '.\App\Services\Social\SocialStats::compact($p['count']).' '.plural($p['count'], ...$p['noun']) : '' }}" title="{{ $p['label'] }}">
                    @include('partials.social-icon', ['n' => $p['network'], 'c' => 'w-[18px] h-[18px]'])
                    @if (($counts ?? false) && $p['count'] !== null)<span class="tabular">{{ \App\Services\Social\SocialStats::compact($p['count']) }}</span>@endif
                </a>
            @endforeach
        </div>
    @endif
@endif
