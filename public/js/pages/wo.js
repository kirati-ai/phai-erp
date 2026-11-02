(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('wo-config').textContent);
    const head = document.getElementById('wo-head');
    const h = head.elements;
    const saveBtn = document.getElementById('wo-save');
    const editBtn = document.getElementById('wo-edit');
    let editing = false; // a saved order opens as a view; Edit (while firm) turns the head into inputs
    const matForm = document.getElementById('mat-form');
    const rcvForm = document.getElementById('rcv-form');
    const num = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 5 });
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const date = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '');
    const plain = (v) => (v === null || v === undefined || v === '' ? '' : String(Number(v)));
    const api = () => '/api/work-orders/' + encodeURIComponent(cfg.nbr);
    let data = null;
    const locations = new Map();
    const stock = new Map();

    async function siteLocations(site) {
        if (!locations.has(site)) locations.set(site, await Phai.api.get('/api/stock/locations', { site }));
        return locations.get(site);
    }
    async function onHand(site, part) {
        const k = site + '|' + part;
        if (!stock.has(k)) stock.set(k, await Phai.api.get('/api/stock/onhand', { site, part }));
        return stock.get(k);
    }
    const locOptions = (list) => list.map((l) => `<option value="${Phai.esc(l.loc_loc)}">${Phai.esc(l.loc_loc)}</option>`).join('');

    // ------------------------------------------------------------ header


    head.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = {};
        for (const n of ['wo_part', 'wo_qty_ord', 'wo_site', 'wo_rel_date', 'wo_due_date', 'wo_rmks']) body[n] = h[n].value;
        if (h.doc_nbr) { body.doc_nbr = h.doc_nbr.value; body.doc_type = 'WO'; }
        Phai.form.errors(head, null);
        Phai.busy(saveBtn, true);
        try {
            const res = cfg.nbr ? await Phai.api.put(api(), body) : await Phai.api.post('/api/work-orders', body);
            if (!cfg.nbr) {
                location.href = Phai.url('/work-orders/' + encodeURIComponent(res.nbr));
                return;
            }
            Phai.toast(cfg.text.saved.replace(':nbr', res.nbr));
            editing = false;
            await load();
        } catch (err) {
            if (err.fields) Phai.form.errors(head, err.fields);
        } finally {
            Phai.busy(saveBtn, false);
        }
    });

    // ------------------------------------------------------------ status and actions

    function showStatus() {
        const st = data.head.wo_status;
        const s = cfg.status[st];
        const badge = document.getElementById('wo-status');
        badge.className = `badge bg-${s.color}-lt`;
        badge.textContent = s.text;
        const box = document.getElementById('wo-actions');
        box.innerHTML = '';
        const action = (label, style, path, confirmText, icon) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = `btn btn-sm ${style}`;
            b.innerHTML = `<i class="ti ti-${icon} me-1"></i>${Phai.esc(label)}`;
            b.onclick = async () => {
                if (!(await Phai.confirm(confirmText))) return;
                Phai.busy(b, true);
                try {
                    await Phai.api.post(api() + path);
                    if (path === '/cancel') {
                        location.href = Phai.url('/work-orders');
                        return;
                    }
                    Phai.toast(cfg.text.done);
                    await load();
                } catch (err) {
                    Phai.busy(b, false);
                }
            };
            box.appendChild(b);
        };
        if (st === 'F' && cfg.canEdit) action(cfg.text.release, 'btn-ghost-primary', '/release', cfg.text.release_confirm, 'player-play');
        if (st === 'F' && cfg.canDelete) action(cfg.text.cancel, 'btn-ghost-danger', '/cancel', cfg.text.cancel_confirm, 'ban');
        // closing books what is left in work in progress as the production variance: say how much
        const left = Number(data.cost?.wip || 0);
        if (st === 'R' && cfg.canEdit) action(cfg.text.close, 'btn-ghost-secondary', '/close', left ? cfg.text.close_confirm_var.replace(':amt', money(left)) : cfg.text.close_confirm, 'lock');

        setEditing(editing && !!data.head._editable);
        // issuing, receiving and labour only on a released order
        const busy = st === 'R' && cfg.canEdit;
        document.querySelectorAll('#wo-body [data-act]').forEach((x) => x.classList.toggle('d-none', !busy));
        applyMode();
    }

    /** View mode shows plain values (Phai.viewFields); edit mode the inputs and Save. */
    function setEditing(on) {
        editing = on;
        head.querySelectorAll('input, select').forEach((x) => { x.disabled = !on; });
        Phai.viewFields(head, !on);
        saveBtn.hidden = !on;
        document.getElementById('wo-undo').hidden = !(on && cfg.nbr);
        editBtn.hidden = on || !(data.head._editable && cfg.canEdit);
    }
    editBtn.addEventListener('click', () => setEditing(true));
    // Cancel: back to the saved order
    document.getElementById('wo-undo').addEventListener('click', async () => {
        editing = false;
        Phai.form.errors(head, null);
        await load();
    });

    // ------------------------------------------------------------ materials: issue / return slips

    let mode = 'issue'; // 'issue' against the bill, or 'return' what was not used — each save is one slip (MI)
    let more = false;   // everything on the bill is issued: inputs only after "issue more" (a reason is then required)
    let matLocs = [];
    let splitSeq = 0;
    const partRows = (part) => [...matForm.querySelectorAll('tr[data-part]')].filter((x) => x.dataset.part === part);
    const qtyOf = (tr) => Number(String(tr.querySelector('[name$=".qty"]').value).replace(/,/g, '')) || 0;
    const round5 = (v) => Math.round(v * 100000) / 100000;
    const allIssued = () => data.comps.length > 0 && data.comps.every((c) => Number(c.remaining) <= 0);
    const working = () => data.head.wo_status === 'R' && cfg.canEdit;

    /** What the order still has out of each lot of a component (what may come back): [{loc, lot, qty}] from the slips. */
    function takenRows(part) {
        const by = new Map();
        for (const s of data.slips) {
            if (s.wis_status === 'X') continue; // cancelled: everything went back
            for (const l of s.lines) {
                if (l.tr_part !== part) continue;
                const r = by.get(l.tr_lot) ?? { loc: l.tr_loc, lot: l.tr_lot, qty: 0 };
                r.qty = round5(r.qty - Number(l.tr_chg_qty)); // an issue line is negative, a return positive
                by.set(l.tr_lot, r);
            }
        }
        return [...by.values()].filter((r) => r.qty > 0);
    }

    /**
     * One row of a component. A component short in one lot gets more rows (+): each its own
     * location / lot / quantity, all on the same component line (data-key = the row's key in the request).
     */
    function matRow(c, key, split) {
        const lot = c.pt_lot_ser === 'L' || c.pt_lot_ser === 'S';
        const n = Phai.esc(`lines.${key}`);
        const lead = split
            ? `<td colspan="5" class="text-end text-secondary small">${Phai.esc(c.wod_part)} <i class="ti ti-corner-down-right"></i></td>`
            : `<td class="fw-medium">${Phai.esc(c.wod_part)}</td>
                    <td>${Phai.esc(c.pt_desc1 ?? '')} <span class="text-secondary small">${Phai.esc(c.pt_um ?? '')}</span></td>
                    <td class="num">${Phai.esc(num(c.wod_qty_req))}</td>
                    <td class="num">${Phai.esc(num(c.wod_qty_iss))}</td>
                    <td class="num">${Phai.esc(num(c.remaining))}</td>`;
        const btn = split
            ? `<button type="button" class="phai-act-btn" data-split-del title="${Phai.esc(Phai.t('delete'))}" aria-label="${Phai.esc(Phai.t('delete'))}"><i class="ti ti-x"></i></button>`
            : `<button type="button" class="phai-act-btn" data-split-add title="${Phai.esc(Phai.t('add_lot'))}" aria-label="${Phai.esc(Phai.t('add_lot'))}"><i class="ti ti-plus"></i></button>`;
        return `
                <tr data-part="${Phai.esc(c.wod_part)}" data-key="${Phai.esc(key)}"${split ? ' data-split' : ''} data-remaining="${Number(c.remaining)}" data-lot="${lot ? 1 : 0}" data-um="${Phai.esc(c.pt_um ?? '')}">
                    ${lead}
                    <td data-in><input class="form-control form-control-sm text-end" name="${n}.qty" inputmode="decimal"></td>
                    <td data-in><select class="form-select form-select-sm" name="${n}.loc">${locOptions(matLocs)}</select></td>
                    <td data-in><input class="form-control form-control-sm" name="${n}.lot" data-pick="items" ${lot ? '' : 'disabled'}></td>
                    <td class="num small text-secondary" data-in data-onhand></td>
                    <td class="w-1" data-in>${btn}</td>
                </tr>`;
    }

    async function renderMaterials() {
        const site = data.head.wo_site;
        matLocs = await siteLocations(site);
        const tbody = matForm.querySelector('tbody');
        tbody.innerHTML = data.comps.map((c) => matRow(c, c.wod_part, false)).join('')
            || `<tr><td colspan="10" class="text-center text-secondary py-3">${Phai.esc(Phai.t('empty'))}</td></tr>`;
        for (const tr of tbody.querySelectorAll('tr[data-part]')) {
            const sel = tr.querySelector('select');
            if (mode === 'return') {
                const back = takenRows(tr.dataset.part)[0];
                if (back) sel.value = back.loc; // back where it came from
            } else {
                const rows = await onHand(site, tr.dataset.part);
                const comp = data.comps.find((c) => c.wod_part === tr.dataset.part);
                const has = rows.find((r) => Number(r.qty) >= Number(tr.dataset.remaining)) ?? rows.find((r) => Number(r.qty) > 0);
                sel.value = has ? has.loc : (comp.wod_loc || sel.value);
            }
            await refreshMat(tr);
        }
        applyMode();
    }

    /**
     * Issuing: lots at the row's location, its default lot (one the other rows of the component do not use, covering what
     * is left) and what is there. Returning: the lots the order took from, and how much of each it still has.
     */
    async function refreshMat(tr) {
        const lotInput = tr.querySelector('[name$=".lot"]');
        const qtyInput = tr.querySelector('[name$=".qty"]');
        if (mode === 'return') {
            const rows = takenRows(tr.dataset.part);
            if (tr.dataset.lot === '1') {
                Phai.pick.items(lotInput, rows.map((r) => ({ value: r.lot, label: num(r.qty) })));
                if (!lotInput.value && rows.length) lotInput.value = rows[0].lot;
            }
            const out = rows.filter((r) => tr.dataset.lot !== '1' || r.lot === lotInput.value).reduce((s, r) => s + r.qty, 0);
            tr.querySelector('[data-onhand]').textContent = num(out);
            tr.dataset.here = out;
            Phai.stockShort(qtyInput, out, tr.dataset.um);
            return;
        }
        const rows = (await onHand(data.head.wo_site, tr.dataset.part)).filter((r) => r.loc === tr.querySelector('select').value);
        if (tr.dataset.lot === '1') {
            Phai.pick.items(lotInput, rows.map((r) => ({ value: r.lot, label: num(r.qty) })));
            if (!lotInput.value && rows.length) {
                const others = partRows(tr.dataset.part).filter((x) => x !== tr);
                const used = others.map((x) => x.querySelector('[name$=".loc"]').value + '|' + x.querySelector('[name$=".lot"]').value);
                const need = Number(tr.dataset.remaining) - others.reduce((s, x) => s + qtyOf(x), 0);
                const free = rows.filter((r) => Number(r.qty) > 0 && !used.includes(r.loc + '|' + r.lot));
                lotInput.value = (free.find((r) => Number(r.qty) >= need) ?? free[0] ?? rows[0]).lot;
            }
        }
        const qty = rows.filter((r) => tr.dataset.lot !== '1' || r.lot === lotInput.value).reduce((s, r) => s + Number(r.qty), 0);
        tr.querySelector('[data-onhand]').textContent = num(qty);
        tr.dataset.here = qty;
        Phai.stockShort(qtyInput, qty, tr.dataset.um);
    }

    /** Inputs only while the order is released and there is something to do; the labels follow issue / return. */
    function applyMode() {
        if (!data?.slips) return;
        const done = mode === 'issue' && allIssued() && !more;
        matForm.querySelectorAll('[data-in]').forEach((x) => x.classList.toggle('d-none', !working() || done));
        matForm.querySelectorAll('[data-issue]').forEach((x) => { x.hidden = mode !== 'issue'; });
        matForm.querySelectorAll('[data-return]').forEach((x) => { x.hidden = mode !== 'return'; });
        document.getElementById('mat-done').hidden = !allIssued();
        document.getElementById('mat-more').hidden = !(working() && done);
        const modeBtn = document.getElementById('mat-mode');
        modeBtn.querySelector('span').textContent = mode === 'issue' ? cfg.text.return_mode : cfg.text.issue_mode;
        modeBtn.querySelector('i').className = `ti ti-${mode === 'issue' ? 'arrow-back-up' : 'file-export'} me-1`;
        modeBtn.hidden = !working() || (mode === 'issue' && !data.slips.length); // nothing taken: nothing to give back
    }

    /** Another row for the component, under its last one: the next location / lot with stock, and what is still left to issue. */
    async function addSplit(part, loc = null, lot = null, qty = null) {
        const c = data.comps.find((x) => x.wod_part === part);
        const rows = partRows(part);
        const last = rows[rows.length - 1];
        last.insertAdjacentHTML('afterend', matRow(c, `${part}~${++splitSeq}`, true));
        const tr = last.nextElementSibling;
        const sel = tr.querySelector('select');
        if (loc === null) {
            const used = rows.map((x) => x.querySelector('[name$=".loc"]').value + '|' + x.querySelector('[name$=".lot"]').value);
            const pool = mode === 'return' ? takenRows(part) : await onHand(data.head.wo_site, part);
            const next = pool.find((r) => Number(r.qty) > 0 && !used.includes(r.loc + '|' + r.lot));
            loc = next ? next.loc : sel.value;
            lot = next ? next.lot : null;
        }
        sel.value = loc;
        if (lot !== null) tr.querySelector('[name$=".lot"]').value = lot;
        const left = round5(Number(c.remaining) - rows.reduce((s, x) => s + qtyOf(x), 0));
        tr.querySelector('[name$=".qty"]').value = qty !== null ? plain(qty) : (mode === 'issue' && left > 0 ? plain(left) : '');
        await refreshMat(tr);
        return tr;
    }

    // more than the row's lot holds: warned under the quantity, and when issuing the rest goes to the next lots
    matForm.addEventListener('input', (e) => {
        const tr = e.target.closest('tr[data-part]');
        if (tr && (e.target.name || '').endsWith('.qty') && tr.dataset.here !== undefined) Phai.stockShort(e.target, tr.dataset.here, tr.dataset.um);
    });
    matForm.addEventListener('change', (e) => {
        if (/\.(loc|lot)$/.test(e.target.name || '')) refreshMat(e.target.closest('tr'));
        if ((e.target.name || '').endsWith('.qty') && mode === 'issue') spill(e.target.closest('tr'));
    });
    matForm.addEventListener('click', (e) => {
        const add = e.target.closest('[data-split-add]');
        if (add) addSplit(add.closest('tr').dataset.part);
        const del = e.target.closest('[data-split-del]');
        if (del) del.closest('tr').remove();
    });

    /** More typed than the row's lot holds: the row keeps what is there, the rest goes to the next lots with stock (new rows). */
    async function spill(tr) {
        if (!tr || tr.dataset.here === undefined) return;
        let extra = round5(qtyOf(tr) - Number(tr.dataset.here));
        if (extra <= 0) return;
        const part = tr.dataset.part;
        const used = partRows(part).map((x) => x.querySelector('[name$=".loc"]').value + '|' + x.querySelector('[name$=".lot"]').value);
        const free = (await onHand(data.head.wo_site, part)).filter((r) => Number(r.qty) > 0 && !used.includes(r.loc + '|' + r.lot));
        if (!free.length) return; // nowhere else: the row stays short and says so
        tr.querySelector('[name$=".qty"]').value = plain(tr.dataset.here);
        Phai.stockShort(tr.querySelector('[name$=".qty"]'), tr.dataset.here, tr.dataset.um);
        for (const [i, r] of free.entries()) {
            if (extra <= 0) break;
            const q = i === free.length - 1 ? extra : round5(Math.min(extra, Number(r.qty))); // the last takes any shortfall, warned
            await addSplit(part, r.loc, r.lot, q);
            extra = round5(extra - q);
        }
    }

    // what is left of every component, taken lot by lot (oldest expiry first) until covered — more rows where one lot is not enough
    document.getElementById('mat-fill').addEventListener('click', () => fillAll());
    async function fillAll() {
        for (const c of data.comps) {
            const [main, ...splits] = partRows(c.wod_part);
            if (!main) continue;
            splits.forEach((x) => x.remove());
            let need = Number(c.remaining);
            const take = [];
            for (const r of await onHand(data.head.wo_site, c.wod_part)) {
                if (need <= 0) break;
                if (Number(r.qty) <= 0) continue;
                const q = round5(Math.min(need, Number(r.qty)));
                take.push({ loc: r.loc, lot: r.lot, q });
                need = round5(need - q);
            }
            if (!take.length) { // nothing anywhere: the whole quantity on the row, which then says it is short
                main.querySelector('[name$=".qty"]').value = Number(c.remaining) > 0 ? plain(c.remaining) : '';
                await refreshMat(main);
                continue;
            }
            take[take.length - 1].q = round5(take[take.length - 1].q + Math.max(need, 0)); // still short: the last row shows it
            main.querySelector('select').value = take[0].loc;
            main.querySelector('[name$=".lot"]').value = take[0].lot;
            main.querySelector('[name$=".qty"]').value = plain(take[0].q);
            await refreshMat(main);
            for (const t of take.slice(1)) await addSplit(c.wod_part, t.loc, t.lot, t.q);
        }
    }

    document.getElementById('mat-mode').addEventListener('click', async () => {
        mode = mode === 'issue' ? 'return' : 'issue';
        more = false;
        await renderMaterials();
        if (mode === 'issue' && !allIssued()) await fillAll();
        matForm.querySelector('tr[data-part] [name$=".qty"]')?.focus();
    });
    // ยกเลิก (Phai.cancelButtons) beside each save: drop what was typed in that section only
    matForm.phaiCancel = async () => {
        matForm.elements.reason.value = '';
        mode = 'issue';
        more = false;
        await renderMaterials();
        if (working() && !allIssued()) await fillAll();
    };
    rcvForm.phaiCancel = () => {
        ['qty', 'scrap', 'expire'].forEach((n) => { rcvForm.elements[n].value = ''; });
        renderReceive(); // what is left to make again
    };
    document.getElementById('mat-more').addEventListener('click', () => {
        more = true;
        applyMode();
        matForm.elements.reason.focus();
    });

    matForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = { date: matForm.elements.date.value, type: mode === 'return' ? 'R' : 'I', reason: matForm.elements.reason.value, lines: {} };
        matForm.querySelectorAll('tr[data-part]').forEach((tr) => {
            const v = (f) => tr.querySelector(`[name$=".${f}"]`).value;
            body.lines[tr.dataset.key] = { part: tr.dataset.part, qty: v('qty'), loc: v('loc'), lot: v('lot') };
        });
        const res = await post(matForm, '/issue', body, (r) => cfg.text.slip_saved.replace(':nbr', r.slip));
        if (res) {
            matForm.elements.reason.value = '';
            mode = 'issue';
            more = false;
            await render();
        }
    });

    function renderSlips() {
        const tbody = document.querySelector('#slip-list tbody');
        if (!data.slips.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-secondary py-3">${Phai.esc(cfg.text.no_slips)}</td></tr>`;
            return;
        }
        // newest first: the slip, then what it moved
        tbody.innerHTML = [...data.slips].reverse().map((s) => `
            <tr class="bg-body-tertiary${s.wis_status === 'X' ? ' text-secondary text-decoration-line-through' : ''}">
                <td><span class="fw-semibold">${Phai.esc(s.wis_nbr)}</span>
                    <span class="badge ${s.wis_type === 'R' ? 'bg-orange-lt' : 'bg-blue-lt'} ms-1">${Phai.esc(cfg.text['slip_' + s.wis_type])}</span>
                    ${s.wis_status === 'X' ? `<span class="badge bg-secondary-lt ms-1">${Phai.esc(cfg.text.slip_cancelled)}</span>` : ''}
                    <span class="text-secondary small ms-1">${Phai.esc(date(s.wis_date))} · ${Phai.esc(s.created_by ?? '')}</span></td>
                <td></td><td></td><td></td>
                <td class="num fw-semibold">${Phai.esc(money(s.wis_amt))}</td>
                <td class="small">${Phai.esc(s.wis_reason ?? '')}</td>
                <td class="text-nowrap">${working() && s.wis_status === 'A' ? `<button type="button" class="phai-act-btn" data-cancel-slip="${Phai.esc(s.wis_nbr)}"
                    title="${Phai.esc(cfg.text.slip_cancel)}" aria-label="${Phai.esc(cfg.text.slip_cancel)}"><i class="ti ti-arrow-back-up"></i></button>` : ''}${cfg.canPrint ? `<a class="phai-act-btn" target="_blank" rel="noopener" title="${Phai.esc(Phai.t('print'))}" aria-label="${Phai.esc(Phai.t('print'))}"
                    href="${Phai.esc(Phai.url('/work-orders/issues/' + encodeURIComponent(s.wis_nbr) + '/print'))}"><i class="ti ti-printer"></i></a><a class="phai-act-btn" target="_blank" rel="noopener" title="${Phai.esc(Phai.t('sign_doc'))}" aria-label="${Phai.esc(Phai.t('sign_doc'))}"
                    href="${Phai.esc(Phai.url('/work-orders/issues/' + encodeURIComponent(s.wis_nbr) + '/print?html=1'))}"><i class="ti ti-writing-sign"></i></a>` : ''}</td>
            </tr>` + s.lines.map((l) => `
            <tr class="${s.wis_status === 'X' ? 'text-secondary text-decoration-line-through' : ''}">
                <td class="ps-4">${Phai.esc(l.tr_part)} <span class="text-secondary small">${Phai.esc(l.pt_desc1 ?? '')}</span></td>
                <td>${Phai.esc(l.tr_lot)}</td><td>${Phai.esc(l.tr_loc)}</td>
                <td class="num">${Phai.esc(num(Math.abs(Number(l.tr_chg_qty))))} <span class="text-secondary small">${Phai.esc(l.tr_um)}</span></td>
                <td class="num">${Phai.esc(money(Math.abs(Number(l.tr_chg_qty)) * Number(l.tr_cost)))}</td>
                <td></td><td></td>
            </tr>`).join('')).join('');
    }

    // cancel a slip: everything goes back where it came from (server), then the order again
    document.querySelector('#slip-list tbody').addEventListener('click', async (e) => {
        const b = e.target.closest('[data-cancel-slip]');
        if (!b || !(await Phai.confirm(cfg.text.slip_cancel_confirm.replace(':nbr', b.dataset.cancelSlip)))) return;
        Phai.busy(b, true);
        try {
            data = await Phai.api.post('/api/work-orders/issues/' + encodeURIComponent(b.dataset.cancelSlip) + '/cancel');
            stock.clear();
            Phai.toast(Phai.t('saved'));
            mode = 'issue';
            more = false;
            await render();
        } catch (err) {
            Phai.busy(b, false);
        }
    });

    // ------------------------------------------------------------ receive, labour, cost

    async function renderReceive() {
        const locs = await siteLocations(data.head.wo_site);
        const sel = rcvForm.elements.loc;
        const keep = sel.value;
        sel.innerHTML = locOptions(locs);
        const def = cfg.sites.find((s) => s.si_site === data.head.wo_site)?.si_default_loc;
        sel.value = locs.some((l) => l.loc_loc === keep) ? keep : (def || sel.value);
        const lotted = data.head.pt_lot_ser === 'L' || data.head.pt_lot_ser === 'S';
        rcvForm.elements.expire.disabled = !lotted; // the lot is the order number
        // what is still to come off the line, filled in (good + scrap may not pass it)
        const left = round5(Number(data.head.wo_qty_ord) - Number(data.head.wo_qty_comp) - Number(data.head.wo_qty_rjct));
        rcvForm.elements.qty.placeholder = left > 0 ? plain(left) : '0';
        if (!rcvForm.elements.qty.value && left > 0) rcvForm.elements.qty.value = plain(left);
        // each receipt is a QC record: good went into stock, scrap did not
        document.querySelector('#rcv-list tbody').innerHTML = (data.qcs || []).map((r) => `
            <tr><td>${Phai.esc(date(r.qc_date))}</td><td><a href="${Phai.url('/qc')}?q=${encodeURIComponent(r.qc_test_nbr)}">${Phai.esc(r.qc_test_nbr)}</a></td>
                <td>${Phai.esc(r.qc_lot ?? '')}</td><td class="num">${Phai.esc(num(r.qc_qty_pass))}</td><td class="num">${Phai.esc(num(r.qc_qty_fail))}</td>
                <td>${Phai.esc(cfg.qcResults?.[r.qc_result] ?? r.qc_result)}</td><td class="text-secondary">${Phai.esc(r.qc_inspector ?? '')}</td></tr>`).join('');
    }
    rcvForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = rcvForm.elements;
        if (await post(rcvForm, '/receive', { date: f.date.value, qty: f.qty.value, scrap: f.scrap.value, loc: f.loc.value, expire: f.expire.value, inspector: f.inspector.value })) {
            await render();
        }
    });

    /**
     * Labour, read only: per routing step the standard hours booked with the receipts (good + scrap) and what they cost
     * in labour and overhead — nobody times anyone; a step's standard is changed on the item's routing.
     */
    function renderLabor() {
        // the plan shows from the start (run time × quantity ordered + set-up); what is booked comes with each receipt
        const ordered = Number(data.head.wo_qty_ord);
        const steps = data.ops.map((o) => {
            const rows = data.labor.filter((l) => Number(l.wol_op) === Number(o.ro_op));
            return {
                o,
                plan: Number(o.ro_run_time) * ordered + Number(o.ro_setup_time),
                hours: rows.reduce((s, l) => s + Number(l.wol_hours), 0),
                labor: rows.reduce((s, l) => s + Number(l.wol_hours) * Number(l.rate), 0),
                ovh: rows.reduce((s, l) => s + Number(l.wol_hours) * Number(l.ovh), 0),
            };
        });
        const h = (v) => Phai.esc(num(round5(v)));
        document.querySelector('#lab-table tbody').innerHTML = steps.map((x) => `
            <tr>
                <td>${x.o.ro_op} · ${Phai.esc(x.o.ro_desc || '')}</td>
                <td>${Phai.esc(x.o.ro_wkctr)} <span class="text-secondary small">${Phai.esc(x.o.wc_desc ?? '')}</span></td>
                <td class="num">${h(x.o.ro_run_time)}</td>
                <td class="num">${h(x.o.ro_setup_time)}</td>
                <td class="num">${h(x.plan)}</td>
                <td class="num">${h(x.hours)}</td>
                <td class="num">${Phai.esc(money(x.labor))}</td>
                <td class="num">${Phai.esc(money(x.ovh))}</td>
            </tr>`).join('') || `<tr><td colspan="8" class="text-center text-secondary py-3">${Phai.esc(Phai.t('empty'))}</td></tr>`;
        const sum = (k) => steps.reduce((s, x) => s + x[k], 0);
        document.querySelector('#lab-table tfoot').innerHTML = steps.length > 1 ? `
            <tr class="fw-semibold"><td colspan="4">${Phai.esc(cfg.text.total)}</td><td class="num">${h(sum('plan'))}</td><td class="num">${h(sum('hours'))}</td>
                <td class="num">${Phai.esc(money(sum('labor')))}</td><td class="num">${Phai.esc(money(sum('ovh')))}</td></tr>` : '';
    }


    /** Standard for the good output against actual, per element; the variance once the order is finished (or closed). */
    function renderCost() {
        const c = data.cost;
        const h = data.head;
        const finished = h.wo_status === 'C' || Number(h.wo_qty_comp) + Number(h.wo_qty_rjct) >= Number(h.wo_qty_ord);
        document.querySelector('#cost [data-c="done"]').textContent = `(${num(c.done)})`;
        const row = (label, unit, std, actual, v, bold) => `
            <tr class="${bold ? 'fw-semibold' : ''}">
                <td>${Phai.esc(label)}</td><td class="num">${Phai.esc(money(unit))}</td><td class="num">${Phai.esc(money(std))}</td>
                <td class="num">${Phai.esc(money(actual))}</td>
                <td class="num ${finished && v > 0 ? 'text-danger' : (finished && v < 0 ? 'text-success' : '')}">${finished ? Phai.esc(money(v)) : ''}</td>
            </tr>`;
        const keys = ['material', 'labor', 'overhead'];
        const sum = (o) => keys.reduce((s, k) => s + Number(o[k]), 0);
        document.querySelector('#cost tbody').innerHTML = keys.map((k) => row(cfg.text.cost_rows[k], c.std[k], c.std_done[k], c[k], c.variance[k])).join('')
            + row(cfg.text.total, c.std.total, sum(c.std_done), sum(c), c.variance.total, true);
        document.querySelector('#cost [data-c="received"]').textContent = money(c.received);
        document.querySelector('#cost [data-c="wip"]').textContent = money(c.wip);
    }

    /** Post one section's form; the server answers with the whole order again. @return the answer, or null on error */
    async function post(form, path, body, message = null) {
        const btn = form.querySelector('[type=submit]');
        Phai.form.errors(form, null);
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post(api() + path, body);
            data = res;
            stock.clear();
            form.querySelectorAll('input[name$=".qty"], input[name=qty], input[name=scrap]').forEach((x) => { x.value = ''; });
            Phai.toast(message ? message(res) : Phai.t('saved'));
            return res;
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
            return null;
        } finally {
            Phai.busy(btn, false);
        }
    }

    // ------------------------------------------------------------ load

    async function render() {
        showStatus();
        await renderMaterials();
        // a released order opens ready to issue: what is left, lot by lot (change or clear before saving)
        if (working() && mode === 'issue' && !allIssued()) await fillAll();
        renderSlips();
        await renderReceive();
        renderLabor();
        renderCost();
        showStatus(); // the section rows were just rebuilt
    }

    async function load() {
        data = await Phai.api.get(api());
        const w = data.head;
        for (const n of ['wo_part', 'wo_qty_ord', 'wo_site', 'wo_rel_date', 'wo_due_date', 'wo_rmks']) {
            h[n].value = n === 'wo_qty_ord' ? plain(w[n]) : (w[n] ?? '');
        }
        if (h.doc_nbr) h.doc_nbr.value = w.wo_nbr;
        Phai.pick.label(h.wo_part, `${w.pt_desc1 ?? ''} (${w.pt_um ?? ''})`);
        document.getElementById('wo-body').hidden = false;
        for (const f of [matForm, rcvForm]) f.elements.date.value = cfg.today;
        if (!rcvForm.elements.inspector.value) rcvForm.elements.inspector.value = cfg.meName || '';
        await render();
    }

    if (cfg.nbr) {
        // the list's pencil opens it ready to edit (?edit=1); the address goes back to the order's own
        load().then(() => {
            if (!new URLSearchParams(location.search).has('edit')) return;
            history.replaceState(null, '', location.pathname);
            if (!editBtn.hidden) editBtn.click();
        });
    } else {
        h.wo_rel_date.value = cfg.today;
        h.wo_due_date.value = cfg.today;
        saveBtn.hidden = !cfg.canAdd;
        h.wo_part.focus();
    }
})();
