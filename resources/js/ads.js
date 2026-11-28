// A51 · Рекламные места: учёт видимых показов, «Скрыть», ленивая вставка кода сети.
// Показ засчитывается, когда ≥ 50% объявления на экране ≥ 1 с (как у IAB). Отправка — пачкой, fetch keepalive.
const ENDPOINT = '/ads/e';
const queue = new Set();
let timer = null;

function send(body) {
    try {
        fetch(ENDPOINT, { method: 'POST', keepalive: true, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) }).catch(() => {});
    } catch {}
}

function flush() {
    timer = null;
    if (!queue.size) return;
    send({ v: [...queue] });
    queue.clear();
}

function seen(token) {
    queue.add(token);
    timer ??= setTimeout(flush, 400);
}

// Вставка HTML со скриптами: innerHTML скрипты не запускает — пересоздаём их
function inject(tpl) {
    const box = tpl.parentElement;
    const frag = tpl.content.cloneNode(true);
    frag.querySelectorAll('script').forEach((old) => {
        const s = document.createElement('script');
        [...old.attributes].forEach((a) => s.setAttribute(a.name, a.value));
        s.text = old.textContent;
        old.replaceWith(s);
    });
    tpl.remove();
    box.appendChild(frag);
}

const timers = new WeakMap();
const viewObserver = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
    entries.forEach((e) => {
        const el = e.target;
        if (e.isIntersecting && e.intersectionRatio >= 0.5) {
            if (!timers.has(el)) timers.set(el, setTimeout(() => { viewObserver.unobserve(el); seen(el.dataset.ad); }, 1000));
        } else if (timers.has(el)) {
            clearTimeout(timers.get(el));
            timers.delete(el);
        }
    });
}, { threshold: [0, 0.5] }) : null;

const lazyObserver = 'IntersectionObserver' in window ? new IntersectionObserver((entries, obs) => {
    entries.forEach((e) => { if (e.isIntersecting) { obs.unobserve(e.target); inject(e.target.querySelector('template[data-ad-code]')); } });
}, { rootMargin: '400px 0px' }) : null;

export function scanAds(root = document) {
    root.querySelectorAll('.ad:not([data-ad-ready])').forEach((el) => {
        el.dataset.adReady = '1';
        if (el.dataset.ad && viewObserver) viewObserver.observe(el);
        const tpl = el.querySelector('template[data-ad-code]');
        if (tpl) lazyObserver ? lazyObserver.observe(el) : inject(tpl);
        el.querySelector('[data-ad-hide]')?.addEventListener('click', () => {
            send({ h: el.dataset.ad });
            el.classList.add('is-hiding');
            setTimeout(() => el.remove(), 260);
        });
    });
}

export default function registerAds() {
    const start = () => {
        scanAds();
        // Лента «Показать ещё» и другие подгрузки
        new MutationObserver((list) => list.forEach((m) => m.addedNodes.forEach((n) => n.nodeType === 1 && scanAds(n.parentElement || document)))).observe(document.body, { childList: true, subtree: true });
    };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flush(); });
}
