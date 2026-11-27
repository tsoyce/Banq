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
                    Placeholder.configure({
                        placeholder: ({ node }) => node.type.name === 'heading' ? 'Заголовок раздела' : 'Начните писать… Картинку можно перетащить или вставить из буфера',
                    }),
                    CharacterCount,
                ],
                editorProps: {
                    attributes: { class: 'prose-banq !p-0', spellcheck: 'true', 'aria-label': 'Текст публикации' },
                    handleDrop: (view, event, slice, moved) => {
                        const file = !moved && event.dataTransfer?.files?.[0];
                        if (!file || !file.type.startsWith('image/')) return false;
                        event.preventDefault();
                        const pos = view.posAtCoords({ left: event.clientX, top: event.clientY })?.pos;
                        self.uploadImage(file, pos);
                        return true;
                    },
                    handlePaste: (view, event) => {
                        const file = [...(event.clipboardData?.files || [])].find((f) => f.type.startsWith('image/'));
                        if (!file) return false;
                        event.preventDefault();
                        self.uploadImage(file);
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
        async uploadImage(file, pos = null) {
            if (!file) return;
            this.uploading = true;
            const fd = new FormData();
            fd.append('image', file);
            try {
                const r = await fetch(cfg.uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: fd });
                const d = await r.json();
                if (!r.ok) throw new Error(d.message || 'Не удалось загрузить');
                const alt = (file.name || '').replace(/\.[a-z0-9]+$/i, '').replace(/[-_]+/g, ' ');
                const chain = editor.chain().focus();
                (pos != null ? chain.insertContentAt(pos, { type: 'image', attrs: { src: d.url, alt } }) : chain.setImage({ src: d.url, alt })).run();
            } catch (e) {
                alert(e.message || 'Не удалось загрузить картинку');
            }
            this.uploading = false;
        },
        imageAlt() {
            const cur = editor.getAttributes('image').alt || '';
            const alt = prompt('Описание картинки (для поиска и незрячих читателей)', cur);
            if (alt !== null) this.run((c) => c.updateAttributes('image', { alt }));
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
