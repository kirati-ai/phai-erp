/* New deposit (views/accounting/deposit-new.php): an open order's figures, the amount received split into value and VAT. */
(function () {
    'use strict';

    const form = document.getElementById('dp-form');
    const el = form.elements;
    const $ = (id) => document.getElementById(id);
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (v) => Number(String(v || '').replace(/,/g, '')) || 0;
    let order = null;

    function split() {
        const amt = num(el.amount.value);
        const pct = order ? order.tax_pct : 0;
        const net = Math.round(amt * 100 / (100 + pct) * 100) / 100;
        $('dp-net').textContent = money(net);
        $('dp-tax').textContent = money(amt - net);
        $('dp-pct').textContent = pct ? `${pct}%` : '';
        $('dp-total').textContent = money(amt);
    }

    async function loadOrder() {
        const so = el.so.value.trim().toUpperCase();
        el.so.value = so;
        order = null;
        $('dp-customer').textContent = '—';
        if (so) {
            try {
                order = await Phai.api.get('/api/deposits/so/' + encodeURIComponent(so));
            } catch (err) {
                if (err.fields) Phai.form.errors(form, err.fields);
            }
        }
        if (order) $('dp-customer').textContent = `${order.customer} · ${order.cm_name ?? ''}`;
        $('dp-so-total').textContent = order ? money(order.total) : '';
        $('dp-taken').textContent = order ? money(order.taken) : '';
        split();
    }

    Phai.pick.source('open-sos', { url: '/api/deposits/open-sos', value: 'so_nbr', label: (s) => s.so_cm_code + ' · ' + (s.cm_name ?? '') });
    el.so.addEventListener('change', loadOrder);
    el.amount.addEventListener('input', split);
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = { so: el.so.value, dp_date: el.dp_date.value, dp_bank: el.dp_bank.value, amount: el.amount.value, dp_remarks: el.dp_remarks.value };
        if (el.doc_nbr) { body.doc_nbr = el.doc_nbr.value; body.doc_type = 'DP'; }
        Phai.form.errors(form, null);
        try {
            const res = await Phai.api.post('/api/deposits', body);
            location.href = Phai.url('/deposits/' + encodeURIComponent(res.nbr));
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
        }
    });
    loadOrder();
})();
