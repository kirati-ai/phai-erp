/** Journal entry (views/accounting/journal.php): view any posting, or key in a balanced manual journal. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('journal-config').textContent);
    const form = document.getElementById('journal');
    const el = form.elements;
    const tbody = form.querySelector('#je-lines tbody');
    const $ = (id) => document.getElementById(id);
    const money = (v) => (Number(v) ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '');
    const num = (v) => Number(String(v ?? '').replace(/,/g, '')) || 0;
    const opts = (list, blank) => (blank ? '<option value=""></option>' : '<option value="">—</option>')
        + list.map((o) => `<option value="${Phai.esc(o.value)}">${Phai.esc(o.label)}</option>`).join('');

    function totals(dr, cr) {
        $('je-dr').textContent = money(dr);
        $('je-cr').textContent = money(cr);
        const diff = Math.round((dr - cr) * 100) / 100;
        $('je-diff').innerHTML = diff ? `<span class="text-danger">${Phai.esc(cfg.text.diff)} ${money(Math.abs(diff))}</span>` : '';
    }

    // ------------------------------------------------------------ view a posted entry

    if (cfg.entry) {
        $('je-head').classList.add('d-none');
        (async () => {
            const d = await Phai.api.get('/api/journals/' + encodeURIComponent(cfg.entry));
            const h = d.head;
            if (el.doc_nbr) el.doc_nbr.value = h.ref;
            el.date.value = h.date;
            el.desc.value = h.desc;
            form.querySelectorAll('input').forEach((x) => { x.disabled = true; });
            Phai.viewFields(form, true); // a posted entry reads as plain values (ux/ui.md "View pages")
            const type = cfg.types[h.type] || { text: h.type, color: 'secondary' };
            const badge = $('je-type');
            badge.className = `badge bg-${type.color}-lt`;
            badge.textContent = type.text;
            let dr = 0;
            let cr = 0;
            tbody.innerHTML = d.lines.map((l) => {
                dr += Number(l.dr);
                cr += Number(l.cr);
                return `
                    <tr>
                        <td class="text-nowrap">${Phai.esc(l.gl_acct)} <span class="text-secondary">${Phai.esc(l.ac_desc_th ?? '')}</span></td>
                        <td>${Phai.esc(l.gl_cc ?? '')}</td>
                        <td>${Phai.esc(l.gl_desc ?? '')}</td>
                        <td class="num">${money(l.dr)}</td>
                        <td class="num">${money(l.cr)}</td>
                        <td></td>
                    </tr>`;
            }).join('');
            totals(dr, cr);
            const rev = $('je-reverse');
            rev.hidden = !h.reversible;
            rev.onclick = async () => {
                if (!(await Phai.confirm(cfg.text.confirm))) return;
                const res = await Phai.api.post('/api/journals/' + encodeURIComponent(cfg.entry) + '/reverse');
                location.href = Phai.url('/journals/' + encodeURIComponent(res.nbr));
            };
        })();
        return;
    }

    // ------------------------------------------------------------ new manual journal

    el.date.value = cfg.today < cfg.periodStart ? cfg.periodStart : cfg.today;
    $('je-save').hidden = false;
    let row = 0;

    function addLine() {
        const i = row++;
        tbody.insertAdjacentHTML('beforeend', `
            <tr>
                <td><select class="form-select form-select-sm" name="lines.${i}.acct">${opts(cfg.accounts, true)}</select></td>
                <td><select class="form-select form-select-sm" name="lines.${i}.cc">${opts(cfg.costCenters)}</select></td>
                <td><input type="text" class="form-control form-control-sm" name="lines.${i}.desc"></td>
                <td><input type="text" class="form-control form-control-sm text-end" name="lines.${i}.dr" data-dr inputmode="decimal"></td>
                <td><input type="text" class="form-control form-control-sm text-end" name="lines.${i}.cr" data-cr inputmode="decimal"></td>
                <td><button type="button" class="btn btn-sm btn-ghost-danger px-1" data-remove><i class="ti ti-x"></i></button></td>
            </tr>`);
    }

    function total() {
        let dr = 0;
        let cr = 0;
        tbody.querySelectorAll('[data-dr]').forEach((x) => { dr += num(x.value); });
        tbody.querySelectorAll('[data-cr]').forEach((x) => { cr += num(x.value); });
        totals(dr, cr);
        return dr - cr;
    }

    $('je-add').addEventListener('click', addLine);
    tbody.addEventListener('click', (e) => {
        if (e.target.closest('[data-remove]')) {
            e.target.closest('tr').remove();
            total();
        }
    });
    tbody.addEventListener('input', (e) => {
        // a line is either debit or credit
        const tr = e.target.closest('tr');
        if (e.target.matches('[data-dr]') && e.target.value) tr.querySelector('[data-cr]').value = '';
        if (e.target.matches('[data-cr]') && e.target.value) tr.querySelector('[data-dr]').value = '';
        total();
    });
    // a new line offers the amount that balances the entry
    tbody.addEventListener('focusin', (e) => {
        if (!e.target.matches('[data-dr], [data-cr]')) return;
        const tr = e.target.closest('tr');
        if (tr.querySelector('[data-dr]').value || tr.querySelector('[data-cr]').value) return;
        const diff = Math.round(total() * 100) / 100;
        if (diff > 0 && e.target.matches('[data-cr]')) e.target.value = diff.toFixed(2);
        if (diff < 0 && e.target.matches('[data-dr]')) e.target.value = (-diff).toFixed(2);
        total();
    });
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') e.preventDefault();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $('je-save');
        const lines = {};
        tbody.querySelectorAll('tr').forEach((tr) => {
            const get = (n) => tr.querySelector(`[name$=".${n}"]`);
            const i = get('acct').name.split('.')[1];
            lines[i] = {
                acct: get('acct').value, cc: get('cc').value, desc: get('desc').value,
                dr: get('dr').value.replace(/,/g, ''), cr: get('cr').value.replace(/,/g, ''),
            };
        });
        Phai.form.errors(form, null);
        Phai.busy(btn, true);
        try {
            const payload = { date: el.date.value, desc: el.desc.value, lines };
            if (el.doc_nbr) { payload.doc_nbr = el.doc_nbr.value; payload.doc_type = 'JE'; }
            const res = await Phai.api.post('/api/journals', payload);
            location.href = Phai.url('/journals/' + encodeURIComponent(res.nbr));
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
            Phai.busy(btn, false);
        }
    });

    addLine();
    addLine();
    total();
    if (!cfg.entry) el.desc.focus();
})();
