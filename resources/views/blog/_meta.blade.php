{{-- A37 · Строка метаданных публикации: дата · время чтения · просмотры. --}}
<span class="post-meta {{ $c ?? '' }}">
    @if ($post->published_at)<time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->isCurrentYear() ? $post->published_at->translatedFormat('j F') : $post->published_at->translatedFormat('j F Y') }}</time>@endif
    <span>{{ $post->reading_minutes }} мин</span>
    @if ($post->views)<span class="inline-flex items-center gap-1">@include('partials.icon', ['i' => 'eye', 'c' => 'w-3.5 h-3.5']){{ number_format($post->views, 0, ',', ' ') }}</span>@endif
</span>
