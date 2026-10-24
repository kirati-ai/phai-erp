/* งบการเงิน (views/accounting/statements.php): balance sheet at "to", income statement over from–to. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('fs-config').textContent);
    const T = cfg.text;
    const $ = (id) => document.getElementById(id);
    const money = (v) => {
        const n = Number(v || 0);
        const s = Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return n < 0 ? `(${s})` : s;
    };
    const date = (v) => v.split('-').reverse().join('/');

    const head = (text) => `<tr><td colspan="2" class="fw-semibold pt-2">${Phai.esc(text)}</td></tr>`;
    const line = (r) => `<tr><td class="ps-4">${r.acct ? `<span class="text-secondary">${Phai.esc(r.acct)}</span> ` : ''}${Phai.esc(r.name)}</td><td class="num">${money(r.amount)}</td></tr>`;
    const total = (text, v, strong) => `<tr class="${strong ? 'fw-semibold table-primary' : 'fw-semibold'}"><td>${Phai.esc(text)}</td><td class="num">${money(v)}</td></tr>`;
    const block = (d, key) => d.sections[key].length ? head(T[key]) + d.sections[key].map(line).join('') + total(T.total + T[key], d.totals[key]) : '';

    async function load() {
        const d = await Phai.api.get('/api/financial-statements', { from: $('from').value, to: $('to').value });
        $('bs-date').textContent = T.as_of.replace(':date', date(d.to));
        $('pl-range').textContent = `${date(d.from)} – ${date(d.to)}`;
        $('bs').querySelector('tbody').innerHTML =
            block(d, 'current_assets') + block(d, 'noncurrent_assets') + total(T.assets, d.assets, true)
            + block(d, 'current_liabilities') + block(d, 'noncurrent_liabilities') + total(T.liabilities, d.liabilities)
            + block(d, 'equity') + total(T.liab_equity, d.liabilities + d.totals.equity, true);
        $('pl').querySelector('tbody').innerHTML =
            block(d, 'revenue') + block(d, 'cost_of_sales') + total(T.gross, d.gross)
            + block(d, 'other_income') + block(d, 'expenses') + block(d, 'finance_cost') + total(T.before_tax, d.before_tax)
            + block(d, 'income_tax') + total(T.net, d.net, true);
        const print = $('fs-print');
        if (print) print.href = Phai.url(`/financial-statements/${d.to}/print?from=${d.from}`);
    }

    $('from').addEventListener('change', load);
    $('to').addEventListener('change', load);
    load();
})();
