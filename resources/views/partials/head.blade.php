{{-- A04 · Общий <head>: мета, шрифты, тема до отрисовки (без мигания), Vite. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) ? $title.' · ' : '' }}{{ settings('site.name') }}</title>
<meta name="description" content="{{ $description ?? settings('site.description') }}">
<meta property="og:title" content="{{ $title ?? settings('site.name') }}">
<meta property="og:description" content="{{ $description ?? settings('site.description') }}">
<meta name="theme-color" content="#efeff1">
@if (settings('site.favicon'))
    <link rel="icon" href="{{ settings('site.favicon') }}">
@else
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0' stop-color='%238b5cf6'/%3E%3Cstop offset='.5' stop-color='%236d7cf5'/%3E%3Cstop offset='1' stop-color='%2322d3ee'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='64' height='64' rx='18' fill='url(%23g)'/%3E%3Ctext x='32' y='43' font-family='Arial' font-weight='900' font-size='30' text-anchor='middle' fill='white'%3ET%3C/text%3E%3C/svg%3E">
@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Onest:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script>
    (function () {
        var t = '{{ settings('theme.default', 'light') }}';
        try { t = localStorage.getItem('banq.theme') || t; } catch (e) {}
        document.documentElement.dataset.theme = t;
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
