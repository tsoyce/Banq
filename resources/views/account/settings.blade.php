{{-- A28 · Кабинет — настройки: профиль и фото, публичность, почта, пароль, уведомления, удаление аккаунта. --}}
@extends('layouts.app', ['title' => 'Настройки аккаунта'])

@section('content')
<div class="space-y-6">
    @include('account._shell', ['tab' => 'settings'])

    <div class="grid grid-cols-6 gap-4 lg:gap-6 items-start">
        {{-- Профиль --}}
        <form id="profile" method="POST" action="{{ route('account.settings.profile') }}" enctype="multipart/form-data"
              class="card col-span-6 lg:col-span-4 p-6 lg:p-8 space-y-6 scroll-mt-28" data-reveal
              x-data="{ preview: null, bio: @js(old('bio', $user->bio) ?? '') }">
            @csrf @method('PUT')
            <div><p class="kicker">02 / Профиль</p><h2 class="font-display font-bold text-xl mt-1">Как вас видят другие</h2>
                <p class="text-sm muted mt-1">Имя, фото и город показываются рядом с вашими отзывами и в публичном профиле.</p></div>

            <div class="flex flex-wrap items-center gap-5">
                <span x-show="preview" x-cloak class="avatar avatar-xl"><img :src="preview" alt=""></span>
                <span x-show="!preview">@include('partials.avatar', ['u' => $user, 'c' => 'avatar-xl'])</span>
                <div class="space-y-2">
                    <label class="btn btn-ghost cursor-pointer">@include('partials.icon', ['i' => 'camera', 'c' => 'w-4 h-4']) {{ $user->avatar ? 'Заменить фото' : 'Загрузить фото' }}
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="const f = $event.target.files[0]; preview = f ? URL.createObjectURL(f) : null">
                    </label>
                    <p class="text-xs muted">JPG, PNG или WebP до 2 МБ, от 64×64</p>
                </div>
                @if ($user->avatar)
                    <button form="avatar-delete" class="btn btn-outline !py-2 text-sm">@include('partials.icon', ['i' => 'trash', 'c' => 'w-4 h-4']) Удалить фото</button>
                @endif
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <label class="field"><span>Имя</span><input name="name" class="input" value="{{ old('name', $user->name) }}" required minlength="2" maxlength="60" autocomplete="nickname"></label>
                <label class="field"><span>Адрес профиля</span>
                    <span class="flex items-center rounded-[12px] bg-[var(--card-2)] focus-within:ring-[3px] focus-within:ring-[color-mix(in_oklab,var(--text)_12%,transparent)]"><span class="pl-3.5 text-sm muted whitespace-nowrap mono">/u/</span><input name="username" class="input !bg-transparent !pl-1 mono" value="{{ old('username', $user->username) }}" required minlength="3" maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9\-]{1,38}[A-Za-z0-9]" autocapitalize="off" spellcheck="false"></span></label>
                <label class="field"><span>Город</span><input name="city" class="input" value="{{ old('city', $user->city) }}" maxlength="80" autocomplete="address-level2" placeholder="Алматы"></label>
                <label class="field"><span>Страна</span>
                    <select name="country_code" class="input">
                        <option value="">Не указана</option>
                        @foreach ($countries as $code => $label)<option value="{{ $code }}" @selected(old('country_code', $user->country_code) === $code)>{{ $label }}</option>@endforeach
                    </select></label>
            </div>
            <label class="field"><span class="flex justify-between">О себе <small class="muted font-normal tabular" x-text="bio.length + ' / 280'"></small></span>
                <textarea name="bio" rows="3" maxlength="280" class="input" x-model="bio" placeholder="Например: держу вклады в трёх банках, слежу за ставками с 2020 года"></textarea></label>
            <label class="switch plate !px-4 !py-3"><span><b class="block">Публичный профиль</b><small class="muted">Имя у отзывов ведёт на вашу страницу. Выключите — отзывы останутся, но без ссылки.</small></span>
                <input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" class="sr-only" @checked(old('is_public', $user->is_public))><i></i></label>

            @if ($errors->default->any())<ul class="error space-y-1">@foreach ($errors->default->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
            <div class="flex flex-wrap items-center gap-3">
                <button class="btn btn-primary">Сохранить профиль</button>
                <a href="{{ route('profile.show', $user->username) }}" class="btn btn-ghost">Посмотреть, как видят другие</a>
            </div>
        </form>
        <form id="avatar-delete" method="POST" action="{{ route('account.settings.avatar.destroy') }}" class="hidden">@csrf @method('DELETE')</form>

        <div class="col-span-6 lg:col-span-2 space-y-4 lg:space-y-6">
            {{-- Почта --}}
            <form method="POST" action="{{ route('account.settings.email') }}" class="card p-6 space-y-4" data-reveal style="--d:.06s">
                @csrf @method('PUT')
                <h2 class="font-display font-bold text-lg flex items-center gap-2">@include('partials.icon', ['i' => 'mail', 'c' => 'w-5 h-5 muted']) Почта</h2>
                <label class="field"><span>Email</span><input name="email" type="email" class="input" value="{{ old('email', $user->email) }}" required autocomplete="email"></label>
                <label class="field"><span>Текущий пароль</span><input name="current_password" type="password" class="input" required autocomplete="current-password"></label>
                @if ($errors->email->any())<ul class="error space-y-1">@foreach ($errors->email->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
                <button class="btn btn-ghost w-full">Сменить почту</button>
            </form>

            {{-- Пароль --}}
            <form method="POST" action="{{ route('account.settings.password') }}" class="card p-6 space-y-4" data-reveal style="--d:.1s">
                @csrf @method('PUT')
                <h2 class="font-display font-bold text-lg flex items-center gap-2">@include('partials.icon', ['i' => 'lock', 'c' => 'w-5 h-5 muted']) Пароль</h2>
                <label class="field"><span>Текущий пароль</span><input name="current_password" type="password" class="input" required autocomplete="current-password"></label>
                <label class="field"><span>Новый пароль</span><input name="password" type="password" class="input" required minlength="8" autocomplete="new-password"></label>
                <label class="field"><span>Ещё раз</span><input name="password_confirmation" type="password" class="input" required autocomplete="new-password"></label>
                @if ($errors->password->any())<ul class="error space-y-1">@foreach ($errors->password->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
                <button class="btn btn-ghost w-full">Сменить пароль</button>
                <p class="text-xs muted">На других устройствах потребуется войти заново.</p>
            </form>
        </div>

        {{-- Уведомления --}}
        <form id="notify" method="POST" action="{{ route('account.settings.notifications') }}" class="card col-span-6 lg:col-span-4 p-6 lg:p-8 space-y-4 scroll-mt-28" data-reveal>
            @csrf @method('PUT')
            <div><p class="kicker">03 / Уведомления</p><h2 class="font-display font-bold text-xl mt-1 flex items-center gap-2">Письма на {{ $user->email }}</h2></div>
            <div class="plate !p-1.5 divide-y divide-[var(--line)]">
                @foreach ($notify as $key => [$label, $hint])
                    <label class="switch !px-3.5 !py-3"><span><b class="block">{{ $label }}</b><small class="muted">{{ $hint }}</small></span>
                        <input type="checkbox" name="notify[{{ $key }}]" value="1" class="sr-only" @checked($user->wants($key))><i></i></label>
                @endforeach
            </div>
            <button class="btn btn-primary">Сохранить уведомления</button>
        </form>

        {{-- Удаление --}}
        @if ($user->role !== 'admin')
            <form method="POST" action="{{ route('account.destroy') }}" class="card col-span-6 lg:col-span-2 p-6 space-y-4" data-reveal style="--d:.06s" x-data="{ open: {{ $errors->delete->any() ? 'true' : 'false' }} }">
                @csrf @method('DELETE')
                <h2 class="font-display font-bold text-lg flex items-center gap-2">@include('partials.icon', ['i' => 'trash', 'c' => 'w-5 h-5 status-rejected']) Удалить аккаунт</h2>
                <p class="text-sm muted">Удалятся отзывы, оценки «полезно», избранное и сравнения. Вернуть нельзя.</p>
                <button type="button" class="btn btn-outline w-full" x-show="!open" @click="open = true">Удалить…</button>
                <div x-show="open" x-cloak class="space-y-3">
                    <label class="field"><span>Пароль</span><input name="current_password" type="password" class="input" autocomplete="current-password"></label>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm" value="1" class="mt-0.5 accent-[var(--bad)]"> Понимаю, что всё удалится навсегда</label>
                    @if ($errors->delete->any())<ul class="error space-y-1">@foreach ($errors->delete->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
                    <button class="btn w-full !bg-[var(--bad)] !text-white">Удалить навсегда</button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
