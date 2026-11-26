{{-- A28 · Кабинет — сохранённые сравнения: марки банков, сумма и срок, открыть / переименовать / удалить. --}}
@extends('layouts.app', ['title' => 'Сохранённые сравнения'])

@section('content')
<div class="space-y-6">
    @include('account._shell', ['tab' => 'comparisons'])

    @if ($comparisons->isEmpty())
        <section class="card empty" data-reveal>
            <span class="icon-tile">@include('partials.icon', ['i' => 'scale'])</span>
            <div><p class="font-semibold">Сохранённых сравнений нет</p><p class="text-sm muted mt-1">Соберите до 4 вкладов, на странице сравнения нажмите «Сохранить» — подборка останется здесь с суммой и сроком.</p></div>
            <a href="{{ route('deposits.index') }}" class="btn btn-primary">Подобрать вклады</a>
        </section>
    @else
        <section class="grid md:grid-cols-2 gap-4 lg:gap-6">
            @foreach ($comparisons as $i => ['item' => $c, 'products' => $products])
                <article class="card p-6 flex flex-col gap-5" data-reveal style="--d: {{ 0.04 * $i }}s" x-data="{ edit: false }">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <h2 class="font-display font-bold text-lg leading-tight truncate" x-show="!edit">{{ $c->title }}</h2>
                            <form method="POST" action="{{ route('comparisons.update', $c) }}" class="flex gap-2" x-show="edit" x-cloak>
                                @csrf @method('PUT')
                                <input name="title" value="{{ $c->title }}" maxlength="120" required class="input !py-2" aria-label="Название">
                                <button class="btn btn-primary !py-2">OK</button>
                            </form>
                            <p class="text-xs muted mt-1">{{ $c->created_at->translatedFormat('j F Y') }}@if ($c->amount) · {{ money_format($c->amount) }}@endif @if ($c->term_months) · {{ $c->term_months }} мес@endif</p>
                        </div>
                        <div class="flex gap-1.5">
                            <button type="button" class="fav-btn" @click="edit = !edit" title="Переименовать" aria-label="Переименовать">@include('partials.icon', ['i' => 'pen', 'c' => 'w-4 h-4'])</button>
                            <form method="POST" action="{{ route('comparisons.destroy', $c) }}">@csrf @method('DELETE')
                                <button class="fav-btn hover:!text-[var(--bad)]" title="Удалить" aria-label="Удалить сравнение">@include('partials.icon', ['i' => 'trash', 'c' => 'w-4 h-4'])</button></form>
                        </div>
                    </div>
                    <div class="plate p-1.5">
                        @forelse ($products as $p)
                            <a href="{{ route('deposits.show', $p) }}" class="flex items-center gap-3 rounded-[10px] px-2.5 py-2 hover:bg-[var(--card)] transition">
                                <span class="bank-mark !w-8 !h-8 !text-[0.55rem]" style="--c: {{ $p->bank->brand_color ?? 'var(--brand-1)' }}">{{ $p->bank->mark() }}</span>
                                <span class="min-w-0 flex-1 text-sm"><b class="block truncate">{{ $p->name }}</b><span class="muted">{{ $p->bank->displayName() }}</span></span>
                                @unless ($p->is_active)<span class="chip">снят</span>@endunless
                            </a>
                        @empty
                            <p class="text-sm muted p-3">Вклады из этой подборки больше не продаются.</p>
                        @endforelse
                    </div>
                    <a href="{{ $c->url() }}" class="btn btn-primary mt-auto">Открыть сравнение @include('partials.icon', ['i' => 'arrow', 'c' => 'w-4 h-4'])</a>
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
