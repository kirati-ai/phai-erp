/* New purchase return (views/purchase/return-new.php): the receipt's lines with what can still be sent back. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('rt-config').textContent);
    const form = document.getElementById('rt-form');
    const el = form.elements;
    const body = form.querySelector('#rt-lines tbody');
    const qty = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 5 });
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    async function loadReceipt() {
        const rc = el.rc.value.trim().toUpperCase();
        el.rc.value = rc;
        body.innerHTML = '';
        document.getElementById('rt-vendor').textContent = '—';
        if (!rc) return;
        let d;
        try {
            d = await Phai.api.get('/api/purchase-returns/receipt/' + encodeURIComponent(rc));
        } catch (_) {
            return;
        }
        document.getElementById('rt-vendor').textContent = `${d.head.rc_vd_code} · ${d.head.vd_name ?? ''}`;
        const rows = d.lines.filter((l) => Number(l.returnable) > 0);
        body.innerHTML = rows.length ? rows.map((l) => `
            <tr>
                <td class="text-secondary">${Phai.esc(l.rcd_line)}</td><td>${Phai.esc(l.rcd_part)}</td><td>${Phai.esc(l.pt_desc1 ?? '')}</td>
                <td>${Phai.esc(l.rcd_loc ?? '')}</td><td>${Phai.esc(l.rcd_lot ?? '')}</td>
                <td class="num">${qty(l.returnable)} <span class="text-secondary">${Phai.esc(l.rcd_um)}</span></td>
                <td class="num">${money(l.price)}</td>
                <td><input class="form-control form-control-sm text-end" name="lines.${Phai.esc(l.rcd_line)}.qty" inputmode="decimal"></td>
            </tr>`).join('') : `<tr><td colspan="8" class="text-center text-secondary py-3">${Phai.esc(cfg.text.none)}</td></tr>`;
    }

    Phai.pick.source('returnable-rcs', { url: '/api/purchase-returns/receipts', value: 'rc_nbr', label: (r) => r.rc_vd_code + ' · ' + (r.vd_name ?? '') + ' · ' + r.rc_po_nbr });
    el.rc.addEventListener('change', loadReceipt);
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const lines = {};
        body.querySelectorAll('input[name^="lines."]').forEach((i) => { lines[i.name.split('.')[1]] = i.value; });
        const payload = { rc: el.rc.value, rt_date: el.rt_date.value, rt_reason: el.rt_reason.value, lines };
        if (el.doc_nbr) { payload.doc_nbr = el.doc_nbr.value; payload.doc_type = 'RT'; }
        Phai.form.errors(form, null);
        try {
            const res = await Phai.api.post('/api/purchase-returns', payload);
            location.href = Phai.url('/purchase-returns/' + encodeURIComponent(res.nbr));
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
        }
    });
    loadReceipt();
})();
