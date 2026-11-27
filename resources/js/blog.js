// A37 · Журнал на витрине: «Показать ещё», реакции со «всплеском», поделиться/скопировать, прогресс чтения, оглавление.
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;

async function copyToClipboard(text) {
    try { await navigator.clipboard.writeText(text); return true; } catch {}
    const t = Object.assign(document.createElement('textarea'), { value: text });
    t.style.cssText = 'position:fixed;opacity:0';
    document.body.append(t); t.select();
    const ok = document.execCommand('copy'); t.remove();
    return ok;
}

export default function registerBlog(Alpine) {
    // «Показать ещё»: следующая страница ленты частичным HTML (X-Partial: posts), URL — без перезагрузки
    Alpine.data('loadMore', (next) => ({
        next, loading: false,
        async more() {
            if (!this.next || this.loading) return;
            this.loading = true;
            try {
                const r = await fetch(this.next, { headers: { 'X-Partial': 'posts', 'X-Requested-With': 'XMLHttpRequest' } });
                const html = await r.text();
                this.$refs.grid.insertAdjacentHTML('beforeend', html);
                this.$refs.grid.querySelectorAll('[data-reveal]:not(.is-in)').forEach((el) => requestAnimationFrame(() => el.classList.add('is-in')));
                const url = new URL(this.next, location.href);
                history.replaceState(null, '', url);
                if (r.headers.get('X-Has-More') === '1') {
                    url.searchParams.set('page', (+url.searchParams.get('page') || 1) + 1);
                    this.next = url.toString();
                } else this.next = null;
            } catch { location.href = this.next; }
            this.loading = false;
        },
    }));

    // Копировать произвольный текст (ссылка RSS и т.п.)
    Alpine.data('copyText', (text) => ({
        done: false,
        async copy() { if (await copyToClipboard(text)) { this.done = true; setTimeout(() => (this.done = false), 1800); } },
    }));

    // Поделиться: системное меню, если есть; копирование ссылки с подтверждением
    Alpine.data('share', (url, title) => ({
        done: false,
        native: typeof navigator.share === 'function' && matchMedia('(pointer: coarse)').matches,
        open() { navigator.share({ url, title }).catch(() => {}); },
        async copy() { if (await copyToClipboard(url)) { this.done = true; setTimeout(() => (this.done = false), 1800); } },
    }));

    // Реакции: общее состояние для всех копий блока на странице
    const post = window.banqPost;
    if (post) {
        Alpine.store('post', {
            counts: post.counts, mine: post.mine, busy: false,
            async toggle(kind, el) {
                if (!post.auth) { location.href = post.login; return; }
                if (this.busy) return;
                const on = !this.mine.includes(kind);
                // оптимистично: сразу меняем и «взрываем»
                this.mine = on ? [...this.mine, kind] : this.mine.filter((k) => k !== kind);
                this.counts = { ...this.counts, [kind]: Math.max(0, (this.counts[kind] || 0) + (on ? 1 : -1)) };
                if (on) burst(el);
                this.busy = true;
                try {
                    const r = await fetch(post.react, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ kind }) });
                    if (!r.ok) throw new Error();
                    const d = await r.json();
                    this.counts = d.counts;
                    this.mine = d.on ? [...new Set([...this.mine, kind])] : this.mine.filter((k) => k !== kind);
                } catch {
                    this.mine = on ? this.mine.filter((k) => k !== kind) : [...this.mine, kind];
                    this.counts = { ...this.counts, [kind]: Math.max(0, (this.counts[kind] || 0) + (on ? -1 : 1)) };
                }
                this.busy = false;
            },
        });
    }

    function burst(el) {
        el.classList.remove('is-pop'); void el.offsetWidth; el.classList.add('is-pop');
        if (reduced) return;
        const emoji = el.querySelector('.reaction-emoji')?.textContent || '✦';
        for (let i = 0; i < 8; i++) {
            const s = document.createElement('span');
            const a = (Math.PI * 2 * i) / 8 + Math.random() * 0.5;
            const d = 26 + Math.random() * 22;
            s.className = 'burst';
            s.textContent = i % 2 ? '✦' : emoji;
            s.style.setProperty('--x', `${Math.cos(a) * d}px`);
            s.style.setProperty('--y', `${Math.sin(a) * d - 10}px`);
            s.style.setProperty('--r', `${(Math.random() - 0.5) * 90}deg`);
            if (i % 2) s.style.color = getComputedStyle(document.documentElement).getPropertyValue('--brand-1');
            el.append(s);
            setTimeout(() => s.remove(), 800);
        }
    }

    // Прогресс чтения статьи (по телу публикации)
    Alpine.data('readProgress', () => ({
        p: 0,
        init() {
            const body = document.getElementById('post-body');
            if (!body) return;
            const update = () => {
                const r = body.getBoundingClientRect();
                const total = r.height - window.innerHeight * 0.6;
                this.p = Math.min(1, Math.max(0, -r.top / Math.max(1, total))).toFixed(4);
            };
            addEventListener('scroll', update, { passive: true });
            addEventListener('resize', update);
            update();
        },
    }));

    // Оглавление: подсветка раздела, который сейчас читают
    Alpine.data('tocSpy', () => ({
        active: null,
        init() {
            const heads = [...document.querySelectorAll('#post-body h2[id], #post-body h3[id]')];
            if (!heads.length) return;
            const update = () => {
                let cur = heads[0].id;
                for (const h of heads) if (h.getBoundingClientRect().top < 140) cur = h.id;
                this.active = cur;
            };
            addEventListener('scroll', update, { passive: true });
            update();
        },
    }));
}
