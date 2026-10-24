/** Trial balance (views/accounting/trial-balance.php): click an account for its ledger. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('tb-config').textContent);
    const $ = (id) => document.getElementById(id);
    const money = (v) => (Number(v) ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '');
    // balances: debit positive, credit shown in brackets
    const bal = (v) => (Number(v) < 0 ? `(${money(-v)})` : money(v));
    const date = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '');
    const range = () => ({ from: $('from').value, to: $('to').value });

    async function load() {
        const d = await Phai.api.get('/api/trial-balance', range());
        $('tb').querySelector('tbody').innerHTML = d.rows.length ? d.rows.map((r) => `
            <tr data-acct="${Phai.esc(r.acct)}" data-name="${Phai.esc(r.name ?? '')}" role="button">
                <td><span class="fw-medium">${Phai.esc(r.acct)}</span> <span class="text-secondary">${Phai.esc(r.name ?? '')}</span></td>
                <td class="num">${bal(r.opening)}</td>
                <td class="num">${money(r.debit)}</td>
                <td class="num">${money(r.credit)}</td>
                <td class="num fw-medium">${bal(r.closing)}</td>
            </tr>`).join('') : `<tr><td colspan="5" class="text-center text-secondary py-3">${Phai.esc(cfg.text.empty)}</td></tr>`;
        const t = d.total;
        const off = Math.abs(t.debit - t.credit) > 0.004 || Math.abs(t.closing) > 0.004;
        $('tb').querySelector('tfoot').innerHTML = `
            <tr class="${off ? 'text-danger' : ''}">
                <td>${Phai.esc(cfg.text.total)}${off ? ` · ${Phai.esc(cfg.text.unbalanced)}` : ''}</td>
                <td class="num">${bal(t.opening)}</td>
                <td class="num">${money(t.debit)}</td>
                <td class="num">${money(t.credit)}</td>
                <td class="num">${bal(t.closing)}</td>
            </tr>`;
    }

    async function ledger(acct, name) {
        const d = await Phai.api.get('/api/trial-balance/ledger', { acct, ...range() });
        $('ledger-title').textContent = `${acct} ${name}`;
        $('ledger').querySelector('tbody').innerHTML = `
            <tr class="text-secondary"><td colspan="6">${Phai.esc(cfg.text.opening)}</td><td class="num">${bal(d.opening)}</td></tr>`
            + d.lines.map((l) => `
            <tr data-entry="${Phai.esc(l.entry)}" role="button">
                <td>${Phai.esc(date(l.gl_date))}</td>
                <td class="text-nowrap"><span class="badge bg-secondary-lt me-1">${Phai.esc(l.gl_type)}</span>${Phai.esc(l.gl_ref)}</td>
                <td>${Phai.esc(l.gl_desc ?? '')}</td>
                <td>${Phai.esc(l.gl_cc ?? '')}</td>
                <td class="num">${Number(l.gl_amt) > 0 ? money(l.gl_amt) : ''}</td>
                <td class="num">${Number(l.gl_amt) < 0 ? money(-l.gl_amt) : ''}</td>
                <td class="num">${bal(l.balance)}</td>
            </tr>`).join('');
        $('tb-card').classList.add('d-none');
        $('ledger-card').classList.remove('d-none');
    }

    $('tb').querySelector('tbody').addEventListener('click', (e) => {
        const tr = e.target.closest('tr[data-acct]');
        if (tr) ledger(tr.dataset.acct, tr.dataset.name);
    });
    $('ledger').querySelector('tbody').addEventListener('click', (e) => {
        const tr = e.target.closest('tr[data-entry]');
        if (tr) location.href = Phai.url('/journals/' + encodeURIComponent(tr.dataset.entry));
    });
    $('ledger-back').addEventListener('click', () => {
        $('ledger-card').classList.add('d-none');
        $('tb-card').classList.remove('d-none');
    });
    $('from').addEventListener('change', load);
    $('to').addEventListener('change', load);

    $('close-period')?.addEventListener('click', async () => {
        if (!(await Phai.confirm(cfg.text.confirm.replace(':period', $('period').textContent)))) return;
        const res = await Phai.api.post('/api/trial-balance/close');
        $('period').textContent = res.period;
        Phai.toast(cfg.text.closed.replace(':period', res.period));
    });

    load();
})();
