{{-- A37 · Публикация: прогресс чтения, шапка с крупным заголовком, обложка, текст с живыми вставками продуктов,
     справа — оглавление, реакции и «поделиться»; ниже — о ком материал, теги, комментарии, похожие. --}}
@extends('layouts.app', ['title' => $post->metaTitle(), 'description' => $post->metaDescription()])

@push('head')
    <link rel="canonical" href="{{ $post->url() }}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ $post->url() }}">
    @if ($post->coverUrl())<meta property="og:image" content="{{ url($post->coverUrl()) }}"><meta name="twitter:card" content="summary_large_image">@endif
    @if ($post->published_at)<meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">@endif
    @unless ($post->isPublished())<meta name="robots" content="noindex">@endunless
    <link rel="alternate" type="application/rss+xml" title="{{ settings('site.name') }} — журнал" href="{{ route('blog.rss') }}">
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org', '@type' => $post->kind === 'news' ? 'NewsArticle' : 'Article',
        'headline' => $post->title, 'description' => $post->metaDescription(), 'image' => $post->coverUrl() ? [url($post->coverUrl())] : null,
        'datePublished' => $post->published_at?->toIso8601String(), 'dateModified' => $post->updated_at?->toIso8601String(),
        'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author->name] : null,
        'publisher' => ['@type' => 'Organization', 'name' => settings('site.name')], 'mainEntityOfPage' => $post->url(),
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
{{-- Прогресс чтения --}}
<div class="read-progress" x-data="readProgress" :style="'--p:' + p" aria-hidden="true"></div>

@unless ($post->isPublished())
    <div class="card px-5 py-3 mb-5 flex flex-wrap items-center gap-3 text-sm" style="background: color-mix(in oklab, var(--warn) 14%, var(--card))">
        @include('partials.icon', ['i' => 'lock', 'c' => 'w-4 h-4']) <b>{{ $post->stateLabel() }}</b>
        <span class="muted">{{ $post->isScheduled() ? 'Выйдет '.$post->published_at->translatedFormat('j F в H:i').'. ' : '' }}Видно только сотрудникам.</span>
        <a href="{{ route('admin.resource.edit', ['posts', $post->id]) }}" class="btn btn-primary !py-1.5 text-sm ml-auto">@include('partials.icon', ['i' => 'pen', 'c' => 'w-4 h-4']) В редактор</a>
    </div>
@endunless

