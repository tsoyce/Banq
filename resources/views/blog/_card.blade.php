{{-- A37 · Карточка публикации в ленте. $post; $i — номер для задержки появления. --}}
<article class="card post-card lift" data-reveal style="--d: {{ min(0.04 * ($i ?? 0), 0.3) }}s">
    <a href="{{ $post->url() }}" class="post-card-media" tabindex="-1" aria-hidden="true">@include('blog._cover', ['post' => $post])</a>
    <div class="post-card-body">
        <div class="flex items-center gap-2 flex-wrap">
            @include('blog._category', ['post' => $post])
            @if ($post->kind !== 'article' && mb_substr($post->kindLabel(), 0, 4) !== mb_substr($post->category?->name ?? '', 0, 4))<span class="chip !py-0.5 !text-[0.7rem]">{{ $post->kindLabel() }}</span>@endif
        </div>
        <h3 class="post-card-title"><a href="{{ $post->url() }}" class="stretched">{{ $post->title }}</a></h3>
        @if ($post->lead)<p class="post-card-lead">{{ $post->lead }}</p>@endif
        <div class="post-card-foot">
            @include('blog._meta', ['post' => $post])
            @if (($post->comments_count ?? 0) > 0)<span class="inline-flex items-center gap-1 text-xs muted ml-auto">@include('partials.icon', ['i' => 'quote', 'c' => 'w-3.5 h-3.5']){{ $post->comments_count }}</span>@endif
        </div>
    </div>
</article>
