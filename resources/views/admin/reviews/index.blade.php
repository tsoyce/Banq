{{-- A18 · Модерация отзывов: вкладки по статусу, одобрить / отклонить с причиной, ответ банка, удалить. --}}
@extends('layouts.admin', ['title' => 'Отзывы'])

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <h1 class="font-display font-bold text-3xl">Отзывы</h1>
    <nav class="seg">
        @foreach (\App\Models\Review::STATUSES as $key => $label)
            <a href="{{ route('admin.reviews.index', ['status' => $key]) }}" class="{{ $status === $key ? 'is-active' : 'muted' }}">{{ $label }} <span class="tabular opacity-60">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
    </nav>
</div>

@forelse ($reviews as $r)
    <article class="card p-6 space-y-4" x-data="{ mode: null }">
        <header class="flex flex-wrap items-start gap-3 justify-between">
            <div class="flex items-center gap-3">
                <span class="bank-mark" style="--c: {{ $r->bank->brand_color ?? 'var(--ink)' }}">{{ $r->bank->mark() }}</span>
                <div>
                    <p class="font-semibold">{{ $r->bank->displayName() }} @if ($r->product)<span class="muted font-normal">· {{ $r->product->name }}</span>@endif</p>
                    <p class="text-xs muted">{{ $r->user->name }} · {{ $r->user->email }} · {{ $r->updated_at->format('d.m.Y H:i') }} · IP {{ $r->ip ?? '—' }}</p>
                </div>
            </div>
            @include('partials.stars', ['score' => $r->score, 'starSize' => 'w-5 h-5'])
        </header>
        <div>
            <h2 class="font-semibold text-lg">{{ $r->title }}</h2>
            <p class="mt-1 whitespace-pre-line text-[15px]">{{ $r->body }}</p>
        </div>
        @if ($r->status === 'rejected' && $r->reject_reason)<p class="text-sm"><span class="chip !text-[var(--bad)]">отклонён</span> {{ $r->reject_reason }}</p>@endif
        @if ($r->reply)<div class="plate p-4 text-sm"><p class="font-semibold">{{ $r->reply_author ?: 'Ответ банка' }}</p><p class="whitespace-pre-line mt-1">{{ $r->reply }}</p></div>@endif

        <div class="flex flex-wrap gap-2">
            @if ($r->status !== 'approved')
                <form method="POST" action="{{ route('admin.reviews.update', $r) }}">@csrf @method('PUT')<input type="hidden" name="action" value="approve"><button class="btn btn-primary !py-2">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) Опубликовать</button></form>
            @endif
            @if ($r->status !== 'rejected')<button type="button" class="btn btn-ghost !py-2" @click="mode = mode === 'reject' ? null : 'reject'">Отклонить…</button>@endif
            <button type="button" class="btn btn-ghost !py-2" @click="mode = mode === 'reply' ? null : 'reply'">Ответ банка…</button>
            <form method="POST" action="{{ route('admin.reviews.destroy', $r) }}" class="ml-auto">@csrf @method('DELETE')<button class="btn btn-outline !py-2 text-[var(--bad)]">Удалить</button></form>
        </div>
        <form method="POST" action="{{ route('admin.reviews.update', $r) }}" x-show="mode === 'reject'" x-cloak class="plate p-4 flex flex-col sm:flex-row gap-3">
            @csrf @method('PUT')<input type="hidden" name="action" value="reject">
            <input name="reject_reason" class="input !bg-[var(--card)] flex-1" placeholder="Причина: реклама, оскорбления, не про банк…" required maxlength="255">
            <button class="btn btn-primary">Отклонить</button>
        </form>
        <form method="POST" action="{{ route('admin.reviews.update', $r) }}" x-show="mode === 'reply'" x-cloak class="plate p-4 space-y-3">
            @csrf @method('PUT')<input type="hidden" name="action" value="reply">
            <input name="reply_author" class="input !bg-[var(--card)]" placeholder="Подпись: «Служба заботы Kaspi»" value="{{ $r->reply_author }}" maxlength="120">
            <textarea name="reply" class="input !bg-[var(--card)]" placeholder="Официальный ответ банка или редакции">{{ $r->reply }}</textarea>
            <button class="btn btn-primary">Сохранить ответ</button>
        </form>
    </article>
@empty
    <div class="card p-10 text-center muted">{{ $status === 'pending' ? 'Очередь пуста — все отзывы проверены.' : 'Здесь пока пусто.' }}</div>
@endforelse

@if ($errors->any())<ul class="error">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
{{ $reviews->links() }}
@endsection
