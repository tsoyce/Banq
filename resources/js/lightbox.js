// A46 · Лайтбокс и слайдеры галерей в статьях журнала (без зависимостей).
// Клик по картинке в [data-lightbox] (текст статьи, обложка) — картинка на весь экран: подпись, счётчик «3 / 12»,
// стрелки и ←/→, Esc, свайп влево/вправо, свайп вниз — закрыть, двойной клик/тап — увеличение с перетаскиванием,
// лента миниатюр, открыть оригинал. Все картинки статьи — одна серия в порядке текста.
// Слайдер <figure data-gallery="slider">: стрелки и точки поверх .gallery-track (на телефоне — свайп).
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const svg = (d) => `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${d}</svg>`;
const ICON = {
    x: svg('<path d="M18 6 6 18M6 6l12 12"/>'),
    prev: svg('<path d="m15 18-6-6 6-6"/>'),
    next: svg('<path d="m9 18 6-6-6-6"/>'),
    zoom: svg('<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/>'),
    open: svg('<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>'),
};
const IMAGE_FILE = /\.(jpe?g|png|gif|webp|avif)(\?.*)?$/i;

/** Картинки контейнера, которые открываются: не иконки, не внутри ссылок на страницы и живых вставок. */
function collect(root) {
    return [...root.querySelectorAll('img')].filter((img) => {
        if (img.closest('.embed, .embed-video, [data-embed]')) return false;
        const a = img.closest('a');
        if (a && !IMAGE_FILE.test(a.getAttribute('href') || '')) return false; // картинка-ссылка ведёт на страницу — не мешаем
        const w = img.naturalWidth || +img.getAttribute('width') || 999;
        return w >= 120;
    });
}

function itemOf(img) {
    const a = img.closest('a');
    const fig = img.closest('figure');
    const cap = fig && fig.querySelector(':scope > figcaption');
    return {
        src: a && IMAGE_FILE.test(a.getAttribute('href') || '') ? a.href : (img.currentSrc || img.src),
        thumb: img.currentSrc || img.src,
        alt: img.alt || '',
        caption: cap ? cap.textContent.trim() : (img.title || ''),
    };
}

class Lightbox {
    constructor() {
        this.items = [];
        this.i = 0;
        this.zoom = 1; this.px = 0; this.py = 0;
        this.el = null;
    }

    build() {
        const el = document.createElement('div');
        el.className = 'lb';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-label', 'Просмотр картинки');
        el.innerHTML = `
            <div class="lb-backdrop"></div>
            <div class="lb-top">
                <span class="lb-count" aria-live="polite"></span>
                <span class="lb-actions">
                    <button type="button" class="lb-btn" data-act="zoom" aria-label="Увеличить" title="Увеличить · двойной клик">${ICON.zoom}</button>
                    <a class="lb-btn" data-act="open" target="_blank" rel="noopener" aria-label="Открыть оригинал" title="Открыть оригинал">${ICON.open}</a>
                    <button type="button" class="lb-btn" data-act="close" aria-label="Закрыть" title="Закрыть · Esc">${ICON.x}</button>
                </span>
            </div>
            <div class="lb-stage"><img class="lb-img" alt="" draggable="false"></div>
            <button type="button" class="lb-btn lb-nav is-prev" data-act="prev" aria-label="Предыдущая">${ICON.prev}</button>
            <button type="button" class="lb-btn lb-nav is-next" data-act="next" aria-label="Следующая">${ICON.next}</button>
            <div class="lb-bottom"><p class="lb-caption"></p><div class="lb-thumbs" role="tablist" aria-label="Все картинки"></div></div>`;
        document.body.append(el);
        this.el = el;
        this.img = el.querySelector('.lb-img');
        this.stage = el.querySelector('.lb-stage');

        el.addEventListener('click', (e) => {
            const act = e.target.closest('[data-act]')?.dataset.act;
            if (act === 'close') this.close();
            else if (act === 'prev') this.go(this.i - 1);
            else if (act === 'next') this.go(this.i + 1);
            else if (act === 'zoom') this.toggleZoom();
            else if (e.target.closest('.lb-thumb')) this.go(+e.target.closest('.lb-thumb').dataset.i);
            // щелчок мимо картинки — закрыть (при захвате указателя цель клика — сцена, поэтому смотрим, где нажали)
            else if ((e.target === this.stage || e.target.classList.contains('lb-backdrop')) && !this.moved && !this.downOnImg) this.close();
        });
        this.stage.addEventListener('dblclick', (e) => { if (this.pointerType === 'touch') return; if (this.downOnImg || this.zoom > 1) this.toggleZoom(e); });
        this.keys = (e) => {
            if (e.key === 'Escape') this.close();
            else if (e.key === 'ArrowLeft') this.go(this.i - 1);
            else if (e.key === 'ArrowRight') this.go(this.i + 1);
            else if (e.key === 'Tab') this.trap(e);
        };
        this.pointer();
    }

