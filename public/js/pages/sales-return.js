/* New sales return (views/sales/return-new.php): the shipment's lines with what can still come back. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('sr-config').textContent);
    const form = document.getElementById('sr-form');
    const el = form.elements;
    const body = form.querySelector('#sr-lines tbody');
    const qty = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 5 });
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    async function loadShipment() {
        const sh = el.sh.value.trim().toUpperCase();
        el.sh.value = sh;
        body.innerHTML = '';
        document.getElementById('sr-customer').textContent = '—';
        if (!sh) return;
        let d;
        try {
            d = await Phai.api.get('/api/sales-returns/shipment/' + encodeURIComponent(sh));
        } catch (_) {
            return;
        }
        document.getElementById('sr-customer').textContent = `${d.head.sh_cm_code} · ${d.head.cm_name ?? ''}`;
        const rows = d.lines.filter((l) => Number(l.returnable) > 0);
        body.innerHTML = rows.length ? rows.map((l) => `
            <tr>
                <td class="text-secondary">${Phai.esc(l.shd_line)}</td><td>${Phai.esc(l.shd_part)}</td><td>${Phai.esc(l.pt_desc1 ?? '')}</td>
                <td>${Phai.esc(l.shd_loc ?? '')}</td><td>${Phai.esc(l.shd_lot ?? '')}</td>
                <td class="num">${qty(l.returnable)} <span class="text-secondary">${Phai.esc(l.shd_um)}</span></td>
                <td class="num">${money(l.cost)}</td>
                <td><input class="form-control form-control-sm text-end" name="lines.${Phai.esc(l.shd_line)}.qty" inputmode="decimal"></td>
            </tr>`).join('') : `<tr><td colspan="8" class="text-center text-secondary py-3">${Phai.esc(cfg.text.none)}</td></tr>`;
    }

    Phai.pick.source('returnable-shs', { url: '/api/sales-returns/shipments', value: 'sh_nbr', label: (s) => s.sh_cm_code + ' · ' + (s.cm_name ?? '') + ' · ' + s.sh_so_nbr });
    el.sh.addEventListener('change', loadShipment);
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const lines = {};
        body.querySelectorAll('input[name^="lines."]').forEach((i) => { lines[i.name.split('.')[1]] = i.value; });
        const payload = { sh: el.sh.value, sr_date: el.sr_date.value, sr_reason: el.sr_reason.value, lines };
        if (el.doc_nbr) { payload.doc_nbr = el.doc_nbr.value; payload.doc_type = 'SR'; }
        Phai.form.errors(form, null);
        try {
            const res = await Phai.api.post('/api/sales-returns', payload);
            location.href = Phai.url('/sales-returns/' + encodeURIComponent(res.nbr));
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
        }
    });
    loadShipment();
})();
