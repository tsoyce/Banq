// A37 · Редактор публикаций на Tiptap (ProseMirror): заголовки, списки, цитаты, код, ссылки, выделение маркером,
// картинки (кнопка, перетаскивание, вставка из буфера → /admin/blog/upload), таблицы, врезки <aside data-tone>,
// живые вставки продуктов <div data-embed data-slug> (превью — /admin/blog/embed), черновик в localStorage,
// Ctrl+S — сохранить, счётчики слов и времени чтения. HTML уходит в скрытое поле body, на сервере чистится HtmlSanitizer.
import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import Placeholder from '@tiptap/extension-placeholder';
import CharacterCount from '@tiptap/extension-character-count';
import Highlight from '@tiptap/extension-highlight';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';

const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;

// Врезка: <aside data-tone="info|tip|warn|money">…</aside>
const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes() {
        return { tone: { default: 'info', parseHTML: (el) => el.getAttribute('data-tone') || 'info', renderHTML: (a) => ({ 'data-tone': a.tone }) } };
    },
    parseHTML() { return [{ tag: 'aside' }]; },
    renderHTML({ HTMLAttributes }) { return ['aside', mergeAttributes(HTMLAttributes), 0]; },
    addCommands() {
        return {
            setCallout: (tone) => ({ commands, editor }) => editor.isActive('callout')
                ? commands.updateAttributes('callout', { tone })
                : commands.wrapIn(this.name, { tone }),
            unsetCallout: () => ({ commands }) => commands.lift(this.name),
        };
    },
});

// Вставка продукта: атом, на сайте рисуется живой карточкой (App\Support\Blog\PostRenderer)
const Embed = (previewUrl) => Node.create({
    name: 'embed',
    group: 'block',
    atom: true,
    draggable: true,
    selectable: true,
    addAttributes() {
        return {
            type: { default: null, parseHTML: (el) => el.getAttribute('data-embed'), renderHTML: (a) => ({ 'data-embed': a.type }) },
            slug: { default: null, parseHTML: (el) => el.getAttribute('data-slug'), renderHTML: (a) => ({ 'data-slug': a.slug }) },
        };
    },
    parseHTML() { return [{ tag: 'div[data-embed]' }]; },
    renderHTML({ HTMLAttributes }) { return ['div', HTMLAttributes]; },
    addNodeView() {
        return ({ node }) => {
            const dom = document.createElement('div');
            dom.className = 'ed-embed';
            dom.innerHTML = '<span class="ed-embed-tag">живая карточка</span><div class="embed-missing">Загружаю превью…</div>';
            fetch(`${previewUrl}?type=${encodeURIComponent(node.attrs.type)}&slug=${encodeURIComponent(node.attrs.slug)}`)
                .then((r) => r.text())
                .then((html) => { dom.innerHTML = '<span class="ed-embed-tag">живая карточка</span>' + html; });
            return { dom };
        };
    },
});

// A52 · Галереи и картинки с подписью ────────────────────────────────────────
const LAYOUTS = [['grid', 'Авто'], ['grid-2', '2 колонки'], ['grid-3', '3 колонки'], ['masonry', 'Мозаика'], ['slider', 'Слайдер']];
const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
const figImg = (fig) => fig.querySelector(':scope > img, :scope > a > img');
const galleryItems = (el) => [...el.querySelectorAll(':scope > figure, :scope > ul > li > figure, :scope > ol > li > figure, :scope > .gallery-track > figure')]
    .filter(figImg)
    .map((f) => ({ src: figImg(f).getAttribute('src'), alt: figImg(f).getAttribute('alt') || '', caption: f.querySelector(':scope > figcaption')?.textContent.trim() || '' }));
const isGallery = (el) => el.hasAttribute('data-gallery') || galleryItems(el).length >= 2;
// Изменить атрибуты узла из NodeView (позиция узла может смениться — берём её в момент правки)
const patchNode = (editor, getPos, attrs) => editor.chain().command(({ tr }) => {
    const pos = getPos();
    if (typeof pos !== 'number') return false;
    tr.setNodeMarkup(pos, undefined, { ...tr.doc.nodeAt(pos).attrs, ...attrs });
    return true;
}).run();
const typing = (dom) => dom.contains(document.activeElement) && document.activeElement.matches('input, textarea');

