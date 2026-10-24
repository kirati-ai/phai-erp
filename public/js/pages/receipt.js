(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('receipt-config').textContent);
    const form = document.getElementById('receipt');
    const el = form.elements;
    const tbody = form.querySelector('#rc-lines tbody');
    const vendorText = document.getElementById('rc-vendor');
    const num = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 5 });
    const locations = new Map();

    async function siteLocations(site) {
        if (!locations.has(site)) locations.set(site, await Phai.api.get('/api/stock/locations', { site }));
        return locations.get(site);
    }

    async function fillLocations() {
        const site = el.rc_site.value;
        const list = await siteLocations(site);
        const def = cfg.sites.find((s) => s.si_site === site)?.si_default_loc || '';
        tbody.querySelectorAll('select[name$=".loc"]').forEach((sel) => {
            const keep = sel.value;
            sel.innerHTML = list.map((l) => `<option value="${Phai.esc(l.loc_loc)}">${Phai.esc(l.loc_loc)}</option>`).join('');
            sel.value = list.some((l) => l.loc_loc === keep) ? keep : (list.some((l) => l.loc_loc === def) ? def : (list[0]?.loc_loc ?? ''));
        });
    }

    // ------------------------------------------------------------ new receipt

    Phai.pick.source('open-pos', { url: '/api/receipts/open-pos', value: 'po_nbr', label: (p) => p.po_vd_code + ' · ' + (p.vd_name ?? '') });
    el.po.addEventListener('change', () => loadPo(el.po.value.trim().toUpperCase()));
    el.rc_site.addEventListener('change', fillLocations);

    async function loadPo(po) {
        el.po.value = po;
        tbody.innerHTML = '';
        vendorText.textContent = '';
        if (!po) return;
        let data;
        try {
            data = await Phai.api.get('/api/receipts/po/' + encodeURIComponent(po));
        } catch (err) {
            return;
        }
        const h = data.head;
        vendorText.textContent = `${h.po_vd_code} · ${h.vd_name ?? ''}`;
        if (h.po_ship_to) el.rc_site.value = h.po_ship_to;
        tbody.innerHTML = data.lines.map((l) => {
            // items not kept in stock (services, free text) go to expense: no location or lot
            const stocked = Number(l.pt_inventory) === 1;
            const lot = stocked && (l.pt_lot_ser === 'L' || l.pt_lot_ser === 'S');
            const n = Phai.esc(`lines.${l.pod_line}`);
            return `
                <tr data-open="${Number(l.open_qty)}">
                    <td class="text-secondary small">${Phai.esc(l.pod_line)}</td>
                    <td class="fw-medium">${Phai.esc(l.pod_part)}</td>
                    <td>${Phai.esc(l.pod_desc ?? '')}</td>
                    <td class="num">${Phai.esc(num(l.pod_qty_ord))}</td>
                    <td class="num">${Phai.esc(num(l.pod_qty_rcvd))}</td>
                    <td class="num">${Phai.esc(num(l.open_qty))}</td>
                    <td><input class="form-control form-control-sm text-end" name="${n}.qty" inputmode="decimal" autocomplete="off" data-over="${Number(l.max_qty)}" data-um="${Phai.esc(l.pod_um)}">
                        <div class="small text-warning" data-over-text hidden></div></td>
                    <td class="text-secondary">${Phai.esc(l.pod_um)}</td>
                    <td>${stocked ? `<select class="form-select form-select-sm" name="${n}.loc"></select>` : '<span class="text-secondary">–</span>'}</td>
                    <td><input class="form-control form-control-sm" name="${n}.lot" ${lot ? '' : 'disabled'} autocomplete="off"></td>
                    <td><input type="date" class="form-control form-control-sm" name="${n}.expire" ${lot ? '' : 'disabled'}></td>
                    <td><input class="form-control form-control-sm text-end" name="${n}.pack" value="1" inputmode="decimal" autocomplete="off" ${stocked ? '' : 'disabled'}></td>
                </tr>`;
        }).join('');
        await fillLocations();
        fillLots();
        tbody.querySelector('input')?.focus();
    }

    // the lot of lot-controlled lines is the vendor's document number (tax invoice or delivery note), unless typed over
    let lastInv = '';
    function fillLots() {
        const inv = el.rc_inv_nbr.value.trim();
        tbody.querySelectorAll('input[name$=".lot"]:not(:disabled)').forEach((x) => {
            if (x.value === '' || x.value === lastInv) x.value = inv;
        });
        lastInv = inv;
    }
    el.rc_inv_nbr.addEventListener('input', fillLots);

    document.getElementById('rc-all').addEventListener('click', () => {
        tbody.querySelectorAll('tr[data-open]').forEach((tr) => {
            tr.querySelector('[name$=".qty"]').value = tr.dataset.open;
        });
    });

    // more than still open (beyond the tolerance) is allowed, with a warning under the box
    tbody.addEventListener('input', (e) => {
        if (!e.target.name?.endsWith('.qty')) return;
        const tr = e.target.closest('tr');
        const over = (Number(e.target.value.replace(/,/g, '')) || 0) - Number(tr.dataset.open);
        const hint = tr.querySelector('[data-over-text]');
        const warn = (Number(e.target.value.replace(/,/g, '')) || 0) > Number(e.target.dataset.over);
        hint.hidden = !warn;
        hint.textContent = warn ? cfg.text.over.replace(':qty', `${num(over)} ${e.target.dataset.um}`) : '';
    });

    // Enter moves down the "receive now" column.
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
        const body = { lines: {} };
        for (const n of ['po', 'rc_date', 'rc_site', 'rc_inv_nbr', 'rc_inv_date', 'rc_remarks']) body[n] = el[n].value;
        if (el.doc_nbr) { body.doc_nbr = el.doc_nbr.value; body.doc_type = 'RC'; }
        tbody.querySelectorAll('input[name$=".qty"]').forEach((q) => {
            const line = q.name.split('.')[1];
            const v = (f) => el[`lines.${line}.${f}`]?.value ?? ''; // non-stock lines have no location
            body.lines[line] = { qty: q.value, loc: v('loc'), lot: v('lot'), expire: v('expire'), pack: v('pack') };
        });
        const btn = document.getElementById('rc-save');
        Phai.form.errors(form, null);
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post('/api/receipts', body);
            Phai.toast(res.notice || cfg.text.saved.replace(':nbr', res.nbr), res.notice ? 'warning' : 'success');
            setTimeout(() => { location.href = Phai.url('/receipts/' + encodeURIComponent(res.nbr)); }, res.notice ? 2500 : 600);
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
            Phai.busy(btn, false);
        }
    });

    if (cfg.po) loadPo(cfg.po.toUpperCase());
    else el.po?.focus();
})();