    open(items, index, from) {
        if (!this.el) this.build();
        this.items = items;
        this.from = from;
        this.lastFocus = document.activeElement;
        const thumbs = this.el.querySelector('.lb-thumbs');
        thumbs.innerHTML = items.length > 1
            ? items.map((it, n) => `<button type="button" class="lb-thumb" role="tab" data-i="${n}" aria-label="Картинка ${n + 1}"><img src="${it.thumb}" alt="" loading="lazy"></button>`).join('')
            : '';
        this.el.classList.toggle('is-single', items.length < 2);
        document.documentElement.classList.add('lb-lock');
        this.el.hidden = false;
        this.go(index, true);
        requestAnimationFrame(() => this.el.classList.add('is-open'));
        addEventListener('keydown', this.keys);
        this.el.querySelector('[data-act=close]').focus({ preventScroll: true });
    }

    close() {
        if (!this.el || !this.el.classList.contains('is-open')) return;
        this.el.classList.remove('is-open');
        removeEventListener('keydown', this.keys);
        document.documentElement.classList.remove('lb-lock');
        setTimeout(() => { this.el.hidden = true; this.img.removeAttribute('src'); }, reduced ? 0 : 260);
        this.lastFocus?.focus?.({ preventScroll: true });
    }

    go(n, first = false) {
        const len = this.items.length;
        if (!len) return;
        if (!first && len < 2) return;
        const dir = n > this.i ? 1 : -1;
        this.i = (n + len) % len;
        const it = this.items[this.i];
        this.setZoom(1);
        // новая картинка въезжает со стороны листания: сбрасываем без анимации, затем «появление»
        this.img.style.transition = 'none';
        this.img.classList.remove('is-in');
        this.img.style.setProperty('--from', first || reduced ? '0px' : `${dir * 40}px`);
        void this.img.offsetWidth;
        this.img.style.transition = '';
        this.img.onload = () => requestAnimationFrame(() => this.img.classList.add('is-in'));
        this.img.src = it.src;
        this.img.alt = it.alt;
        if (this.img.complete) requestAnimationFrame(() => this.img.classList.add('is-in'));
        const cap = this.el.querySelector('.lb-caption');
        cap.textContent = it.caption;
        cap.hidden = !it.caption;
        this.el.querySelector('.lb-count').textContent = len > 1 ? `${this.i + 1} / ${len}` : '';
        this.el.querySelector('[data-act=open]').href = it.src;
        this.el.querySelectorAll('.lb-thumb').forEach((t, k) => {
            t.classList.toggle('is-on', k === this.i);
            t.setAttribute('aria-selected', k === this.i ? 'true' : 'false');
            if (k === this.i) t.scrollIntoView({ block: 'nearest', inline: 'center', behavior: reduced ? 'auto' : 'smooth' });
        });
        // соседние — заранее
        [this.i + 1, this.i - 1].forEach((k) => { if (len > 1) new Image().src = this.items[(k + len) % len].src; });
    }

    setZoom(z, cx = null, cy = null) {
        const prev = this.zoom;
        this.zoom = z;
        if (z === 1) { this.px = 0; this.py = 0; }
        else if (cx !== null) {
            // точка под курсором остаётся на месте
            const r = this.stage.getBoundingClientRect();
            const ox = cx - (r.left + r.width / 2), oy = cy - (r.top + r.height / 2);
            this.px = -ox * (z / prev - 1); this.py = -oy * (z / prev - 1);
        }
        this.apply();
        this.el.classList.toggle('is-zoomed', z > 1);
    }

    toggleZoom(e) { this.setZoom(this.zoom > 1 ? 1 : 2.2, e?.clientX ?? null, e?.clientY ?? null); }

    apply(dragX = 0, dragY = 0, fade = 1) {
        const idle = this.zoom === 1 && !dragX && !dragY;
        this.img.style.transform = idle ? '' : `translate(${this.px + dragX}px, ${this.py + dragY}px) scale(${this.zoom})`;
        this.el.style.setProperty('--fade', fade);
    }

