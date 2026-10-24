/** Monthly ภ.ง.ด.3 / 53 attachment (views/accounting/wht-report.php): pick the form and month, see the payees, print. */
(function () {
    'use strict';

    const form = document.getElementById('form');
    const month = document.getElementById('month');
    const print = document.getElementById('print');
    const table = document.getElementById('pnd-table');
    const money = (v) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const date = (v) => (v ? v.split('-').reverse().join('/') : '');

    async function load() {
        const m = month.value;
        print.href = Phai.url(`/wht-report/${encodeURIComponent(form.value)}/${encodeURIComponent(m)}/print`);
        if (!m) return;
        document.querySelector('[data-files="WR"]').dataset.nbr = `${form.value}~${m}`; // the return as filed, its tax receipt
        const data = await Phai.api.get('/api/wht-report', { form: form.value, month: m });
        table.tBodies[0].innerHTML = data.rows.length ? data.rows.map((r) => `
            <tr>
                <td class="num text-secondary">${r.seq}</td>
                <td>${Phai.esc(r.tax_id ?? '')}</td>
                <td>${Phai.esc(r.branch ?? '')}</td>
                <td>${Phai.esc(r.name ?? '')}</td>
                <td>${Phai.esc(date(r.date))}</td>
                <td>${Phai.esc(r.income ?? '')}</td>
                <td class="num">${Phai.esc(Number(r.pct).toLocaleString('en-US', { maximumFractionDigits: 2 }))}</td>
                <td class="num">${Phai.esc(money(r.base))}</td>
                <td class="num">${Phai.esc(money(r.tax))}</td>
                <td><a href="${Phai.url('/wht/' + encodeURIComponent(r.nbr) + '/print')}" target="_blank" rel="noopener">${Phai.esc(r.nbr)}</a></td>
            </tr>`).join('') : `<tr><td colspan="10" class="text-center text-secondary py-3">${Phai.esc(Phai.t('empty'))}</td></tr>`;
        table.tFoot.innerHTML = `<tr><td colspan="7" class="text-end">${Phai.esc(Phai.t('total'))}</td>
            <td class="num">${Phai.esc(money(data.base))}</td><td class="num">${Phai.esc(money(data.tax))}</td><td></td></tr>`;
    }

    form.addEventListener('change', load);
    month.addEventListener('change', load);
    load();
})();
