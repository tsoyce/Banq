{{-- A37 · Сетка карточек (отдаётся и частично — «Показать ещё», X-Partial: posts). --}}
@foreach ($posts as $i => $post)
    @include('blog._card', ['post' => $post, 'i' => $i])
@endforeach
