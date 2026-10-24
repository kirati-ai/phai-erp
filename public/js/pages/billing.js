(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('billing-config').textContent);
    const form = document.getElementById('billing');
    const el = form.elements;
    const tbody = form.querySelector('#bl-lines tbody');
    const totalCell = document.getElementById('bl-total');
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const date = (v) => (v ? v.split('-').reverse().join('/') : '');

    function total() {
        let sum = 0;
        let vat = 0;
        tbody.querySelectorAll('tr[data-bal]').forEach((tr) => {
            const box = tr.querySelector('input[type=checkbox]');
            if (!box || box.checked) {
                sum += Number(tr.dataset.bal);
                vat += Number(tr.dataset.vat || 0);
            }
        });
        totalCell.textContent = money(sum);
        document.getElementById('bl-net').textContent = money(sum - vat);
        document.getElementById('bl-vat').textContent = money(vat);
    }

    // ------------------------------------------------------------ new billing note

    el.bl_date.value = cfg.today;
    document.getElementById('bl-save').hidden = false;
    el.customer.focus();

    // want: invoices to tick (from the queue on the list page); none = tick all
    async function loadCustomer(want = null) {
        const code = el.customer.value.trim().toUpperCase();
        el.customer.value = code;
        const found = code ? (await Phai.api.get('/api/sales/customers', { q: code })).find((c) => c.cm_code === code) : null;
        const rows = found ? await Phai.api.get('/api/billings/open-invoices', { customer: code }) : [];
        tbody.innerHTML = rows.length ? rows.map((r) => {
            const bal = Number(r.ih_tot_amt) - Number(r.ih_paid_amt);
            // the VAT in what is billed: the invoice's VAT share of its balance
            const vat = Number(r.ih_tot_amt) ? Math.round(bal * Number(r.ih_tax_amt) / Number(r.ih_tot_amt) * 100) / 100 : 0;
            return `
                <tr data-bal="${bal}" data-vat="${vat}">
                    <td><input type="checkbox" class="form-check-input m-0" value="${Phai.esc(r.ih_inv_nbr)}"${!want || want.includes(r.ih_inv_nbr) ? ' checked' : ''}></td>
                    <td>${Phai.esc(r.ih_inv_nbr)}${r.ih_curr !== cfg.base ? ` <span class="text-secondary small">${Phai.esc(r.ih_curr)}</span>` : ''}</td>
                    <td>${Phai.esc(date(r.ih_inv_date))}</td>
                    <td>${Phai.esc(date(r.ih_due_date))}</td>
                    <td class="num">${Phai.esc(money(r.ih_tot_amt))}</td>
                    <td class="num">${Phai.esc(money(bal))}</td>
                </tr>`;
        }).join('') : (found ? `<tr><td colspan="6" class="text-center text-secondary py-3">${Phai.esc(cfg.text.none)}</td></tr>` : '');
        // default payment date: the latest due date on the note
        const due = rows.filter((r) => !want || want.includes(r.ih_inv_nbr)).map((r) => r.ih_due_date).filter(Boolean).sort().pop();
        if (due) el.bl_due_date.value = due;
        total();
        return found;
    }
    el.customer.addEventListener('change', () => loadCustomer());

    // opened from the queue on the list page: ?customer=C1&invoices=INV1,INV2 ticks those invoices
    const pre = new URLSearchParams(location.search);
    if (pre.get('customer')) {
        el.customer.value = pre.get('customer');
        loadCustomer(pre.get('invoices') ? pre.get('invoices').split(',') : null).then((found) => {
            if (found) Phai.pick.label(el.customer, found.cm_name);
        });
    }

    document.getElementById('bl-all').addEventListener('change', (e) => {
        tbody.querySelectorAll('input[type=checkbox]').forEach((b) => { b.checked = e.target.checked; });
        total();
    });
    tbody.addEventListener('change', total);
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') e.preventDefault();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('bl-save');
        const body = {
            customer: el.customer.value, bl_date: el.bl_date.value, bl_due_date: el.bl_due_date.value, bl_remarks: el.bl_remarks.value,
            invoices: [...tbody.querySelectorAll('input[type=checkbox]:checked')].map((b) => b.value),
        };
        if (el.doc_nbr) { body.doc_nbr = el.doc_nbr.value; body.doc_type = 'BL'; }
        Phai.form.errors(form, null);
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post('/api/billings', body);
            location.href = Phai.url('/billings/' + encodeURIComponent(res.nbr));
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
            Phai.busy(btn, false);
        }
    });
})();
