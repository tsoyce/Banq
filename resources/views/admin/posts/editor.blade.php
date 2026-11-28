{{-- A37 · Редактор публикации: слева — «лист» (заголовок, лид, текст на Tiptap — resources/js/editor.js),
     справа — публикация, обложка, рубрика, теги, связи с банками и продуктами (каскад), SEO, источник.
     Сохраняет общий ResourceController (store/update) → PostResource::saving()/saved(). --}}
@php
    $fields = collect($resource->fields($model))->keyBy('name');
    $v = fn (string $name) => old($name, $resource->value($model, $fields[$name], $defaults));
    $opts = fn (string $name) => $resource->options($fields[$name]['options']);
    $isNew = ! $model;
    $state = $model?->state() ?? 'draft';
    $links = old('links', $model ? $model->linkKeys() : []);
    $coverPath = $v('cover');
    $key = $resource->key();          // A42 · posts | pages — один редактор на двоих
    $isPage = $key === 'pages';
    $noun = $isPage ? ['Новая страница', 'Правка страницы', 'Страницы', 'Удалить страницу безвозвратно?', 'Удалить страницу'] : ['Новая публикация', 'Правка публикации', 'Публикации', 'Удалить публикацию безвозвратно? Комментарии и реакции тоже удалятся.', 'Удалить публикацию'];
@endphp
@extends('layouts.admin', ['title' => $isNew ? $noun[0] : $resource->title($model)])

