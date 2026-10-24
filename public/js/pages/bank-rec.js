/**
 * Bank reconciliation (views/accounting/bank-rec.php): pick the bank and statement date, tick the
 * lines that are on the statement, save. The user compares the balance after them with the real statement.
 */
(function () {
    'use strict';

    const bank = document.getElementById('bank');
    const date = document.getElementById('date');
    const save = document.getElementById('save');
    const all = document.getElementById('all');
    const table = document.getElementById('rec-table');
    const hist = document.getElementById('hist-table');
    const $ = (id) => document.getElementById(id);
    const money = (v) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dmy = (v) => (v ? v.split('-').reverse().join('/') : '');
    const cents = (v) => Math.round(Number(v) * 100);
    let data = null;
    let seq = 0;

    async function load() {
        if (!bank.value || !date.value) return;
        const mine = ++seq;
        const [open, rows] = await Promise.all([
            Phai.api.get('/api/bank-rec/open', { bank: bank.value, date: date.value }),
            Phai.api.get('/api/bank-rec', { bank: bank.value }),
        ]);
        if (mine !== seq) return;
        data = open;
        all.checked = false;
        table.tBodies[0].innerHTML = data.lines.length ? data.lines.map((l) => `
            <tr data-id="${Phai.esc(l.gl_trnbr)}">
                <td><input type="checkbox" class="form-check-input m-0" data-pick-line></td>
                <td>${Phai.esc(dmy(l.gl_date))}</td>
                <td>${Phai.esc(l.gl_type)}</td>
                <td>${Phai.esc(l.gl_ref)}</td>
                <td>${Phai.esc(l.gl_desc ?? '')}</td>
                <td class="num">${l.gl_amt > 0 ? Phai.esc(money(l.gl_amt)) : ''}</td>
                <td class="num">${l.gl_amt < 0 ? Phai.esc(money(-l.gl_amt)) : ''}</td>
            </tr>`).join('') : `<tr><td colspan="7" class="text-center text-secondary py-3">${Phai.esc(Phai.t('empty'))}</td></tr>`;
        $('last-date').textContent = data.last ? `(${dmy(data.last.bkr_date)})` : '';
        drawHistory(rows);
        sum();
    }

    function picked() {
        return [...table.querySelectorAll('[data-pick-line]:checked')].map((c) => Number(c.closest('tr').dataset.id));
    }

    /** Opening + ticked lines = the balance the statement should show. */
    function sum() {
        if (!data) return;
        const ids = new Set(picked());
        let inc = 0;
        let out = 0;
        for (const l of data.lines) {
            if (!ids.has(l.gl_trnbr)) continue;
            if (l.gl_amt > 0) inc += cents(l.gl_amt); else out += cents(l.gl_amt);
        }
        const cleared = cents(data.opening) + inc + out;
        $('s-opening').textContent = money(data.opening);
        $('s-in').textContent = money(inc / 100);
        $('s-out').textContent = money(out ? -out / 100 : 0);
        $('s-cleared').textContent = money(cleared / 100);
        $('s-book').textContent = money(data.book);
    }

    function drawHistory(rows) {
        hist.tBodies[0].innerHTML = rows.length ? rows.map((r, i) => `
            <tr>
                <td>${Phai.esc(dmy(r.bkr_date))}</td>
                <td class="num">${Phai.esc(money(r.bkr_balance))}</td>
                <td class="num">${Phai.esc(money(r.bkr_book))}</td>
                <td class="num">${Phai.esc(r.lines_cleared)}</td>
                <td>${Phai.esc(r.created_by ?? '')}</td>
                <td class="phai-act">
                    ${Phai.files.button('BR', `${bank.value}~${r.bkr_date}`, dmy(r.bkr_date))}
                    <button type="button" class="phai-act-btn" data-print="${Phai.esc(r.bkr_date)}" title="${Phai.esc(Phai.t('print'))}" aria-label="${Phai.esc(Phai.t('print'))}"><i class="ti ti-printer"></i></button>
                    <button type="button" class="phai-act-btn" data-undo="${Phai.esc(r.bkr_date)}" title="${Phai.esc(Phai.t('undo'))}" aria-label="${Phai.esc(Phai.t('undo'))}"${i === 0 ? '' : ' disabled'}><i class="ti ti-arrow-back-up"></i></button>
                </td>
            </tr>`).join('') : `<tr><td colspan="6" class="text-center text-secondary py-3">${Phai.esc(Phai.t('empty'))}</td></tr>`;
        Phai.files.mark(hist); // the statement of each reconciliation
    }

    table.tBodies[0].addEventListener('change', (e) => { if (e.target.matches('[data-pick-line]')) sum(); });
    // a click anywhere on the line ticks it
    table.tBodies[0].addEventListener('click', (e) => {
        const tr = e.target.closest('tr[data-id]');
        if (!tr || e.target.matches('input')) return;
        const box = tr.querySelector('[data-pick-line]');
        box.checked = !box.checked;
        sum();
    });
    all.addEventListener('change', () => {
        table.querySelectorAll('[data-pick-line]').forEach((c) => { c.checked = all.checked; });
        sum();
    });
    bank.addEventListener('change', load);
    date.addEventListener('change', load);

    save.addEventListener('click', async () => {
        Phai.busy(save, true);
        try {
            await Phai.api.post('/api/bank-rec', { bank: bank.value, date: date.value, lines: picked() });
            Phai.toast(Phai.t('saved'));
            await load();
        } catch (err) {
            if (err.fields) Phai.toast(Object.values(err.fields).join(' · '), 'danger');
        } finally {
            Phai.busy(save, false);
            sum();
        }
    });

    hist.tBodies[0].addEventListener('click', async (e) => {
        const p = e.target.closest('[data-print]');
        if (p) {
            window.open(Phai.url(`/bank-rec/${encodeURIComponent(bank.value)}/${encodeURIComponent(p.dataset.print)}/print`), '_blank', 'noopener');
            return;
        }
        const u = e.target.closest('[data-undo]');
        if (!u || u.disabled) return;
        if (!(await Phai.confirm(Phai.t('bank_rec_undo', { date: dmy(u.dataset.undo) })))) return;
        try {
            await Phai.api.del(`/api/bank-rec/${encodeURIComponent(bank.value)}/${encodeURIComponent(u.dataset.undo)}`);
            Phai.toast(Phai.t('saved'));
            load();
        } catch (err) {
            // toast already shown
        }
    });

    // opened from the work queue: ?bank=<code> picks that bank
    const want = new URLSearchParams(location.search).get('bank');
    if (want && [...bank.options].some((o) => o.value === want)) bank.value = want;
    load();
})();
