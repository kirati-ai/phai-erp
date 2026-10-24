/* ภ.พ.30 (views/accounting/vat-return.php): the month's return from both VAT reports, file it (posts the closing entry) or undo the latest. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('vr-config').textContent);
    const T = cfg.text;
    const $ = (id) => document.getElementById(id);
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const date = (v) => (v ? v.split('-').reverse().join('/') : '');
    const month = () => $('month').value;

    async function load() {
        if (!month()) return;
        const [r, list] = await Promise.all([Phai.api.get('/api/vat-return', { month: month() }), Phai.api.get('/api/vat-return/list')]);
        $('vr-sales').textContent = money(r.sales_net);
        $('vr-output').textContent = money(r.output);
        $('vr-purchases').textContent = money(r.purchases_net);
        $('vr-input').textContent = money(r.input);
        $('vr-carried').textContent = money(r.carried);
        $('vr-net-label').textContent = r.net >= 0 ? T.to_pay : T.credit;
        $('vr-net').textContent = money(Math.abs(r.net));
        const badge = $('vr-status');
        badge.className = `badge bg-${r.filed ? 'green' : 'secondary'}-lt`;
        badge.textContent = r.filed ? T.filed.replace(':date', date(r.date)) : T.not_filed;
        $('vr-due').textContent = `${T.due} ${date(r.due)}`;
        const latest = list[0]?.vr_month;
        if ($('vr-file')) $('vr-file').hidden = r.filed;
        if ($('vr-undo')) $('vr-undo').hidden = !r.filed || latest !== r.month;
        if ($('vr-print')) $('vr-print').href = Phai.url(`/vat-return/${r.month}/print`);
        $('vr-list').querySelector('tbody').innerHTML = list.map((v) => `
            <tr data-month="${Phai.esc(v.vr_month)}" style="cursor: pointer">
                <td>${Phai.esc(v.vr_month)}</td><td class="num">${money(v.vr_output)}</td><td class="num">${money(v.vr_input)}</td>
                <td class="num">${money(v.vr_carried)}</td><td class="num">${money(v.vr_net)}</td>
                <td class="phai-act">${Phai.files.button('VR', v.vr_month)}</td>
            </tr>`).join('');
        Phai.files.mark($('vr-list')); // the return as filed and its tax receipt
    }

    $('vr-list').addEventListener('click', (e) => {
        const tr = e.target.closest('tr[data-month]');
        if (tr) { $('month').value = tr.dataset.month; load(); }
    });
    $('month').addEventListener('change', load);
    $('vr-file')?.addEventListener('click', async () => {
        if (!(await Phai.confirm(T.file_confirm.replace(':month', month())))) return;
        try {
            await Phai.api.post('/api/vat-return', { month: month() });
            Phai.toast(T.filed_ok);
            load();
        } catch (_) { /* the error is shown as a toast */ }
    });
    $('vr-undo')?.addEventListener('click', async () => {
        if (!(await Phai.confirm(T.undo_confirm.replace(':month', month())))) return;
        try {
            await Phai.api.del('/api/vat-return/' + encodeURIComponent(month()));
            Phai.toast(T.undone);
            load();
        } catch (_) { /* the error is shown as a toast */ }
    });
    load();
})();
