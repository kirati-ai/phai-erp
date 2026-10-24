/** Monthly VAT report (views/accounting/vat.php): pick a month, see the tax invoices and totals, print. */
(function () {
    'use strict';

    const box = document.getElementById('vat');
    const side = box.dataset.side;
    const month = document.getElementById('month');
    const print = document.getElementById('print');
    const table = document.getElementById('vat-table');
    const money = (v) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const date = (v) => (v ? v.split('-').reverse().join('/') : '');

    async function load() {
        const m = month.value;
        print.href = Phai.url(`/vat-${side}/${encodeURIComponent(m)}/print`);
        if (!m) return;
        const data = await Phai.api.get(`/api/vat-${side}`, { month: m });
        table.tBodies[0].innerHTML = data.rows.length ? data.rows.map((r, i) => `
            <tr>
                <td class="num text-secondary">${i + 1}</td>
                <td>${Phai.esc(date(r.date))}</td>
                <td class="fw-medium">${Phai.esc(r.nbr ?? '')}</td>
                <td>${Phai.esc(r.name ?? '')}</td>
                <td>${Phai.esc(r.tax_id ?? '')}</td>
                <td>${Phai.esc(r.branch ?? '')}</td>
                <td class="num">${Phai.esc(money(r.net))}</td>
                <td class="num">${Phai.esc(money(r.tax))}</td>
            </tr>`).join('') : `<tr><td colspan="8" class="text-center text-secondary py-3">${Phai.esc(Phai.t('empty'))}</td></tr>`;
        table.tFoot.innerHTML = `<tr><td colspan="6" class="text-end">${Phai.esc(Phai.t('total'))}</td>
            <td class="num">${Phai.esc(money(data.net))}</td><td class="num">${Phai.esc(money(data.tax))}</td></tr>`;
    }

    month.addEventListener('change', load);
    load();
})();
