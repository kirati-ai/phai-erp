(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('shipment-config').textContent);
    const form = document.getElementById('shipment');
    const el = form.elements;
    const tbody = form.querySelector('#sh-lines tbody');
    const customer = document.getElementById('sh-customer');
    const num = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 5 });
    const locations = new Map();
    const stock = new Map(); // site|part -> [{loc, lot, qty, expire}]

    async function siteLocations(site) {
        if (!locations.has(site)) locations.set(site, await Phai.api.get('/api/stock/locations', { site }));
        return locations.get(site);
    }
    async function onHand(site, part) {
        const key = site + '|' + part;
        if (!stock.has(key)) stock.set(key, await Phai.api.get('/api/stock/onhand', { site, part }));
        return stock.get(key);
    }

    // ------------------------------------------------------------ new shipment

    Phai.pick.source('open-sos', { url: '/api/shipments/open-sos', value: 'so_nbr', label: (s) => s.so_cm_code + ' · ' + (s.cm_name ?? '') });
    el.so.addEventListener('change', () => loadSo(el.so.value.trim().toUpperCase()));

    /** Lots on hand at the chosen location, and the quantity there. */
    async function refresh(tr) {
        const site = tr.dataset.site;
        const part = tr.dataset.part;
        const loc = tr.querySelector('[name$=".loc"]').value;
        const lotInput = tr.querySelector('[name$=".lot"]');
        const rows = (await onHand(site, part)).filter((r) => r.loc === loc);
        if (tr.dataset.lot === '1') {
            Phai.pick.items(lotInput, rows.map((r) => ({ value: r.lot, label: num(r.qty) + (r.expire ? ' · ' + r.expire : '') })));
            // the first lot (oldest expiry) that covers what is left to ship, else simply the first
            if (!lotInput.value && rows.length) lotInput.value = (rows.find((r) => Number(r.qty) >= Number(tr.dataset.open)) ?? rows[0]).lot;
        }
        const here = rows.filter((r) => tr.dataset.lot !== '1' || r.lot === lotInput.value).reduce((s, r) => s + Number(r.qty), 0);
        tr.querySelector('[data-onhand]').textContent = num(here);
        tr.dataset.here = here;
        warn(tr);
    }

    const round5 = (v) => Math.round(Number(v) * 1e5) / 1e5;
    const plain = (v) => String(round5(v));
    let splitSeq = 0;
    const lineRows = (line) => [...tbody.querySelectorAll(`tr[data-line="${line}"]`)];

    /** Another row of the same order line: its own location / lot / quantity, sent as lines.<line>~<n>. */
    function splitRow(main) {
        const key = `${main.dataset.line}~${++splitSeq}`;
        const tr = main.cloneNode(true);
        tr.classList.add('sh-split');
        tr.dataset.key = key;
        delete tr.dataset.here;
        tr.querySelectorAll('[name]').forEach((x) => { x.name = x.name.replace(/^lines\.[^.]+\./, `lines.${key}.`); x.value = ''; });
        tr.querySelectorAll('.invalid-feedback, .phai-short').forEach((x) => x.remove());
        tr.querySelectorAll('.is-invalid').forEach((x) => x.classList.remove('is-invalid'));
        tr.querySelectorAll('.phai-pick-btn').forEach((x) => x.remove()); // the copied box: the new input gets its own
        tr.querySelectorAll('.phai-pick-src').forEach((x) => x.classList.remove('phai-pick-src'));
        for (const i of [0, 1, 2, 3, 4]) tr.cells[i].textContent = '';
        tr.cells[0].innerHTML = '<button type="button" class="btn btn-sm btn-ghost-secondary p-0" data-unsplit><i class="ti ti-x"></i></button>';
        const rows = lineRows(main.dataset.line);
        rows[rows.length - 1].after(tr);
        tr.querySelector('select').innerHTML = main.querySelector('select').innerHTML;
        return tr;
    }

    /**
     * Ship this much of the order line: lot by lot (location, then oldest expiry) until covered, one row each —
     * still short, the last row takes the rest and says so.
     */
    async function allocate(main, qty) {
        lineRows(main.dataset.line).slice(1).forEach((x) => x.remove());
        const qtyInput = main.querySelector('[name$=".qty"]');
        qty = round5(qty);
        if (!qty || !main.hasAttribute('data-stocked')) {
            qtyInput.value = qty ? plain(qty) : '';
            if (main.hasAttribute('data-stocked')) await refresh(main); else warn(main);
            return;
        }
        let need = qty;
        const take = [];
        for (const r of await onHand(main.dataset.site, main.dataset.part)) {
            if (need <= 0) break;
            if (Number(r.qty) <= 0) continue;
            const q = round5(Math.min(need, Number(r.qty)));
            take.push({ loc: r.loc, lot: r.lot, q });
            need = round5(need - q);
        }
        if (!take.length) take.push({ loc: main.querySelector('select').value, lot: main.querySelector('[name$=".lot"]').value, q: 0 });
        take[take.length - 1].q = round5(take[take.length - 1].q + Math.max(need, 0));
        for (const [i, t] of take.entries()) {
            const tr = i === 0 ? main : splitRow(main);
            tr.querySelector('select').value = t.loc;
            if (tr.dataset.lot === '1') tr.querySelector('[name$=".lot"]').value = t.lot ?? '';
            tr.querySelector('[name$=".qty"]').value = plain(t.q);
            await refresh(tr);
        }
    }

    /** Not enough at the chosen location / lot: say so under the quantity before saving (the save is refused anyway). */
    function warn(tr) {
        if (!tr.hasAttribute('data-stocked') || tr.dataset.here === undefined) return;
        const here = Number(tr.dataset.here);
        Phai.stockShort(tr.querySelector('[name$=".qty"]'), here, tr.dataset.um);
        // the on-hand figure turns red while it cannot cover what is still to ship
        const cell = tr.querySelector('[data-onhand]');
        const want = Number(tr.querySelector('[name$=".qty"]').value || tr.dataset.open);
        cell.classList.toggle('text-danger', here < want);
        cell.classList.toggle('fw-bold', here < want);
        cell.classList.toggle('text-secondary', here >= want);
    }

    async function loadSo(so) {
        el.so.value = so;
        tbody.innerHTML = '';
        customer.textContent = '';
        stock.clear();
        if (!so) return;
        let d;
        try {
            d = await Phai.api.get('/api/shipments/so/' + encodeURIComponent(so));
        } catch (err) {
            return;
        }
        customer.textContent = `${d.head.so_cm_code} · ${d.head.cm_name ?? ''}`;
        tbody.innerHTML = d.lines.map((l) => {
            // items not kept in stock (services, free text): no location, lot or on-hand
            const stocked = Number(l.pt_inventory) === 1;
            const lot = stocked && (l.pt_lot_ser === 'L' || l.pt_lot_ser === 'S');
            const n = `lines.${l.sod_line}`;
            if (!stocked) {
                return `
                <tr data-line="${l.sod_line}" data-open="${Number(l.open_qty)}">
                    <td class="text-secondary small">${l.sod_line}</td>
                    <td class="fw-medium">${Phai.esc(l.sod_part)}</td>
                    <td>${Phai.esc(l.sod_desc ?? '')}</td>
                    <td class="num">${Phai.esc(num(l.sod_qty_ord))}</td>
                    <td class="num">${Phai.esc(num(l.open_qty))}</td>
                    <td><input class="form-control form-control-sm text-end" name="${n}.qty" inputmode="decimal" autocomplete="off"></td>
                    <td class="text-secondary">${Phai.esc(l.sod_um)}</td>
                    <td class="text-secondary">–</td><td class="text-secondary">–</td><td></td>
                </tr>`;
            }
            return `
                <tr data-stocked data-line="${l.sod_line}" data-open="${Number(l.open_qty)}" data-site="${Phai.esc(l.sod_site)}" data-part="${Phai.esc(l.sod_part)}" data-lot="${lot ? 1 : 0}" data-um="${Phai.esc(l.sod_um)}">
                    <td class="text-secondary small">${l.sod_line}</td>
                    <td class="fw-medium">${Phai.esc(l.sod_part)}</td>
                    <td>${Phai.esc(l.sod_desc ?? '')}</td>
                    <td class="num">${Phai.esc(num(l.sod_qty_ord))}</td>
                    <td class="num">${Phai.esc(num(l.open_qty))}</td>
                    <td><input class="form-control form-control-sm text-end" name="${n}.qty" inputmode="decimal" autocomplete="off"></td>
                    <td class="text-secondary">${Phai.esc(l.sod_um)}</td>
                    <td><select class="form-select form-select-sm" name="${n}.loc"></select></td>
                    <td><input class="form-control form-control-sm" name="${n}.lot" data-pick="items" ${lot ? '' : 'disabled'} autocomplete="off"></td>
                    <td class="num small text-secondary" data-onhand></td>
                </tr>`;
        }).join('');
        for (const tr of tbody.querySelectorAll('tr[data-stocked]')) {
            const list = await siteLocations(tr.dataset.site);
            const sel = tr.querySelector('select');
            sel.innerHTML = list.map((x) => `<option value="${Phai.esc(x.loc_loc)}">${Phai.esc(x.loc_loc)}</option>`).join('');
            // start at the location that has this item, if any
            const all = await onHand(tr.dataset.site, tr.dataset.part);
            const has = all.find((r) => Number(r.qty) >= Number(tr.dataset.open)) ?? all.find((r) => Number(r.qty) > 0);
            if (has) sel.value = has.loc;
            await refresh(tr);
        }
        // everything still to ship that is on hand goes out at once, lot by lot; what is not there stays for a later shipment
        for (const tr of tbody.querySelectorAll('tr[data-line]')) {
            const open = Number(tr.dataset.open);
            const have = tr.hasAttribute('data-stocked')
                ? (await onHand(tr.dataset.site, tr.dataset.part)).reduce((s, r) => s + Math.max(0, Number(r.qty)), 0)
                : open;
            await allocate(tr, Math.min(open, have));
        }
        tbody.querySelector('input')?.focus();
    }

    tbody.addEventListener('input', (e) => {
        if (e.target.name?.endsWith('.qty')) warn(e.target.closest('tr'));
    });
    tbody.addEventListener('change', (e) => {
        const tr = e.target.closest('tr');
        if (/\.(loc|lot)$/.test(e.target.name)) refresh(tr);
        // the quantity of the line's first row is the whole quantity: the lots are chosen for it
        if (e.target.name?.endsWith('.qty') && !tr.classList.contains('sh-split')) allocate(tr, e.target.value);
    });
    tbody.addEventListener('click', (e) => {
        if (e.target.closest('[data-unsplit]')) e.target.closest('tr').remove();
    });
    tbody.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const name = e.target.name.split('.').pop();
        e.target.closest('tr').nextElementSibling?.querySelector(`[name$=".${name}"]`)?.focus();
    });
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.target.closest('tbody') && e.target.tagName !== 'BUTTON') e.preventDefault();
    });


    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = {
            so: el.so.value, sh_date: el.sh_date.value, sh_ship_via: el.sh_ship_via.value, sh_tracking: el.sh_tracking.value,
            lines: {},
        };
        if (el.doc_nbr) { body.doc_nbr = el.doc_nbr.value; body.doc_type = 'SH'; }
        tbody.querySelectorAll('input[name$=".qty"]').forEach((q) => {
            const line = q.name.split('.')[1]; // "<order line>" or "<order line>~<n>" for its other lots
            body.lines[line] = { qty: q.value, loc: el[`lines.${line}.loc`]?.value ?? '', lot: el[`lines.${line}.lot`]?.value ?? '' };
        });
        // stock goes out and a shipment cannot be undone: say what will happen first
        const n = new Set(Object.entries(body.lines).filter(([, l]) => Number(l.qty) > 0).map(([k]) => k.split('~')[0])).size;
        if (n && !(await Phai.confirm(cfg.text.confirm.replace(':n', n)))) return;
        const btn = document.getElementById('sh-save');
        Phai.form.errors(form, null);
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post('/api/shipments', body);
            Phai.toast(cfg.text.saved.replace(':nbr', res.nbr));
            // shipped: its invoice next, filled in, to check and change before it is saved
            setTimeout(() => {
                location.href = Phai.url(cfg.canInvoice ? '/invoices/new?sh=' + encodeURIComponent(res.nbr) : '/shipments/' + encodeURIComponent(res.nbr));
            }, 700);
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
            Phai.busy(btn, false);
        }
    });

    if (cfg.so) loadSo(cfg.so.toUpperCase());
    else el.so?.focus();
})();
