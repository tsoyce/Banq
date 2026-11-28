{{-- A37 · Лента блога: шапка-журнал с крупными цифрами, рубрики лентой, главный материал, сетка карточек
     («Показать ещё» без перезагрузки), справа — популярное, теги, RSS. --}}
@php
    $heading = $category?->name ?? ($tag ? '#'.$tag->name : 'Журнал');
    $sub = $category?->description ?? ($tag ? 'Публикации с тегом «'.$tag->name.'»' : 'Новости рынка, аналитика ставок, обзоры продуктов и гайды — без воды.');
@endphp
@extends('layouts.app', ['title' => $category ? $category->name.' · Журнал' : ($tag ? '#'.$tag->name.' · Журнал' : 'Журнал: новости и аналитика банков'), 'description' => $sub])

@push('head')
    <link rel="alternate" type="application/rss+xml" title="{{ settings('site.name') }} — журнал" href="{{ route('blog.rss') }}">
@endpush

@section('content')
<div class="grid grid-cols-[minmax(0,1fr)] xl:grid-cols-[minmax(0,1fr)_320px] gap-5">
    <div class="space-y-5 min-w-0">

        {{-- Шапка --}}
        <section class="card p-7 md:p-9 overflow-hidden relative blog-hero" data-reveal>
            <div class="blog-hero-orb" aria-hidden="true"></div>
            <nav class="text-sm muted flex items-center gap-2 relative"><a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span>
                @if ($category || $tag)<a href="{{ route('blog.index') }}" class="hover:text-[var(--text)]">Журнал</a><span class="faint">/</span><span>{{ $category?->name ?? '#'.$tag->name }}</span>@else<span>Журнал</span>@endif
            </nav>
            <div class="relative mt-6 flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div class="min-w-0">
                    <p class="kicker">{{ $category ? '01 / рубрика' : ($tag ? '01 / тег' : '01 / журнал '.\Illuminate\Support\Str::lower(settings('site.name'))) }}</p>
                    <h1 class="font-display font-bold blog-title mt-2">
                        @if ($category || $tag){{ $heading }}@else Журнал <span class="text-grad">о&nbsp;деньгах</span>@endif
                    </h1>
                    <p class="muted mt-3 max-w-xl">{{ $sub }}</p>
                </div>
                <div class="flex items-end gap-6 shrink-0">
                    <div>
                        <p class="num-hero text-[4.5rem] sm:text-[5.5rem] tabular"><span x-data x-count="{{ $category ? $categories->firstWhere('id', $category->id)?->posts_count ?? $posts->total() : ($tag ? $posts->total() : $total) }}">{{ $category ? $categories->firstWhere('id', $category->id)?->posts_count ?? $posts->total() : ($tag ? $posts->total() : $total) }}</span></p>
                        <p class="text-sm muted -mt-1">{{ plural($category || $tag ? $posts->total() : $total, 'публикация', 'публикации', 'публикаций') }}</p>
                    </div>
                </div>
            </div>
            {{-- Поиск по журналу --}}
            <form action="{{ $category ? route('blog.category', $category) : ($tag ? route('blog.tag', $tag) : route('blog.index')) }}" method="get" class="relative mt-7 blog-search" role="search">
                @include('partials.icon', ['i' => 'search', 'c' => 'w-[18px] h-[18px] faint'])
                <input type="search" name="q" value="{{ $q }}" placeholder="Найти в журнале: ставки, Kaspi, кэшбэк…" aria-label="Поиск по журналу" class="flex-1 min-w-0 bg-transparent outline-none py-3">
                @if ($kind)<input type="hidden" name="kind" value="{{ $kind }}">@endif
                <button class="btn btn-primary !py-2">Найти</button>
            </form>
        </section>

        {{-- Рубрики --}}
        <x-hscroll label="Рубрики" class="hscroll-end">
            <a href="{{ route('blog.index') }}" class="pill {{ ! $category && ! $tag ? 'is-active' : '' }}">Все <span class="faint tabular {{ ! $category && ! $tag ? '!text-[inherit] opacity-60' : '' }}">{{ $total }}</span></a>
            @foreach ($categories as $c)
                @continue(! $c->posts_count && $category?->id !== $c->id)
                <a href="{{ route('blog.category', $c) }}" class="pill {{ $category?->id === $c->id ? 'is-active' : '' }}">
                    <span class="w-2 h-2 rounded-full" style="background: {{ $c->colorOrDefault() }}"></span>{{ $c->name }} <span class="tabular opacity-50">{{ $c->posts_count }}</span>
                </a>
            @endforeach
        </x-hscroll>

        @if ($q !== '')
            <p class="text-sm muted px-1">По запросу «<b class="text-[var(--text)]">{{ $q }}</b>» — {{ $posts->total() }} {{ plural($posts->total(), 'публикация', 'публикации', 'публикаций') }} · <a href="{{ url()->current() }}" class="underline underline-offset-4">сбросить</a></p>
        @endif

        {{-- Главный материал --}}
        @if ($featured)
            <article class="card featured-post overflow-hidden" data-reveal>
                <a href="{{ $featured->url() }}" class="featured-media" tabindex="-1" aria-hidden="true">@include('blog._cover', ['post' => $featured, 'eager' => true])</a>
                <div class="featured-glass">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="chip !bg-white/15 !text-white backdrop-blur">@include('partials.icon', ['i' => 'spark', 'c' => 'w-3.5 h-3.5']) Главное</span>
                        @include('blog._category', ['post' => $featured, 'c' => 'is-light'])
                    </div>
                    <h2 class="featured-title font-display font-bold"><a href="{{ $featured->url() }}" class="stretched">{{ $featured->title }}</a></h2>
                    @if ($featured->lead)<p class="featured-lead">{{ $featured->lead }}</p>@endif
                    <div class="flex items-center gap-3 text-sm">
                        @if ($featured->author)@include('partials.avatar', ['u' => $featured->author, 'c' => '!w-8 !h-8 text-xs'])<span class="font-semibold">{{ $featured->author->name }}</span>@endif
                        @include('blog._meta', ['post' => $featured, 'c' => 'opacity-80'])
                    </div>
                </div>
            </article>
        @endif

        {{-- Лента --}}
        @if ($posts->isEmpty() && ! $featured)
            <section class="card empty" data-reveal>
                <span class="icon-tile !w-16 !h-16 !rounded-2xl" style="background: var(--grad); color: #fff">@include('partials.icon', ['i' => $q !== '' ? 'search' : 'news', 'c' => 'w-7 h-7'])</span>
                <div>
                    <h2 class="font-display font-bold text-2xl">{{ $q !== '' ? 'Ничего не нашлось' : 'Первые материалы уже в работе' }}</h2>
                    <p class="muted mt-2 max-w-md mx-auto">{{ $q !== '' ? 'Попробуйте другое слово — например, название банка или продукта.' : 'Здесь будут новости рынка, разборы вкладов и карт, аналитика ставок и честные обзоры. Подпишитесь на RSS, чтобы не пропустить.' }}</p>
                </div>
                <div class="flex flex-wrap justify-center gap-2">
                    @if ($q !== '' || $category || $tag || $kind)<a href="{{ route('blog.index') }}" class="btn btn-primary">Весь журнал</a>@endif
                    <a href="{{ route('deposits.index') }}" class="btn btn-ghost">@include('partials.icon', ['i' => 'piggy', 'c' => 'w-4 h-4']) Вклады</a>
                    <a href="{{ route('cards.index') }}" class="btn btn-ghost">@include('partials.icon', ['i' => 'card', 'c' => 'w-4 h-4']) Карты</a>
                </div>
            </section>
        @else
            <div x-data="loadMore('{{ $posts->nextPageUrl() }}')">
                <div class="post-grid" x-ref="grid">
                    @include('blog._grid', ['posts' => $posts])
                </div>
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

    {{-- ===== Правая колонка ===== --}}
    <aside class="space-y-5">
        @if ($popular->isNotEmpty())
            <section class="card p-6" data-reveal>
                <h2 class="font-display font-bold text-xl mb-3">Читают сейчас</h2>
                <ol class="grid">
                    @foreach ($popular as $n => $p)
                        <li class="popular-row">
                            <span class="popular-n tabular">{{ str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="min-w-0">
                                <a href="{{ $p->url() }}" class="font-semibold leading-snug hover:underline underline-offset-4 line-clamp-2">{{ $p->title }}</a>
                                <p class="text-xs muted mt-1 flex items-center gap-1">@if ($p->views)@include('partials.icon', ['i' => 'eye', 'c' => 'w-3.5 h-3.5']){{ number_format($p->views, 0, ',', ' ') }} · @endif{{ $p->category?->name ?? $p->kindLabel() }} · {{ $p->reading_minutes }} мин</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        @include('ads.slot', ['slot' => 'sidebar']){{-- A51 --}}

        @if ($tags->isNotEmpty())
            <section class="card p-6" data-reveal style="--d:.05s">
                <h2 class="font-display font-bold text-xl mb-4">Темы</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($tags as $t)
                        <a href="{{ route('blog.tag', $t) }}" class="btn {{ $tag?->id === $t->id ? 'btn-primary' : 'btn-ghost' }} !py-1.5 !px-3 text-sm !font-medium">#{{ $t->name }} <span class="opacity-50 tabular">{{ $t->posts_count }}</span></a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="card card-ink p-6 space-y-4 overflow-hidden relative" data-reveal style="--d:.1s">
            <span class="icon-tile !bg-white/10 !text-white">@include('partials.icon', ['i' => 'rss', 'c' => 'w-5 h-5'])</span>
            <div>
                <h2 class="font-display font-bold text-xl">Читайте в своём ридере</h2>
                <p class="text-sm opacity-60 mt-1">RSS-лента журнала: новые материалы сразу в Feedly, Inoreader или Telegram-боте.</p>
            </div>
            <div class="flex gap-2" x-data="copyText('{{ route('blog.rss') }}')">
                <a href="{{ route('blog.rss') }}" class="btn !bg-white !text-black !py-2 text-sm">Открыть RSS</a>
                <button type="button" class="btn !bg-white/10 !text-white !py-2 text-sm" @click="copy()">
                    <span x-show="!done" class="inline-flex items-center gap-1.5">@include('partials.icon', ['i' => 'copy', 'c' => 'w-4 h-4']) Ссылка</span>
                    <span x-show="done" x-cloak class="inline-flex items-center gap-1.5">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) Скопировано</span>
                </button>
            </div>
        </section>

        @if ($categories->where('posts_count', '>', 0)->isNotEmpty())
            <section class="card p-6" data-reveal style="--d:.14s">
                <h2 class="font-display font-bold text-xl mb-2">Рубрики</h2>
                @foreach ($categories as $c)
                    @continue(! $c->posts_count)
                    <a href="{{ route('blog.category', $c) }}" class="list-row group">
                        <span class="icon-tile" style="background: color-mix(in oklab, {{ $c->colorOrDefault() }} 14%, transparent); color: {{ $c->colorOrDefault() }}">@include('partials.icon', ['i' => $c->icon ?: 'news', 'c' => 'w-[19px] h-[19px]'])</span>
                        <span class="min-w-0 flex-1"><span class="font-medium block">{{ $c->name }}</span><span class="text-xs muted block truncate">{{ $c->description }}</span></span>
                        <span class="tabular text-sm muted">{{ $c->posts_count }}</span>
                    </a>
                @endforeach
            </section>
        @endif
    </aside>
</div>
@endsection
