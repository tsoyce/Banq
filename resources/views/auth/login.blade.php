<!DOCTYPE html>
<html lang="ru" data-theme="{{ settings('theme.default', 'dark') }}">
<head>@include('partials.head', ['title' => 'Вход'])</head>
<body class="min-h-dvh grid place-items-center px-4">
@include('partials.backdrop')
<form method="POST" action="{{ url('/login') }}" class="glass glow-edge w-full max-w-sm p-8 space-y-5">
    @csrf
    @include('partials.logo')
    <h1 class="font-display font-bold text-2xl">Вход</h1>
    <label class="field"><span>Email</span><input name="email" type="email" class="input" value="{{ old('email') }}" required autofocus></label>
    <label class="field"><span>Пароль</span><input name="password" type="password" class="input" required></label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" class="accent-[var(--a2)]"> Запомнить меня</label>
    @error('email')<p class="error">{{ $message }}</p>@enderror
    <button class="btn btn-primary w-full">Войти</button>
</form>
</body>
</html>