// <figure data-gallery="…"><figure><img><figcaption?></figure>…<figcaption?></figure> — атом с картинками в атрибутах
const Gallery = (upload) => Node.create({
    name: 'gallery',
    group: 'block',
    atom: true,
    draggable: true,
    selectable: true,
    addAttributes() {
        return {
            layout: { default: 'grid', parseHTML: (el) => el.getAttribute('data-gallery') || 'grid', rendered: false },
            images: { default: [], parseHTML: galleryItems, rendered: false },
            caption: { default: '', parseHTML: (el) => el.querySelector(':scope > figcaption')?.textContent.trim() || '', rendered: false },
        };
    },
    parseHTML() { return [{ tag: 'figure', priority: 70, getAttrs: (el) => (isGallery(el) ? null : false) }]; },
    renderHTML({ node }) {
        const { layout, images, caption } = node.attrs;
        return ['figure', { 'data-gallery': layout },
            ...images.map((i) => ['figure', {}, ['img', { src: i.src, alt: i.alt || '' }], ...(i.caption ? [['figcaption', {}, i.caption]] : [])]),
            ...(caption ? [['figcaption', {}, caption]] : [])];
    },
    addNodeView() {
        return ({ node, editor, getPos }) => {
            let current = node;
            const dom = document.createElement('div');
            dom.className = 'ed-gallery';
            dom.contentEditable = 'false';
            const set = (attrs) => patchNode(editor, getPos, attrs);
            const images = () => current.attrs.images.map((i) => ({ ...i }));
            const draw = () => {
                const { layout, images: list, caption } = current.attrs;
                dom.dataset.layout = layout;
                dom.innerHTML = `
                    <div class="ed-gallery-bar">
                        <span class="ed-gallery-tag">Галерея · ${list.length}</span>
                        <span class="ed-gallery-layouts" role="radiogroup" aria-label="Вид галереи">${LAYOUTS.map(([v, t]) => `<button type="button" role="radio" aria-checked="${v === layout}" class="${v === layout ? 'is-on' : ''}" data-layout="${v}">${t}</button>`).join('')}</span>
                        <label class="ed-gallery-add">+ Картинки<input type="file" accept="image/*" multiple hidden></label>
                    </div>
                    <div class="ed-gallery-grid">${list.map((i, n) => `
                        <div class="ed-gi" data-i="${n}">
                            <img src="${esc(i.src)}" alt="${esc(i.alt)}" draggable="false">
                            <span class="ed-gi-tools">
                                <button type="button" data-move="-1" title="Левее" aria-label="Левее"${n === 0 ? ' disabled' : ''}>←</button>
                                <button type="button" data-move="1" title="Правее" aria-label="Правее"${n === list.length - 1 ? ' disabled' : ''}>→</button>
                                <button type="button" data-remove title="Убрать из галереи" aria-label="Убрать">✕</button>
                            </span>
                            <input type="text" data-cap value="${esc(i.caption)}" placeholder="Подпись" maxlength="300">
                        </div>`).join('')}</div>
                    <input type="text" class="ed-gallery-cap" data-gcap value="${esc(caption)}" placeholder="Подпись ко всей галерее (необязательно)" maxlength="300">`;
            };
            dom.addEventListener('click', (e) => {
                const b = e.target.closest('button');
                if (!b) return;
                e.preventDefault();
                if (b.dataset.layout) return set({ layout: b.dataset.layout });
                const n = +b.closest('.ed-gi')?.dataset.i;
                const list = images();
                if (b.hasAttribute('data-remove')) {
                    list.splice(n, 1);
                    if (!list.length) return editor.chain().focus().setNodeSelection(getPos()).deleteSelection().run();
                    return set({ images: list });
                }
                if (b.dataset.move) {
                    const k = n + +b.dataset.move;
                    [list[n], list[k]] = [list[k], list[n]];
                    return set({ images: list });
                }
            });
            dom.addEventListener('input', (e) => {
                if (e.target.matches('[data-gcap]')) return set({ caption: e.target.value });
                if (e.target.matches('[data-cap]')) {
                    const list = images();
                    list[+e.target.closest('.ed-gi').dataset.i].caption = e.target.value;
                    set({ images: list });
                }
            });
            dom.addEventListener('change', async (e) => {
                if (e.target.type !== 'file') return;
                const files = [...e.target.files];
                e.target.value = '';
                const added = (await Promise.all(files.map(upload))).filter(Boolean);
                if (added.length) set({ images: [...images(), ...added] });
            });
            draw();
            return {
                dom,
                update(updated) {
                    if (updated.type.name !== 'gallery') return false;
                    current = updated;
                    if (!typing(dom)) draw(); // подпись набирается — не перерисовываем поле под курсором
                    else dom.querySelector('.ed-gallery-tag').textContent = `Галерея · ${updated.attrs.images.length}`;
                    return true;
                },
                stopEvent: (e) => !!e.target.closest?.('input, button, label'),
                ignoreMutation: () => true,
            };
        };
    },
});

