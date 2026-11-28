{{-- A37 · Сетка карточек (отдаётся и частично — «Показать ещё», X-Partial: posts). --}}
@php($adAt = (int) settings('ads.feed_position', 6)) {{-- A51 · нативная карточка — один раз, на первой странице ленты --}}
@foreach ($posts as $i => $post)
    @include('blog._card', ['post' => $post, 'i' => $i])
    @if ($adAt > 0 && $i + 1 === $adAt && $posts instanceof \Illuminate\Contracts\Pagination\Paginator && $posts->currentPage() === 1 && $posts->count() > $adAt)
        @include('ads.slot', ['slot' => 'feed-native', 'class' => 'card post-card'])
    @endif
@endforeach
