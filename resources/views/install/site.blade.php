@extends('layouts.install')

@section('content')
<h1 class="font-display font-bold text-3xl">Сайт и администратор</h1>
<p class="muted mt-3">База подключена, таблицы созданы. Осталось назвать портал и завести главный аккаунт. Всё это потом меняется в админке.</p>

<form method="POST" action="{{ route('install.finish') }}" class="mt-8 grid sm:grid-cols-2 gap-4">
    @csrf
    <label class="field sm:col-span-2"><span>Название сайта</span><input name="site_name" class="input" value="{{ old('site_name', $defaults['site.name']) }}" required></label>
    <label class="field sm:col-span-2"><span>Слоган</span><input name="site_tagline" class="input" value="{{ old('site_tagline', $defaults['site.tagline']) }}"></label>
    <label class="field sm:col-span-2"><span>Описание (для поиска и главной)</span><textarea name="site_description" class="input">{{ old('site_description', $defaults['site.description']) }}</textarea></label>
    <label class="field"><span>Год рождения проекта</span><input name="founded_year" type="number" class="input" value="{{ old('founded_year', date('Y')) }}" required></label>
    <label class="flex items-center gap-3 text-sm font-semibold self-end pb-3"><input type="checkbox" name="seed_banks" value="1" checked class="w-4 h-4 accent-[var(--ink)]"> Заполнить справочник банков РК</label>

    <p class="sm:col-span-2 faint text-xs uppercase tracking-widest font-bold mt-4">Администратор</p>
    <label class="field"><span>Имя</span><input name="admin_name" class="input" value="{{ old('admin_name') }}" required></label>
    <label class="field"><span>Email</span><input name="admin_email" type="email" class="input" value="{{ old('admin_email') }}" required></label>
    <label class="field"><span>Пароль (от 10 символов)</span><input name="admin_password" type="password" class="input" autocomplete="new-password" required></label>
    <label class="field"><span>Пароль ещё раз</span><input name="admin_password_confirmation" type="password" class="input" autocomplete="new-password" required></label>

    @if ($errors->any())
        <ul class="sm:col-span-2 error space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif

    <div class="sm:col-span-2 mt-4 flex justify-between">
        <a href="{{ route('install.database') }}" class="btn btn-ghost">← Назад</a>
        <button class="btn btn-primary">Запустить портал 🚀</button>
    </div>
</form>
@endsection
