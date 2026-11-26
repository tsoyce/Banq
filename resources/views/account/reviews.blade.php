{{-- A28 · Кабинет — мои отзывы: фильтр по статусу, причина отказа, ответ банка; банки, о которых ещё не писали. --}}
@extends('layouts.app', ['title' => 'Мои отзывы'])

@section('content')
<div class="space-y-6">
    @include('account._shell', ['tab' => 'reviews'])

    <section class="grid grid-cols-6 gap-4 lg:gap-6 items-start">
        <div class="card col-span-6 lg:col-span-4 p-6 lg:p-7" data-reveal>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
                <h2 class="font-display font-bold text-xl">Мои отзывы</h2>
                <nav class="seg max-w-full overflow-x-auto" aria-label="Статус">
                    <a href="{{ route('account.reviews') }}" class="{{ $status ? '' : 'is-active' }}">Все</a>
                    <a href="{{ route('account.reviews', ['status' => 'approved']) }}" class="{{ $status === 'approved' ? 'is-active' : '' }}">Опубликованы <span class="tabular muted">{{ $stats['published'] }}</span></a>
                    <a href="{{ route('account.reviews', ['status' => 'pending']) }}" class="{{ $status === 'pending' ? 'is-active' : '' }}">На модерации <span class="tabular muted">{{ $stats['pending'] }}</span></a>
                    @if ($stats['rejected'])<a href="{{ route('account.reviews', ['status' => 'rejected']) }}" class="{{ $status === 'rejected' ? 'is-active' : '' }}">Отклонены <span class="tabular muted">{{ $stats['rejected'] }}</span></a>@endif
                </nav>
            </div>
            @forelse ($reviews as $r)
                @include('account._review-row', ['r' => $r])
            @empty
                <div class="empty">
                    <span class="icon-tile">@include('partials.icon', ['i' => 'pen'])</span>
                    <div><p class="font-semibold">{{ $status ? 'Здесь пусто' : 'Отзывов пока нет' }}</p><p class="text-sm muted mt-1">Один отзыв на банк — его можно поправить в любой момент.</p></div>
                </div>
            @endforelse
            @if ($reviews->hasPages())<div class="pt-4">{{ $reviews->links() }}</div>@endif
        </div>

        <aside class="col-span-6 lg:col-span-2 space-y-4 lg:space-y-6">
            <div class="card p-6" data-reveal style="--d:.06s">
                <p class="kicker">Оценить ещё</p>
                <h2 class="font-display font-bold text-lg mt-1">Банки без вашего отзыва</h2>
                <div class="mt-2">
                    @forelse ($suggest as $b)
                        <a href="{{ route('banks.show', $b) }}#review-form" class="list-row group">
                            <span class="bank-mark" style="--c: {{ $b->brand_color ?? 'var(--brand-1)' }}">{{ $b->mark() }}</span>
                            <span class="flex-1 min-w-0 font-semibold truncate group-hover:underline">{{ $b->displayName() }}</span>
                            @include('partials.icon', ['i' => 'pen', 'c' => 'w-4 h-4 muted'])
                        </a>
                    @empty
                        <p class="text-sm muted py-3">Вы оценили все банки. Спасибо!</p>
                    @endforelse
                </div>
            </div>
            <div class="card card-soft p-6 text-sm space-y-2" data-reveal style="--d:.1s">
                <p class="font-semibold flex items-center gap-2">@include('partials.icon', ['i' => 'info', 'c' => 'w-4 h-4']) Как работает модерация</p>
                <p class="muted">Отзыв появится на сайте и в народном рейтинге после проверки модератором. Правка отправляет отзыв на модерацию снова. Письмо о решении — если включено в <a href="{{ route('account.settings') }}#notify" class="underline">настройках</a>.</p>
            </div>
        </aside>
    </section>
</div>
@endsection