    // Свайпы и перетаскивание: одна логика для мыши, пальца и пера
    pointer() {
        let sx = 0, sy = 0, start = 0, active = false, lastTap = 0;
        const st = this.stage;
        st.addEventListener('pointerdown', (e) => {
            if (e.button !== 0) return;
            active = true; this.moved = false;
            this.downOnImg = e.target === this.img;
            this.pointerType = e.pointerType;
            sx = e.clientX; sy = e.clientY; start = performance.now();
            st.setPointerCapture(e.pointerId);
            this.img.classList.add('is-drag');
        });
        st.addEventListener('pointermove', (e) => {
            if (!active) return;
            const dx = e.clientX - sx, dy = e.clientY - sy;
            if (Math.abs(dx) + Math.abs(dy) > 6) this.moved = true;
            if (this.zoom > 1) this.apply(dx, dy);
            else if (Math.abs(dy) > Math.abs(dx)) this.apply(0, Math.max(0, dy), 1 - Math.min(0.6, Math.max(0, dy) / 400));
            else this.apply(dx, 0);
        });
        const end = (e) => {
            if (!active) return;
            active = false;
            this.img.classList.remove('is-drag');
            const dx = e.clientX - sx, dy = e.clientY - sy;
            const fast = performance.now() - start < 300;
            if (this.zoom > 1) { this.px += dx; this.py += dy; this.apply(); }
            else if (Math.abs(dx) > Math.abs(dy) && (Math.abs(dx) > 70 || (fast && Math.abs(dx) > 30)) && this.items.length > 1) this.go(this.i + (dx < 0 ? 1 : -1));
            else if (dy > 110 || (fast && dy > 50)) this.close();
            else this.apply();
            // двойной тап на сенсорном экране — увеличение
            if (e.pointerType === 'touch' && !this.moved) {
                const now = performance.now();
                if (now - lastTap < 300) { this.toggleZoom(e); lastTap = 0; } else lastTap = now;
            }
            setTimeout(() => (this.moved = false), 0);
        };
        st.addEventListener('pointerup', end);
        st.addEventListener('pointercancel', end);
        st.addEventListener('wheel', (e) => {
            if (!e.ctrlKey && this.zoom === 1) return;
            e.preventDefault();
            const z = Math.min(4, Math.max(1, this.zoom * (e.deltaY < 0 ? 1.15 : 1 / 1.15)));
            this.setZoom(z, e.clientX, e.clientY);
        }, { passive: false });
    }

    trap(e) {
        const f = [...this.el.querySelectorAll('button, a[href]')].filter((x) => x.offsetParent !== null);
        if (!f.length) return;
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
}

function slider(fig) {
    const track = fig.querySelector('.gallery-track');
    if (!track || fig.dataset.ready) return;
    fig.dataset.ready = '1';
    const slides = [...track.children];
    const mk = (cls, label, icon) => Object.assign(document.createElement('button'), { type: 'button', className: `gallery-nav ${cls}`, innerHTML: icon, ariaLabel: label });
    const prev = mk('is-prev', 'Назад', ICON.prev), next = mk('is-next', 'Вперёд', ICON.next);
    const dots = document.createElement('div');
    dots.className = 'gallery-dots';
    dots.innerHTML = slides.map(() => '<i></i>').join('');
    fig.prepend(prev, next);
    track.after(dots);
    const current = () => {
        const mid = track.scrollLeft + track.clientWidth / 2;
        let best = 0, dist = Infinity;
        slides.forEach((s, k) => { const d = Math.abs(s.offsetLeft + s.offsetWidth / 2 - mid); if (d < dist) { dist = d; best = k; } });
        return best;
    };
    const to = (k) => { const s = slides[Math.max(0, Math.min(slides.length - 1, k))]; track.scrollTo({ left: s.offsetLeft - (track.clientWidth - s.offsetWidth) / 2, behavior: reduced ? 'auto' : 'smooth' }); };
    const update = () => {
        const k = current();
        prev.disabled = track.scrollLeft <= 4;
        next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 4;
        [...dots.children].forEach((d, n) => d.classList.toggle('is-on', n === k));
    };
    prev.addEventListener('click', () => to(current() - 1));
    next.addEventListener('click', () => to(current() + 1));
    track.addEventListener('scroll', () => requestAnimationFrame(update), { passive: true });
    new ResizeObserver(update).observe(track);
    update();
}

export default function initLightbox() {
    const roots = [...document.querySelectorAll('[data-lightbox]')];
    if (!roots.length) return;
    const lb = new Lightbox();
    // одна серия на страницу: обложка + все картинки статьи в порядке появления
    const series = () => roots.flatMap(collect);
    const mark = () => series().forEach((img) => {
        if (img.hasAttribute('data-lb')) return;
        img.setAttribute('data-lb', '');
        img.tabIndex = 0; // с клавиатуры: Tab до картинки, Enter — открыть
        img.setAttribute('role', 'button');
        img.setAttribute('aria-label', `Открыть картинку${img.alt ? ': ' + img.alt : ''}`);
    });
    const openFrom = (img, e) => {
        const list = series();
        const index = list.indexOf(img);
        if (index < 0) return;
        e.preventDefault();
        lb.open(list.map(itemOf), index, img);
    };
    roots.forEach((r) => {
        r.classList.add('has-lightbox');
        r.querySelectorAll('img').forEach((img) => img.complete || img.addEventListener('load', mark, { once: true }));
        r.addEventListener('click', (e) => {
            const img = e.target.closest('img');
            if (!img || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
            openFrom(img, e);
        });
        r.addEventListener('keydown', (e) => {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.matches?.('img[data-lb]')) openFrom(e.target, e);
        });
        r.querySelectorAll('figure[data-gallery=slider]').forEach(slider);
    });
    mark();
}
