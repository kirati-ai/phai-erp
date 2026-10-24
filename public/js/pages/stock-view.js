/*
 * One stock line (views/inventory/view.php): the movements of its warehouse, location and lot,
 * and the actions on it — convert to FG, transfer (value = qty × unit cost), edit.
 */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('view-config').textContent);
    const row = cfg.row;
    const num = (v) => Number(v).toLocaleString('en-US', { maximumFractionDigits: 5 });
    const money = (v) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const bs = window.bootstrap || window.tabler;
    const view = (id) => { location.href = Phai.url('/stock/view') + '?' + new URLSearchParams({ id }); };

    if (cfg.history) PhaiStockHistory.grid({ types: cfg.types, query: () => cfg.filter }).reload();

    async function fillLocations(select, site, skip) {
        const list = (await Phai.api.get('/api/stock/locations', { site })).filter((l) => l.loc_loc !== skip);
        const def = cfg.sites.find((s) => s.si_site === site)?.si_default_loc || '';
        select.innerHTML = list.map((l) => `<option value="${Phai.esc(l.loc_loc)}">${Phai.esc(l.loc_loc)}</option>`).join('');
        select.value = list.some((l) => l.loc_loc === def) ? def : (list[0]?.loc_loc ?? '');
        select.dispatchEvent(new Event('change'));
    }

    /** A modal form: open from its button, submit with send(form) → true closes it. */
    function action(id, open, send) {
        const el = document.getElementById(id);
        if (!el) return;
        const modal = bs.Modal.getOrCreateInstance(el);
        const form = el.querySelector('form');
        document.querySelector(`[data-open="${id}"]`).addEventListener('click', () => {
            Phai.form.errors(form, null);
            open(form);
            modal.show();
        });
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('[type=submit]');
            Phai.form.errors(form, null);
            Phai.busy(btn, true);
            try {
                await send(form);
            } catch (err) {
                if (err.fields) Phai.form.errors(form, err.fields);
            } finally {
                Phai.busy(btn, false);
            }
        });
    }

    // raw material out of this line, finished good in, carrying its value
    action('convert-modal', (f) => {
        f.elements['lines.0.qty'].value = num(row.ld_qty_oh).replace(/,/g, '');
        fillLocations(f.elements['lines.0.to_loc'], row.ld_site);
    }, async (f) => {
        const v = Phai.form.read(f);
        const res = await Phai.api.post('/api/stock/moves', {
            kind: 'convert', date: v.date, site: row.ld_site, remark: v.remark,
            lines: { 0: { part: row.ld_part, loc: row.ld_loc, lot: row.ld_lot, qty: v['lines.0.qty'],
                fg_part: v['lines.0.fg_part'], fg_qty: v['lines.0.fg_qty'], to_loc: v['lines.0.to_loc'] } },
        });
        Phai.toast(cfg.text.posted.replace(':nbr', res.nbr));
        setTimeout(() => location.reload(), 600);
    });

    // to another place: the value follows the quantity at this line's unit cost
    action('transfer-modal', (f) => {
        f.elements.qty.value = num(row.ld_qty_oh).replace(/,/g, '');
        f.elements.qty.dispatchEvent(new Event('input'));
        f.elements.to_site.value = row.ld_site;
        f.elements.to_site.dispatchEvent(new Event('change'));
    }, async (f) => {
        const res = await Phai.api.post(`/api/stock/view/${cfg.id}/transfer`, Phai.form.read(f));
        Phai.toast(cfg.text.posted.replace(':nbr', res.nbr));
        setTimeout(() => view(res.id), 600);
    });
    const tf = document.querySelector('#transfer-modal form');
    if (tf) {
        tf.elements.to_site.addEventListener('change', () => {
            fillLocations(tf.elements.to_loc, tf.elements.to_site.value, tf.elements.to_site.value === row.ld_site ? row.ld_loc : null);
        });
        tf.elements.qty.addEventListener('input', () => {
            const q = Number(tf.elements.qty.value.replace(/,/g, '')) || 0;
            tf.querySelector('[data-value]').textContent = money(q * Number(row.cost));
            Phai.stockShort(tf.elements.qty, row.ld_qty_oh, row.pt_um ?? '');
        });
    }
    // converting more than this line holds: warned under the quantity
    const cf = document.querySelector('#convert-modal form');
    cf?.elements['lines.0.qty'].addEventListener('input', (e) => Phai.stockShort(e.target, row.ld_qty_oh, row.pt_um ?? ''));

    // status, grade, expiry — and for a lot line its number (renamed everywhere) and supplier lot
    action('edit-modal', () => {}, async (f) => {
        const saved = await Phai.api.put(`/api/stock/view/${cfg.id}`, Phai.form.read(f));
        Phai.toast(cfg.text.saved);
        setTimeout(() => view(saved.ld_id), 600); // a line that just got its first lot has a new id
    });
})();
