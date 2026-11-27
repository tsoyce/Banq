// A39 · Табличный редактор «как Excel» для админки: /admin/exchange/grid/{entity}.
// Ячейки, выделение мышью и Shift+стрелками, правка с клавиатуры, Ctrl+C / Ctrl+X / Ctrl+V (в том числе
// диапазоны из Excel и Google Таблиц, с заголовками или без), отмена и повтор, вставка и удаление строк,
// заполнение вниз, поиск и сортировка, ширина колонок. Сохраняет через движок обмена данными:
// изменённые строки уходят с _id, удалённые — с _delete, сначала можно проверить (dry-run).
// Отрисовываются только видимые строки, поэтому тысячи ставок не тормозят.

const ROW_H = 32;
const HEAD_H = 44;
const NUM_W = 52;
const OVERSCAN = 12;

const WIDTH = { int: 96, decimal: 104, bool: 84, date: 120, enum: 132, ref: 132, string: 180, text: 280, list: 220, json: 240 };

const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const headerKey = (h) => String(h ?? '').trim().toLowerCase().replace(/[\s\-_.()«»"']+/g, '');
const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

/** Значение из выгрузки → текст ячейки. */
function toCell(v) {
    if (v === null || v === undefined) return '';
    if (typeof v === 'boolean') return v ? 'да' : 'нет';
    if (Array.isArray(v)) return v.every((x) => x === null || typeof x !== 'object') ? v.join('|') : JSON.stringify(v);
    if (typeof v === 'object') return JSON.stringify(v);
    return String(v);
}

const toNumber = (s) => {
    const t = String(s).replace(/[\s  %₸$€]/g, '').replace(',', '.');
    return t !== '' && !Number.isNaN(Number(t)) ? Number(t) : null;
};

/** TSV из буфера Excel/Google Таблиц: кавычки, переводы строк внутри ячеек, \r\n. */
export function parseTsv(text) {
    const rows = [];
    let row = [];
    let cell = '';
    let quoted = false;
    const s = text.replace(/\r\n?/g, '\n');
    for (let i = 0; i < s.length; i++) {
        const ch = s[i];
        if (quoted) {
            if (ch === '"' && s[i + 1] === '"') { cell += '"'; i++; }
            else if (ch === '"') quoted = false;
            else cell += ch;
        } else if (ch === '"' && cell === '') quoted = true;
        else if (ch === '\t') { row.push(cell); cell = ''; }
        else if (ch === '\n') { row.push(cell); rows.push(row); row = []; cell = ''; }
        else cell += ch;
    }
    if (cell !== '' || row.length) { row.push(cell); rows.push(row); }
    return rows;
}

const tsvCell = (v) => (/[\t\n"]/.test(v) ? `"${v.replace(/"/g, '""')}"` : v);

const colName = (i) => {
    let s = '';
    for (i++; i > 0; i = Math.floor((i - 1) / 26)) s = String.fromCharCode(65 + ((i - 1) % 26)) + s;
    return s;
};

let uid = 0;

class Grid {
    constructor(root, opts) {
        this.root = root;
        this.opts = opts;
        this.allCols = opts.columns;
        this.hidden = new Set(this.load('hidden', []));
        this.widths = this.load('widths', {});
        this.rows = [];
        this.view = [];
        this.sel = { r: 0, c: 0, r2: 0, c2: 0 }; // r/c — активная ячейка, r2/c2 — второй угол
        this.undoStack = [];
        this.redoStack = [];
        this.editing = null;
        this.filter = '';
        this.sort = null; // {key, dir}
        this.dragging = false;
        this.build();
    }

    // ── хранение мелочей (ширина, скрытые колонки) ─────────────
    load(k, def) {
        try { return JSON.parse(localStorage.getItem(`banq.sheet.${this.opts.entity}.${k}`)) ?? def; } catch { return def; }
    }

    store(k, v) {
        try { localStorage.setItem(`banq.sheet.${this.opts.entity}.${k}`, JSON.stringify(v)); } catch {}
    }

    get cols() {
        return this.allCols.filter((c) => !this.hidden.has(c.key));
    }

    width(c) {
        return this.widths[c.key] ?? WIDTH[c.type] ?? 160;
    }

    // ── разметка ────────────────────────────────────────────────
    build() {
        this.root.innerHTML = `
            <div class="sheet-scroll" tabindex="0" role="grid" aria-label="Таблица ${esc(this.opts.label)}">
                <table class="sheet-table"><colgroup></colgroup><thead></thead><tbody></tbody></table>
                <input class="sheet-editor" type="text" spellcheck="false" autocomplete="off" hidden>
                <datalist id="sheet-options-${this.opts.entity}"></datalist>
            </div>`;
        this.scroll = this.root.querySelector('.sheet-scroll');
        this.table = this.root.querySelector('table');
        this.colgroup = this.root.querySelector('colgroup');
        this.thead = this.root.querySelector('thead');
        this.tbody = this.root.querySelector('tbody');
        this.editor = this.root.querySelector('.sheet-editor');
        this.datalist = this.root.querySelector('datalist');
        this.editor.setAttribute('list', this.datalist.id);

        this.scroll.addEventListener('scroll', () => this.schedule());
        new ResizeObserver(() => this.schedule()).observe(this.scroll);
        this.scroll.addEventListener('keydown', (e) => this.onKey(e));
        this.editor.addEventListener('keydown', (e) => this.onEditorKey(e));
        this.editor.addEventListener('blur', () => { if (this.editing) this.commit(); });
        this.tbody.addEventListener('mousedown', (e) => this.onMouseDown(e));
        this.tbody.addEventListener('dblclick', (e) => { const td = e.target.closest('td'); if (td) this.startEdit(null, true); });
        this.thead.addEventListener('mousedown', (e) => this.onHeadDown(e));
        document.addEventListener('mousemove', (e) => this.onMouseMove(e));
        document.addEventListener('mouseup', () => { this.dragging = false; });
        document.addEventListener('copy', (e) => this.onCopy(e, false));
        document.addEventListener('cut', (e) => this.onCopy(e, true));
        document.addEventListener('paste', (e) => this.onPaste(e));
        this.renderHead();
    }

    active() {
        return document.activeElement === this.scroll || this.root.contains(document.activeElement) && document.activeElement !== this.editor;
    }

    renderHead() {
        const cols = this.cols;
        this.colgroup.innerHTML = `<col style="width:${NUM_W}px">` + cols.map((c) => `<col style="width:${this.width(c)}px">`).join('');
        this.table.style.width = `${NUM_W + cols.reduce((s, c) => s + this.width(c), 0)}px`;
        this.thead.innerHTML = `<tr><th class="sheet-corner" data-all title="Выделить всё (${isMac ? '⌘' : 'Ctrl'}+A)"></th>${cols.map((c, i) => {
            const sorted = this.sort?.key === c.key ? (this.sort.dir > 0 ? '↑' : '↓') : '';
            return `<th data-c="${i}" title="${esc([c.hint, c.values ? `варианты: ${c.values.join(', ')}` : '', c.example ? `пример: ${c.example}` : ''].filter(Boolean).join('\n'))}">
                <span class="sheet-letter">${colName(i)}</span>
                <span class="sheet-name">${esc(c.key)}${c.required ? '<b>*</b>' : ''}</span>
                ${c.ru ? `<span class="sheet-ru">${esc(c.ru)}</span>` : ''}
                <button type="button" class="sheet-sort${sorted ? ' is-on' : ''}" data-sort="${i}" aria-label="Сортировать">${sorted || '⇅'}</button>
                <span class="sheet-resize" data-resize="${i}"></span>
            </th>`;
        }).join('')}</tr>`;
        this.offsets = [NUM_W];
        cols.forEach((c) => this.offsets.push(this.offsets[this.offsets.length - 1] + this.width(c)));
        this.render(true);
    }

    schedule() {
        if (this.raf) return;
        this.raf = requestAnimationFrame(() => { this.raf = null; this.render(); });
    }

    render(force = false) {
        const total = this.view.length;
        const h = this.scroll.clientHeight || 600;
        const first = Math.max(0, Math.floor((this.scroll.scrollTop - HEAD_H) / ROW_H) - OVERSCAN);
        const last = Math.min(total, first + Math.ceil(h / ROW_H) + OVERSCAN * 2);
        if (!force && first === this.first && last === this.last) return;
        this.first = first;
        this.last = last;

        const cols = this.cols;
        const { r1, r2, c1, c2 } = this.range();
        let html = `<tr class="sheet-pad" style="height:${first * ROW_H}px"></tr>`;
        for (let i = first; i < last; i++) {
            const row = this.view[i];
            const state = row.deleted ? 'is-del' : row.id ? (this.isDirty(row) ? 'is-changed' : '') : 'is-new';
            html += `<tr class="${state}${row.error ? ' is-error' : ''}" data-r="${i}">`;
            html += `<th class="sheet-num${i >= r1 && i <= r2 ? ' is-hl' : ''}" data-r="${i}" title="${esc(row.error || (row.id ? `id ${row.id}` : 'новая строка'))}">${row.error ? '!' : i + 1}</th>`;
            cols.forEach((c, j) => {
                const v = row.cells[c.key] ?? '';
                const cls = ['sheet-cell', `t-${c.type}`];
                if (i >= r1 && i <= r2 && j >= c1 && j <= c2) cls.push('is-sel');
                if (i === this.sel.r && j === this.sel.c) cls.push('is-focus');
                if (row.id && !row.deleted && v !== (row.orig[c.key] ?? '')) cls.push('is-dirty');
                if ((!row.id || this.isDirty(row)) && !row.deleted && c.required && v === '') cls.push('is-missing');
                if (v !== '' && (c.type === 'int' || c.type === 'decimal') && toNumber(v) === null) cls.push('is-invalid');
                html += `<td class="${cls.join(' ')}" data-c="${j}">${esc(v.length > 300 ? v.slice(0, 300) + '…' : v)}</td>`;
            });
            html += '</tr>';
        }
        html += `<tr class="sheet-pad" style="height:${Math.max(0, total - last) * ROW_H}px"></tr>`;
        if (!total) html = `<tr><td class="sheet-empty" colspan="${cols.length + 1}">${this.filter ? 'Ничего не нашлось' : 'Строк пока нет — вставьте диапазон из Excel (Ctrl+V) или добавьте строку'}</td></tr>`;
        this.tbody.innerHTML = html;
        this.thead.querySelectorAll('th[data-c]').forEach((th) => th.classList.toggle('is-hl', +th.dataset.c >= c1 && +th.dataset.c <= c2));
        this.opts.onChange?.(this);
    }

    // ── данные ──────────────────────────────────────────────────
    setRows(list) {
        this.rows = list.map((r) => {
            const cells = {};
            this.allCols.forEach((c) => { cells[c.key] = toCell(r[c.key]); });
            return { uid: ++uid, id: r._id ?? null, cells, orig: { ...cells }, deleted: false, error: null };
        });
        this.undoStack = [];
        this.redoStack = [];
        this.refresh();
    }

    isDirty(row) {
        return this.allCols.some((c) => (row.cells[c.key] ?? '') !== (row.orig[c.key] ?? ''));
    }

    newRow(cells = {}) {
        const blank = {};
        this.allCols.forEach((c) => { blank[c.key] = ''; });
        return { uid: ++uid, id: null, cells: { ...blank, ...this.opts.defaults?.(), ...cells }, orig: {}, deleted: false, error: null };
    }

    /** Пересобрать видимый порядок: поиск + сортировка. Новые строки при сортировке остаются в конце. */
    refresh() {
        const q = this.filter.trim().toLowerCase();
        let v = q ? this.rows.filter((r) => !r.id || Object.values(r.cells).some((x) => x.toLowerCase().includes(q))) : [...this.rows];
        if (this.sort) {
            const { key, dir } = this.sort;
            v.sort((a, b) => (!a.id - !b.id) || dir * String(a.cells[key]).localeCompare(String(b.cells[key]), 'ru', { numeric: true, sensitivity: 'base' }));
        }
        this.view = v;
        this.clamp();
        this.render(true);
    }

    clamp() {
        const maxR = Math.max(0, this.view.length - 1);
        const maxC = Math.max(0, this.cols.length - 1);
        const s = this.sel;
        s.r = Math.min(Math.max(0, s.r), maxR); s.r2 = Math.min(Math.max(0, s.r2), maxR);
        s.c = Math.min(Math.max(0, s.c), maxC); s.c2 = Math.min(Math.max(0, s.c2), maxC);
    }

    range() {
        const s = this.sel;
        return { r1: Math.min(s.r, s.r2), r2: Math.max(s.r, s.r2), c1: Math.min(s.c, s.c2), c2: Math.max(s.c, s.c2) };
    }

    stats() {
        const rows = this.rows;
        return {
            total: rows.filter((r) => r.id).length,
            changed: rows.filter((r) => r.id && !r.deleted && this.isDirty(r)).length,
            added: rows.filter((r) => !r.id && this.hasData(r)).length,
            deleted: rows.filter((r) => r.deleted).length,
            errors: rows.filter((r) => r.error).length,
        };
    }

    hasData(row) {
        const defaults = this.opts.defaults?.() ?? {};
        return Object.entries(row.cells).some(([k, v]) => v !== '' && v !== (defaults[k] ?? ''));
    }

    /** Строки к отправке (в порядке rows) — для сопоставления ответа сервера. */
    payload() {
        const out = [];
        const refs = [];
        for (const row of this.rows) {
            const values = {};
            for (const [k, v] of Object.entries(row.cells)) if (v !== '' || row.id) values[k] = v;
            if (row.deleted) { out.push({ _id: row.id, _delete: true, ...values }); refs.push(row); }
            else if (!row.id && this.hasData(row)) { out.push(values); refs.push(row); }
            else if (row.id && this.isDirty(row)) { out.push({ _id: row.id, ...values }); refs.push(row); }
        }
        return { rows: out, refs };
    }

    selectionInfo() {
        const { r1, r2, c1, c2 } = this.range();
        const cols = this.cols;
        if (!this.view.length || !cols.length) return '';
        const addr = `${colName(c1)}${r1 + 1}` + (r1 !== r2 || c1 !== c2 ? `:${colName(c2)}${r2 + 1}` : '');
        const n = (r2 - r1 + 1) * (c2 - c1 + 1);
        let sum = 0;
        let nums = 0;
        if (n > 1 && n < 50000) {
            for (let i = r1; i <= r2; i++) for (let j = c1; j <= c2; j++) {
                const x = toNumber(this.view[i]?.cells[cols[j].key] ?? '');
                if (x !== null && ['int', 'decimal'].includes(cols[j].type)) { sum += x; nums++; }
            }
        }
        const fmt = (x) => x.toLocaleString('ru-RU', { maximumFractionDigits: 3 });
        return [addr, n > 1 ? `${n.toLocaleString('ru-RU')} яч.` : cols[c1].key, nums > 1 ? `сумма ${fmt(sum)} · среднее ${fmt(sum / nums)}` : ''].filter(Boolean).join(' · ');
    }

    // ── правки с отменой ───────────────────────────────────────
    /** Применить набор действий как одну операцию (одна отмена). */
    apply(actions, label) {
        if (!actions.length) return;
        actions.forEach((a) => this.doAction(a, false));
        this.undoStack.push({ actions, label });
        if (this.undoStack.length > 200) this.undoStack.shift();
        this.redoStack = [];
        this.rows.forEach((r) => { if (actions.some((a) => a.row === r)) r.error = null; });
        this.refresh();
    }

    doAction(a, reverse) {
        switch (a.t) {
            case 'set': a.row.cells[a.key] = reverse ? a.a : a.b; break;
            case 'del': a.row.deleted = reverse ? a.a : a.b; break;
            case 'add': reverse ? this.rows.splice(this.rows.indexOf(a.row), 1) : this.rows.splice(Math.min(a.index, this.rows.length), 0, a.row); break;
            case 'rm': reverse ? this.rows.splice(Math.min(a.index, this.rows.length), 0, a.row) : this.rows.splice(this.rows.indexOf(a.row), 1); break;
        }
    }

    undo() {
        const op = this.undoStack.pop();
        if (!op) return this.opts.toast?.('Отменять нечего');
        [...op.actions].reverse().forEach((a) => this.doAction(a, true));
        this.redoStack.push(op);
        this.refresh();
        this.opts.toast?.(`Отменено: ${op.label}`);
    }

    redo() {
        const op = this.redoStack.pop();
        if (!op) return this.opts.toast?.('Возвращать нечего');
        op.actions.forEach((a) => this.doAction(a, false));
        this.undoStack.push(op);
        this.refresh();
        this.opts.toast?.(`Возвращено: ${op.label}`);
    }

    setCells(changes, label) {
        const actions = [];
        for (const [row, key, value] of changes) {
            const before = row.cells[key] ?? '';
            if (before !== value) actions.push({ t: 'set', row, key, a: before, b: value });
        }
        this.apply(actions, label);
    }

    clearRange() {
        const { r1, r2, c1, c2 } = this.range();
        const ch = [];
        for (let i = r1; i <= r2; i++) for (let j = c1; j <= c2; j++) ch.push([this.view[i], this.cols[j].key, '']);
        this.setCells(ch.filter(([r]) => r && !r.deleted), 'очистка');
    }

    fillDown() {
        const { r1, r2, c1, c2 } = this.range();
        if (r1 === r2) return this.opts.toast?.('Выделите диапазон: первая строка — образец, ниже — куда копировать');
        const ch = [];
        for (let j = c1; j <= c2; j++) {
            const key = this.cols[j].key;
            for (let i = r1 + 1; i <= r2; i++) ch.push([this.view[i], key, this.view[r1].cells[key]]);
        }
        this.setCells(ch, 'заполнить вниз');
    }

    insertRows(below = true) {
        const { r1, r2 } = this.range();
        const n = this.view.length ? r2 - r1 + 1 : 1;
        const anchor = this.view[below ? r2 : r1];
        let index = anchor ? this.rows.indexOf(anchor) + (below ? 1 : 0) : this.rows.length;
        const actions = [];
        for (let k = 0; k < n; k++) actions.push({ t: 'add', row: this.newRow(), index: index++ });
        this.sort = null;
        this.apply(actions, n > 1 ? `${n} новых строк` : 'новая строка');
        const pos = this.view.indexOf(actions[0].row);
        this.select(pos, 0);
        this.renderHead();
    }

    deleteRows() {
        const { r1, r2 } = this.range();
        const actions = [];
        const targets = this.view.slice(r1, r2 + 1);
        const undelete = targets.every((r) => !r.id || r.deleted) && targets.some((r) => r.deleted);
        for (const row of targets) {
            if (!row.id) actions.push({ t: 'rm', row, index: this.rows.indexOf(row) });
            else actions.push({ t: 'del', row, a: row.deleted, b: !undelete });
        }
        this.apply(actions, undelete ? 'вернуть строки' : 'удаление строк');
        if (!undelete) this.opts.toast?.(`К удалению: ${targets.length}. Запишется при сохранении${this.opts.entity === 'deposit_rates' ? ' — ставки закроются, история останется' : ''}. Отменить — ${isMac ? '⌘' : 'Ctrl'}+Z`);
    }

    // ── выделение и прокрутка ─────────────────────────────────
    select(r, c, extend = false) {
        const s = this.sel;
        if (extend) { s.r2 = r; s.c2 = c; } else { s.r = s.r2 = r; s.c = s.c2 = c; }
        this.clamp();
        this.reveal(extend ? s.r2 : s.r, extend ? s.c2 : s.c);
        this.render(true);
    }

    reveal(r, c) {
        const sc = this.scroll;
        const top = HEAD_H + r * ROW_H;
        if (top - HEAD_H < sc.scrollTop) sc.scrollTop = top - HEAD_H;
        else if (top + ROW_H > sc.scrollTop + sc.clientHeight) sc.scrollTop = top + ROW_H - sc.clientHeight;
        const left = this.offsets[c];
        const right = this.offsets[c + 1];
        if (left - NUM_W < sc.scrollLeft) sc.scrollLeft = left - NUM_W;
        else if (right > sc.scrollLeft + sc.clientWidth) sc.scrollLeft = right - sc.clientWidth;
    }

    cellAt(e) {
        const td = e.target.closest?.('td[data-c], th.sheet-num');
        if (!td || !this.tbody.contains(td)) return null;
        const r = +td.closest('tr').dataset.r;
        return Number.isNaN(r) ? null : { r, c: td.dataset.c !== undefined ? +td.dataset.c : null };
    }

    onMouseDown(e) {
        if (e.button !== 0) return;
        const hit = this.cellAt(e);
        if (!hit) return;
        if (this.editing) this.commit();
        e.preventDefault();
        this.scroll.focus({ preventScroll: true });
        if (hit.c === null) { // номер строки — вся строка
            if (e.shiftKey) { this.sel.r2 = hit.r; } else { this.sel.r = this.sel.r2 = hit.r; }
            this.sel.c = 0; this.sel.c2 = this.cols.length - 1;
            this.dragging = 'row';
            return this.render(true);
        }
        this.select(hit.r, hit.c, e.shiftKey);
        this.dragging = 'cell';
    }

    onMouseMove(e) {
        if (!this.dragging) return;
        const hit = this.cellAt(e);
        if (!hit) return;
        if (this.dragging === 'row') { this.sel.r2 = hit.r; return this.render(true); }
        if (hit.c !== null && (hit.r !== this.sel.r2 || hit.c !== this.sel.c2)) { this.sel.r2 = hit.r; this.sel.c2 = hit.c; this.render(true); }
    }

    onHeadDown(e) {
        const resize = e.target.closest('[data-resize]');
        if (resize) return this.startResize(e, +resize.dataset.resize);
        const sort = e.target.closest('[data-sort]');
        if (sort) {
            const key = this.cols[+sort.dataset.sort].key;
            this.sort = this.sort?.key === key ? (this.sort.dir > 0 ? { key, dir: -1 } : null) : { key, dir: 1 };
            this.renderHead();
            return this.refresh();
        }
        if (e.target.closest('[data-all]')) return this.selectAll();
        const th = e.target.closest('th[data-c]');
        if (!th) return;
        e.preventDefault();
        this.scroll.focus({ preventScroll: true });
        const c = +th.dataset.c;
        if (e.shiftKey) this.sel.c2 = c; else { this.sel.c = this.sel.c2 = c; }
        this.sel.r = 0; this.sel.r2 = Math.max(0, this.view.length - 1);
        this.render(true);
    }

    startResize(e, i) {
        e.preventDefault();
        const col = this.cols[i];
        const x0 = e.clientX;
        const w0 = this.width(col);
        const move = (ev) => { this.widths[col.key] = Math.max(56, Math.min(800, w0 + ev.clientX - x0)); this.renderHead(); };
        const up = () => { document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); this.store('widths', this.widths); };
        document.addEventListener('mousemove', move);
        document.addEventListener('mouseup', up);
    }

    selectAll() {
        this.sel = { r: 0, c: 0, r2: Math.max(0, this.view.length - 1), c2: Math.max(0, this.cols.length - 1) };
        this.render(true);
    }

    // ── клавиатура ─────────────────────────────────────────────
    onKey(e) {
        if (this.editing) return;
        const mod = isMac ? e.metaKey : e.ctrlKey;
        const s = this.sel;
        const k = e.key;
        const maxR = this.view.length - 1;
        const maxC = this.cols.length - 1;
        const move = (dr, dc) => {
            const ext = e.shiftKey;
            let r = ext ? s.r2 : s.r;
            let c = ext ? s.c2 : s.c;
            if (mod) { // к краю данных
                if (dr) r = dr > 0 ? maxR : 0;
                if (dc) c = dc > 0 ? maxC : 0;
            } else { r += dr; c += dc; }
            if (!ext && dr > 0 && r > maxR && !mod) { // ниже последней строки — новая строка
                const last = this.view[maxR];
                if (last && (last.id || this.hasData(last))) { this.insertRows(true); return; }
            }
            this.select(Math.min(Math.max(r, 0), maxR), Math.min(Math.max(c, 0), maxC), ext);
        };

        if (mod && !e.altKey) {
            const key = k.toLowerCase();
            if (key === 'z' && !e.shiftKey) { e.preventDefault(); return this.undo(); }
            if (key === 'y' || (key === 'z' && e.shiftKey)) { e.preventDefault(); return this.redo(); }
            if (key === 'a') { e.preventDefault(); return this.selectAll(); }
            if (key === 'd') { e.preventDefault(); return this.fillDown(); }
            if (key === 's') { e.preventDefault(); return this.opts.onSave?.(e.shiftKey); }
            if (key === 'f') { e.preventDefault(); return this.opts.onFind?.(); }
            if (k === '+' || k === '=' ) { e.preventDefault(); return this.insertRows(!e.altKey); }
            if (k === '-' || k === '_') { e.preventDefault(); return this.deleteRows(); }
            if (k === 'Enter') { e.preventDefault(); return this.insertRows(true); }
            if (k === 'Home') { e.preventDefault(); return this.select(0, 0, e.shiftKey); }
            if (k === 'End') { e.preventDefault(); return this.select(maxR, maxC, e.shiftKey); }
        }
        switch (k) {
            case 'ArrowDown': e.preventDefault(); return move(1, 0);
            case 'ArrowUp': e.preventDefault(); return move(-1, 0);
            case 'ArrowRight': e.preventDefault(); return move(0, 1);
            case 'ArrowLeft': e.preventDefault(); return move(0, -1);
            case 'Tab': e.preventDefault(); return this.select(s.r, Math.min(Math.max(s.c + (e.shiftKey ? -1 : 1), 0), maxC));
            case 'Enter': e.preventDefault(); return e.shiftKey ? move(-1, 0) : this.startEdit(null, true);
            case 'F2': e.preventDefault(); return this.startEdit(null, true);
            case 'Home': e.preventDefault(); return this.select(s.r, 0, e.shiftKey);
            case 'End': e.preventDefault(); return this.select(s.r, maxC, e.shiftKey);
            case 'PageDown': e.preventDefault(); return this.select(Math.min(maxR, s.r + Math.floor(this.scroll.clientHeight / ROW_H)), s.c, e.shiftKey);
            case 'PageUp': e.preventDefault(); return this.select(Math.max(0, s.r - Math.floor(this.scroll.clientHeight / ROW_H)), s.c, e.shiftKey);
            case 'Delete':
            case 'Backspace': e.preventDefault(); return this.clearRange();
            case 'Escape': this.select(s.r, s.c); return;
        }
        if (k.length === 1 && !mod && !e.altKey) { // печать — заменить значение
            e.preventDefault();
            this.startEdit(k, false);
        }
    }

    startEdit(initial, keepValue) {
        const row = this.view[this.sel.r];
        const col = this.cols[this.sel.c];
        if (!row || !col) {
            if (!this.view.length && this.cols.length) { this.insertRows(true); return this.startEdit(initial, keepValue); }
            return;
        }
        if (row.deleted) return this.opts.toast?.('Строка помечена к удалению — верните её (Ctrl+−), чтобы править');
        this.sel.r2 = this.sel.r; this.sel.c2 = this.sel.c;
        this.render(true);
        this.reveal(this.sel.r, this.sel.c);
        const opts = this.opts.options?.()[col.key] ?? [];
        this.datalist.innerHTML = opts.map((o) => `<option value="${esc(o)}"></option>`).join('');
        const ed = this.editor;
        ed.hidden = false;
        ed.style.left = `${this.offsets[this.sel.c]}px`;
        ed.style.top = `${HEAD_H + this.sel.r * ROW_H}px`;
        ed.style.width = `${Math.max(this.width(col), col.type === 'json' || col.type === 'text' ? 360 : 0)}px`;
        ed.value = keepValue ? row.cells[col.key] ?? '' : initial ?? '';
        ed.placeholder = col.example ? `напр. ${col.example}` : '';
        // «ввод» (напечатали поверх) — стрелки завершают правку; «правка» (Enter/F2/двойной клик) — двигают курсор
        this.editing = { row, key: col.key, mode: keepValue ? 'edit' : 'enter' };
        ed.focus();
        const end = ed.value.length;
        ed.setSelectionRange(end, end);
    }

    commit(move = null) {
        const ed = this.editor;
        const st = this.editing;
        if (!st) return;
        this.editing = null;
        ed.hidden = true;
        const value = ed.value.trim() === '' ? '' : ed.value;
        if ((st.row.cells[st.key] ?? '') !== value) this.setCells([[st.row, st.key, value]], `ячейка ${st.key}`);
        this.scroll.focus({ preventScroll: true });
        if (move) {
            const [dr, dc] = move;
            const maxR = this.view.length - 1;
            if (dr > 0 && this.sel.r >= maxR) return this.insertRows(true);
            this.select(Math.min(Math.max(this.sel.r + dr, 0), maxR), Math.min(Math.max(this.sel.c + dc, 0), this.cols.length - 1));
        } else this.render(true);
    }

    cancel() {
        this.editing = null;
        this.editor.hidden = true;
        this.scroll.focus({ preventScroll: true });
        this.render(true);
    }

    onEditorKey(e) {
        const st = this.editing;
        if (!st) return;
        if (e.key === 'Enter') { e.preventDefault(); return this.commit(e.shiftKey ? [-1, 0] : [1, 0]); }
        if (e.key === 'Tab') { e.preventDefault(); return this.commit([0, e.shiftKey ? -1 : 1]); }
        if (e.key === 'Escape') { e.preventDefault(); return this.cancel(); }
        if (st.mode === 'enter') {
            const dirs = { ArrowDown: [1, 0], ArrowUp: [-1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1] };
            if (dirs[e.key]) { e.preventDefault(); return this.commit(dirs[e.key]); }
        }
        if (e.key === 'F2') { e.preventDefault(); st.mode = st.mode === 'edit' ? 'enter' : 'edit'; }
    }

    // ── буфер обмена ───────────────────────────────────────────
    onCopy(e, cut) {
        if (!this.active() || this.editing) return;
        const { r1, r2, c1, c2 } = this.range();
        if (!this.view.length) return;
        const cols = this.cols.slice(c1, c2 + 1);
        const rows = this.view.slice(r1, r2 + 1);
        const text = rows.map((r) => cols.map((c) => tsvCell(r.cells[c.key] ?? '')).join('\t')).join('\r\n');
        const html = `<table>${rows.map((r) => `<tr>${cols.map((c) => `<td>${esc(r.cells[c.key] ?? '')}</td>`).join('')}</tr>`).join('')}</table>`;
        e.clipboardData.setData('text/plain', text);
        e.clipboardData.setData('text/html', html);
        e.preventDefault();
        if (cut) this.clearRange();
        this.opts.toast?.(`${cut ? 'Вырезано' : 'Скопировано'}: ${rows.length} × ${cols.length}. Вставляется в Excel как есть`);
    }

    onPaste(e) {
        if (!this.active() || this.editing) return;
        const text = e.clipboardData.getData('text/plain');
        if (!text) return;
        e.preventDefault();
        this.paste(text);
    }

    /** Вставка TSV: с заголовками — колонки по именам и новые строки; без — от активной ячейки, с расширением вниз. */
    paste(text) {
        let m = parseTsv(text);
        while (m.length && m[m.length - 1].every((x) => x === '')) m.pop();
        if (!m.length) return;

        const all = this.allCols;
        const names = new Map();
        all.forEach((c) => [c.key, c.ru, ...(c.aliases ?? [])].filter(Boolean).forEach((n) => names.set(headerKey(n), c.key)));
        const head = m[0].map((h) => names.get(headerKey(h)) ?? null);
        const hits = head.filter(Boolean).length;
        const filled = m[0].filter((x) => x.trim() !== '').length;
        const actions = [];

        if (m.length > 1 && hits >= Math.max(1, Math.ceil(filled / 2)) && hits >= Math.min(2, filled)) {
            // строка заголовков: сопоставляем колонки, все строки — новые (сервер сам найдёт существующие по ключу)
            let index = this.rows.length;
            for (const line of m.slice(1)) {
                const cells = {};
                head.forEach((key, j) => { if (key) cells[key] = (line[j] ?? '').trim(); });
                actions.push({ t: 'add', row: this.newRow(cells), index: index++ });
            }
            this.sort = null;
            this.filter = '';
            this.opts.onFilterReset?.();
            this.apply(actions, `вставка ${m.length - 1} строк`);
            const skipped = m[0].filter((h, j) => h.trim() && !head[j]);
            this.opts.toast?.(`Вставлено ${m.length - 1} новых строк, колонки по заголовкам: ${hits}${skipped.length ? `. Не узнали: ${skipped.join(', ')}` : ''}. Совпадающие с базой — обновятся при сохранении`);
            this.select(this.view.indexOf(actions[0].row), 0);
            return;
        }

        const { r1, r2, c1, c2 } = this.range();
        const cols = this.cols;
        const selH = r2 - r1 + 1;
        const selW = c2 - c1 + 1;
        // одно значение на диапазон или кратный диапазон — размножаем, как Excel
        const tileR = selH > m.length && selH % m.length === 0 ? selH : m.length;
        const tileC = selW > m[0].length && selW % m[0].length === 0 ? selW : Math.max(...m.map((x) => x.length));
        const set = [];
        const base = this.rows.length;
        let added = 0;
        for (let i = 0; i < tileR; i++) {
            let row = this.view[r1 + i];
            if (!row) { // ниже последней строки — новые строки в конец
                row = this.newRow();
                actions.push({ t: 'add', row, index: base + added++ });
            }
            if (row.deleted) continue;
            for (let j = 0; j < tileC && c1 + j < cols.length; j++) {
                const v = (m[i % m.length][j % m[i % m.length].length] ?? '').trim();
                set.push([row, cols[c1 + j].key, v]);
            }
        }
        for (const [row, key, value] of set) {
            const before = row.cells[key] ?? '';
            if (before !== value) actions.push({ t: 'set', row, key, a: before, b: value });
        }
        this.sort = null;
        this.apply(actions, 'вставка');
        this.sel.r = r1; this.sel.c = c1;
        this.sel.r2 = Math.min(this.view.length - 1, r1 + tileR - 1);
        this.sel.c2 = Math.min(cols.length - 1, c1 + tileC - 1);
        this.render(true);
        this.opts.toast?.(`Вставлено ${tileR} × ${Math.min(tileC, cols.length - c1)}${added ? `, новых строк: ${added}` : ''}`);
    }

    // ── ответ сервера ──────────────────────────────────────────
    markReport(report, refs) {
        this.rows.forEach((r) => { r.error = null; });
        for (const line of report.rows ?? []) {
            const row = refs[line.row - 1];
            if (row && line.action === 'error') row.error = line.message || 'ошибка';
        }
        this.render(true);
    }

    errorRows() {
        return this.rows.filter((r) => r.error);
    }

    /** После записи — свежие строки с сервера, а строки с ошибками остаются правками поверх. */
    restore(pending) {
        const byId = new Map(this.rows.filter((r) => r.id).map((r) => [r.id, r]));
        for (const p of pending) {
            if (p.id && byId.has(p.id)) {
                const row = byId.get(p.id);
                Object.assign(row.cells, p.cells);
                row.deleted = p.deleted;
                row.error = p.error;
            } else if (!p.id) {
                const row = this.newRow(p.cells);
                row.error = p.error;
                this.rows.push(row);
            }
        }
        this.refresh();
    }

    setHidden(key, hide) {
        hide ? this.hidden.add(key) : this.hidden.delete(key);
        this.store('hidden', [...this.hidden]);
        this.clamp();
        this.renderHead();
    }

    setFilter(q) {
        this.filter = q;
        this.refresh();
    }
}

/** Alpine-обёртка: панель инструментов, фильтры, проверка и запись, подсказки. */
export function sheet(config) {
    return {
        config,
        loading: true,
        busy: false,
        country: config.country ?? '',
        bank: '',
        current: true,
        banks: [],
        options: {},
        q: '',
        info: '',
        counts: { total: 0, changed: 0, added: 0, deleted: 0, errors: 0 },
        report: null,
        toastText: '',
        help: false,
        colsOpen: false,
        canUndo: false,
        canRedo: false,
        columns: config.columns,
        hiddenKeys: [],

        init() {
            this.grid = new Grid(this.$refs.grid, {
                entity: config.entity,
                label: config.label,
                columns: config.columns,
                options: () => ({ ...this.options, bank: this.banks.map((b) => b.code) }),
                defaults: () => {
                    const keys = new Set(config.columns.map((c) => c.key));
                    const d = {};
                    if (keys.has('country') && this.country) d.country = this.country;
                    if (keys.has('bank') && this.bank) d.bank = this.bank;
                    return d;
                },
                onChange: (g) => {
                    this.counts = g.stats();
                    this.info = g.selectionInfo();
                    this.canUndo = g.undoStack.length > 0;
                    this.canRedo = g.redoStack.length > 0;
                },
                toast: (t) => this.toast(t),
                onSave: (check) => (check ? this.save(true) : this.save(false)),
                onFind: () => this.$refs.search.focus(),
                onFilterReset: () => { this.q = ''; },
            });
            this.hiddenKeys = [...this.grid.hidden];
            this.load();
            window.addEventListener('beforeunload', (e) => {
                const c = this.counts;
                if (c.changed + c.added + c.deleted > 0) { e.preventDefault(); e.returnValue = ''; }
            });
        },

        get dirty() {
            return this.counts.changed + this.counts.added + this.counts.deleted;
        },

        async load(keep = [], force = false) {
            if (!force && this.dirty && !confirm('Есть несохранённые правки — загрузить заново и потерять их?')) return;
            this.loading = true;
            const p = new URLSearchParams();
            if (this.country) p.set('country', this.country);
            if (this.bank) p.set('bank', this.bank);
            if (config.hasRates && this.current) p.set('current', '1');
            try {
                const res = await fetch(`${config.rowsUrl}?${p}`, { headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                this.banks = data.banks;
                this.options = data.options;
                this.grid.setRows(data.rows);
                if (keep.length) this.grid.restore(keep);
            } catch (e) {
                this.toast(`Не удалось загрузить строки: ${e.message}`);
            }
            this.loading = false;
            this.$nextTick(() => this.grid.scroll.focus({ preventScroll: true }));
        },

        exportHref(format) {
            const p = new URLSearchParams({ format, 'entities[]': config.entity });
            if (this.country) p.set('country', this.country);
            if (config.hasRates && this.current) p.set('current', '1');
            return `${config.exportUrl}?${p}`;
        },

        async save(dry) {
            if (this.grid.editing) this.grid.commit();
            const { rows, refs } = this.grid.payload();
            if (!rows.length) return this.toast('Нечего сохранять — правок нет');
            this.busy = true;
            try {
                const res = await fetch(config.saveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                    body: JSON.stringify({ rows, dry_run: dry, country: this.country || null }),
                });
                const data = await res.json().catch(() => ({ message: `HTTP ${res.status}` }));
                if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`);
                this.report = data;
                this.grid.markReport(data, refs);
                const t = data.totals;
                const parts = [t.created && `новых ${t.created}`, t.updated && `изменено ${t.updated}`, t.deleted && `удалено ${t.deleted}`, t.unchanged && `без изменений ${t.unchanged}`, t.skipped && `пропущено ${t.skipped}`, t.error && `ошибок ${t.error}`].filter(Boolean).join(', ');
                if (dry) {
                    this.toast(`Проверка: ${parts || 'изменений нет'}. Ничего не записано${t.error ? ' — строки с ошибками отмечены «!»' : ''}`);
                } else {
                    const pending = this.grid.errorRows().map((r) => ({ id: r.id, cells: { ...r.cells }, deleted: r.deleted, error: r.error }));
                    await this.load(pending, true);
                    this.toast(`Сохранено: ${parts}${t.error ? '. Строки с ошибками остались в таблице — наведите на «!»' : ''}`);
                }
            } catch (e) {
                this.toast(`Не сохранилось: ${e.message}`);
            }
            this.busy = false;
        },

        toggleCol(key) {
            const hide = !this.hiddenKeys.includes(key);
            this.grid.setHidden(key, hide);
            this.hiddenKeys = [...this.grid.hidden];
        },

        toast(text) {
            this.toastText = text;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => { this.toastText = ''; }, 5200);
        },
    };
}
