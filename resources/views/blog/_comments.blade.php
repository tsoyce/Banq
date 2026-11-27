{{-- A37 · Комментарии: форма (вошедшие), ответы в один уровень, свои на проверке видны автору. --}}
<section id="comments" class="card p-5 sm:p-7 scroll-mt-28" x-data="{ parent: null, parentName: '', replyTo(id, name) { this.parent = id; this.parentName = name; this.$nextTick(() => { this.$refs.body?.focus(); this.$refs.form?.scrollIntoView({ block: 'center', behavior: 'smooth' }) }) } }">
    <div class="flex items-end justify-between gap-3 mb-5">
        <h2 class="font-display font-bold text-2xl">Обсуждение <span class="faint tabular">{{ $commentsCount ?: '' }}</span></h2>
    </div>

    @if ($post->allow_comments && $post->isPublished())
        @auth
            <form method="POST" action="{{ route('blog.comments.store', $post) }}" class="comment-form" x-ref="form" x-data="{ len: {{ mb_strlen(old('body', '')) }} }">
                @csrf
                <input type="hidden" name="parent_id" :value="parent">
                @include('partials.avatar', ['u' => auth()->user(), 'c' => '!w-10 !h-10 text-sm hidden sm:grid'])
                <div class="flex-1 min-w-0 space-y-2">
                    <p class="text-xs muted" x-show="parent" x-cloak>Ответ для <b x-text="parentName"></b> · <button type="button" class="underline" @click="parent = null">отменить</button></p>
                    <textarea name="body" x-ref="body" rows="3" maxlength="3000" class="input" placeholder="Что думаете? Поделитесь опытом с банком или продуктом…" @input="len = $event.target.value.length" required>{{ old('body') }}</textarea>
                    @error('body')<p class="error">{{ $message }}</p>@enderror
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs faint tabular" x-text="len ? len + ' / 3000' : 'Без оскорблений и рекламы — такие комментарии скрываем'"></span>
                        <button class="btn btn-primary !py-2" :disabled="len < 2">@include('partials.icon', ['i' => 'send', 'c' => 'w-4 h-4']) Отправить</button>
                    </div>
                </div>
            </form>
        @else
            <div class="plate p-5 flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
                <p class="text-sm"><b>Войдите, чтобы комментировать</b><span class="muted block">и ставить реакции — это бесплатно и займёт минуту.</span></p>
                <div class="flex gap-2 shrink-0">
                    <a href="{{ route('login', ['to' => $post->url().'#comments']) }}" class="btn btn-primary !py-2">Войти</a>
                    <a href="{{ route('register') }}" class="btn btn-ghost !py-2">Регистрация</a>
                </div>
            </div>
        @endauth
    @elseif (! $post->allow_comments)
        <p class="plate p-4 text-sm muted flex items-center gap-2">@include('partials.icon', ['i' => 'lock', 'c' => 'w-4 h-4']) Комментарии к этой публикации закрыты.</p>
    @endif

    @if ($comments->isNotEmpty())
        <ul class="comments">
            @foreach ($comments as $c)@include('blog._comment', ['c' => $c])@endforeach
        </ul>
    @elseif ($post->allow_comments)
        <p class="text-sm muted mt-5 text-center">Пока тихо. Ваш комментарий может стать первым.</p>
    @endif
</section>
