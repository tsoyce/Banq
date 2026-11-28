{{-- A55 · Страница автора /author: основатель TSOY.IN — кто пишет, где читать (соцсети со счётчиками), все его публикации, поддержка. --}}
@php
    $headline = settings('author.headline', 'Основатель TSOY.IN');
    $slogan = settings('author.slogan');
@endphp
@extends('layouts.app', [
    'title' => $user->name.' — автор '.settings('site.name'),
    'description' => trim($headline.'. '.($about->first() ? str($about->first())->limit(180) : $stats['posts'].' '.plural($stats['posts'], 'публикация', 'публикации', 'публикаций').' о банках и финансах.')),
])

@push('head')
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org', '@type' => 'ProfilePage',
        'mainEntity' => array_filter(['@type' => 'Person', 'name' => $user->name, 'jobTitle' => $headline, 'url' => route('author'),
            'image' => $user->avatarUrl() ? url($user->avatarUrl()) : null, 'sameAs' => array_column($profiles, 'url')]),
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<div class="space-y-6">
    <nav class="text-sm muted flex items-center gap-2 px-1 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span>
        <a href="{{ route('blog.index') }}" class="hover:text-[var(--text)]">Журнал</a><span class="faint">/</span>
        <span class="text-[var(--text)]">Автор</span>
    </nav>

    {{-- ===== Кто это ===== --}}
    <section class="grid grid-cols-6 gap-4 lg:gap-6">
        <div class="card author-hero col-span-6 lg:col-span-4 p-6 lg:p-9 flex flex-col gap-6" data-reveal>
            <img src="{{ asset('images/brand/ta-500.png') }}" alt="" class="author-mono" aria-hidden="true">
            <div class="flex flex-col sm:flex-row sm:items-center gap-5 relative">
                <span class="author-ava">
                    @if ($user->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}">
                    @else
                        <img src="{{ asset('images/brand/ta-500.png') }}" alt="{{ $user->name }}" class="is-mono">
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <p class="kicker">Автор и основатель</p>
                    <h1 class="font-display font-bold text-[34px] sm:text-5xl leading-[1.05] mt-1 break-words">{{ $user->name }}</h1>
                    <p class="muted mt-2 text-[15px]">{{ $headline }}@if ($user->location()) · {{ $user->location() }}@endif</p>
                </div>
            </div>
            @if ($slogan)<p class="author-slogan font-display font-bold relative">{{ $slogan }}</p>@endif
            @if ($about->isNotEmpty())
                <div class="space-y-3 text-[15.5px] leading-[1.6] max-w-2xl relative">
                    @foreach ($about as $para)<p>{{ $para }}</p>@endforeach
                </div>
            @endif
            <div class="flex flex-wrap items-center gap-3 mt-auto relative">
                <a href="#posts" class="btn btn-primary">@include('partials.icon', ['i' => 'news', 'c' => 'w-4 h-4']) Публикации</a>
                @if (settings('donate.enabled', true) && settings('donate.items'))
                    <a href="#donate" class="btn btn-ghost">@include('partials.icon', ['i' => 'heart', 'c' => 'w-4 h-4 text-[var(--brand-3)]']) Поддержать</a>
                @endif
                @include('partials.socials', ['profiles' => $profiles, 'class' => 'sm:ml-auto'])
            </div>
        </div>

        {{-- Главные цифры --}}
        <div class="card card-ink col-span-6 lg:col-span-2 p-6 lg:p-7 flex flex-col justify-between gap-6 min-h-[260px]" data-reveal style="--d:.06s">
            <div class="flex items-start justify-between gap-3">
                <span class="text-sm font-semibold opacity-70">Публикаций в журнале</span>
                @include('partials.icon', ['i' => 'pen', 'c' => 'w-5 h-5 opacity-60'])
            </div>
            <div>
                <p class="num-hero tabular leading-none" x-count="{{ $stats['posts'] }}">{{ $stats['posts'] }}</p>
                @if ($stats['since'])<p class="text-sm opacity-60 mt-2">пишет с {{ $stats['since'] }} года</p>@endif
            </div>
            <div class="grid grid-cols-2 gap-4 pt-5 border-t border-current/15">
                <div><p class="text-xs opacity-60">Прочтений</p><p class="font-display font-bold text-2xl tabular mt-1">{{ \App\Services\Social\SocialStats::compact($stats['views']) }}</p></div>
                <div><p class="text-xs opacity-60">Подписчиков</p><p class="font-display font-bold text-2xl tabular mt-1">{{ $stats['followers'] ? \App\Services\Social\SocialStats::compact($stats['followers']) : '—' }}</p></div>
            </div>
        </div>
    </section>

    {{-- ===== Соцсети ===== --}}
    @if ($profiles)
        <section class="card p-6 lg:p-7 space-y-5" data-reveal>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><p class="kicker">01 / Соцсети</p><h2 class="font-display font-bold text-xl mt-1">Где читать и смотреть</h2></div>
                @if ($stats['followers'])<p class="text-sm muted">Всего <b class="text-[var(--text)] tabular">{{ number_format($stats['followers'], 0, ',', ' ') }}</b> {{ plural($stats['followers'], 'подписчик', 'подписчика', 'подписчиков') }}</p>@endif
            </div>
            @include('partials.socials', ['profiles' => $profiles, 'variant' => 'grid'])
        </section>
    @endif

    {{-- ===== Публикации ===== --}}
    <section id="posts" class="grid xl:grid-cols-[minmax(0,1fr)_320px] gap-6 scroll-mt-24">
        <div class="space-y-5 min-w-0">
            <div class="flex flex-wrap items-end justify-between gap-3 px-1">
                <div><p class="kicker">02 / Публикации</p><h2 class="font-display font-bold text-2xl mt-1">{{ $category ? $category->name : 'Все материалы автора' }}</h2></div>
                <p class="text-sm muted">{{ $posts->total() }} {{ plural($posts->total(), 'публикация', 'публикации', 'публикаций') }}</p>
            </div>
            @if ($categories->count() > 1)
                <x-hscroll label="Рубрики автора" class="hscroll-end">
                    <a href="{{ route('author') }}#posts" class="pill {{ $category ? '' : 'is-active' }}">Все <span class="tabular opacity-50">{{ $stats['posts'] }}</span></a>
                    @foreach ($categories as $c)
                        <a href="{{ route('author', ['c' => $c->slug]) }}#posts" class="pill {{ $category?->is($c) ? 'is-active' : '' }}"><span class="w-2 h-2 rounded-full" style="background: {{ $c->colorOrDefault() }}"></span>{{ $c->name }} <span class="tabular opacity-50">{{ $c->posts_count }}</span></a>
                    @endforeach
                </x-hscroll>
            @endif
            @if ($posts->isEmpty())
                <div class="card empty">
                    <span class="icon-tile">@include('partials.icon', ['i' => 'news'])</span>
                    <p class="muted">Публикаций пока нет — первые материалы уже в работе.</p>
                </div>
            @else
                <div x-data="loadMore('{{ $posts->nextPageUrl() }}')">
                    <div class="post-grid" x-ref="grid">@include('blog._grid', ['posts' => $posts])</div>
                    @if ($posts->hasMorePages())
                        <div class="flex justify-center mt-6" x-show="next">
                            <a href="{{ $posts->nextPageUrl() }}" class="btn btn-ghost !px-6" @click.prevent="more()" :class="loading && 'is-loading'">
                                <span x-show="!loading">Показать ещё</span><span x-show="loading" x-cloak class="dot-loader" aria-label="Загрузка"><i></i><i></i><i></i></span>
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <aside class="space-y-5">
            @if ($popular->count() > 1)
                <div class="card p-5">
                    <p class="kicker">Самое читаемое</p>
                    <ol class="mt-3 space-y-3">
                        @foreach ($popular as $i => $p)
                            <li class="flex gap-3">
                                <span class="font-display font-bold text-xl text-grad tabular w-6 shrink-0">{{ $i + 1 }}</span>
                                <a href="{{ $p->url() }}" class="text-[14.5px] leading-snug font-semibold hover:underline">{{ $p->title }}<span class="block text-xs muted font-normal mt-0.5">{{ number_format($p->views, 0, ',', ' ') }} {{ plural($p->views, 'просмотр', 'просмотра', 'просмотров') }}</span></a>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
            <a href="{{ route('blog.rss') }}" class="card lift p-5 flex items-center gap-3">
                <span class="icon-tile !w-10 !h-10">@include('partials.icon', ['i' => 'rss', 'c' => 'w-5 h-5'])</span>
                <span><b class="block">RSS журнала</b><span class="text-xs muted">Новые материалы в вашей читалке</span></span>
            </a>
        </aside>
    </section>

    {{-- ===== Поддержка ===== --}}
    @include('partials.donate', ['kicker' => '03 / Поддержка'])
</div>
@endsection
