/** Receivables aging (views/accounting/receivables.php): click a customer to see its open items. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('ar-config').textContent);
    const table = document.getElementById('aging');
    const tbody = table.querySelector('tbody');
    const money = (v) => (Number(v) ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '');
    const date = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '');
    const keys = ['b0', 'b30', 'b60', 'b90', 'b91', 'total'];

    async function load() {
        const rows = await Phai.api.get('/api/receivables', { q: document.getElementById('q').value.trim() });
        tbody.innerHTML = rows.length ? rows.map((r) => `
            <tr data-cm="${Phai.esc(r.ar_cm_code)}" role="button">
                <td><span class="fw-medium">${Phai.esc(r.ar_cm_code)}</span> <span class="text-secondary">${Phai.esc(r.cm_name ?? '')}</span></td>
                ${keys.map((k) => `<td class="num${k === 'b91' && Number(r[k]) ? ' text-danger' : ''}${k === 'total' ? ' fw-medium' : ''}">${money(r[k])}</td>`).join('')}
            </tr>`).join('') : `<tr><td colspan="7" class="text-center text-secondary py-3">${Phai.esc(cfg.text.empty)}</td></tr>`;
        const sum = Object.fromEntries(keys.map((k) => [k, rows.reduce((s, r) => s + Number(r[k]), 0)]));
        table.querySelector('tfoot').innerHTML = rows.length > 1
            ? `<tr><td></td>${keys.map((k) => `<td class="num">${money(sum[k])}</td>`).join('')}</tr>` : '';
    }

    tbody.addEventListener('click', async (e) => {
        const tr = e.target.closest('tr[data-cm]');
        if (!tr) return;
        const next = tr.nextElementSibling;
        if (next?.dataset.items !== undefined) {
            next.remove();
            return;
        }
        const items = await Phai.api.get('/api/receivables/' + encodeURIComponent(tr.dataset.cm));
        tr.insertAdjacentHTML('afterend', `
            <tr data-items><td colspan="7" class="bg-body-tertiary p-0">
                <table class="table table-sm mb-0">
                    ${items.map((i) => `
                        <tr>
                            <td class="ps-4"><a href="${Phai.url('/invoices/' + encodeURIComponent(i.ar_nbr))}">${Phai.esc(i.ar_nbr)}</a></td>
                            <td>${Phai.esc(date(i.ar_date))}</td>
                            <td>${Phai.esc(date(i.ar_due_date))}</td>
                            <td class="${Number(i.days) > 0 ? 'text-danger' : 'text-secondary'}">${Number(i.days) > 0 ? Phai.esc(cfg.text.days.replace(':n', i.days)) : ''}</td>
                            <td class="num">${money(i.ar_amt)}</td>
                            <td class="num fw-medium">${money(i.balance)}</td>
                        </tr>`).join('')}
                </table>
            </td></tr>`);
    });

    let timer;
    document.getElementById('q').addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(load, 250);
    });
    load();
})();