// Одиночная картинка с подписью: <figure><img><figcaption>…</figcaption></figure>
const FigureImage = Node.create({
    name: 'figureImage',
    group: 'block',
    atom: true,
    draggable: true,
    selectable: true,
    addAttributes() {
        return {
            src: { default: null, parseHTML: (el) => figImg(el)?.getAttribute('src'), rendered: false },
            alt: { default: '', parseHTML: (el) => figImg(el)?.getAttribute('alt') || '', rendered: false },
            caption: { default: '', parseHTML: (el) => el.querySelector(':scope > figcaption')?.textContent.trim() || '', rendered: false },
        };
    },
    parseHTML() { return [{ tag: 'figure', priority: 60, getAttrs: (el) => (!isGallery(el) && figImg(el) ? null : false) }]; },
    renderHTML({ node }) {
        const { src, alt, caption } = node.attrs;
        return ['figure', {}, ['img', { src, alt: alt || '' }], ...(caption ? [['figcaption', {}, caption]] : [])];
    },
    addNodeView() {
        return ({ node, editor, getPos }) => {
            let current = node;
            const dom = document.createElement('figure');
            dom.className = 'ed-figure';
            dom.contentEditable = 'false';
            const draw = () => {
                dom.innerHTML = `<img src="${esc(current.attrs.src)}" alt="${esc(current.attrs.alt)}" draggable="false"><input type="text" value="${esc(current.attrs.caption)}" placeholder="Подпись к картинке" maxlength="300">`;
            };
            dom.addEventListener('input', (e) => patchNode(editor, getPos, { caption: e.target.value }));
            draw();
            return {
                dom,
                update(updated) {
                    if (updated.type.name !== 'figureImage') return false;
                    current = updated;
                    if (!typing(dom)) draw();
                    return true;
                },
                stopEvent: (e) => !!e.target.closest?.('input'),
                ignoreMutation: () => true,
            };
        };
    },
});

