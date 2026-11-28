{{-- A46 · Блок «Поддержать проект»: ссылки на переводы и реквизиты с копированием. Настройки donate.* — админка → «Автор и соцсети».
     $compact — короткая карточка (сайдбар статьи): заголовок, одна строка и кнопки. --}}
@php($donateItems = collect((array) settings('donate.items', []))->filter(fn ($d) => filled($d['url'] ?? null) || filled($d['details'] ?? null))->values())
@if (settings('donate.enabled', true) && $donateItems->isNotEmpty())
    @if ($compact ?? false)
        <div class="card donate-card is-compact p-5 space-y-3">
            <div class="flex items-center gap-3">
                <span class="donate-heart">@include('partials.icon', ['i' => 'heart', 'c' => 'w-[18px] h-[18px]'])</span>
                <b class="leading-tight">{{ settings('donate.title', 'Поддержать проект') }}</b>
            </div>
            <p class="text-sm muted leading-snug">Без рекламы банков и платных мест в рейтингах.</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($donateItems as $d)
                    <a href="{{ filled($d['url'] ?? null) ? $d['url'] : route('author').'#donate' }}" @if (filled($d['url'] ?? null)) target="_blank" rel="noopener" @endif class="chip !py-1.5 !px-3 font-semibold hover:!bg-[var(--card-2)]">{{ $d['currency'] ?? '' }} · {{ $d['label'] }}</a>
                @endforeach
            </div>
        </div>
    @else
        <section id="donate" class="card donate-card p-6 lg:p-8 scroll-mt-24" data-reveal>
            <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] gap-6 lg:gap-10 items-start">
                <div class="space-y-3">
                    <p class="kicker">{{ $kicker ?? 'Поддержка' }}</p>
                    <h2 class="font-display font-bold text-2xl lg:text-3xl leading-tight flex items-center gap-3">
                        <span class="donate-heart">@include('partials.icon', ['i' => 'heart', 'c' => 'w-5 h-5'])</span>
                        {{ settings('donate.title', 'Поддержать проект') }}
                    </h2>
                    @if (settings('donate.text'))<p class="text-[15px] leading-[1.6] muted max-w-xl">{{ settings('donate.text') }}</p>@endif
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach ($donateItems as $d)
                        <div class="plate p-4 flex flex-col gap-3" x-data="{ done: false, copy(t) { navigator.clipboard?.writeText(t).then(() => { this.done = true; setTimeout(() => this.done = false, 1800); }); } }">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <b class="block">{{ $d['label'] }}</b>
                                    @if (filled($d['note'] ?? null))<span class="block text-xs muted mt-0.5">{{ $d['note'] }}</span>@endif
                                </div>
                                @if (filled($d['currency'] ?? null))<span class="chip !py-0.5 !text-[0.72rem] mono shrink-0">{{ $d['currency'] }}</span>@endif
                            </div>
                            @if (filled($d['details'] ?? null))
                                <button type="button" class="donate-details" @click="copy(@js($d['details']))" :class="done && 'is-done'" title="Скопировать">
                                    <span class="mono text-sm break-all text-left">{{ $d['details'] }}</span>
                                    <span class="shrink-0 inline-flex items-center gap-1 text-xs font-semibold">
                                        <span x-show="!done">@include('partials.icon', ['i' => 'copy', 'c' => 'w-4 h-4'])</span>
                                        <span x-show="done" x-cloak class="inline-flex items-center gap-1">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) Скопировано</span>
                                    </span>
                                </button>
                            @endif
                            @if (filled($d['url'] ?? null))
                                <a href="{{ $d['url'] }}" target="_blank" rel="noopener" class="btn btn-brand justify-center mt-auto">Перевести @include('partials.icon', ['i' => 'external', 'c' => 'w-4 h-4'])</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endif