@section('content')
<form method="POST" enctype="multipart/form-data" novalidate x-ref="form" data-post-editor
      action="{{ $isNew ? route('admin.resource.store', $key) : route('admin.resource.update', [$key, $model->id]) }}"
      x-data="postEditor(@js([
          'title' => $v('title'), 'lead' => $v('lead'), 'slug' => $v('slug'), 'seoTitle' => $v('seo_title'), 'seoDescription' => $v('seo_description'),
          'cover' => $coverPath, 'coverUrl' => $coverPath ? (\Illuminate\Support\Str::startsWith($coverPath, ['http://', 'https://']) ? $coverPath : asset(ltrim($coverPath, '/'))) : '',
          'links' => array_values((array) $links),
          'draftKey' => 'banq.'.($isPage ? 'page' : 'post').'.'.($model?->id ?? 'new'), 'updatedAt' => $model?->updated_at?->timestamp ?? 0,
          'uploadUrl' => route('admin.blog.upload'), 'lookupUrl' => route('admin.blog.lookup'), 'embedUrl' => route('admin.blog.embed'),
      ]))"
      :class="focus && 'ed-focus'" class="space-y-5">
    @csrf
    @unless ($isNew) @method('PUT') @endunless
    <input type="hidden" name="_action" x-ref="action" value="">

    {{-- Шапка редактора --}}
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('admin.resource.index', $key) }}" class="muted text-sm inline-flex items-center gap-1 hover:text-[var(--text)]">@include('partials.icon', ['i' => 'back', 'c' => 'w-4 h-4']) {{ $noun[2] }}</a>
            <div class="flex items-center gap-3 mt-1 flex-wrap">
                <h1 class="font-display font-bold text-2xl">{{ $isNew ? $noun[0] : $noun[1] }}</h1>
                <span class="chip status-{{ ['draft' => 'pending', 'scheduled' => 'pending', 'published' => 'approved'][$state] }}"><span class="status-dot"></span>{{ $model?->stateLabel() ?? 'Черновик' }}</span>
                <span class="ed-status" :class="saving ? 'is-saved' : (dirty ? 'is-dirty' : 'is-saved')"><i></i><span x-text="saving ? 'Сохраняю…' : (dirty ? 'Есть несохранённые правки' : '{{ $model ? 'Сохранено '.$model->updated_at->diffForHumans() : 'Новый материал' }}')"></span></span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if ($model)<a href="{{ $resource->publicUrl($model) }}" target="_blank" class="btn btn-ghost !py-2 text-sm">@include('partials.icon', ['i' => 'eye', 'c' => 'w-4 h-4']) {{ $model->isPublished() ? 'На сайте' : 'Предпросмотр' }}</a>@endif
            <button type="button" class="btn btn-ghost !p-2.5 hidden lg:inline-flex" @click="focus = !focus" :title="focus ? 'Обычный режим' : 'Режим фокуса'" aria-label="Режим фокуса">@include('partials.icon', ['i' => 'maximize', 'c' => 'w-4 h-4'])</button>
        </div>
    </header>

    @if ($errors->any())
        <div class="card p-4 text-sm error" role="alert"><p class="font-semibold mb-1">Проверь поля:</p><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- Найден несохранённый черновик в браузере --}}
    <div class="card p-4 flex flex-wrap items-center gap-3 text-sm" x-show="draftFound" x-cloak style="background: color-mix(in oklab, var(--warn) 12%, var(--card))">
        @include('partials.icon', ['i' => 'history', 'c' => 'w-5 h-5'])
        <span class="flex-1 min-w-[12rem]"><b>В этом браузере есть несохранённая версия</b> — новее сохранённой на сервере.</span>
        <button type="button" class="btn btn-primary !py-1.5 text-sm" @click="restoreDraft()">Восстановить</button>
        <button type="button" class="btn btn-ghost !py-1.5 text-sm" @click="dropDraft()">Удалить</button>
    </div>

    <div class="ed-shell">
        {{-- ===== Лист ===== --}}
        <div class="card ed-paper min-w-0">
            <div class="px-5 sm:px-10 pt-8 sm:pt-10 space-y-4">
                <textarea name="title" x-ref="title" x-model="title" rows="1" class="ed-title" placeholder="Заголовок публикации" maxlength="200" @input="grow($el)" @keydown.enter.prevent="$refs.lead.focus()" aria-label="Заголовок" required></textarea>
                <textarea name="lead" x-ref="lead" x-model="lead" rows="1" class="ed-lead" placeholder="Лид: одна-две фразы, ради чего читать (видно в ленте и соцсетях)" maxlength="400" @input="grow($el)" @keydown.enter.prevent="editor && $refs.body.querySelector('.ProseMirror')?.focus()" aria-label="Лид"></textarea>
            </div>

            {{-- Тулбар --}}
            <div class="ed-toolbar mt-6" role="toolbar" aria-label="Форматирование" @click.outside="menu = null">
                <div class="relative">
                    <button type="button" class="ed-btn !px-2.5 min-w-[5.2rem] !justify-between" @click="menu = menu === 'block' ? null : 'block'"><span x-text="blockLabel()">Текст</span>@include('partials.icon', ['i' => 'down', 'c' => 'w-3.5 h-3.5'])</button>
                    <div class="ed-menu" x-show="menu === 'block'" x-cloak x-transition.origin.top.left>
                        <button type="button" @click="setBlock('p')" :class="is('paragraph') && 'is-active'"><b class="w-7">¶</b><span>Обычный текст</span></button>
                        <button type="button" @click="setBlock(2)" :class="is('heading', { level: 2 }) && 'is-active'"><b class="w-7">H2</b><span>Раздел<span class="hint">попадает в оглавление</span></span></button>
                        <button type="button" @click="setBlock(3)" :class="is('heading', { level: 3 }) && 'is-active'"><b class="w-7">H3</b><span>Подраздел</span></button>
                        <button type="button" @click="setBlock(4)" :class="is('heading', { level: 4 }) && 'is-active'"><b class="w-7">H4</b><span>Малый заголовок</span></button>
                        <button type="button" @click="setBlock('code')" :class="is('codeBlock') && 'is-active'"><b class="w-7">{ }</b><span>Блок кода</span></button>
                    </div>
                </div>
                <span class="sep"></span>
                <button type="button" class="ed-btn" :class="is('bold') && 'is-on'" @click="run(c => c.toggleBold())" title="Жирный · Ctrl+B" aria-label="Жирный">@include('partials.icon', ['i' => 'bold', 'c' => 'w-4 h-4'])</button>
                <button type="button" class="ed-btn" :class="is('italic') && 'is-on'" @click="run(c => c.toggleItalic())" title="Курсив · Ctrl+I" aria-label="Курсив">@include('partials.icon', ['i' => 'italic', 'c' => 'w-4 h-4'])</button>
                <button type="button" class="ed-btn" :class="is('underline') && 'is-on'" @click="run(c => c.toggleUnderline())" title="Подчёркнутый · Ctrl+U" aria-label="Подчёркнутый">@include('partials.icon', ['i' => 'underline', 'c' => 'w-4 h-4'])</button>
                <button type="button" class="ed-btn" :class="is('strike') && 'is-on'" @click="run(c => c.toggleStrike())" title="Зачёркнутый" aria-label="Зачёркнутый">@include('partials.icon', ['i' => 'strike', 'c' => 'w-4 h-4'])</button>
                <button type="button" class="ed-btn" :class="is('highlight') && 'is-on'" @click="run(c => c.toggleHighlight())" title="Маркер" aria-label="Маркер">@include('partials.icon', ['i' => 'highlight', 'c' => 'w-4 h-4'])</button>
                <div class="relative">
                    <button type="button" class="ed-btn" :class="is('link') && 'is-on'" @click="openLink()" title="Ссылка · Ctrl+K" aria-label="Ссылка">@include('partials.icon', ['i' => 'link', 'c' => 'w-4 h-4'])</button>
                    <div class="ed-menu !min-w-[300px] p-2" x-show="menu === 'link'" x-cloak x-transition.origin.top.left>
                        <form class="flex gap-2" @submit.prevent="applyLink()">
                            <input x-ref="linkInput" x-model="linkUrl" class="input !py-2 text-sm flex-1" placeholder="https://… или /deposits" @keydown.enter.prevent="applyLink()">
                            <button type="button" class="btn btn-primary !py-2 text-sm" @click="applyLink()">OK</button>
                        </form>
                        <p class="text-xs muted px-1 pt-2">Пустое поле — убрать ссылку. Внешние ссылки откроются в новой вкладке.</p>
                    </div>
                </div>
                <span class="sep"></span>
                <button type="button" class="ed-btn" :class="is('bulletList') && 'is-on'" @click="run(c => c.toggleBulletList())" title="Список" aria-label="Маркированный список">@include('partials.icon', ['i' => 'list', 'c' => 'w-4 h-4'])</button>
                <button type="button" class="ed-btn" :class="is('orderedList') && 'is-on'" @click="run(c => c.toggleOrderedList())" title="Нумерованный список" aria-label="Нумерованный список">@include('partials.icon', ['i' => 'list-ol', 'c' => 'w-4 h-4'])</button>
                <button type="button" class="ed-btn" :class="is('blockquote') && 'is-on'" @click="run(c => c.toggleBlockquote())" title="Цитата" aria-label="Цитата">@include('partials.icon', ['i' => 'quote', 'c' => 'w-4 h-4'])</button>
                <div class="relative">
                    <button type="button" class="ed-btn" :class="is('callout') && 'is-on'" @click="menu = menu === 'callout' ? null : 'callout'" title="Врезка" aria-label="Врезка">@include('partials.icon', ['i' => 'bulb', 'c' => 'w-4 h-4'])</button>
                    <div class="ed-menu" x-show="menu === 'callout'" x-cloak x-transition.origin.top.left>
                        @foreach (['info' => ['i', 'Справка', 'нейтральная заметка', 'var(--brand-1)'], 'tip' => ['✓', 'Совет', 'как сделать лучше', 'var(--ok)'], 'warn' => ['!', 'Внимание', 'риски и подводные камни', 'var(--warn)'], 'money' => ['₸', 'Про деньги', 'цифры, выгода, расчёт', 'var(--brand-2)']] as $tone => [$sym, $label, $hint, $color])
                            <button type="button" @click="run(c => c.setCallout('{{ $tone }}'))" :class="is('callout', { tone: '{{ $tone }}' }) && 'is-active'">
                                <span class="w-6 h-6 rounded-full grid place-items-center text-white text-xs font-bold shrink-0" style="background: {{ $color }}">{{ $sym }}</span><span>{{ $label }}<span class="hint">{{ $hint }}</span></span>
                            </button>
                        @endforeach
                        <button type="button" x-show="is('callout')" @click="run(c => c.unsetCallout())"><span class="w-6 text-center">×</span><span>Убрать врезку</span></button>
                    </div>
                </div>
                <button type="button" class="ed-btn" @click="run(c => c.setHorizontalRule())" title="Разделитель" aria-label="Разделитель">@include('partials.icon', ['i' => 'hr', 'c' => 'w-4 h-4'])</button>
                <span class="sep"></span>
                <button type="button" class="ed-btn" @click="pickImage()" :disabled="uploading" title="Картинка (можно перетащить в текст)" aria-label="Картинка">
                    <span x-show="!uploading">@include('partials.icon', ['i' => 'image', 'c' => 'w-4 h-4'])</span><span x-show="uploading" x-cloak class="dot-loader"><i></i><i></i><i></i></span>
                </button>
                <button type="button" class="ed-btn" x-show="is('image')" x-cloak @click="imageAlt()" title="Описание картинки" aria-label="Описание картинки">alt</button>
                <div class="relative">
                    <button type="button" class="ed-btn" :class="is('table') && 'is-on'" @click="is('table') ? (menu = menu === 'table' ? null : 'table') : insertTable()" title="Таблица" aria-label="Таблица">@include('partials.icon', ['i' => 'table', 'c' => 'w-4 h-4'])</button>
                    <div class="ed-menu" x-show="menu === 'table'" x-cloak x-transition.origin.top.left>
                        <button type="button" @click="run(c => c.addRowAfter())">+ строка ниже</button>
                        <button type="button" @click="run(c => c.addColumnAfter())">+ столбец справа</button>
                        <button type="button" @click="run(c => c.toggleHeaderRow())">Шапка вкл/выкл</button>
                        <button type="button" @click="run(c => c.mergeOrSplit())">Объединить / разделить ячейки</button>
                        <button type="button" @click="run(c => c.deleteRow())">− удалить строку</button>
                        <button type="button" @click="run(c => c.deleteColumn())">− удалить столбец</button>
                        <button type="button" class="text-[var(--bad)]" @click="run(c => c.deleteTable())">Удалить таблицу</button>
                    </div>
                </div>
                <div class="relative">
                    <button type="button" class="ed-btn !px-2.5" @click="menu = menu === 'embed' ? null : 'embed'; lookupFor = 'embed'; $nextTick(() => $refs.embedQ?.focus())" title="Вставить карточку банка или продукта" aria-label="Вставить продукт">
                        @include('partials.icon', ['i' => 'box', 'c' => 'w-4 h-4']) <span class="hidden 2xl:inline">Продукт</span>
                    </button>
                    <div class="ed-menu !w-[340px] max-w-[85vw] p-2" x-show="menu === 'embed'" x-cloak x-transition.origin.top.left>
                        <input x-ref="embedQ" x-model="lookupQ" @input.debounce.250ms="lookup()" class="input !py-2 text-sm" placeholder="Банк, вклад, карта, пакет, система…">
                        <p class="text-xs muted px-1 pt-2" x-show="!lookupItems.length">Живая карточка с текущей ставкой или условиями. Продукт сам добавится в связи.</p>
                        <div class="max-h-72 overflow-y-auto mt-1">
                            <template x-for="it in lookupItems.filter(i => i.embeddable)" :key="it.key">
                                <button type="button" @click="insertEmbed(it)"><span class="chip !py-0 !text-[0.68rem] shrink-0" x-text="it.label"></span><span class="min-w-0"><b class="block truncate" x-text="it.title"></b><span class="hint truncate" x-text="it.sub"></span></span></button>
                            </template>
                        </div>
                    </div>
                </div>
                <span class="sep"></span>
                <button type="button" class="ed-btn" @click="run(c => c.undo())" :disabled="!can(c => c.undo())" title="Отменить · Ctrl+Z" aria-label="Отменить">@include('partials.icon', ['i' => 'undo', 'c' => 'w-4 h-4'])</button>
                <button type="button" class="ed-btn" @click="run(c => c.redo())" :disabled="!can(c => c.redo())" title="Вернуть · Ctrl+Shift+Z" aria-label="Вернуть">@include('partials.icon', ['i' => 'redo', 'c' => 'w-4 h-4'])</button>
                <input type="file" accept="image/*" class="hidden" x-ref="imageFile" @change="uploadImage($event.target.files[0]); $event.target.value = ''">
            </div>

            <div class="ed-body px-5 sm:px-10 pt-6" x-ref="body"></div>
            <input type="hidden" name="body" x-ref="input" value="{{ $v('body') }}">

            <div class="px-5 sm:px-10 py-4 border-t border-[var(--line)] flex flex-wrap items-center gap-x-5 gap-y-1 text-xs muted">
                <span class="tabular"><b class="text-[var(--text)]" x-text="words">0</b> слов</span>
                <span class="tabular"><b class="text-[var(--text)]" x-text="chars">0</b> знаков</span>
                <span class="tabular">≈ <b class="text-[var(--text)]" x-text="minutes">1</b> мин чтения</span>
                <span class="ml-auto hidden sm:inline">Ctrl+S — сохранить · картинки можно перетаскивать и вставлять из буфера</span>
            </div>
        </div>

        {{-- ===== Боковая панель ===== --}}
        <aside class="ed-side space-y-4 xl:sticky xl:top-4" x-show="!focus" x-transition.opacity>
            {{-- Публикация --}}
            <section class="card p-5 space-y-4">
                <p class="font-display font-bold text-lg">Публикация</p>
                <div class="grid {{ $isPage ? '' : 'grid-cols-2' }} gap-3">
                    <label class="field"><span>Статус</span>
                        <select name="status" class="input !py-2.5 !pl-3 !pr-7 text-sm">@foreach ($opts('status') as $k => $l)<option value="{{ $k }}" @selected($v('status') === $k)>{{ $l }}</option>@endforeach</select>
                    </label>
                    @unless ($isPage)
                    <label class="field"><span>Формат</span>
                        <select name="kind" class="input !py-2.5 !pl-3 !pr-7 text-sm">@foreach ($opts('kind') as $k => $l)<option value="{{ $k }}" @selected($v('kind') === $k)>{{ $l }}</option>@endforeach</select>
                    </label>
                    @endunless
                </div>
                <label class="field"><span>Дата и время</span>
                    <input type="datetime-local" name="published_at" value="{{ $v('published_at') }}" class="input !py-2.5">
                    <span class="text-xs faint !font-normal">Пусто — в момент публикации. Будущая дата — выйдет сама.</span>
                </label>
                <div class="grid gap-2">
                    @if ($isPage)
                        <label class="field"><span>Ссылка в меню сайта</span>
                            <select name="nav" class="input !py-2.5 text-sm">@foreach ($opts('nav') as $k => $l)<option value="{{ $k }}" @selected((string) $v('nav') === (string) $k)>{{ $l }}</option>@endforeach</select>
                        </label>
                        <label class="field"><span>Порядок в меню</span><input type="number" name="nav_sort" value="{{ $v('nav_sort') }}" min="0" max="65000" class="input !py-2.5 text-sm"></label>
                        <label class="switch"><span>Показывать в ленте журнала</span><input type="hidden" name="in_feed" value="0"><input type="checkbox" name="in_feed" value="1" class="sr-only" @checked($v('in_feed'))><i></i></label>
                    @else
                    <label class="switch"><span>Главный материал</span><input type="hidden" name="is_featured" value="0"><input type="checkbox" name="is_featured" value="1" class="sr-only" @checked($v('is_featured'))><i></i></label>
                    @endif
                    <label class="switch"><span>Комментарии открыты</span><input type="hidden" name="allow_comments" value="0"><input type="checkbox" name="allow_comments" value="1" class="sr-only" @checked($v('allow_comments'))><i></i></label>
                </div>
                <div class="grid gap-2 pt-1">
                    @if ($state === 'published')
                        <button type="button" class="btn btn-primary w-full" @click="submit()">@include('partials.icon', ['i' => 'check', 'c' => 'w-4 h-4']) Обновить</button>
                        <button type="button" class="btn btn-ghost w-full" @click="submit('draft')">Снять с публикации</button>
                    @else
                        <button type="button" class="btn btn-brand w-full" @click="submit('publish')">@include('partials.icon', ['i' => 'send', 'c' => 'w-4 h-4']) {{ $state === 'scheduled' ? 'Сохранить расписание' : 'Опубликовать' }}</button>
                        <button type="button" class="btn btn-ghost w-full" @click="submit('draft')">@include('partials.icon', ['i' => 'save', 'c' => 'w-4 h-4']) Сохранить черновик</button>
                    @endif
                </div>
            </section>

            {{-- Обложка --}}
            <section class="card p-5 space-y-3">
                <p class="font-display font-bold text-lg">Обложка</p>
                <input type="hidden" name="cover" :value="cover">
                <input type="hidden" name="cover_remove" x-ref="coverRemove" value="0">
                <input type="file" name="cover_file" accept="image/*" class="hidden" x-ref="coverFile" @change="coverPicked($event)">
                <div class="ed-cover" :class="coverDrag && 'is-drag'" @click="$refs.coverFile.click()" @dragover.prevent="coverDrag = true" @dragleave="coverDrag = false" @drop.prevent="coverDrag = false; coverPicked($event)" role="button" tabindex="0" aria-label="Выбрать обложку">
                    <template x-if="coverPreview"><img :src="coverPreview" alt=""></template>
                    <div x-show="!coverPreview" class="p-4 text-sm muted grid justify-items-center gap-2">@include('partials.icon', ['i' => 'image', 'c' => 'w-7 h-7']) <span><b class="text-[var(--text)]">Перетащите картинку</b><br>или нажмите, чтобы выбрать · до 8 МБ</span></div>
                </div>
                <div class="flex gap-2" x-show="coverPreview" x-cloak>
                    <button type="button" class="btn btn-ghost !py-1.5 text-sm flex-1" @click="$refs.coverFile.click()">Заменить</button>
                    <button type="button" class="btn btn-ghost !py-1.5 text-sm text-[var(--bad)]" @click="removeCover()">Убрать</button>
                </div>
                <p class="text-xs faint" x-show="!coverPreview">Без обложки покажем фирменную заглушку в цвете рубрики.</p>
                <input name="cover_alt" value="{{ $v('cover_alt') }}" class="input !py-2 text-sm" placeholder="Что на картинке (alt)" maxlength="200">
                <input name="cover_caption" value="{{ $v('cover_caption') }}" class="input !py-2 text-sm" placeholder="Подпись / автор фото" maxlength="200">
            </section>

            @unless ($isPage)
            {{-- Рубрика, страна, автор, теги --}}
            <section class="card p-5 space-y-3">
                <p class="font-display font-bold text-lg">Рубрика и теги</p>
                <label class="field"><span>Рубрика</span>
                    <select name="category_id" class="input !py-2.5"><option value="">— без рубрики —</option>@foreach ($opts('category_id') as $k => $l)<option value="{{ $k }}" @selected((string) $v('category_id') === (string) $k)>{{ $l }}</option>@endforeach</select>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="field"><span>Страна</span>
                        <select name="country_id" class="input !py-2.5"><option value="">Все страны</option>@foreach ($opts('country_id') as $k => $l)<option value="{{ $k }}" @selected((string) $v('country_id') === (string) $k)>{{ $l }}</option>@endforeach</select>
                    </label>
                    <label class="field"><span>Автор</span>
                        <select name="author_id" class="input !py-2.5"><option value="">—</option>@foreach ($opts('author_id') as $k => $l)<option value="{{ $k }}" @selected((string) $v('author_id') === (string) $k)>{{ $l }}</option>@endforeach</select>
                    </label>
                </div>
                <label class="field"><span>Теги</span>
                    <input name="tags" value="{{ $v('tags') }}" class="input !py-2.5" placeholder="вклады, ставки, Kaspi" maxlength="1000">
                    <span class="text-xs faint !font-normal">Через запятую. Новые теги создаются сами.</span>
                </label>
            </section>

            {{-- Связи --}}
            <section class="card p-5 space-y-3">
                <div>
                    <p class="font-display font-bold text-lg">О ком материал</p>
                    <p class="text-xs muted mt-0.5">Отметьте карту — публикация сама появится у банка, пакетов с этой картой, платёжной системы и её уровня. Вклад → банк.</p>
                </div>
                <template x-for="k in links" :key="k"><input type="hidden" name="links[]" :value="k"></template>
                <div class="relative" @click.outside="lookupItems = []">
                    <input x-model="lookupQ" @focus="lookupFor = 'links'" @input.debounce.250ms="lookupFor = 'links'; lookup()" class="input !py-2.5" placeholder="Найти банк, карту, вклад…" aria-label="Добавить связь">
                    <div class="ed-menu w-full max-h-72 overflow-y-auto" x-show="lookupFor === 'links' && lookupItems.length" x-cloak>
                        <template x-for="it in lookupItems" :key="it.key">
                            <button type="button" @click="addLink(it)" :disabled="links.includes(it.key)" :class="links.includes(it.key) && 'opacity-40'">
                                <span class="chip !py-0 !text-[0.68rem] shrink-0" x-text="it.label"></span><span class="min-w-0"><b class="block truncate" x-text="it.title"></b><span class="hint truncate" x-text="it.sub"></span></span>
                            </button>
                        </template>
                    </div>
                </div>
                <div class="flex flex-wrap gap-1.5" x-show="links.length">
                    <template x-for="k in links" :key="k">
                        <span class="ed-chip"><span class="faint text-[0.7rem]" x-text="linkItems[k]?.label || k.split(':')[0]"></span><span class="truncate" x-text="linkItems[k]?.title || k.split(':')[1]"></span><button type="button" @click="removeLink(k)" aria-label="Убрать">×</button></span>
                    </template>
                </div>
                <div x-show="derived().length" x-cloak>
                    <p class="text-xs muted mb-1.5">Добавится автоматически:</p>
                    <div class="flex flex-wrap gap-1.5"><template x-for="d in derived()" :key="d"><span class="ed-chip is-derived" x-text="d"></span></template></div>
                </div>
            </section>

            @endunless

            {{-- SEO --}}
            <section class="card p-5 space-y-3" x-data="{ open: {{ $v('seo_title') || $v('seo_description') || $errors->has('slug') ? 'true' : 'false' }} }">
                <button type="button" class="flex items-center justify-between w-full" @click="open = !open" :aria-expanded="open">
                    <span class="font-display font-bold text-lg">SEO и адрес</span>@include('partials.icon', ['i' => 'down', 'c' => 'w-4 h-4 transition'])
                </button>
                <div class="ed-seo">
                    <p class="u truncate">{{ preg_replace('#^https?://#', '', url($isPage ? '/' : '/blog')) }}{{ $isPage ? '' : '/' }}<span x-text="slug || slugify(title) || '…'"></span></p>
                    <p class="t line-clamp-1" x-text="(seoTitle || title || 'Заголовок публикации') + ' · {{ settings('site.name') }}'"></p>
                    <p class="d line-clamp-2" x-text="seoDescription || lead || 'Описание появится из лида публикации.'"></p>
                </div>
                <div class="space-y-3" x-show="open" x-transition x-cloak>
                    <label class="field"><span>Адрес</span><input name="slug" x-model="slug" class="input !py-2.5 font-mono text-sm" :placeholder="slugify(title) || 'из заголовка'" maxlength="191"></label>
                    <label class="field"><span class="flex justify-between">SEO-заголовок <span class="ed-count faint" :class="seoTitle.length > 60 && 'is-over'" x-text="seoTitle.length + '/60'"></span></span><input name="seo_title" x-model="seoTitle" class="input !py-2.5" maxlength="200" :placeholder="title"></label>
                    <label class="field"><span class="flex justify-between">SEO-описание <span class="ed-count faint" :class="seoDescription.length > 160 && 'is-over'" x-text="seoDescription.length + '/160'"></span></span><textarea name="seo_description" x-model="seoDescription" rows="3" class="input" maxlength="300" :placeholder="lead"></textarea></label>
                </div>
            </section>

            {{-- Источник --}}
            @unless ($isPage)
            <section class="card p-5 space-y-3">
                <p class="font-display font-bold text-lg">Источник <span class="text-xs muted font-normal">для новостей</span></p>
                <input name="source_name" value="{{ $v('source_name') }}" class="input !py-2.5" placeholder="Нацбанк РК, пресс-релиз банка…" maxlength="120">
                <input name="source_url" type="url" value="{{ $v('source_url') }}" class="input !py-2.5" placeholder="https://…" maxlength="255">
            </section>
            @endunless

            @if ($model)
                <section class="card p-5 text-sm space-y-2">
                    <div class="flex justify-between"><span class="muted">Просмотры</span><b class="tabular">{{ number_format($model->views, 0, ',', ' ') }}</b></div>
                    @unless ($isPage)<div class="flex justify-between"><span class="muted">Комментарии</span><a class="font-semibold underline underline-offset-4" href="{{ route('admin.resource.index', ['comments', 'q' => $model->title]) }}">{{ $model->comments()->count() }}</a></div>@endunless
                    @if (data_get($model->meta, 'wp.link'))<div class="flex justify-between gap-3"><span class="muted">Было на WordPress</span><a class="truncate underline underline-offset-4" href="{{ data_get($model->meta, 'wp.link') }}" target="_blank" rel="noopener">{{ rawurldecode(parse_url(data_get($model->meta, 'wp.link'), PHP_URL_PATH) ?? '') }}</a></div>@endif
                    <div class="flex justify-between"><span class="muted">Создано</span><span>{{ $model->created_at->translatedFormat('j M Y, H:i') }}</span></div>
                </section>
            @endif
        </aside>
    </div>
</form>

@if ($model)
    <form method="POST" action="{{ route('admin.resource.destroy', [$key, $model->id]) }}" onsubmit="return confirm(@js($noun[3]))">
        @csrf @method('DELETE')
        <button class="btn btn-outline !py-2 text-sm text-[var(--bad)]">@include('partials.icon', ['i' => 'trash', 'c' => 'w-4 h-4']) {{ $noun[4] }}</button>
    </form>
@endif
@endsection
