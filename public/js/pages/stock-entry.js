/**
 * Stock document entry (views/inventory/entry.php).
 * Receive / issue / convert / price adjust in one warehouse.
 */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('entry-config').textContent);
    const form = document.getElementById('entry');
    const tbody = form.querySelector('#lines tbody');
    const saveBtn = document.getElementById('save');
    const el = form.elements;

    // the stock page opens this one on a tab: ?kind=receipt|issue|convert|cost
    let kind = new URLSearchParams(location.search).get('kind') || 'receipt';
    const tab = form.querySelector(`[name=kind][value="${CSS.escape(kind)}"]`);
    if (tab) tab.checked = true; else kind = 'receipt';
    let seq = 0; // row ids are never reused, so error keys always point at the right row

    const parts = new Map();     // code -> {pt_part, pt_desc1, pt_um, pt_lot_ser}
    const locations = new Map(); // site -> [{loc_loc, loc_desc}]
    const onhand = new Map();    // site|part -> [{loc, lot, qty, expire}]

    const fromSite = () => el.site.value;
    const needsLot = (p) => !!p && (p.pt_lot_ser === 'L' || p.pt_lot_ser === 'S');
    const num = (v) => Number(v).toLocaleString('en-US', { maximumFractionDigits: 5 });

    // ------------------------------------------------------------ data

    async function siteLocations(site) {
        if (!site) return [];
        if (!locations.has(site)) locations.set(site, await Phai.api.get('/api/stock/locations', { site }));
        return locations.get(site);
    }

    async function findPart(code) {
        code = code.trim().toUpperCase();
        if (!code) return null;
        if (!parts.has(code)) {
            const found = await Phai.api.get('/api/stock/parts', { q: code });
            found.forEach((p) => parts.set(p.pt_part, p));
            if (!parts.has(code)) return null;
        }
        return parts.get(code);
    }

    async function stockOf(site, part) {
        const key = site + '|' + part;
        if (!onhand.has(key)) onhand.set(key, await Phai.api.get('/api/stock/onhand', { part, site }));
        return onhand.get(key);
    }

    // ------------------------------------------------------------ rows

    function addRow(focus) {
        const i = seq++;
        const tr = document.createElement('tr');
        tr.dataset.i = i;
        tr.innerHTML = `
            <td class="text-secondary small" data-n></td>
            <td style="min-width: 14rem">
                <input class="form-control form-control-sm text-uppercase" name="lines.${i}.part" autocomplete="off" data-pick="parts">
            </td>
            <td style="width: 9rem" data-show="receipt issue convert"><select class="form-select form-select-sm" name="lines.${i}.loc"></select></td>
            <td style="width: 9rem" data-show="receipt issue convert">
                <input class="form-control form-control-sm" name="lines.${i}.lot" autocomplete="off" disabled data-pick="items">
            </td>
            <td class="num small text-nowrap" data-onhand data-show="issue cost convert"></td>
            <td style="width: 8rem" data-show="receipt issue convert"><input class="form-control form-control-sm text-end" name="lines.${i}.qty" inputmode="decimal" autocomplete="off"></td>
            <td class="num small text-nowrap" data-curcost data-show="cost"></td>
            <td style="width: 8rem" data-show="receipt cost"><input class="form-control form-control-sm text-end" name="lines.${i}.cost" inputmode="decimal" autocomplete="off"></td>
            <td style="width: 9.5rem" data-show="receipt"><input type="date" class="form-control form-control-sm" name="lines.${i}.expire" disabled></td>
            <td style="min-width: 12rem" data-show="convert"><input class="form-control form-control-sm text-uppercase" name="lines.${i}.fg_part" autocomplete="off" data-pick="parts" data-sale></td>
            <td style="width: 7rem" data-show="convert"><input class="form-control form-control-sm text-end" name="lines.${i}.fg_qty" inputmode="decimal" autocomplete="off"></td>
            <td style="width: 9rem" data-show="convert"><select class="form-select form-select-sm" name="lines.${i}.to_loc"></select></td>
            <td><button type="button" class="btn btn-sm btn-icon btn-ghost-secondary" data-remove tabindex="-1"><i class="ti ti-x"></i></button></td>`;
        tbody.appendChild(tr);
        fillLocations(tr.querySelector(`[name$=".loc"]`), fromSite());
        fillLocations(tr.querySelector('[name$=".to_loc"]'), fromSite()); // a conversion puts the good in the same warehouse
        showColumns();
        renumber();
        if (focus) tr.querySelector('[name$=".part"]').focus();
        return tr;
    }

    function renumber() {
        [...tbody.rows].forEach((tr, n) => { tr.querySelector('[data-n]').textContent = n + 1; });
    }

    async function fillLocations(select, site) {
        const keep = select.value;
        const list = await siteLocations(site);
        const def = cfg.sites.find((s) => s.si_site === site)?.si_default_loc || '';
        select.innerHTML = list.map((l) => `<option value="${Phai.esc(l.loc_loc)}">${Phai.esc(l.loc_loc)}</option>`).join('');
        select.value = list.some((l) => l.loc_loc === keep) ? keep : (list.some((l) => l.loc_loc === def) ? def : (list[0]?.loc_loc ?? ''));
    }

    /** After part, location or lot changes: description, lot field, on-hand, lot suggestions. */
    async function refreshRow(tr) {
        const partInput = tr.querySelector('[name$=".part"]');
        const lotInput = tr.querySelector('[name$=".lot"]');
        const expire = tr.querySelector('[name$=".expire"]');
        const cell = tr.querySelector('[data-onhand]');

        const p = await findPart(partInput.value);
        partInput.value = partInput.value.trim().toUpperCase();
        lotInput.disabled = !needsLot(p);
        if (expire) expire.disabled = !needsLot(p);
        if (!needsLot(p)) lotInput.value = '';

        cell.textContent = '';
        tr.querySelector('[data-curcost]').textContent = '';
        if (!p) return;
        if (kind === 'cost') { // the whole stock of the part, at its current cost
            const c = await Phai.api.get('/api/stock/moves/cost', { part: p.pt_part });
            cell.textContent = num(c.onhand) + ' ' + c.um;
            tr.querySelector('[data-curcost]').textContent = Number(c.cost).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 5 });
            return;
        }
        const rows = await stockOf(fromSite(), p.pt_part);
        const loc = tr.querySelector('[name$=".loc"]').value;
        const here = rows.filter((r) => r.loc === loc);
        Phai.pick.items(lotInput, here.filter((r) => r.lot).map((r) => ({ value: r.lot, label: num(r.qty) + (r.expire ? ' · ' + r.expire : '') })));
        const qty = here.filter((r) => r.lot === (needsLot(p) ? lotInput.value.trim() : '')).reduce((s, r) => s + Number(r.qty), 0);
        cell.textContent = num(qty) + ' ' + p.pt_um;
        tr.dataset.here = qty;
        tr.dataset.um = p.pt_um;
        warn(tr);
    }

    /** Issuing or converting more than is there: warned under the quantity (a receipt adds, so never short). */
    function warn(tr) {
        const q = tr.querySelector('[name$=".qty"]');
        if (kind !== 'issue' && kind !== 'convert') { q.classList.remove('is-invalid'); return; }
        if (tr.dataset.here !== undefined) Phai.stockShort(q, tr.dataset.here, tr.dataset.um);
    }

    tbody.addEventListener('input', (e) => {
        if ((e.target.name || '').endsWith('.qty')) warn(e.target.closest('tr'));
    });

    tbody.addEventListener('change', (e) => {
        const name = e.target.name || '';
        if (/\.(part|loc|lot)$/.test(name)) refreshRow(e.target.closest('tr'));
    });

    tbody.addEventListener('click', (e) => {
        if (!e.target.closest('[data-remove]')) return;
        e.target.closest('tr').remove();
        if (!tbody.rows.length) addRow(true);
        renumber();
    });

    // Enter moves to the next field; on the last field of the last row it adds a row.
    form.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' || e.target.tagName === 'BUTTON') return;
        e.preventDefault();
        if (e.ctrlKey || e.metaKey) {
            form.requestSubmit();
            return;
        }
        const tr = e.target.closest('tr');
        const fields = [...(tr || form).querySelectorAll('input:not([disabled]):not([type=radio]), select')]
            .filter((x) => x.offsetParent !== null);
        const at = fields.indexOf(e.target);
        if (at >= 0 && at < fields.length - 1) {
            fields[at + 1].focus();
        } else if (tr) {
            const next = tr.nextElementSibling || addRow(false);
            next.querySelector('[name$=".part"]').focus();
        } else {
            tbody.querySelector('[name$=".part"]')?.focus();
        }
    });

    document.getElementById('add-line').addEventListener('click', () => addRow(true));

    // ------------------------------------------------------------ header

    function showColumns() {
        form.querySelectorAll('[data-show]').forEach((x) => {
            x.classList.toggle('d-none', !x.dataset.show.split(' ').includes(kind));
        });
        const costHead = document.getElementById('cost-head');
        if (costHead) costHead.textContent = kind === 'cost' ? cfg.text.new_cost : cfg.text.unit_cost;
        // lots come from receiving: a receipt takes its document number (or one typed), the others pick a lot in stock
        tbody.querySelectorAll('[name$=".lot"]').forEach((x) => {
            x.toggleAttribute('data-free', kind === 'receipt');
            x.placeholder = kind === 'receipt' ? (el.doc_nbr?.value ?? '') : '';
        });
    }

    function fillReasons() {
        const type = { receipt: 'RCT', issue: 'ISS', convert: 'ISS' }[kind];
        const list = cfg.reasons.filter((r) => !type || r.rs_type === type);
        const keep = el.reason.value;
        el.reason.innerHTML = '<option value=""></option>'
            + list.map((r) => `<option value="${Phai.esc(r.rs_code)}">${Phai.esc(r.rs_code + ' · ' + r.rs_desc)}</option>`).join('');
        if (list.some((r) => r.rs_code === keep)) el.reason.value = keep;
    }

    form.querySelectorAll('[name=kind]').forEach((r) => r.addEventListener('change', () => {
        kind = r.value;
        fillReasons();
        showColumns();
        for (const tr of tbody.rows) refreshRow(tr);
    }));
    fillReasons();

    el.site.addEventListener('change', () => {
        onhand.clear();
        for (const tr of tbody.rows) {
            fillLocations(tr.querySelector('[name$=".loc"]'), fromSite()).then(() => refreshRow(tr));
            fillLocations(tr.querySelector('[name$=".to_loc"]'), fromSite());
        }
    });

    // ------------------------------------------------------------ save

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = { kind, date: el.date.value, site: el.site.value, reason: el.reason.value, remark: el.remark.value, lines: {} };
        if (el.doc_nbr) { body.doc_nbr = el.doc_nbr.value; body.doc_type = 'IC'; }
        for (const tr of tbody.rows) {
            const v = (f) => tr.querySelector(`[name$=".${f}"]`);
            body.lines[tr.dataset.i] = kind === 'cost' ? { part: v('part').value, cost: v('cost').value } : {
                part: v('part').value, loc: v('loc').value, lot: v('lot').value, qty: v('qty').value,
                cost: v('cost').value, expire: v('expire').value, to_loc: v('to_loc').value,
                fg_part: v('fg_part').value, fg_qty: v('fg_qty').value,
            };
        }

        Phai.form.errors(form, null);
        Phai.busy(saveBtn, true);
        try {
            const res = await Phai.api.post('/api/stock/moves', body);
            Phai.toast(cfg.text.posted.replace(':nbr', res.nbr));
            const badge = document.querySelector('.phai-doc-nbr'); // the number the next entry gets
            if (badge && res.next) badge.textContent = res.next;
            if (el.doc_nbr && res.next) el.doc_nbr.value = res.next;
            tbody.innerHTML = '';
            onhand.clear();
            addRow(true);
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
        } finally {
            Phai.busy(saveBtn, false);
        }
    });

    // ?part= (from the stock item page) starts the first line with that item
    const first = addRow(true);
    const startPart = new URLSearchParams(location.search).get('part');
    if (startPart) {
        const input = first.querySelector('[name$=".part"]');
        input.value = startPart;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
})();
