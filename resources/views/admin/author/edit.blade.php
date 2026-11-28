{{-- A55 · Админка → Сайт → «Автор и соцсети»: страница /author, соцсети со счётчиками, способы поддержать проект. --}}
@extends('layouts.admin', ['title' => 'Автор и соцсети'])

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="font-display font-bold text-3xl">Автор и соцсети</h1>
        <p class="muted mt-1">Страница <a href="{{ route('author') }}" class="underline" target="_blank">/author</a>, значки соцсетей в подвале и блок «Поддержать проект».</p>
    </div>
    <form method="POST" action="{{ route('admin.author.refresh') }}">@csrf
        <button class="btn btn-ghost">@include('partials.icon', ['i' => 'history', 'c' => 'w-4 h-4']) Обновить счётчики</button>
    </form>
</div>

@if (session('social_errors'))
    <ul class="glass p-4 mt-4 text-sm space-y-1 error">@foreach (session('social_errors') as $e)<li>{{ $e }}</li>@endforeach</ul>
@endif

<form method="POST" action="{{ route('admin.author.update') }}" class="space-y-4 mt-6"
      x-data="{ nets: @js($networks),
                rows: @js(array_map(fn ($r) => ['network' => $r['network'] ?? 'site', 'url' => $r['url'] ?? '', 'label' => $r['label'] ?? '', 'followers' => $r['followers'] ?? '', 'visible' => (bool) ($r['visible'] ?? true)], $rows)),
                dons: @js(array_map(fn ($d) => ['label' => $d['label'] ?? '', 'currency' => $d['currency'] ?? '', 'url' => $d['url'] ?? '', 'details' => $d['details'] ?? '', 'note' => $d['note'] ?? ''], $donations)),
                move(list, i, d) { const j = i + d; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; } }">
    @csrf @method('PUT')

    {{-- Автор --}}
    <section class="glass p-6 grid sm:grid-cols-2 gap-4">
        <h2 class="sm:col-span-2 font-display font-bold text-lg">Автор</h2>
        <label class="field"><span>Кто автор страницы</span>
            <select name="user_id" class="input">
                <option value="">Первый администратор{{ $founder ? ' ('.$founder->name.')' : '' }}</option>
                @foreach ($staff as $u)<option value="{{ $u->id }}" @selected((int) old('user_id', settings('author.user_id')) === $u->id)>{{ $u->name }} · {{ $u->role }}</option>@endforeach
            </select></label>
        <label class="field"><span>Кто он (подпись под именем)</span><input name="headline" class="input" value="{{ old('headline', settings('author.headline')) }}" placeholder="Основатель TSOY.IN"></label>
        <label class="field sm:col-span-2"><span>Слоган (крупно, градиентом)</span><input name="slogan" class="input" value="{{ old('slogan', settings('author.slogan')) }}" placeholder="My Unlimited Story"></label>
        <label class="field sm:col-span-2"><span>О себе — абзацы через пустую строку. Пусто — «О себе» из профиля пользователя</span>
            <textarea name="about" rows="6" class="input">{{ old('about', settings('author.about')) }}</textarea></label>
        <p class="sm:col-span-2 text-xs faint">Фото, имя и город — из профиля автора (Личный кабинет → Настройки). Без фото на странице будет монограмма TA.</p>
    </section>

    {{-- Соцсети --}}
    <section class="glass p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display font-bold text-lg">Соцсети</h2>
            <label class="switch"><span>Показывать на сайте</span>
                <input type="hidden" name="social_enabled" value="0"><input type="checkbox" name="social_enabled" value="1" class="sr-only" @checked(old('social_enabled', settings('social.enabled', true)))><i></i></label>
        </div>
        <p class="text-sm muted">Число подписчиков берётся по API, где это можно (YouTube, Telegram-каналы, VK, GitHub) — раз в сутки и по кнопке «Обновить счётчики». Где нельзя (X, Instagram, Facebook) или пока нет ключа — показывается ваше число из колонки «Вручную».
            @if ($refreshedAt) Последнее обновление: {{ \Illuminate\Support\Carbon::parse($refreshedAt)->timezone(config('app.timezone'))->translatedFormat('j F, H:i') }}.@endif</p>
        <div class="space-y-2">
            <template x-for="(r, i) in rows" :key="i">
                <div class="plate p-3 grid gap-2 md:grid-cols-[150px_minmax(0,1fr)_140px_120px_auto] items-center" :class="r.visible ? '' : 'opacity-60'">
                    <select class="input" :name="`networks[${i}][network]`" x-model="r.network">
                        <template x-for="(n, k) in nets" :key="k"><option :value="k" x-text="n.label" :selected="k === r.network"></option></template>
                    </select>
                    <input class="input" :name="`networks[${i}][url]`" x-model="r.url" placeholder="https://…" required>
                    <input class="input" :name="`networks[${i}][label]`" x-model="r.label" placeholder="Подпись">
                    <input class="input tabular" type="number" min="0" :name="`networks[${i}][followers]`" x-model="r.followers" placeholder="Вручную">
                    <div class="flex items-center gap-1 justify-end">
                        <label class="flex items-center gap-1.5 text-xs font-semibold mr-1" title="Показывать"><input type="checkbox" value="1" :name="`networks[${i}][visible]`" x-model="r.visible" class="w-4 h-4 accent-[var(--ink)]"> вкл</label>
                        <button type="button" class="btn btn-ghost !p-2" @click="move(rows, i, -1)" :disabled="i === 0" aria-label="Выше">↑</button>
                        <button type="button" class="btn btn-ghost !p-2" @click="move(rows, i, 1)" :disabled="i === rows.length - 1" aria-label="Ниже">↓</button>
                        <button type="button" class="btn btn-ghost !p-2" @click="rows.splice(i, 1)" aria-label="Убрать">@include('partials.icon', ['i' => 'trash', 'c' => 'w-4 h-4'])</button>
                    </div>
                    <p class="md:col-span-5 text-xs muted" x-show="nets[r.network]">
                        <span x-show="nets[r.network]?.auto" x-text="'Авто: ' + (nets[r.network]?.auto || '')"></span>
                        <span x-show="!nets[r.network]?.auto">Только ручное число — открытого API нет.</span>
                    </p>
                </div>
            </template>
        </div>
        <button type="button" class="btn btn-ghost" @click="rows.push({ network: 'telegram', url: '', label: '', followers: '', visible: true })">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Добавить соцсеть</button>

        @if ($status)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left faint text-xs"><th class="py-2 pr-3">Сеть</th><th class="pr-3">На сайте</th><th class="pr-3">Источник</th><th>Статус API</th></tr></thead>
                    <tbody>
                        @foreach ($status as $s)
                            <tr class="border-t border-[var(--line)]">
                                <td class="py-2 pr-3 font-semibold whitespace-nowrap"><span class="inline-flex items-center gap-2" style="color: {{ $s['color'] }}">@include('partials.social-icon', ['n' => $s['network'], 'c' => 'w-4 h-4'])</span> {{ $s['label'] }}</td>
                                <td class="pr-3 tabular">{{ $s['count'] !== null ? number_format($s['count'], 0, ',', ' ') : '—' }}</td>
                                <td class="pr-3">{{ ['api' => 'API', 'manual' => 'вручную', 'none' => 'нет числа'][$s['source']] }}</td>
                                <td class="text-xs {{ $s['error'] ? 'error' : 'muted' }}">{{ $s['error'] ?: ($s['at'] ? 'ок, '.\Illuminate\Support\Carbon::parse($s['at'])->diffForHumans() : (\App\Services\Social\SocialStats::NETWORKS[$s['network']]['auto'] ? 'ещё не запрашивали' : '—')) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Ключи API --}}
    <section class="glass p-6 grid sm:grid-cols-3 gap-4">
        <div class="sm:col-span-3">
            <h2 class="font-display font-bold text-lg">Ключи API для счётчиков</h2>
            <p class="text-sm muted mt-1">Необязательно. Сохранённый ключ не показывается; пустое поле — оставить как есть. Можно задать и в .env: BANQ_YOUTUBE_API_KEY, BANQ_TELEGRAM_BOT_TOKEN, BANQ_VK_SERVICE_TOKEN.</p>
        </div>
        @foreach (['youtube' => ['YouTube Data API v3', 'Google Cloud → APIs → YouTube Data API v3 → ключ'], 'telegram' => ['Токен бота Telegram', 'Нужен, если канал закрыт; бот — админ канала'], 'vk' => ['Сервисный токен VK', 'vk.com/apps?act=manage → приложение → сервисный ключ']] as $k => [$label, $hint])
            <div class="field">
                <span>{{ $label }}</span>
                <input type="password" name="keys[{{ $k }}]" class="input" autocomplete="off" placeholder="{{ $keys[$k] === 'admin' ? 'задан в админке ••••' : ($keys[$k] === 'env' ? 'задан в .env' : 'не задан') }}">
                <span class="text-xs faint font-normal">{{ $hint }}</span>
                @if ($keys[$k] === 'admin')<label class="flex items-center gap-2 text-xs muted font-normal"><input type="checkbox" name="clear_keys[]" value="{{ $k }}"> удалить ключ</label>@endif
            </div>
        @endforeach
    </section>

    {{-- Поддержка --}}
    <section class="glass p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display font-bold text-lg">Поддержать проект</h2>
            <label class="switch"><span>Показывать блок</span>
                <input type="hidden" name="donate_enabled" value="0"><input type="checkbox" name="donate_enabled" value="1" class="sr-only" @checked(old('donate_enabled', settings('donate.enabled', true)))><i></i></label>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <label class="field"><span>Заголовок</span><input name="donate_title" class="input" value="{{ old('donate_title', settings('donate.title')) }}"></label>
            <label class="field sm:row-span-2"><span>Текст</span><textarea name="donate_text" rows="4" class="input">{{ old('donate_text', settings('donate.text')) }}</textarea></label>
        </div>
        <div class="space-y-2">
            <template x-for="(d, i) in dons" :key="i">
                <div class="plate p-3 grid gap-2 md:grid-cols-[minmax(0,1fr)_90px_minmax(0,1.4fr)_auto] items-start">
                    <input class="input" :name="`donations[${i}][label]`" x-model="d.label" placeholder="Банк / сервис" required>
                    <input class="input mono uppercase" :name="`donations[${i}][currency]`" x-model="d.currency" placeholder="KZT" maxlength="8">
                    <input class="input" :name="`donations[${i}][url]`" x-model="d.url" placeholder="Ссылка на перевод https://…">
                    <div class="flex items-center gap-1 justify-end">
                        <button type="button" class="btn btn-ghost !p-2" @click="move(dons, i, -1)" :disabled="i === 0" aria-label="Выше">↑</button>
                        <button type="button" class="btn btn-ghost !p-2" @click="move(dons, i, 1)" :disabled="i === dons.length - 1" aria-label="Ниже">↓</button>
                        <button type="button" class="btn btn-ghost !p-2" @click="dons.splice(i, 1)" aria-label="Убрать">@include('partials.icon', ['i' => 'trash', 'c' => 'w-4 h-4'])</button>
                    </div>
                    <input class="input md:col-span-2" :name="`donations[${i}][note]`" x-model="d.note" placeholder="Подсказка: «Картой любого банка»">
                    <input class="input mono md:col-span-2" :name="`donations[${i}][details]`" x-model="d.details" placeholder="Реквизиты для копирования (номер кошелька, телефон…) — необязательно">
                </div>
            </template>
        </div>
        <button type="button" class="btn btn-ghost" @click="dons.push({ label: '', currency: '', url: '', details: '', note: '' })">@include('partials.icon', ['i' => 'plus', 'c' => 'w-4 h-4']) Добавить способ</button>
        <p class="text-xs faint">Номера карт целиком сюда не пишите — лучше ссылка на перевод банка (как у Т-Банка и ForteBank).</p>
    </section>

    @if ($errors->any())<ul class="error space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
    <button class="btn btn-primary">Сохранить</button>
</form>
@endsection
