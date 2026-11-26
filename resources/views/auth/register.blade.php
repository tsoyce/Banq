{{-- A18 · Регистрация посетителя: отзывы и голоса «полезно» в народном рейтинге. --}}
<!DOCTYPE html>
<html lang="ru" data-theme="{{ settings('theme.default', 'light') }}">
<head>@include('partials.head', ['title' => 'Регистрация'])</head>
<body class="min-h-dvh grid place-items-center px-4 py-10">
<form method="POST" action="{{ route('register') }}" class="card w-full max-w-sm p-8 space-y-5">
    @csrf
    @include('partials.logo')
    <div>
        <h1 class="font-display font-bold text-2xl">Регистрация</h1>
        <p class="text-sm muted mt-1">Чтобы оценивать банки и писать отзывы.</p>
    </div>
    <label class="field"><span>Имя</span><input name="name" class="input" value="{{ old('name') }}" required autofocus maxlength="60" autocomplete="nickname">
        <small class="text-xs faint">Его увидят рядом с отзывом</small></label>
    <label class="field"><span>Email</span><input name="email" type="email" class="input" value="{{ old('email') }}" required autocomplete="email"></label>
    <label class="field"><span>Пароль</span><input name="password" type="password" class="input" required minlength="8" autocomplete="new-password"></label>
    <label class="field"><span>Пароль ещё раз</span><input name="password_confirmation" type="password" class="input" required autocomplete="new-password"></label>
    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="agree" value="1" class="mt-0.5 accent-[var(--ink)]" @checked(old('agree'))> Пишу о своём опыте, без рекламы и оскорблений. Отзывы проходят модерацию.</label>
    @if ($errors->any())<ul class="error space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
    <button class="btn btn-primary w-full">Создать аккаунт</button>
    <p class="text-sm muted text-center">Уже есть аккаунт? <a href="{{ route('login') }}" class="font-semibold text-[var(--text)] underline">Войти</a></p>
</form>
</body>
</html>