<div class="grid grid-cols-[minmax(0,1fr)] xl:grid-cols-[minmax(0,1fr)_300px] gap-5 items-start">
    <article class="min-w-0 space-y-5">
        <div class="card post-sheet" data-reveal>
            <header class="post-head">
                <nav class="text-sm muted flex items-center gap-2 flex-wrap"><a href="{{ route('blog.index') }}" class="hover:text-[var(--text)]">Журнал</a>
                    @if ($post->category)<span class="faint">/</span><a href="{{ route('blog.category', $post->category) }}" class="hover:text-[var(--text)]">{{ $post->category->name }}</a>@endif
                </nav>
                <div class="flex items-center gap-2 flex-wrap mt-6">
                    @include('blog._category', ['post' => $post])
                    @if ($post->kind !== 'article' && mb_substr($post->kindLabel(), 0, 4) !== mb_substr($post->category?->name ?? '', 0, 4))<span class="chip !py-0.5 !text-[0.72rem]">{{ $post->kindLabel() }}</span>@endif
                    @if ($post->country)<span class="chip !py-0.5 !text-[0.72rem]">{{ $post->country->flag }} {{ $post->country->name }}</span>@endif
                </div>
                <h1 class="post-title font-display font-bold">{{ $post->title }}</h1>
                @if ($post->lead)<p class="post-lead">{{ $post->lead }}</p>@endif
                <div class="post-byline">
                    @if ($post->author)
                        @php($authorUrl = app(\App\Support\Founder::class)->urlFor($post->author)) {{-- A46 · основатель → /author --}}
                        <a @if ($authorUrl) href="{{ $authorUrl }}" @endif class="flex items-center gap-3 min-w-0">
                            @include('partials.avatar', ['u' => $post->author, 'c' => '!w-11 !h-11'])
                            <span class="min-w-0"><b class="block truncate">{{ $post->author->name }}</b><span class="text-xs muted">{{ app(\App\Support\Founder::class)->is($post->author) ? settings('author.headline', 'Автор') : (['admin' => 'Главный редактор', 'editor' => 'Редактор'][$post->author->role] ?? 'Автор') }}</span></span>
                        </a>
                    @endif
                    <div class="post-byline-facts">
                        @if ($post->published_at)<span><span class="cell-label">Дата</span><time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->translatedFormat('j F Y') }}</time></span>@endif
                        <span><span class="cell-label">Чтение</span>{{ $post->reading_minutes }} мин</span>
                        <span><span class="cell-label">Просмотры</span><span class="tabular">{{ number_format($post->views, 0, ',', ' ') }}</span></span>
                    </div>
                </div>
            </header>

            @if ($post->coverUrl())
                <figure class="post-hero-cover">
                    @include('blog._cover', ['post' => $post, 'eager' => true])
                    @if ($post->cover_caption)<figcaption>{{ $post->cover_caption }}</figcaption>@endif
                </figure>
            @endif

            {{-- Оглавление для телефона/планшета --}}
            @if (count($toc) > 2)
                <details class="toc-inline xl:hidden">
                    <summary>@include('partials.icon', ['i' => 'list', 'c' => 'w-4 h-4']) Содержание <span class="faint tabular">{{ count($toc) }}</span></summary>
                    <ol>@foreach ($toc as $h)<li class="lvl-{{ $h['level'] }}"><a href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ol>
                </details>
            @endif

            <div class="prose-banq" id="post-body">{!! $html !!}</div>

            @if ($post->source_url || $post->source_name)
                <p class="post-source">Источник: @if ($post->source_url)<a href="{{ $post->source_url }}" target="_blank" rel="noopener nofollow">{{ $post->source_name ?: parse_url($post->source_url, PHP_URL_HOST) }}</a>@else{{ $post->source_name }}@endif</p>
            @endif

            @if ($post->tags->isNotEmpty())
                <div class="post-tags">
                    @foreach ($post->tags as $t)<a href="{{ route('blog.tag', $t) }}" class="btn btn-ghost !py-1.5 !px-3 text-sm !font-medium">#{{ $t->name }}</a>@endforeach
                </div>
            @endif

            {{-- Реакции + поделиться (внизу статьи — на всех экранах) --}}
            <div class="post-actions">
                @include('blog._reactions')
                @include('blog._share')
            </div>
        </div>

        {{-- О ком материал: банки, вклады, карты, пакеты, платёжные системы, уровни --}}
        @if ($linked)
            @include('blog._linked')
        @endif

        {{-- Комментарии --}}
        @include('blog._comments')

        {{-- Дальше по журналу --}}
        @if ($prev || $next)
            <nav class="grid sm:grid-cols-2 gap-5" aria-label="Соседние публикации">
                @if ($prev)<a href="{{ route('blog.show', $prev) }}" class="card lift p-5 group"><span class="kicker">← раньше</span><p class="font-semibold mt-2 line-clamp-2 group-hover:underline underline-offset-4">{{ $prev->title }}</p></a>@else<span></span>@endif
                @if ($next)<a href="{{ route('blog.show', $next) }}" class="card lift p-5 text-right group"><span class="kicker">позже →</span><p class="font-semibold mt-2 line-clamp-2 group-hover:underline underline-offset-4">{{ $next->title }}</p></a>@endif
            </nav>
        @endif

        @if ($related->isNotEmpty())
            <section class="space-y-4">
                <h2 class="font-display font-bold text-2xl px-1">Читайте также</h2>
                <div class="post-grid is-3">
                    @foreach ($related as $i => $r)@include('blog._card', ['post' => $r, 'i' => $i])@endforeach
                </div>
            </section>
        @endif
    </article>

    {{-- ===== Правая колонка: оглавление (со скролл-шпионом), реакции, поделиться ===== --}}
    <aside class="hidden xl:block sticky top-[88px] space-y-5">
        @if (count($toc) > 1)
            <nav class="card p-5" x-data="tocSpy" aria-label="Содержание">
                <p class="kicker mb-3">Содержание</p>
                <ol class="toc">
                    @foreach ($toc as $h)
                        <li class="lvl-{{ $h['level'] }}"><a href="#{{ $h['id'] }}" :class="active === '{{ $h['id'] }}' && 'is-active'">{{ $h['text'] }}</a></li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <div class="card p-5 space-y-4">
            <p class="kicker">Реакции</p>
            @include('blog._reactions', ['vertical' => true])
            <div class="border-t border-[var(--line)] pt-4">@include('blog._share')</div>
        </div>
        <a href="#comments" class="card lift p-5 flex items-center gap-3">
            <span class="icon-tile">@include('partials.icon', ['i' => 'quote', 'c' => 'w-[19px] h-[19px]'])</span>
            <span><b class="block">{{ $commentsCount ? $commentsCount.' '.plural($commentsCount, 'комментарий', 'комментария', 'комментариев') : 'Обсудить' }}</b><span class="text-xs muted">{{ $post->allow_comments ? 'Поделитесь опытом' : 'Комментарии закрыты' }}</span></span>
        </a>
        {{-- A46 · материал основателя — короткий блок «Поддержать проект» --}}
        @if (app(\App\Support\Founder::class)->is($post->author))@include('partials.donate', ['compact' => true])@endif
    </aside>
</div>
@endsection
