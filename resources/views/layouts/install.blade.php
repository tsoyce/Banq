{{-- A03 · Макет мастера установки. --}}
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>@include('partials.head', ['title' => 'Установка'])</head>
<body class="min-h-dvh grid place-items-center px-4 py-10">
@include('partials.backdrop')
<div class="w-full max-w-2xl space-y-6">
    <div class="flex items-center justify-between">
        @include('partials.logo')
        <span class="chip">Мастер установки</span>
    </div>
    <ol class="grid grid-cols-3 gap-2 text-xs font-bold">
        @foreach (['Окружение', 'База данных', 'Сайт и админ'] as $i => $label)
            <li class="rounded-full px-3 py-2 text-center bg-[var(--card)] {{ $step > $i + 1 ? 'muted' : '' }}"
                style="{{ $step === $i + 1 ? 'background: var(--ink); color: var(--ink-text); border-color: transparent' : '' }}">
                {{ $i + 1 }}. {{ $label }}
            </li>
        @endforeach
    </ol>
    <div class="glass p-6 sm:p-10">
        @yield('content')
    </div>
    <p class="text-center text-xs faint">TSOY.IN Banq · v{{ config('banq.version') }}</p>
</div>
</body>
</html>
