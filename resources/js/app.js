// A04/A13 · Клиентский слой: Alpine-острова + микроанимации.
import Alpine from 'alpinejs';

const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Тема: localStorage → настройка сайта (data-theme на <html> из Blade)
Alpine.store('theme', {
    value: document.documentElement.dataset.theme,
    toggle() {
        this.value = this.value === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = this.value;
        try { localStorage.setItem('banq.theme', this.value); } catch {}
    },
});

// x-count="16.35" — плавный счётчик при появлении
Alpine.directive('count', (el, { expression }) => {
    const target = parseFloat(expression);
    if (Number.isNaN(target)) return;
    const decimals = (expression.split('.')[1] || '').length;
    const fmt = (v) => v.toLocaleString('ru-RU', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    el.textContent = fmt(target); // реальное значение сразу (SEO, без JS, скриншоты)
    if (reduced) return;
    new IntersectionObserver(([e], obs) => {
        if (!e.isIntersecting) return;
        obs.disconnect();
        const start = performance.now();
        const dur = 1600;
        const tick = (t) => {
            const p = Math.min(1, (t - start) / dur);
            el.textContent = fmt(target * (1 - Math.pow(1 - p, 4)));
            if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }, { threshold: 0.4 }).observe(el);
});

// A36 · Горизонтальная лента (чипы сортировки, вкладки): без системного скроллбара,
// затухание по краю, где есть что листать, стрелки на устройствах с мышью. Разметка — partials/hscroll.
Alpine.data('hscroll', () => ({
    start: false,
    end: false,
    init() {
        const t = this.$refs.track;
        this.update();
        new ResizeObserver(() => this.update()).observe(t);
        // активный чип — в видимую область
        const active = t.querySelector('.is-active');
        if (active && t.scrollWidth > t.clientWidth) t.scrollLeft = active.offsetLeft - t.clientWidth / 2 + active.offsetWidth / 2;
        // колесо мыши листает ленту по горизонтали
        t.addEventListener('wheel', (e) => {
            if (Math.abs(e.deltaY) <= Math.abs(e.deltaX) || t.scrollWidth <= t.clientWidth) return;
            const max = t.scrollWidth - t.clientWidth;
            if ((e.deltaY < 0 && t.scrollLeft <= 0) || (e.deltaY > 0 && t.scrollLeft >= max)) return;
            e.preventDefault();
            t.scrollLeft += e.deltaY;
        }, { passive: false });
    },
    update() {
        const t = this.$refs.track;
        this.start = t.scrollLeft > 2;
        this.end = t.scrollLeft + t.clientWidth < t.scrollWidth - 2;
    },
    by(dir) {
        const t = this.$refs.track;
        t.scrollBy({ left: dir * t.clientWidth * 0.7, behavior: reduced ? 'auto' : 'smooth' });
    },
}));

// Калькулятор дохода по вкладу (простой/капитализация ежемесячно)
Alpine.data('calc', (rate = 16) => ({
    amount: 1_000_000,
    months: 12,
    rate,
    cap: true,
    get income() {
        const r = this.rate / 100;
        return this.cap
            ? this.amount * (Math.pow(1 + r / 12, this.months) - 1)
            : this.amount * r * (this.months / 12);
    },
    money(v) { return money(v); },
}));

// A14 · Доход по вкладу: по ГЭСВ (она уже учитывает капитализацию), иначе номинал с ежемесячной капитализацией.
// Та же формула — app/Services/Deposits/Income.php
export function depositIncome(amount, months, rate, effective = null, cap = true) {
    if (amount <= 0 || months <= 0) return 0;
    if (effective) return amount * (Math.pow(1 + effective / 100, months / 12) - 1);
    return cap ? amount * (Math.pow(1 + rate / 100 / 12, months) - 1) : amount * (rate / 100) * (months / 12);
}
// A21 · знак валюты страны — из <html data-currency>
// A36 · неразрывный пробел перед знаком валюты (как money_format() на сервере): «₸» не уезжает на новую строку
const money = (v) => Math.round(v).toLocaleString('ru-RU') + '\u00A0' + (document.documentElement.dataset.currency || '₸');
const parseMoney = (s) => +(String(s).replace(/\D/g, '')) || 0;

// A14 · Список сравнения (до 4 вкладов) — в localStorage, общий для всех страниц.
// A33 · Фабрика: $store.compare — вклады, $store.cardCompare — карты (свой ключ и адрес сравнения).
const compareStore = (key, path) => ({
    limit: 4,
    items: (() => { try { return JSON.parse(localStorage.getItem(key)) || []; } catch { return []; } })(),
    has(id) { return this.items.some((i) => i.id === id); },
    toggle(item) {
        if (this.has(item.id)) this.items = this.items.filter((i) => i.id !== item.id);
        else if (this.items.length < this.limit) this.items = [...this.items, item];
        else { this.full = true; setTimeout(() => (this.full = false), 2200); return; }
        this.save();
    },
    remove(id) { this.items = this.items.filter((i) => i.id !== id); this.save(); },
    clear() { this.items = []; this.save(); },
    set(items) { this.items = items.slice(0, this.limit); this.save(); },
    save() { try { localStorage.setItem(key, JSON.stringify(this.items)); } catch {} },
    url() { return path + '?ids=' + this.items.map((i) => i.id).join(','); },
    full: false,
});
Alpine.store('compare', compareStore('banq.compare', '/deposits/compare'));
Alpine.store('cardCompare', compareStore('banq.cards.compare', '/cards/compare'));

// A33 · x-tilt — объёмный наклон карты за курсором (+ блик через --mx/--my). Уважает reduced-motion и тач.
Alpine.directive('tilt', (el) => {
    if (reduced || !window.matchMedia('(hover: hover)').matches) return;
    const max = parseFloat(el.dataset.tilt || '10');
    el.addEventListener('pointermove', (e) => {
        const r = el.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width;
        const y = (e.clientY - r.top) / r.height;
        el.style.setProperty('--ry', `${(x - 0.5) * max}deg`);
        el.style.setProperty('--rx', `${(0.5 - y) * max}deg`);
        el.style.setProperty('--mx', `${x * 100}%`);
        el.style.setProperty('--my', `${y * 100}%`);
    });
    el.addEventListener('pointerleave', () => ['--rx', '--ry', '--mx', '--my'].forEach((p) => el.style.removeProperty(p)));
});

// A14 · Фильтры каталога: любое изменение → fetch частичного HTML (X-Partial) → подмена списка + URL.
// Без JS форма работает обычным GET-сабмитом. A33: общий для вкладов и карт — target (id списка) и skip (значения по умолчанию, их не пишем в URL).
const catalogFilters = (target, skip = {}) => ({
    loading: false,
    open: false,
    timer: null,
    ctrl: null,
    changed(delay = 0) {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.load(), delay);
    },
    async load() {
        const form = this.$refs.form;
        const params = new URLSearchParams();
        for (const [k, v] of new FormData(form)) {
            const val = k === 'amount' ? String(parseMoney(v) || '') : v;
            if (val !== '' && skip[k] !== val) params.append(k, val);
        }
        const url = form.action + (params.toString() ? '?' + params : '');
        this.ctrl?.abort();
        this.ctrl = new AbortController();
        this.loading = true;
        try {
            const res = await fetch(url, { headers: { 'X-Partial': 'results' }, signal: this.ctrl.signal });
            document.getElementById(target).innerHTML = await res.text();
            history.replaceState(null, '', url);
            document.querySelectorAll(`#${target} [data-reveal]`).forEach((el) => el.classList.add('is-in'));
        } catch (e) {
            if (e.name !== 'AbortError') form.submit();
        } finally {
            this.loading = false;
        }
    },
    reset() {
        const form = this.$refs.form;
        form.querySelectorAll('input[type=checkbox]').forEach((i) => (i.checked = false));
        form.querySelectorAll('input[type=text], input[type=search]').forEach((i) => (i.value = ''));
        form.querySelectorAll('input[type=radio][data-default]').forEach((i) => (i.checked = true));
        form.querySelectorAll('select').forEach((s) => (s.selectedIndex = 0));
        this.changed();
    },
    money,
    parseMoney,
});
Alpine.data('depositFilters', () => catalogFilters('deposit-results', { currency: 'KZT', sort: 'rate' }));
Alpine.data('cardFilters', () => catalogFilters('card-results', { sort: 'score' })); // A33

// A14 · Калькулятор на карточке вклада и в сравнении
Alpine.data('depositCalc', (opts = {}) => ({
    amount: opts.amount || 1_000_000,
    months: opts.months || 12,
    rate: opts.rate || 0,
    effective: opts.effective || null,
    income(rate = this.rate, effective = this.effective) { return depositIncome(this.amount, this.months, rate, effective); },
    money,
    setAmount(e) { this.amount = parseMoney(e.target.value); },
}));

// A24 · Админка: палитра поиска ⌘K
Alpine.data('adminPalette', (endpoint) => ({
    open: false, q: '', items: [], active: 0, loading: false, ctrl: null,
    show() { this.open = true; this.$nextTick(() => this.$refs.q.focus()); },
    async search() {
        if (this.q.trim().length < 2) { this.items = []; return; }
        this.ctrl?.abort(); this.ctrl = new AbortController(); this.loading = true;
        try {
            const r = await fetch(`${endpoint}?q=${encodeURIComponent(this.q)}`, { headers: { Accept: 'application/json' }, signal: this.ctrl.signal });
            this.items = await r.json(); this.active = 0;
        } catch (e) { /* отменён — пришёл новый запрос */ } finally { this.loading = false; }
    },
    move(d) { if (this.items.length) this.active = (this.active + d + this.items.length) % this.items.length; },
    go() { const it = this.items[this.active]; if (it) location.href = it.url; },
}));

// A24 · Админка: выбор строк для массовых действий (+ «все по фильтру»)
Alpine.data('bulkSelect', (total = 0) => ({
    ids: [], all: false, total,
    get count() { return this.all ? this.total : this.ids.length; },
    toggleAll(e) {
        const boxes = [...this.$root.querySelectorAll('input[data-row]')];
        this.ids = e.target.checked ? boxes.map((b) => b.value) : [];
        if (!e.target.checked) this.all = false;
    },
    clear() { this.ids = []; this.all = false; },
    confirmRun(e) {
        const opt = e.target.querySelector('select[name=action]')?.selectedOptions[0];
        if (opt?.dataset.confirm && !window.confirm(opt.dataset.confirm)) e.preventDefault();
    },
}));

// A40 · Конвертер в карточке курсов: rates — курс за 1 единицу в нацвалюте
Alpine.data('fxConvert', (rates = {}, symbol = '₸') => ({
    rates, symbol, amount: 100, code: Object.keys(rates)[0] || 'USD', reverse: false,
    get result() {
        const r = this.rates[this.code] || 0;
        return this.reverse ? (r ? this.amount / r : 0) : this.amount * r;
    },
    parse(v) { return parseFloat(String(v).replace(/\s/g, '').replace(',', '.')) || 0; },
    fmt(v, d = 2) { return (+v).toLocaleString('ru-RU', { minimumFractionDigits: d, maximumFractionDigits: d }); },
}));

// A28 · Избранное: переключение без перезагрузки (FavoriteController отвечает JSON), без JS — обычная форма
Alpine.data('favorite', (on = false) => ({
    on,
    busy: false,
    async toggle(form) {
        if (this.busy) return;
        this.busy = true;
        this.on = !this.on; // оптимистично
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error(res.status);
            this.on = (await res.json()).on;
        } catch {
            this.on = !this.on;
            form.submit();
        } finally {
            this.busy = false;
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();

// Появление блоков
const io = new IntersectionObserver((entries) => entries.forEach((e) => {
    if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
}), { threshold: 0.12 });
document.querySelectorAll('[data-reveal]').forEach((el) => io.observe(el));

// Подсветка граней за курсором (v1, на карточках .glow-edge)
document.addEventListener('pointermove', (e) => {
    const card = e.target.closest?.('.glow-edge');
    if (!card) return;
    const r = card.getBoundingClientRect();
    card.style.setProperty('--mx', `${e.clientX - r.left}px`);
    card.style.setProperty('--my', `${e.clientY - r.top}px`);
});
