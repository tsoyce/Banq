@extends('layouts.admin', ['title' => 'Настройки сайта'])

@section('content')
<h1 class="font-display font-bold text-3xl">Настройки сайта</h1>

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="grid xl:grid-cols-[1fr_380px] gap-4"
      x-data="{ name: @js(old('site_name', $s['site.name'])), founded: {{ (int) old('founded_year', $s['site.founded_year']) }}, copyright: @js(old('copyright', $s['site.copyright'])), footer: @js(old('footer_text', $s['site.footer_text'])), logo: @js($s['site.logo']), favicon: @js($s['site.favicon']),
               get years() { const y = new Date().getFullYear(); return this.founded >= y ? String(y) : this.founded + '–' + y; },
               preview(e, key) { const f = e.target.files[0]; if (f) this[key] = URL.createObjectURL(f); } }">
    @csrf @method('PUT')
    <div class="space-y-4">
        <section class="glass p-6 grid sm:grid-cols-2 gap-4">
            <h2 class="sm:col-span-2 font-display font-bold text-lg">Основное</h2>
            <label class="field sm:col-span-2"><span>Название</span><input name="site_name" x-model="name" class="input" required></label>
            <label class="field sm:col-span-2"><span>Слоган</span><input name="site_tagline" class="input" value="{{ old('site_tagline', $s['site.tagline']) }}"></label>
            <label class="field sm:col-span-2"><span>Описание</span><textarea name="site_description" class="input">{{ old('site_description', $s['site.description']) }}</textarea></label>
            <label class="field"><span>Email для связи</span><input name="contact_email" type="email" class="input" value="{{ old('contact_email', $s['site.contact_email']) }}"></label>
            <label class="field"><span>Тема по умолчанию</span>
                <select name="theme_default" class="input">
                    <option value="dark" @selected(old('theme_default', $s['theme.default']) === 'dark')>Тёмная</option>
                    <option value="light" @selected(old('theme_default', $s['theme.default']) === 'light')>Светлая</option>
                </select></label>
        </section>

        <section class="glass p-6 grid sm:grid-cols-2 gap-6">
            <h2 class="sm:col-span-2 font-display font-bold text-lg">Логотип и фавикон</h2>
            @foreach (['logo' => ['Логотип', 'PNG, SVG, WebP до 2 МБ', 'h-14'], 'favicon' => ['Фавикон', 'PNG, ICO, SVG до 512 КБ', 'h-10 w-10']] as $key => [$label, $hint, $size])
                <div class="space-y-3">
                    <p class="text-sm font-semibold muted">{{ $label }}</p>
                    <div class="h-24 rounded-2xl border border-dashed border-[var(--border)] grid place-items-center bg-[var(--surface)]">
                        <template x-if="{{ $key }}"><img :src="{{ $key }}" class="{{ $size }} object-contain"></template>
                        <span x-show="!{{ $key }}" class="faint text-sm">нет файла</span>
                    </div>
                    <input type="file" name="{{ $key }}" accept="image/*,.ico" class="text-sm muted" @change="preview($event, '{{ $key }}')">
                    <label class="flex items-center gap-2 text-xs muted"><input type="checkbox" name="remove_{{ $key }}" value="1"> удалить</label>
                    <p class="faint text-xs">{{ $hint }}</p>
                    @error($key)<p class="error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </section>

        <section class="glass p-6 grid sm:grid-cols-2 gap-4">
            <h2 class="sm:col-span-2 font-display font-bold text-lg">Подвал и годы жизни</h2>
            <label class="field"><span>Год рождения проекта</span><input name="founded_year" type="number" x-model.number="founded" class="input" required></label>
            <label class="field"><span>Копирайт</span><input name="copyright" x-model="copyright" class="input"></label>
            <label class="field sm:col-span-2"><span>Текст подвала</span><textarea name="footer_text" x-model="footer" class="input"></textarea></label>
            <label class="field sm:col-span-2"><span>Счётчики и метрики (HTML, вставляется в &lt;head&gt;)</span><textarea name="counters" class="input font-mono text-xs">{{ old('counters', $s['site.counters']) }}</textarea></label>
        </section>

        @if ($errors->any())<ul class="error space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
        <button class="btn btn-primary">Сохранить</button>
    </div>

    <aside class="space-y-4 xl:sticky xl:top-8 self-start">
        <p class="faint text-xs uppercase tracking-widest font-bold">Живой предпросмотр</p>
        <div class="glass p-5 space-y-4">
            <div class="flex items-center gap-3">
                <template x-if="logo"><img :src="logo" class="h-9"></template>
                <template x-if="!logo"><span class="grid place-items-center w-10 h-10 rounded-2xl text-white font-display font-black" style="background: var(--grad)">T</span></template>
                <span class="font-display font-bold" x-text="name"></span>
            </div>
            <div class="flex items-center gap-2 text-xs muted rounded-lg bg-[var(--surface)] px-3 py-2">
                <template x-if="favicon"><img :src="favicon" class="w-4 h-4"></template>
                <span x-text="name"></span>
            </div>
            <div class="pt-4 border-t border-[var(--border)] text-xs faint space-y-1">
                <p>© <span x-text="years"></span> <span x-text="copyright || name"></span></p>
                <p x-text="footer"></p>
            </div>
        </div>
    </aside>
</form>
@endsection
