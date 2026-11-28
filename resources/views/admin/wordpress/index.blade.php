{{-- A43 · Админка → Сайт → Перенос с WordPress: пробный прогон (план + проверка безопасности) → перенос по шагам → отчёт. --}}
@extends('layouts.admin', ['title' => 'Перенос с WordPress'])

@php
    $cnt = $report['counts'] ?? [];
    $phase = $state['phase'] ?? null;
    $levels = ['danger' => ['Опасно', '--bad'], 'warn' => ['Проверить', '--warn'], 'info' => ['Для сведения', '--muted']];
    $findings = collect($report['findings'] ?? []);
    $items = collect($report['items'] ?? []);
@endphp

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <p class="kicker">Сайт</p>
        <h1 class="font-display font-bold text-3xl">Перенос с WordPress</h1>
        <p class="muted mt-1 max-w-3xl">Посты станут публикациями журнала, страницы — страницами сайта. Картинки и файлы скачиваются к себе с проверкой (настоящая ли это картинка, нет ли внутри кода) и без EXIF. Старые адреса отвечают редиректом 301 на новые.</p>
    </div>
    @if ($state)
        <div class="flex gap-2">
            <a href="{{ route('admin.wordpress.report') }}" class="btn btn-ghost">@include('partials.icon', ['i' => 'download', 'c' => 'w-4 h-4']) Отчёт JSON</a>
            <form method="POST" action="{{ route('admin.wordpress.reset') }}" onsubmit="return confirm('Удалить план и снимок? Перенесённые записи останутся.')">@csrf<button class="btn btn-ghost">Новый перенос</button></form>
        </div>
    @endif
</div>

@if ($errors->any())<p class="error mt-4">{{ $errors->first() }}</p>@endif

@if (! $state)
    {{-- Шаг 1. Откуда переносим --}}
    <form method="POST" action="{{ route('admin.wordpress.scan') }}" enctype="multipart/form-data" class="glass p-6 mt-6 space-y-5 max-w-3xl">
        @csrf
        <p class="font-display font-bold text-xl">1. Пробный прогон</p>
        <label class="field"><span>Адрес сайта WordPress</span>
            <input name="url" type="url" value="{{ old('url', 'https://tsoy.in') }}" class="input" placeholder="https://example.com">
            <span class="text-xs faint !font-normal">Данные берутся через REST API (/wp-json). В базу на этом шаге ничего не пишется.</span>
        </label>
        <details class="text-sm">
            <summary class="cursor-pointer font-semibold">REST API закрыт? Загрузите файл экспорта</summary>
            <div class="mt-3 space-y-2">
                <input type="file" name="file" accept=".xml,.json,application/xml,application/json" class="input">
                <p class="faint text-xs">WordPress → Инструменты → Экспорт → «Всё содержимое» (.xml), или JSON-снимок. Если выбран файл, адрес выше не нужен — очистите его.</p>
            </div>
        </details>
        <div class="grid gap-2">
            <label class="switch"><span>Скачивать картинки со сторонних сайтов (ВКонтакте, OneDrive…), которые вставлены в тексты</span><input type="hidden" name="external_images" value="0"><input type="checkbox" name="external_images" value="1" class="sr-only" checked><i></i></label>
            <label class="switch"><span>Перезаписать уже перенесённые записи (правки, сделанные после переноса, пропадут)</span><input type="hidden" name="update" value="0"><input type="checkbox" name="update" value="1" class="sr-only"><i></i></label>
        </div>
        <button class="btn btn-brand">@include('partials.icon', ['i' => 'search', 'c' => 'w-4 h-4']) Проверить сайт</button>
    </form>
