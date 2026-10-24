/**
 * Document editor (views/doc/edit.php): header + lines + totals + status actions.
 * Everything comes from <script id="doc-config"> (built by the controller), so the
 * same script serves requisitions, purchase orders and later sales documents.
 */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('doc-config').textContent);
    const text = JSON.parse(document.getElementById('doc-text').textContent);
    const form = document.getElementById('doc');
    const tbody = form.querySelector('#doc-lines tbody');
    const saveBtn = document.getElementById('doc-save');
    const addBtn = document.getElementById('doc-add-line');
    const actionsBox = document.getElementById('doc-actions');
    const statusBadge = document.getElementById('doc-status');
    const cols = cfg.lines.columns;
    const partCfg = cfg.lines.part || {};
    const amountCfg = cfg.lines.amount || null;
    const bs = window.bootstrap || window.tabler;

    const backBtn = document.createElement('a');
    backBtn.className = 'btn btn-sm'; backBtn.href = Phai.url(cfg.page); backBtn.innerHTML = `<i class="ti ti-arrow-left me-1"></i>${Phai.esc(Phai.t('back'))}`;
    form.querySelector('.phai-toolbar').prepend(backBtn);
    const editBtn = document.createElement('button');
    editBtn.type = 'button'; editBtn.className = 'btn btn-sm btn-primary';
    editBtn.innerHTML = `<i class="ti ti-pencil me-1"></i>${Phai.esc(Phai.t('edit'))}`; saveBtn.before(editBtn);
    editBtn.hidden = true;
    editBtn.addEventListener('click', () => { editable = true; editBtn.hidden = true; applyEditable(); });
    // while changing a saved document: Cancel drops the changes and shows it as saved again
    const undoBtn = document.createElement('button');
    undoBtn.type = 'button'; undoBtn.className = 'btn btn-sm'; undoBtn.dataset.cancel = '';
    undoBtn.innerHTML = `<i class="ti ti-x me-1"></i>${Phai.esc(Phai.t('cancel'))}`; saveBtn.before(undoBtn);
    undoBtn.hidden = true;
    // a saved document goes back to what is saved; a new one starts over empty
    undoBtn.addEventListener('click', () => { Phai.form.errors(form, null); if (cfg.nbr) load(); else location.reload(); });
    let seq = 0;
    let editable = cfg.canEdit && !cfg.nbr;
    let dirty = false;
    let status = null;
    const parts = new Map();

    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const plain = (v) => (v === null || v === undefined || v === '' ? '' : String(Number(v)));
    const head = (name) => form.elements[name];

    // ------------------------------------------------------------ lookups

    async function findPart(code) {
        code = (code || '').trim().toUpperCase();
        if (!code) return null;
        if (!parts.has(code)) {
            (await Phai.api.get('/api/stock/parts', { q: code })).forEach((p) => parts.set(p.pt_part, p));
        }
        return parts.get(code) || null;
    }

    Phai.pick.source('uoms', { items: cfg.uoms || [] });
    // Vendors and customers ("parties") work the same way: picked in a select box, then defaults.
    const PARTY = {
        vendor: { api: '/api/purchase/vendors', code: 'vd_code', name: 'vd_name' },
        customer: { api: '/api/sales/customers', code: 'cm_code', name: 'cm_name' },
    };
    const partyCache = { vendor: new Map(), customer: new Map() };

    async function findParty(type, code) {
        code = (code || '').trim().toUpperCase();
        if (!code) return null;
        const p = PARTY[type];
        if (!partyCache[type].has(code)) {
            (await Phai.api.get(p.api, { q: code })).forEach((r) => partyCache[type].set(r[p.code], r));
        }
        return partyCache[type].get(code) || null;
    }

    // ------------------------------------------------------------ lines

    function cellHtml(c, i) {
        const name = `lines.${i}.${c.name}`;
        switch (c.type) {
            case 'part': // an item, or text for something not in the system
                return `<input class="form-control form-control-sm text-uppercase" name="${name}" autocomplete="off" data-part data-pick="parts" data-free${partCfg.sale ? ' data-sale' : ''}>`;
            case 'vendor':
                return `<input class="form-control form-control-sm text-uppercase" name="${name}" autocomplete="off" data-vendor data-pick="vendors">`;
            case 'um':
                return `<input class="form-control form-control-sm text-uppercase" name="${name}" autocomplete="off" data-pick="uoms">`;
            case 'number': // c.readonly: shown and sent, not typed (e.g. the quantity shipped on an invoice)
                return `<input class="form-control form-control-sm text-end" name="${name}" inputmode="decimal" autocomplete="off"${c.readonly ? ' readonly tabindex="-1"' : ''}>`;
            case 'date':
                return `<input type="date" class="form-control form-control-sm" name="${name}">`;
            case 'amount':
                return `<span class="d-block text-end" data-cell="${c.name}"></span>`;
            case 'static':
                return `<span class="d-block${c.number ? ' text-end' : ''} text-secondary" data-cell="${c.name}"></span>`;
            default:
                return `<input class="form-control form-control-sm" name="${name}" autocomplete="off">`;
        }
    }

    function addRow(row, focus) {
        const i = seq++;
        const tr = document.createElement('tr');
        tr.dataset.i = i;
        tr.innerHTML = '<td class="text-secondary small" data-n></td>'
            + cols.map((c) => `<td${c.existing ? ' data-existing' : ''}>${cellHtml(c, i)}</td>`).join('')
            + '<td><button type="button" class="btn btn-sm btn-icon btn-ghost-secondary" data-remove tabindex="-1"><i class="ti ti-x"></i></button></td>';
        tbody.appendChild(tr);
        for (const c of cols) {
            const v = row ? row[c.name] : (c.default ?? '');
            const input = tr.querySelector(`[name="lines.${i}.${c.name}"]`);
            if (input) input.value = c.type === 'number' ? plain(v) : (v ?? '');
            const span = tr.querySelector(`[data-cell="${c.name}"]`);
            if (span && c.type === 'static') setStatic(span, c, v);
        }
        renumber();
        lineAmount(tr);
        applyEditable();
        if (focus) tr.querySelector('input')?.focus();
        return tr;
    }

    function setStatic(span, c, v) {
        if (c.link && v) {
            span.innerHTML = `<a href="${Phai.esc(Phai.url(c.link + encodeURIComponent(v)))}">${Phai.esc(v)}</a>`;
        } else {
            span.textContent = c.number && v !== null && v !== undefined && v !== '' ? Number(v).toLocaleString('en-US', { maximumFractionDigits: 5 }) : (v ?? '');
        }
    }

    function renumber() {
        [...tbody.rows].forEach((tr, n) => { tr.querySelector('[data-n]').textContent = n + 1; });
    }

    function val(tr, name) {
        const el = tr.querySelector(`[name$=".${name}"]`);
        return el ? el.value : '';
    }

    function lineAmount(tr) {
        if (!amountCfg) return 0;
        const num = (n) => Number(String(val(tr, n) || 0).replace(/,/g, '')) || 0;
        const amt = Math.round(num(amountCfg.qty) * num(amountCfg.price) * (1 - (amountCfg.disc ? num(amountCfg.disc) : 0) / 100) * 100) / 100;
        tr.querySelector(`[data-cell="${amountCfg.out}"]`).textContent = money(amt);
        return amt;
    }

    function totals() {
        if (!cfg.totals) return;
        const net = [...tbody.rows].reduce((s, tr) => s + lineAmount(tr), 0);
        const taxSel = cfg.tax ? head(cfg.tax) : null;
        const pct = taxSel ? Number(taxSel.selectedOptions[0]?.dataset.pct || 0) : 0;
        // a deduction before VAT kept on the head (cfg.deduct, e.g. the deposit a tax invoice takes off)
        const ded = cfg.deduct && loadedHead ? Number(loadedHead[cfg.deduct] || 0) : 0;
        const tax = Math.round((net - ded) * pct) / 100;
        const set = (k, v) => { const el = form.querySelector(`[data-total="${k}"]`); if (el) el.textContent = v; };
        set('net', money(net));
        set('pct', pct ? `${pct}%` : '');
        set('deduct', '-' + money(ded));
        set('after', money(net - ded));
        form.querySelectorAll('[data-deduct-row]').forEach((tr) => { tr.hidden = !ded; });
        set('tax', money(tax));
        set('total', money(net - ded + tax));
    }

    async function partChanged(tr) {
        const input = tr.querySelector('[data-part]');
        input.value = input.value.trim().toUpperCase();
        const p = await findPart(input.value);
        if (!p) return;
        const setIf = (name, v, always) => {
            if (!name) return;
            const el = tr.querySelector(`[name$=".${name}"]`);
            if (el && (always || !el.value)) el.value = v ?? '';
            const span = tr.querySelector(`[data-cell="${name}"]`);
            if (span) span.textContent = v ?? '';
        };
        setIf(partCfg.desc, p.pt_desc1, true);
        setIf(partCfg.um, p.pt_um, true);
        await fetchPrice(tr);
        totals();
    }

    /**
     * Price (and discount) from partCfg.priceApi. Prices filled this way are marked
     * auto, so a new quantity can fetch a better price break; a price typed by hand stays.
     */
    async function fetchPrice(tr) {
        if (!partCfg.price || !partCfg.priceApi) return;
        const priceEl = tr.querySelector(`[name$=".${partCfg.price}"]`);
        if (!priceEl || (priceEl.value && priceEl.dataset.auto !== '1')) return; // typed by hand: keep it
        const part = val(tr, cols.find((c) => c.type === 'part').name).trim().toUpperCase();
        if (!part) return;
        const params = { part };
        for (const [param, field] of Object.entries(partCfg.priceParams || {})) params[param] = head(field)?.value ?? '';
        if (amountCfg) params.qty = val(tr, amountCfg.qty) || 1;
        const res = await Phai.api.get(partCfg.priceApi, params);
        if (res.price === null || res.price === undefined) return;
        priceEl.value = plain(res.price);
        priceEl.dataset.auto = '1';
        const discEl = partCfg.disc ? tr.querySelector(`[name$=".${partCfg.disc}"]`) : null;
        if (discEl && res.disc !== undefined) {
            discEl.value = res.disc ? plain(res.disc) : '';
            discEl.dataset.auto = '1';
        }
        totals();
    }

    tbody.addEventListener('input', (e) => {
        dirty = true;
        if (partCfg.price && e.target.name?.endsWith('.' + partCfg.price)) delete e.target.dataset.auto;
        if (partCfg.disc && e.target.name?.endsWith('.' + partCfg.disc)) delete e.target.dataset.auto;
        totals();
    });
    tbody.addEventListener('change', (e) => {
        if (e.target.matches('[data-part]')) partChanged(e.target.closest('tr'));
        if (e.target.matches('[data-vendor]')) e.target.value = e.target.value.trim().toUpperCase();
        if (amountCfg && e.target.name?.endsWith('.' + amountCfg.qty)) fetchPrice(e.target.closest('tr'));
    });
    tbody.addEventListener('click', (e) => {
        if (!e.target.closest('[data-remove]')) return;
        e.target.closest('tr').remove();
        dirty = true;
        if (!tbody.rows.length) addRow(null, true);
        renumber();
        totals();
    });
    addBtn.addEventListener('click', () => addRow(null, true));

    // Enter: next field; at the end of the last line, a new line.
    form.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' || e.target.tagName === 'BUTTON') return;
        e.preventDefault();
        if (e.ctrlKey || e.metaKey) {
            form.requestSubmit();
            return;
        }
        const tr = e.target.closest('tbody tr');
        const scope = tr || form.querySelector('.card-body');
        const fields = [...scope.querySelectorAll('input:not([disabled]), select:not([disabled])')];
        const at = fields.indexOf(e.target);
        if (at >= 0 && at < fields.length - 1) fields[at + 1].focus();
        else if (tr) (tr.nextElementSibling || addRow(null, false)).querySelector('input')?.focus();
        else tbody.querySelector('input')?.focus();
    });

    // ------------------------------------------------------------ header

    form.querySelector('.card-body').addEventListener('input', (e) => {
        dirty = true;
    });

    form.querySelector('.card-body').addEventListener('change', async (e) => {
        const name = e.target.name;
        const type = e.target.dataset.party;
        if (type) {
            e.target.value = e.target.value.trim().toUpperCase();
            const r = await findParty(type, e.target.value);
            if (r && cfg.partyDefaults) {
                for (const [field, from] of Object.entries(cfg.partyDefaults)) {
                    if (r[from]) head(field).value = r[from];
                }
                await updateRate();
            }
        }
        if (cfg.rate && (name === cfg.rate.curr || name === cfg.rate.date)) await updateRate();
        totals();
    });

    async function updateRate() {
        if (!cfg.rate) return;
        const res = await Phai.api.get('/api/rate', { curr: head(cfg.rate.curr).value, date: head(cfg.rate.date).value });
        if (res.rate !== null) head(cfg.rate.out).value = plain(res.rate);
    }

    // ------------------------------------------------------------ editable / status / actions

    function applyEditable() {
        // a locked select (readonly head field) keeps its value but cannot be changed
        form.querySelectorAll('input, select, textarea').forEach((el) => { el.disabled = !editable || (el.tagName === 'SELECT' && 'locked' in el.dataset); });
        Phai.viewFields(form, !editable);
        const fixed = !editable || !!cfg.lines.fixed; // cfg.lines.fixed: the lines come from another document, none added or removed
        tbody.querySelectorAll('[data-remove]').forEach((b) => { b.hidden = fixed; });
        saveBtn.hidden = !editable;
        undoBtn.hidden = !editable;
        addBtn.hidden = fixed;
        addBtn.parentElement.hidden = fixed; // no empty footer under the lines in view mode
        // "received", "PO no." and similar columns only mean something on a saved document
        form.querySelectorAll('[data-existing]').forEach((el) => el.classList.toggle('d-none', !cfg.nbr));
        form.querySelectorAll('#doc-lines thead th:last-child, #doc-lines tbody td:last-child').forEach((el) => {
            el.classList.toggle('d-none', !editable);
        });
    }

    // icon of each status action; a controller may give its own with 'icon'
    const actionIcons = { print: 'printer', cancel: 'ban', close: 'lock', lost: 'thumb-down', receive: 'truck-delivery',
        ship: 'truck', so: 'file-check', 'to-po': 'shopping-cart' };
    const actionLabel = (a) => `<i class="ti ti-${a.icon || actionIcons[a.name] || 'point'} me-1"></i>${Phai.esc(a.label)}`;

    function showStatus() {
        const s = status ? cfg.status[status] : null;
        statusBadge.classList.toggle('d-none', !s);
        if (s) {
            statusBadge.className = `badge bg-${s.color}-lt`;
            statusBadge.textContent = s.text;
        }
        actionsBox.innerHTML = '';
        if (!cfg.nbr) return;
        for (const a of cfg.actions || []) {
            if (!a.can || !a.when.includes(status)) continue;
            if (a.href) {
                const link = document.createElement('a');
                link.className = `btn btn-sm ${a.style}`;
                link.href = Phai.url(a.href.replace('{nbr}', encodeURIComponent(cfg.nbr)));
                if (a.target) link.target = a.target;
                link.innerHTML = actionLabel(a);
                actionsBox.appendChild(link);
                continue;
            }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `btn btn-sm ${a.style}`;
            btn.innerHTML = actionLabel(a);
            btn.addEventListener('click', () => runAction(a, btn));
            actionsBox.appendChild(btn);
        }
    }

    async function runAction(a, btn) {
        if (dirty && editable) {
            Phai.toast(text.save_first, 'warning');
            return;
        }
        if (a.askVendor) {
            askVendor(a);
            return;
        }
        // a.event: a page script handles it (e.g. 'etax' opens the e-Tax send dialog)
        if (a.event) {
            document.dispatchEvent(new CustomEvent('doc:' + a.event, { detail: { nbr: cfg.nbr, reload: load } }));
            return;
        }
        if (a.confirm && !(await Phai.confirm(a.confirm))) return;
        Phai.busy(btn, true);
        try {
            // a.post: make another document from this one and open it (e.g. quotation -> sales order)
            if (a.post) {
                const res = await Phai.api.post(a.post, { [a.body]: cfg.nbr });
                location.href = Phai.url(a.go + encodeURIComponent(res.nbr));
                return;
            }
            const res = await Phai.api.post(cfg.api + '/' + encodeURIComponent(cfg.nbr) + a.api);
            // a.gone: the document no longer exists (deleted): back to the list
            if (a.gone) {
                Phai.toast(text.done);
                setTimeout(() => { location.href = Phai.url(a.gone); }, 500);
                return;
            }
            Phai.toast(res?.notice || text.done, res?.notice ? 'warning' : 'success');
            await load();
        } catch (err) {
            Phai.busy(btn, false);
        }
    }

    /**
     * Lines with their own vendor go to that vendor; the dialog asks only for the vendor of
     * the other lines (skipped when every line has one). Several orders come back with a notice.
     */
    function askVendor(a) {
        const vendors = [...tbody.querySelectorAll('[data-vendor]')].map((x) => x.value.trim());
        const make = async (vendor) => {
            const res = await Phai.api.post(a.post, { [a.body]: cfg.nbr, vendor });
            try { if (res.notice) sessionStorage.setItem('phai-notice', res.notice); } catch (err) { /* private mode */ }
            location.href = Phai.url(a.go + encodeURIComponent(res.nbr));
        };
        if (vendors.length && vendors.every(Boolean)) {
            make('').catch(() => {});
            return;
        }
        const el = document.getElementById('doc-vendor');
        const f = el.querySelector('form');
        const modal = bs.Modal.getOrCreateInstance(el);
        el.querySelector('[data-title]').textContent = a.label;
        f.vendor.value = vendors.find(Boolean) || '';
        f.onsubmit = async (e) => {
            e.preventDefault();
            try {
                await make(f.vendor.value);
                modal.hide();
            } catch (err) {
                if (err.fields) Phai.form.errors(f, { vendor: Object.values(err.fields)[0] });
            }
        };
        modal.show();
    }

    // ------------------------------------------------------------ load / save

    let loadedHead = null;
    async function load() {
        const data = await Phai.api.get(cfg.api + '/' + encodeURIComponent(cfg.nbr));
        loadedHead = data.head;
        for (const f of cfg.head) {
            const v = f.name === 'doc_nbr' ? cfg.nbr : data.head[f.name]; // the number itself is not a head column
            head(f.name).value = f.type === 'number' ? plain(v) : (f.type === 'date' && v ? String(v).slice(0, 10) : (v ?? ''));
            if (f.nameField) Phai.pick.label(head(f.name), data.head[f.nameField]); // the name in the box, without a lookup
        }
        tbody.innerHTML = '';
        data.lines.forEach((l) => addRow(l, false));
        status = data.head[cfg.statusField];
        editBtn.hidden = !(cfg.canEdit && !!data.head._editable);
        editable = false;
        dirty = false;
        applyEditable();
        showStatus();
        totals();
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!editable) return;
        const body = { lines: {} };
        for (const f of cfg.head) if (!f.readonly) body[f.name] = head(f.name).value;
        if (cfg.numbering) body.doc_type = cfg.numbering; // a number typed in doc_nbr is used for this kind of document
        if (!cfg.nbr && cfg.prefill?.body) Object.assign(body, cfg.prefill.body); // e.g. the sales return a credit note closes
        for (const tr of tbody.rows) {
            const line = {};
            for (const c of cols) {
                if (c.type !== 'static' && c.type !== 'amount') line[c.name] = val(tr, c.name);
            }
            body.lines[tr.dataset.i] = line;
        }

        Phai.form.errors(form, null);
        Phai.busy(saveBtn, true);
        try {
            const res = cfg.nbr
                ? await Phai.api.put(cfg.api + '/' + encodeURIComponent(cfg.nbr), body)
                : await Phai.api.post(cfg.api, body);
            dirty = false;
            if (!cfg.nbr) {
                // a notice (e.g. over credit limit) is shown on the page we open next
                try { if (res.notice) sessionStorage.setItem('phai-notice', res.notice); } catch (err) { /* private mode */ }
                location.href = Phai.url(cfg.page + '/' + encodeURIComponent(res.nbr));
                return;
            }
            Phai.toast(res.notice || text.saved.replace(':nbr', res.nbr), res.notice ? 'warning' : 'success');
            await load();
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
        } finally {
            Phai.busy(saveBtn, false);
        }
    });

    window.addEventListener('beforeunload', (e) => {
        if (dirty && editable) e.preventDefault();
    });

    // ------------------------------------------------------------ start

    if (cfg.nbr) {
        try {
            const notice = sessionStorage.getItem('phai-notice');
            if (notice) {
                sessionStorage.removeItem('phai-notice');
                Phai.toast(notice, 'warning');
            }
        } catch (err) { /* private mode */ }
        // the list's pencil opens it ready to edit (?edit=1); the address goes back to the document's own
        load().then(() => {
            if (!new URLSearchParams(location.search).has('edit')) return;
            history.replaceState(null, '', location.pathname);
            if (!editBtn.hidden) editBtn.click();
        });
    } else {
        for (const f of cfg.head) {
            if (f.default !== undefined && f.default !== null) head(f.name).value = f.default;
        }
        // a new document made from another one (cfg.prefill: head values and lines), e.g. a credit note from a sales return
        if (cfg.prefill) {
            for (const [name, v] of Object.entries(cfg.prefill.head || {})) {
                if (v !== null && v !== undefined && head(name)) head(name).value = v;
            }
            (cfg.prefill.lines || []).forEach((l) => addRow(l, false));
        }
        if (!tbody.rows.length) addRow(null, false);
        applyEditable();
        showStatus();
        totals();
        if (cfg.rate) updateRate();
        form.querySelector('.card-body input:not([name="doc_nbr"]):not([readonly]), .card-body select')?.focus();
    }
})();
