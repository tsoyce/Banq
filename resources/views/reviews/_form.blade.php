{{-- A18 · Форма отзыва о банке. $bank, $mine (?Review — свой прошлый отзыв), $products (вклады банка). --}}
<section id="review-form" class="card p-6 space-y-4 scroll-mt-32" data-reveal>
    <div>
        <h2 class="font-display font-bold text-xl">{{ $mine ? 'Ваш отзыв' : 'Оцените банк' }}</h2>
        <p class="text-sm muted mt-1">Отзыв попадёт в народный рейтинг после проверки модератором.</p>
    </div>

    @if (session('review_status'))
        <p class="plate p-3 text-sm flex gap-2">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4 mt-0.5 text-[var(--ok)] flex-none']) {{ session('review_status') }}</p>
    @endif

    @guest
        <div class="plate p-5 space-y-3 text-center">
            <span class="icon-tile mx-auto !bg-[var(--card)]">@include('partials.icon', ['i' => 'user'])</span>
            <p class="text-sm">Чтобы оставить отзыв и голосовать «полезно», войдите — это защищает рейтинг от накруток.</p>
            <div class="flex gap-2 justify-center">
                <a href="{{ route('register') }}" class="btn btn-primary">Регистрация</a>
                <a href="{{ route('login', ['to' => route('banks.show', $bank, false).'#review-form']) }}" class="btn btn-ghost">Войти</a>
            </div>
        </div>
    @else
        @if ($mine)
            <p class="text-sm flex items-center gap-2 flex-wrap">
                <span class="chip {{ $mine->status === 'approved' ? '!text-[var(--ok)]' : ($mine->status === 'rejected' ? '!text-[var(--bad)]' : '') }}">{{ $mine->statusLabel() }}</span>
                @if ($mine->status === 'rejected' && $mine->reject_reason)<span class="muted">Причина: {{ $mine->reject_reason }}</span>@endif
                @if ($mine->status === 'approved')<span class="muted">Правка снова уйдёт на проверку.</span>@endif
            </p>
        @endif
        <form method="POST" action="{{ route('reviews.store', $bank) }}" class="space-y-4" x-data="{ score: {{ (int) old('score', $mine?->score ?? 0) }}, hover: 0, len: {{ mb_strlen(old('body', $mine?->body ?? '')) }} }">
            @csrf
            <fieldset class="field">
                <legend class="text-[0.85rem] font-semibold mb-1.5">Оценка</legend>
                <div class="star-pick" @mouseleave="hover = 0">
                    @foreach ([1 => 'ужасно', 2 => 'плохо', 3 => 'нормально', 4 => 'хорошо', 5 => 'отлично'] as $n => $word)
                        <label :class="(hover || score) >= {{ $n }} && 'on'" @mouseenter="hover = {{ $n }}" title="{{ $word }}">
                            <input type="radio" name="score" value="{{ $n }}" class="sr-only" x-model.number="score" @checked((int) old('score', $mine?->score) === $n)>
                            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
                            <span class="sr-only">{{ $n }} — {{ $word }}</span>
                        </label>
                    @endforeach
                </div>
                @error('score')<p class="error">{{ $message }}</p>@enderror
            </fieldset>
            @if ($products->isNotEmpty())
                <label class="field"><span>Продукт <span class="muted font-normal">(необязательно)</span></span>
                    <select name="deposit_product_id" class="input">
                        <option value="">О банке в целом</option>
                        @foreach ($products as $p)<option value="{{ $p->id }}" @selected((int) old('deposit_product_id', $mine?->deposit_product_id) === $p->id)>{{ $p->name }}</option>@endforeach
                    </select></label>
            @endif
            <label class="field"><span>Заголовок</span>
                <input name="title" class="input" maxlength="160" required value="{{ old('title', $mine?->title) }}" placeholder="Коротко: что понравилось или нет">
                @error('title')<p class="error">{{ $message }}</p>@enderror</label>
            <label class="field"><span class="flex justify-between">Отзыв <span class="font-normal tabular" :class="len < {{ config('banq.ratings.review_min_length') }} ? 'faint' : 'text-[var(--ok)]'" x-text="len + ' / {{ config('banq.ratings.review_min_length') }}+'"></span></span>
                <textarea name="body" class="input !min-h-[140px]" maxlength="5000" required @input="len = $event.target.value.length" placeholder="Как открывали вклад, начисляли проценты, работает приложение и поддержка">{{ old('body', $mine?->body) }}</textarea>
                @error('body')<p class="error">{{ $message }}</p>@enderror</label>
            <button class="btn btn-primary w-full">{{ $mine ? 'Обновить отзыв' : 'Отправить на проверку' }}</button>
            <p class="text-xs faint">Без мата, рекламы и личных данных. Один отзыв на банк — его можно править.</p>
        </form>
    @endguest
</section>
