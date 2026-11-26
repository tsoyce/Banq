{{-- A28 · Аватар пользователя: фото или первая буква на градиенте его оттенка. $u — User, $c — доп. классы (размер). --}}
@if ($u->avatarUrl())
    <span class="avatar {{ $c ?? '' }}"><img src="{{ $u->avatarUrl() }}" alt="{{ $u->name }}" loading="lazy" decoding="async"></span>
@else
    <span class="avatar is-gen {{ $c ?? '' }}" style="--h: {{ $u->hue() }}" aria-hidden="true">{{ $u->initial() }}</span>
@endif
