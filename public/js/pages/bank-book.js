/**
 * Bank book (views/accounting/bank-book.php): one bank account month by month with a running balance.
 * A line opens its journal entry; the month exports to Excel and prints.
 */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('bb-config').textContent);
    const T = cfg.text;
    const $ = (id) => document.getElementById(id);
    const esc = Phai.esc;
    const money = (v) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dmy = (v) => (v ? v.slice(0, 10).split('-').reverse().join('/') : '');
    let bank = cfg.bank;
    let month = cfg.month;
    let data = null;

    async function load() {
        const d = await Phai.api.get(`/api/banks/${encodeURIComponent(bank)}/book`, { month });
        data = d;
        $('bb-month').querySelector('span').textContent = d.month_text;
        const print = $('bb-print');
        if (print) print.href = Phai.url(`/banks/${encodeURIComponent(bank)}/book/${d.month}/print`);
        history.replaceState(null, '', Phai.url(`/banks/${encodeURIComponent(bank)}?month=${d.month}`));
        const sum = (label, bal, cls, inn = '', out = '') => `<tr class="${cls}"><td colspan="4">${esc(label)}</td>
            <td class="num">${inn}</td><td class="num">${out}</td><td class="num">${esc(money(bal))}</td><td></td></tr>`;
        $('bb-table').tBodies[0].innerHTML = sum(T.opening, d.opening, 'phai-bb-sum')
            + d.lines.map((l) => `
            <tr data-entry="${esc(l.entry)}" style="cursor: pointer">
                <td>${esc(dmy(l.gl_date))}</td>
                <td>${esc(l.gl_type)}</td>
                <td>${esc(l.gl_ref)}</td>
                <td>${esc(l.gl_desc ?? '')}</td>
                <td class="num">${l.gl_amt > 0 ? esc(money(l.gl_amt)) : ''}</td>
                <td class="num">${l.gl_amt < 0 ? esc(money(-l.gl_amt)) : ''}</td>
                <td class="num">${esc(money(l.balance))}</td>
                <td>${l.rec_date ? `<i class="ti ti-check text-green me-1"></i>${esc(dmy(l.rec_date))}` : ''}</td>
            </tr>`).join('')
            + sum(T.closing, d.closing, 'phai-bb-sum phai-pc-sep', esc(money(d.in)), esc(money(d.out)));
    }

    $('bb-table').tBodies[0].addEventListener('click', (e) => {
        const tr = e.target.closest('tr[data-entry]');
        if (tr) location.href = Phai.url('/journals/' + encodeURIComponent(tr.dataset.entry));
    });
    $('bb-bank').addEventListener('change', (e) => { bank = e.target.value; load(); });
    const shift = (k) => {
        const [y, m] = month.split('-').map(Number);
        const d = new Date(y, m - 1 + k, 1);
        month = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        load();
    };
    $('bb-prev').addEventListener('click', () => shift(-1));
    $('bb-next').addEventListener('click', () => shift(1));

    $('bb-export').addEventListener('click', async () => {
        const d = data;
        if (!d) return;
        const num = (v) => (v === '' ? '' : { t: 'num', v: Number(v), scale: 2 });
        const rows = [['', '', '', T.opening, '', '', num(d.opening), '']];
        for (const l of d.lines) {
            rows.push([{ t: 'date', v: l.gl_date }, l.gl_type, l.gl_ref, l.gl_desc ?? '', num(l.gl_amt > 0 ? l.gl_amt : ''),
                num(l.gl_amt < 0 ? -l.gl_amt : ''), num(l.balance), l.rec_date ? { t: 'date', v: l.rec_date } : '']);
        }
        rows.push(['', '', '', T.closing, num(d.in), num(d.out), num(d.closing), '']);
        const labels = [T.date, T.type, T.ref, T.desc, T.in, T.out, T.balance, T.rec];
        const blob = await Phai.xlsx.write({
            name: d.month, title: `${T.title} ${bank} ${d.month_text}`,
            columns: labels.map((label, i) => ({ label, width: [11, 6, 16, 44, 14, 14, 14, 11][i] })), rows,
        });
        Phai.xlsx.save(blob, `${T.title}-${bank}-${d.month}.xlsx`);
    });

    load();
}());
