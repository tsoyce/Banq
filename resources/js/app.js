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
    money(v) { return Math.round(v).toLocaleString('ru-RU') + ' ₸'; },
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
