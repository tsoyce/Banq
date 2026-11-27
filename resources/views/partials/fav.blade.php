{{-- A28 · Кнопка «в избранное». $type — deposit|bank, $id, $on — уже в избранном, $wide — показать подпись (широкая кнопка). A36: было $label — имя протекало из родительских @foreach и включало подпись где не надо.
     С JS — fetch без перезагрузки (Alpine favorite в resources/js/app.js), без JS — обычная форма. Гостя ведёт на вход. --}}
@auth
    <form method="POST" action="{{ route('favorites.toggle') }}" x-data="favorite({{ ($on ?? false) ? 'true' : 'false' }})" @submit.prevent="toggle($el)" class="contents">
        @csrf<input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="id" value="{{ $id }}">
        <button class="fav-btn {{ ($wide ?? false) ? 'btn-like' : '' }} {{ ($on ?? false) ? 'is-on' : '' }}" :class="on && 'is-on'" :aria-pressed="on"
                :title="on ? 'Убрать из избранного' : 'В избранное'" aria-label="Избранное">
            @include('partials.icon', ['i' => 'heart', 'c' => ($wide ?? false) ? 'w-4 h-4' : 'w-[18px] h-[18px]'])
            @if ($wide ?? false)<span x-text="on ? 'В избранном' : 'В избранное'">{{ ($on ?? false) ? 'В избранном' : 'В избранное' }}</span>@endif
        </button>
    </form>
@else
    <a href="{{ route('login', ['to' => request()->getRequestUri()]) }}" class="fav-btn {{ ($wide ?? false) ? 'btn-like' : '' }}" title="Войдите, чтобы сохранять в избранное" aria-label="В избранное">
        @include('partials.icon', ['i' => 'heart', 'c' => ($wide ?? false) ? 'w-4 h-4' : 'w-[18px] h-[18px]'])@if ($wide ?? false)<span>В избранное</span>@endif
    </a>
@endauth
