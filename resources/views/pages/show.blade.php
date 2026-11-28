{{-- A42 · Статическая страница (/{slug}): тот же лист и типографика, что у публикации, без ленты, реакций и комментариев. --}}
@extends('layouts.app', ['title' => $page->metaTitle(), 'description' => $page->metaDescription()])

@push('head')
    <link rel="canonical" href="{{ $page->url() }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $page->url() }}">
    @if ($page->coverUrl())<meta property="og:image" content="{{ url($page->coverUrl()) }}"><meta name="twitter:card" content="summary_large_image">@endif
    @unless ($page->isPublished())<meta name="robots" content="noindex">@endunless
@endpush

@section('content')
@unless ($page->isPublished())
    <div class="card px-5 py-3 mb-5 flex flex-wrap items-center gap-3 text-sm" style="background: color-mix(in oklab, var(--warn) 14%, var(--card))">
        @include('partials.icon', ['i' => 'lock', 'c' => 'w-4 h-4']) <b>{{ $page->stateLabel() }}</b>
        <span class="muted">Видно только сотрудникам.</span>
        <a href="{{ route('admin.resource.edit', ['pages', $page->id]) }}" class="btn btn-primary !py-1.5 text-sm ml-auto">@include('partials.icon', ['i' => 'pen', 'c' => 'w-4 h-4']) В редактор</a>
    </div>
@endunless

<div class="grid grid-cols-[minmax(0,1fr)] xl:grid-cols-[minmax(0,1fr)_300px] gap-5 items-start">
    <article class="min-w-0">
        <div class="card post-sheet" data-reveal>
            <header class="post-head">
                <nav class="text-sm muted flex items-center gap-2 flex-wrap"><a href="{{ route('home') }}" class="hover:text-[var(--text)]">Главная</a><span class="faint">/</span><span>{{ $page->title }}</span></nav>
                <h1 class="post-title font-display font-bold">{{ $page->title }}</h1>
                @if ($page->lead)<p class="post-lead">{{ $page->lead }}</p>@endif
                @if ($page->updated_at)<p class="text-sm faint mt-4">Обновлено {{ $page->updated_at->translatedFormat('j F Y') }}</p>@endif
            </header>
            @if ($page->coverUrl())
                <figure class="post-hero-cover">
                    @include('blog._cover', ['post' => $page, 'eager' => true])
                    @if ($page->cover_caption)<figcaption>{{ $page->cover_caption }}</figcaption>@endif
                </figure>
            @endif
            @if (count($toc) > 2)
                <details class="toc-inline xl:hidden">
                    <summary>@include('partials.icon', ['i' => 'list', 'c' => 'w-4 h-4']) Содержание <span class="faint tabular">{{ count($toc) }}</span></summary>
                    <ol>@foreach ($toc as $h)<li class="lvl-{{ $h['level'] }}"><a href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ol>
                </details>
            @endif
            <div class="prose-banq" id="post-body">{!! $html !!}</div>
        </div>
    </article>

    @if (count($toc) > 2)
        <aside class="hidden xl:block sticky top-[88px]">
            <nav class="card p-5" x-data="tocSpy" aria-label="Содержание">
                <p class="kicker mb-3">Содержание</p>
                <ol class="toc">@foreach ($toc as $h)<li class="lvl-{{ $h['level'] }}"><a href="#{{ $h['id'] }}" :class="active === '{{ $h['id'] }}' && 'is-active'">{{ $h['text'] }}</a></li>@endforeach</ol>
            </nav>
        </aside>
    @endif
</div>
@endsection
