{{-- A37 · Обложка публикации: картинка или типографская заглушка (градиент рубрики + крупная буква). $post, $c — классы, $size — кегль заглушки. --}}
@php $color = $post->category?->colorOrDefault() ?? '#7b5cff'; @endphp
@if ($post->coverUrl())
    <img src="{{ $post->coverUrl() }}" alt="{{ $post->cover_alt ?: '' }}" class="post-cover {{ $c ?? '' }}" loading="{{ ($eager ?? false) ? 'eager' : 'lazy' }}" decoding="async">
@else
    <div class="post-cover post-cover-gen {{ $c ?? '' }}" style="--c: {{ $color }}" aria-hidden="true">
        <span class="post-cover-glyph">{{ mb_strtoupper(mb_substr(trim(preg_replace('/^[«"„]+/u', '', $post->title)), 0, 1)) }}</span>
        @if ($post->category?->icon)@include('partials.icon', ['i' => $post->category->icon, 'c' => 'post-cover-icon'])@endif
    </div>
@endif