export default function registerEditor(Alpine) {
    // Экземпляр Editor держим вне реактивного состояния Alpine: Proxy ломает ProseMirror
    Alpine.data('postEditor', (cfg) => { let editor = null; return {
        tick: 0,               // перерисовка тулбара после транзакций
        words: 0, chars: 0, minutes: 1,
        dirty: false, saving: false, draftFound: null,
        menu: null,            // открытое меню: block | callout | table | link | embed
        linkUrl: '',
        uploading: false,
        title: cfg.title || '', lead: cfg.lead || '', slug: cfg.slug || '', seoTitle: cfg.seoTitle || '', seoDescription: cfg.seoDescription || '',
        cover: cfg.cover || '', coverPreview: cfg.coverUrl || '', coverDrag: false,
        links: cfg.links || [], linkItems: {}, lookupQ: '', lookupItems: [], lookupFor: 'links', lookupLoading: false,
        focus: false,

        init() {
            const el = this.$refs.body;
            const self = this;
            editor = new Editor({
                element: el,
                content: this.$refs.input.value,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3, 4] },
                        link: { openOnClick: false, autolink: true, defaultProtocol: 'https', HTMLAttributes: { rel: null, target: null } },
                        codeBlock: { HTMLAttributes: {} },
                    }),
                    Image.configure({ inline: false, allowBase64: false }),
                    TableKit.configure({ table: { resizable: false } }),
                    Highlight, Subscript, Superscript,
                    Callout, Embed(cfg.embedUrl),
                    Gallery((file) => self.upload(file)), FigureImage, // A52
                    Placeholder.configure({
                        placeholder: ({ node }) => node.type.name === 'heading' ? 'Заголовок раздела' : 'Начните писать… Картинку можно перетащить или вставить из буфера',
                    }),
                    CharacterCount,
                ],
                editorProps: {
                    attributes: { class: 'prose-banq !p-0', spellcheck: 'true', 'aria-label': 'Текст публикации' },
                    handleDrop: (view, event, slice, moved) => {
                        const files = moved ? [] : [...(event.dataTransfer?.files || [])].filter((f) => f.type.startsWith('image/'));
                        const file = files[0];
                        if (!file) return false;
                        event.preventDefault();
                        const pos = view.posAtCoords({ left: event.clientX, top: event.clientY })?.pos;
                        if (files.length > 1) { self.insertGallery(files, pos); return true; } // A52 · несколько файлов — сразу галерея
                        self.uploadImage(file, pos);
                        return true;
                    },
                    handlePaste: (view, event) => {
                        const files = [...(event.clipboardData?.files || [])].filter((f) => f.type.startsWith('image/'));
                        if (!files.length) return false;
                        event.preventDefault();
                        files.length > 1 ? self.insertGallery(files) : self.uploadImage(files[0]);
                        return true;
                    },
                },
                onTransaction: () => { this.tick++; },
                onUpdate: () => { this.sync(); this.markDirty(); },
                onCreate: () => this.sync(),
            });

            // черновик из localStorage новее сохранённого?
            try {
                const d = JSON.parse(localStorage.getItem(cfg.draftKey) || 'null');
                if (d && d.at > cfg.updatedAt && d.body !== this.$refs.input.value) this.draftFound = d;
            } catch {}

            this.$watch('title', () => this.markDirty());
            this.$watch('lead', () => this.markDirty());
            this.$nextTick(() => { this.grow(this.$refs.title); this.grow(this.$refs.lead); });

            // подписи для уже выбранных связей
            if (this.links.length) this.lookup('', this.links);

            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') { e.preventDefault(); this.submit(); }
                if (e.key === 'Escape') { this.menu = null; if (this.focus) this.focus = false; }
            });
            window.addEventListener('beforeunload', (e) => { if (this.dirty && !this.saving) { e.preventDefault(); e.returnValue = ''; } });
        },

        destroy() { editor?.destroy(); },

        sync() {
            const html = editor.isEmpty ? '' : editor.getHTML();
            this.$refs.input.value = html;
            this.words = editor.storage.characterCount.words();
            this.chars = editor.storage.characterCount.characters();
            this.minutes = Math.max(1, Math.round(this.words / 180));
            return html;
        },

        markDirty() {
            this.dirty = true;
            clearTimeout(this._t);
            this._t = setTimeout(() => {
                try { localStorage.setItem(cfg.draftKey, JSON.stringify({ at: Math.floor(Date.now() / 1000), title: this.title, lead: this.lead, body: this.$refs.input.value })); } catch {}
            }, 600);
        },

        restoreDraft() {
            const d = this.draftFound;
            this.title = d.title ?? this.title; this.lead = d.lead ?? this.lead;
            editor.commands.setContent(d.body || '', { emitUpdate: true });
            this.draftFound = null;
            this.$nextTick(() => { this.grow(this.$refs.title); this.grow(this.$refs.lead); });
        },
        dropDraft() { try { localStorage.removeItem(cfg.draftKey); } catch {} this.draftFound = null; },

        submit(action = null) {
            this.sync();
            if (action) this.$refs.action.value = action;
            this.saving = true;
            try { localStorage.removeItem(cfg.draftKey); } catch {}
            this.$refs.form.requestSubmit ? this.$refs.form.requestSubmit() : this.$refs.form.submit();
        },

        grow(el) { if (!el) return; el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; },

        // ── Тулбар ──────────────────────────────────────────
        is(name, attrs = {}) { this.tick; return editor?.isActive(name, attrs) ?? false; },
        can(fn) { this.tick; try { return fn(editor.can().chain().focus()).run(); } catch { return false; } },
        run(fn) { fn(editor.chain().focus()).run(); this.menu = null; },
        blockLabel() {
            this.tick;
            for (const l of [2, 3, 4]) if (this.is('heading', { level: l })) return 'H' + l;
            return this.is('codeBlock') ? 'Код' : 'Текст';
        },
        setBlock(kind) {
            this.run((c) => kind === 'p' ? c.setParagraph() : kind === 'code' ? c.toggleCodeBlock() : c.toggleHeading({ level: +kind }));
        },

        openLink() {
            this.linkUrl = editor.getAttributes('link').href || '';
            this.menu = 'link';
            this.$nextTick(() => this.$refs.linkInput?.focus());
        },
        applyLink() {
            const url = this.linkUrl.trim();
            const c = editor.chain().focus().extendMarkRange('link');
            url ? c.setLink({ href: /^(https?:|mailto:|\/|#)/i.test(url) ? url : 'https://' + url }).run() : c.unsetLink().run();
            this.menu = null;
        },

        insertTable() { this.run((c) => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true })); },

        pickImage() { this.$refs.imageFile.click(); },
        // A52 · загрузка одного файла → { src, alt, caption } или null (ошибку показываем)
        async upload(file) {
            const fd = new FormData();
            fd.append('image', file);
            try {
                const r = await fetch(cfg.uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: fd });
                const d = await r.json();
                if (!r.ok) throw new Error(d.message || 'Не удалось загрузить');
                return { src: d.url, alt: (file.name || '').replace(/\.[a-z0-9]+$/i, '').replace(/[-_]+/g, ' '), caption: '' };
            } catch (e) {
                alert(`${file.name || 'Картинка'}: ${e.message || 'не удалось загрузить'}`);
                return null;
            }
        },
        async uploadImage(file, pos = null) {
            if (!file) return;
            this.uploading = true;
            const img = await this.upload(file);
            if (img) {
                const chain = editor.chain().focus();
                (pos != null ? chain.insertContentAt(pos, { type: 'image', attrs: { src: img.src, alt: img.alt } }) : chain.setImage({ src: img.src, alt: img.alt })).run();
            }
            this.uploading = false;
        },
        pickGallery() { this.$refs.galleryFiles.click(); },
        async insertGallery(files, pos = null) {
            files = [...files].filter((f) => f.type.startsWith('image/'));
            if (!files.length) return;
            this.uploading = true;
            const images = (await Promise.all(files.map((f) => this.upload(f)))).filter(Boolean);
            if (images.length) {
                const node = { type: 'gallery', attrs: { layout: 'grid', images, caption: '' } };
                const chain = editor.chain().focus();
                (pos != null ? chain.insertContentAt(pos, node) : chain.insertContent(node)).run();
            }
            this.uploading = false;
        },
        // картинка → картинка с подписью (подпись вводится прямо под ней)
        addCaption() {
            const { src, alt } = editor.getAttributes('image');
            this.run((c) => c.insertContent({ type: 'figureImage', attrs: { src, alt, caption: '' } }));
            this.$nextTick(() => document.querySelector('.ProseMirror-selectednode input, .ed-figure:focus-within input')?.focus());
        },
        imageAlt() {
            const type = this.is('figureImage') ? 'figureImage' : 'image';
            const cur = editor.getAttributes(type).alt || '';
            const alt = prompt('Описание картинки (для поиска и незрячих читателей)', cur);
            if (alt !== null) this.run((c) => c.updateAttributes(type, { alt }));
        },

        insertEmbed(item) {
            editor.chain().focus().insertContent({ type: 'embed', attrs: { type: item.type, slug: item.key.split(':')[1] } }).run();
            this.addLink(item);
            this.menu = null;
        },

        // ── Обложка ─────────────────────────────────────────
        coverPicked(e) {
            const f = e.target.files?.[0] || e.dataTransfer?.files?.[0];
            if (!f) return;
            if (e.dataTransfer) { const dt = new DataTransfer(); dt.items.add(f); this.$refs.coverFile.files = dt.files; }
            this.coverPreview = URL.createObjectURL(f);
            this.$refs.coverRemove.value = '0';
            this.markDirty();
        },
        removeCover() { this.coverPreview = ''; this.cover = ''; this.$refs.coverFile.value = ''; this.$refs.coverRemove.value = '1'; this.markDirty(); },

        // ── Связи с банками и продуктами ────────────────────
        async lookup(q = this.lookupQ, keys = null) {
            if (!keys && q.trim().length < 2) { this.lookupItems = []; return; }
            this.lookupLoading = true;
            const url = new URL(cfg.lookupUrl, location.href);
            if (q) url.searchParams.set('q', q);
            if (keys) url.searchParams.set('keys', keys.join(','));
            try {
                const d = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();
                d.items.forEach((i) => (this.linkItems[i.key] = i));
                if (!keys) this.lookupItems = d.items;
            } catch {}
            this.lookupLoading = false;
        },
        addLink(item) {
            this.linkItems[item.key] = item;
            if (!this.links.includes(item.key)) this.links = [...this.links, item.key];
            this.lookupQ = ''; this.lookupItems = [];
            this.markDirty();
        },
        removeLink(key) { this.links = this.links.filter((k) => k !== key); this.markDirty(); },
        derived() {
            const own = new Set(this.links.map((k) => this.linkItems[k]).filter(Boolean).map((i) => `${i.label}: ${i.title}`));
            return [...new Set(this.links.flatMap((k) => this.linkItems[k]?.cascade || []))].filter((x) => !own.has(x));
        },

        slugify(s) {
            const map = { а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'c', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya', ә: 'a', ғ: 'g', қ: 'k', ң: 'n', ө: 'o', ұ: 'u', ү: 'u', һ: 'h', і: 'i' };
            return s.toLowerCase().split('').map((ch) => map[ch] ?? ch).join('').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120);
        },
    }; });
}
