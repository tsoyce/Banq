{{-- A37 · Реакции: эмодзи + счётчик, «всплеск» при нажатии. Состояние общее для всех копий на странице — $store.post (resources/js/blog.js).
     Гость → на вход. $vertical — сеткой 2×2 в боковой колонке. --}}
@once
    <script>window.banqPost = {!! json_encode(['react' => route('blog.react', $post), 'counts' => $reactions, 'mine' => $mine, 'auth' => auth()->check(), 'login' => route('login', ['to' => $post->url()])], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) !!};</script>
@endonce
<div class="reactions {{ ($vertical ?? false) ? 'is-grid' : '' }}" x-data role="group" aria-label="Реакции">
    @foreach (\App\Models\Post::REACTIONS as $k => [$emoji, $label])
        <button type="button" class="reaction {{ in_array($k, $mine, true) ? 'is-on' : '' }}" :class="$store.post.mine.includes('{{ $k }}') && 'is-on'"
                @click="$store.post.toggle('{{ $k }}', $el)" title="{{ $label }}" aria-label="{{ $label }}" :aria-pressed="$store.post.mine.includes('{{ $k }}')">
            <span class="reaction-emoji" aria-hidden="true">{{ $emoji }}</span>
            <span class="tabular" x-text="$store.post.counts['{{ $k }}'] || ''">{{ $reactions[$k] ?: '' }}</span>
        </button>
    @endforeach
</div>
