/** Voucher (views/accounting/ap-voucher.php): tick a vendor's open payables, save, print, pay. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('apv-config').textContent);
    const form = document.getElementById('apv');
    const el = form.elements;
    const tbody = form.querySelector('#apv-lines tbody');
    const $ = (id) => document.getElementById(id);
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const date = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '');

    function row(r, pick) {
        const amt = pick ? r.balance : r.apvd_amt;
        return `
            <tr data-amt="${Number(amt)}" data-wht="${Phai.esc(JSON.stringify(r.wht || []))}">
                <td>${pick ? `<input type="checkbox" class="form-check-input m-0" value="${Phai.esc(r.ap_ref)}">` : ''}</td>
                <td><a href="${Phai.url('/payables/' + encodeURIComponent(r.ap_ref ?? r.apvd_ap_ref))}" target="_blank">${Phai.esc(r.ap_ref ?? r.apvd_ap_ref)}</a></td>
                <td>${Phai.esc(cfg.text.type[r.ap_type] ?? cfg.text.type.IN)}</td>
                <td>${Phai.esc(r.ap_inv_nbr ?? '')}</td>
                <td>${Phai.esc(date(r.ap_date))}</td>
                <td>${Phai.esc(date(r.ap_due_date))}</td>
                <td>${Phai.esc((r.ap_acct ?? r.apvd_acct) + ' · ' + (r.ac_desc_th ?? ''))}</td>
                <td class="num${Number(amt) < 0 ? ' text-danger' : ''}">${money(amt)}</td>
            </tr>`;
    }

    /** Total, then what paying it will withhold (from the payable lines' codes) and what is left to pay. */
    function total(saved) {
        const rows = cfg.nbr ? [...tbody.rows] : [...tbody.querySelectorAll('input:checked')].map((b) => b.closest('tr'));
        const sum = rows.reduce((s, tr) => s + Number(tr.dataset.amt || 0), 0);
        $('apv-total').textContent = money(sum);
        const byPct = {};
        const plan = saved || rows.flatMap((tr) => JSON.parse(tr.dataset.wht || '[]'));
        for (const w of plan) byPct[w.pct] = (byPct[w.pct] || 0) + Number(w.tax);
        const foot = $('apv-foot');
        foot.querySelectorAll('[data-wht-row]').forEach((r) => r.remove());
        const list = Object.entries(byPct).filter(([, v]) => Math.round(v * 100) !== 0).sort((a, b) => b[0] - a[0]);
        $('apv-total-row').classList.toggle('phai-summary-grand', !list.length); // the highlight goes on the bottom line
        $('apv-total-row').classList.toggle('fw-semibold', list.length > 0);
        if (!list.length) return;
        const withheld = list.reduce((s, [, v]) => s + v, 0);
        foot.insertAdjacentHTML('beforeend', list.map(([pct, v]) => `<tr data-wht-row><td>${Phai.esc(cfg.text.whtEst.replace(':pct', Number(pct)))}</td><td class="num">-${money(v)}</td></tr>`).join('')
            + `<tr data-wht-row class="phai-summary-grand"><td>${Phai.esc(cfg.text.netToPay)}</td><td class="num">${money(sum - withheld)}</td></tr>`);
    }

    // ------------------------------------------------------------ view a saved voucher

    if (cfg.nbr) {
        (async () => {
            const d = await Phai.api.get('/api/ap-vouchers/' + encodeURIComponent(cfg.nbr));
            const h = d.head;
            el.vendor.value = h.apv_vd_code;
            Phai.pick.label(el.vendor, h.vd_name);
            for (const n of ['apv_date', 'apv_pay_date', 'apv_remarks']) el[n].value = h[n] ?? '';
            form.querySelectorAll('input, select').forEach((x) => { x.disabled = true; });
            Phai.viewFields(form, true); // a saved document reads as plain values (ux/ui.md "View pages")
            $('apv-all').hidden = true;
            tbody.innerHTML = d.lines.map((l) => row(l, false)).join('');
            total(h.wht || []);

            const s = cfg.status[h.apv_status];
            const badge = $('apv-status');
            badge.className = `badge bg-${s.color}-lt`;
            badge.textContent = s.text;
            const print = $('apv-print');
            print.hidden = !cfg.canPrint;
            print.href = Phai.url('/ap-vouchers/' + encodeURIComponent(cfg.nbr) + '/print');
            const pay = $('apv-pay');
            pay.hidden = !(cfg.canPay && h.apv_status === 'O');
            pay.href = Phai.url('/vouchers/new?vendor=' + encodeURIComponent(h.apv_vd_code) + '&vo=' + encodeURIComponent(cfg.nbr));
            const cancel = $('apv-cancel');
            cancel.hidden = !(cfg.canCancel && h.apv_status === 'O' && Number(h.apv_paid_amt) === 0);
            cancel.onclick = async () => {
                if (!(await Phai.confirm(cfg.text.confirm))) return;
                await Phai.api.post('/api/ap-vouchers/' + encodeURIComponent(cfg.nbr) + '/cancel');
                location.reload();
            };
        })();
        return;
    }

    // ------------------------------------------------------------ new voucher

    el.apv_date.value = cfg.today;
    $('apv-save').hidden = false;

    async function loadVendor() {
        const code = el.vendor.value.trim().toUpperCase();
        el.vendor.value = code;
        const found = code ? (await Phai.api.get('/api/purchase/vendors', { q: code })).find((v) => v.vd_code === code) : null;
        const rows = found ? await Phai.api.get('/api/ap-vouchers/open', { vendor: code }) : [];
        tbody.innerHTML = rows.length
            ? rows.map((r) => row(r, true)).join('')
            : (found ? `<tr><td colspan="8" class="text-center text-secondary py-3">${Phai.esc(cfg.text.none)}</td></tr>` : '');
        $('apv-all').checked = false;
        total();
    }
    el.vendor.addEventListener('change', loadVendor);

    $('apv-all').addEventListener('change', (e) => {
        tbody.querySelectorAll('input[type=checkbox]').forEach((b) => { b.checked = e.target.checked; });
        total();
    });
    tbody.addEventListener('change', total);
    // opened from the waiting payables above the voucher list: ?vendor=V&refs=AP1,AP2 ticks those payables
    const pre = new URLSearchParams(location.search);
    if (pre.get('vendor')) {
        el.vendor.value = pre.get('vendor');
        loadVendor().then(() => {
            const want = (pre.get('refs') || '').split(',');
            tbody.querySelectorAll('input[type=checkbox]').forEach((b) => { b.checked = want.includes(b.value); });
            total();
        });
    }

    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') e.preventDefault();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $('apv-save');
        const body = {
            vendor: el.vendor.value, apv_date: el.apv_date.value, apv_pay_date: el.apv_pay_date.value, apv_remarks: el.apv_remarks.value,
            refs: [...tbody.querySelectorAll('input:checked')].map((b) => b.value),
        };
        Phai.form.errors(form, null);
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post('/api/ap-vouchers', body);
            location.href = Phai.url('/ap-vouchers/' + encodeURIComponent(res.nbr));
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
        } finally {
            Phai.busy(btn, false);
        }
    });
})();
