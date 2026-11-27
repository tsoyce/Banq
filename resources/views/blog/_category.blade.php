{{-- A37 · Метка рубрики (цветная точка + название), ссылка на рубрику. --}}
@if ($post->category)
    <a href="{{ route('blog.category', $post->category) }}" class="post-cat {{ $c ?? '' }}" style="--c: {{ $post->category->colorOrDefault() }}">{{ $post->category->name }}</a>
@else
    <span class="post-cat {{ $c ?? '' }}" style="--c: var(--faint)">{{ $post->kindLabel() }}</span>
@endif
