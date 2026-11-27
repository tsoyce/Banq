{{-- A37 · «Публикации о …» на страницах банка, вклада, карты, пакета и платёжной системы.
     $about — модель (Bank, DepositProduct, Card, ServicePackage, PaymentSystem); связи с учётом каскада (Post::syncLinks). $limit — сколько показать. --}}
@php
    $aboutPosts = \App\Models\Post::query()->published()->forCountry()->about($about)->with('category')
        ->orderByDesc('published_at')->limit($limit ?? 4)->get();
@endphp
@if ($aboutPosts->isNotEmpty())
    <section class="card p-5 sm:p-6 {{ $class ?? '' }}" data-reveal>
        <div class="flex items-center justify-between gap-3 mb-2">
            <h2 class="font-display font-bold text-xl flex items-center gap-2">@include('partials.icon', ['i' => 'news', 'c' => 'w-5 h-5 text-[var(--brand-1)]']) {{ $title ?? 'В журнале' }}</h2>
            <a href="{{ route('blog.index') }}" class="text-sm muted hover:text-[var(--text)] shrink-0">Журнал →</a>
        </div>
        <div>
            @foreach ($aboutPosts as $p)
                <a href="{{ $p->url() }}" class="list-row group items-start">
                    <span class="w-16 h-12 rounded-xl overflow-hidden shrink-0">@include('blog._cover', ['post' => $p])</span>
                    <span class="min-w-0">
                        <span class="font-semibold leading-snug line-clamp-2 group-hover:underline underline-offset-4">{{ $p->title }}</span>
                        <span class="text-xs muted block mt-1">{{ $p->category?->name ?? $p->kindLabel() }} · {{ $p->published_at->translatedFormat('j F') }} · {{ $p->reading_minutes }} мин</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