@else
    {{-- Итоги плана --}}
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3 mt-6">
        @foreach ([
            ['Публикаций', $cnt['posts'] ?? 0, null], ['Страниц', $cnt['pages'] ?? 0, null], ['Пропущено', $cnt['skipped'] ?? 0, 'служебные'],
            ['Картинок в текстах', $cnt['images_in_text'] ?? 0, 'файлов на сайте '.($cnt['media_total'] ?? 0)],
            ['Рубрик', $cnt['categories'] ?? 0, 'тегов '.($cnt['tags'] ?? 0)], ['Связано с банками', $cnt['linked'] ?? 0, 'публикаций'],
            ['Опасных находок', $cnt['danger'] ?? 0, null], ['Предупреждений', $cnt['warn'] ?? 0, null],
        ] as [$label, $n, $sub])
            <div class="plate p-4"><p class="muted text-xs">{{ $label }}</p><p class="font-display font-semibold text-3xl mt-1 tabular" @if ($label === 'Опасных находок' && $n) style="color: var(--bad)" @endif>{{ $n }}</p>@if ($sub)<p class="text-xs faint">{{ $sub }}</p>@endif</div>
        @endforeach
    </div>
    <p class="text-sm muted mt-3">Источник: <b>{{ $state['source'] }}</b> · снимок {{ \Illuminate\Support\Carbon::parse($state['scanned_at'])->translatedFormat('j F, H:i') }}@if (($cnt['comments'] ?? 0)) · комментариев на WordPress: {{ $cnt['comments'] }} (не переносятся — у читателей нет аккаунтов на новом сайте)@endif</p>

    {{-- Шаг 2. Перенос по шагам --}}
    <section class="glass p-6 mt-5 space-y-4" x-data="wpRun(@js(['url' => route('admin.wordpress.step'), 'progress' => $progress]))">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-display font-bold text-xl">2. Перенос</p>
                <p class="text-sm muted" x-text="message"></p>
            </div>
            @if ($phase !== 'done')
                <button type="button" class="btn btn-brand" @click="run()" :disabled="running">
                    <span x-show="!running">@include('partials.icon', ['i' => 'upload', 'c' => 'w-4 h-4 inline']) {{ $phase === 'scanned' ? 'Перенести' : 'Продолжить перенос' }}</span>
                    <span x-show="running" x-cloak>Переношу…</span>
                </button>
            @else
                <span class="chip status-approved"><span class="status-dot"></span>Перенос завершён {{ \Illuminate\Support\Carbon::parse($state['finished_at'])->translatedFormat('j F, H:i') }}</span>
            @endif
        </div>
        <div class="h-2 rounded-full bg-[var(--card-2)] overflow-hidden"><div class="h-full rounded-full transition-all duration-500" style="background: var(--grad)" :style="'width:' + pct + '%'"></div></div>
        <p class="text-xs faint tabular" x-text="p.done + ' из ' + p.total + ' записей · файлов ' + p.media + (p.current ? ' · ' + p.current : '')"></p>
        <p class="error" x-show="error" x-text="error" x-cloak></p>
        @if ($phase === 'done')
            <div class="grid sm:grid-cols-4 gap-3 text-sm">
                <div class="plate p-4"><p class="muted text-xs">Создано</p><p class="font-display font-semibold text-2xl tabular">{{ $cnt['created'] }}</p></div>
                <div class="plate p-4"><p class="muted text-xs">Обновлено / не тронуто</p><p class="font-display font-semibold text-2xl tabular">{{ $cnt['updated'] }} / {{ $cnt['kept'] }}</p></div>
                <div class="plate p-4"><p class="muted text-xs">Файлов перенесено</p><p class="font-display font-semibold text-2xl tabular">{{ $cnt['media_ok'] }}</p><p class="text-xs faint">{{ number_format(($cnt['media_bytes'] ?? 0) / 1048576, 1, ',', ' ') }} МБ · отклонено {{ $cnt['media_rejected'] }}</p></div>
                <div class="plate p-4"><p class="muted text-xs">Редиректов 301</p><p class="font-display font-semibold text-2xl tabular">{{ $cnt['redirects'] }}</p><a href="{{ route('admin.resource.index', ['redirects', 'source' => 'wp']) }}" class="text-xs underline underline-offset-4">список</a></div>
            </div>
        @endif
    </section>

    {{-- Отклонённые файлы --}}
    @if (! empty($report['media_rejected']))
        <section class="glass p-6 mt-5">
            <p class="font-display font-bold text-xl mb-3">Файлы, которые не перенесены</p>
            <ul class="space-y-1.5 text-sm">
                @foreach ($report['media_rejected'] as $m)
                    <li class="flex gap-2"><span style="color: var({{ $m['danger'] ? '--bad' : '--muted' }})">{{ $m['danger'] ? '⚠' : '·' }}</span><span class="min-w-0"><a href="{{ $m['url'] }}" target="_blank" rel="noopener nofollow" class="font-mono text-xs break-all underline underline-offset-4">{{ rawurldecode($m['url']) }}</a> — {{ $m['reason'] }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Проверка безопасности --}}
    <section class="glass p-6 mt-5 space-y-3">
        <p class="font-display font-bold text-xl">Проверка на взлом</p>
        @if ($findings->whereIn('level', ['danger', 'warn'])->isEmpty())
            <p class="text-sm flex items-center gap-2">@include('partials.icon', ['i' => 'shield', 'c' => 'w-4 h-4 text-[var(--ok)]']) Скрытых ссылок, спама, подозрительных скриптов и обработчиков в текстах не найдено.</p>
        @endif
        @foreach ($levels as $lvl => [$label, $color])
            @php($group = $findings->where('level', $lvl))
            @continue($group->isEmpty())
            <details @if ($lvl !== 'info') open @endif>
                <summary class="cursor-pointer font-semibold" style="color: var({{ $color }})">{{ $label }} · {{ $group->count() }}</summary>
                <ul class="mt-2 space-y-1.5 text-sm">
                    @foreach ($group as $f)
                        <li><b>{{ $f['title'] }}</b> <span class="faint">({{ $f['kind'] }})</span> — <span class="break-all">{{ $f['detail'] }}</span></li>
                    @endforeach
                </ul>
                @if ($lvl !== 'danger')<p class="text-xs faint mt-2">Скрипты и iframe в журнал не попадают: видео YouTube становится вставкой, остальное — ссылкой.</p>@endif
            </details>
        @endforeach
    </section>

    {{-- План по записям --}}
    <section class="glass p-0 mt-5 overflow-hidden">
        <div class="p-6 pb-3"><p class="font-display font-bold text-xl">Записи · {{ $items->count() }}</p></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left muted text-xs"><tr><th class="px-6 py-2">Тип</th><th class="py-2">Заголовок</th><th class="py-2">Было</th><th class="py-2">Станет</th><th class="py-2 pr-6">Итог</th></tr></thead>
                <tbody>
                    @foreach ($items as $i)
                        @php($res = $i['result'])
                        <tr class="border-t border-[var(--line)] {{ $i['skip'] ? 'opacity-50' : '' }}">
                            <td class="px-6 py-2 whitespace-nowrap">{{ $i['type'] === 'page' ? 'Страница' : 'Пост' }}</td>
                            <td class="py-2 pr-3 max-w-[22rem]"><span class="block truncate font-semibold">{{ $i['title'] }}</span>@if ($n = collect($res['findings'] ?? $i['findings'])->where('level', 'danger')->count())<span class="text-xs" style="color: var(--bad)">опасных находок: {{ $n }}</span>@endif</td>
                            <td class="py-2 pr-3 max-w-[16rem]"><a href="{{ $i['old_url'] }}" target="_blank" rel="noopener" class="font-mono text-xs truncate block">{{ rawurldecode($i['old_path']) }}</a></td>
                            <td class="py-2 pr-3 max-w-[16rem]">@if ($i['skip'])<span class="faint">—</span>@else<a href="{{ $res['url'] ?? url($i['new_url']) }}" target="_blank" class="font-mono text-xs truncate block {{ $res ? 'underline underline-offset-4' : '' }}">{{ $i['new_url'] }}</a>@endif</td>
                            <td class="py-2 pr-6 whitespace-nowrap text-xs">
                                @if ($i['skip']) {{ $i['skip'] }}
                                @elseif ($res) {{ ['created' => 'создано', 'updated' => 'обновлено', 'kept' => 'уже было, не тронуто', 'missing' => 'нет в снимке'][$res['action']] ?? $res['action'] }}@if (! empty($res['missing'])) · <span title="{{ implode("\n", $res['missing']) }}" style="color: var(--warn)">не скачано {{ count($res['missing']) }}</span>@endif
                                @else {{ $i['exists'] ? 'уже перенесено' : 'будет создано' }} @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('wpRun', (cfg) => ({
        p: cfg.progress || { done: 0, total: 0, media: 0 }, running: false, error: '',
        get pct() { return this.p.total ? Math.round(this.p.done / this.p.total * 100) : 0; },
        get message() { return this.p.finished ? 'Готово.' : (this.running ? 'Не закрывайте страницу — перенос идёт шагами по 20 секунд.' : 'Посты и страницы по плану ниже. Можно прервать и продолжить позже.'); },
        async run() {
            this.running = true; this.error = '';
            const token = document.querySelector('meta[name=csrf-token]')?.content;
            let fails = 0;
            while (!this.p.finished) {
                try {
                    const r = await fetch(cfg.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } });
                    const j = await r.json();
                    if (!r.ok) throw new Error(j.error || ('Ошибка ' + r.status));
                    this.p = j; fails = 0;
                } catch (e) {
                    if (++fails >= 3) { this.error = e.message + ' — нажмите «Продолжить перенос».'; this.running = false; return; }
                    await new Promise((res) => setTimeout(res, 3000));
                }
            }
            location.reload();
        },
    }));
});
</script>
@endsection
