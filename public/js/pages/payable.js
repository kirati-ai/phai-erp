/** Vendor invoice / payable (views/accounting/payable.php). */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('payable-config').textContent);
    const form = document.getElementById('payable');
    const el = form.elements;
    const rcBody = form.querySelector('#rc-lines tbody');
    const exBody = form.querySelector('#ex-lines tbody');
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (v) => Number(String(v ?? '').replace(/,/g, '')) || 0;
    const date = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '');
    const opts = (list, blank) => (blank ? '<option value=""></option>' : '<option value="">—</option>')
        + list.map((o) => `<option value="${Phai.esc(o.value)}">${Phai.esc(o.label)}</option>`).join('');
    const $ = (id) => document.getElementById(id);
    // a line's withholding code: the payment withholds that rate on the line's amount
    const whtSelect = (name, value) => `<select class="form-select form-select-sm" name="${Phai.esc(name)}" data-wht>${opts(cfg.whtCodes || [])}</select>`
        .replace(`value="${Phai.esc(value ?? '')}"`, `value="${Phai.esc(value ?? '')}" selected`);
    let vendorWht = '';
    let vendorTerms = '';
    let dueTyped = false; // a due date typed by hand stays
    /** Due date = invoice date + the vendor's credit term days (as the server does when none is given). */
    function dueFromTerms() {
        if (dueTyped || !el.ap_date.value) return;
        const days = (cfg.termDays || {})[vendorTerms];
        if (days === undefined) return;
        const d = new Date(el.ap_date.value + 'T00:00:00');
        d.setDate(d.getDate() + days);
        el.ap_due_date.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }
    let row = 0;
    let editing = null; // set while changing a saved payable

    /** wht: [{code, base}] — what the payment will withhold, per rate, and what is left to pay. */
    function show(net, vat, wht = []) {
        $('t-net').textContent = money(net);
        $('t-vat').textContent = money(vat);
        $('t-total').textContent = money(net + vat);
        const byPct = {};
        for (const w of wht) {
            const pct = Number((cfg.whtPct || {})[w.code] ?? w.pct ?? 0);
            if (!w.code || !pct) continue;
            byPct[pct] = (byPct[pct] || 0) + Math.round(w.base * pct) / 100;
        }
        const rows = Object.entries(byPct).sort((a, b) => b[0] - a[0]);
        const withheld = rows.reduce((s, [, v]) => s + v, 0);
        $('t-wht').innerHTML = rows.length ? rows.map(([pct, v]) => `<tr><td>${Phai.esc(cfg.text.whtEst.replace(':pct', Number(pct)))}</td><td class="num">-${money(v)}</td></tr>`).join('')
            + `<tr class="phai-summary-grand"><td>${Phai.esc(cfg.text.netToPay)}</td><td class="num">${money(net + vat - withheld)}</td></tr>` : '';
        // the highlight goes on the bottom line: what is actually paid
        $('t-total-row').classList.toggle('phai-summary-grand', !rows.length);
        $('t-total-row').classList.toggle('fw-semibold', rows.length > 0);
    }

    // ------------------------------------------------------------ view a saved payable

    if (cfg.nbr) {
        $('rc-card').classList.add('d-none');
        $('ex-card').classList.add('d-none');
        $('ap-card').classList.remove('d-none');
        (async () => {
            const d = await Phai.api.get('/api/payables/' + encodeURIComponent(cfg.nbr));
            const h = d.head;
            if (el.doc_nbr) el.doc_nbr.value = h.ap_ref;
            el.vendor.value = h.ap_vd_code;
            Phai.pick.label(el.vendor, h.vd_name);
            for (const n of ['ap_inv_nbr', 'ap_date', 'ap_due_date', 'ap_remarks', 'ap_ref_ap']) el[n].value = h[n] ?? '';
            el.ap_type.value = h.ap_type === 'CM' ? 'CM' : 'IN';
            el.ap_acct.value = h.ap_acct ?? '';
            $('ref-col').hidden = el.ap_type.value !== 'CM';
            form.querySelectorAll('input, select').forEach((x) => { x.disabled = true; });
            Phai.viewFields(form, true); // a saved document reads as plain values (ux/ui.md "View pages")
            let vat = 0;
            const vatAcct = cfg.vatAcct;
            form.querySelector('#ap-lines tbody').innerHTML = d.lines.map((l) => {
                if (l.apd_acct === vatAcct) vat += Number(l.apd_amt);
                return `
                    <tr>
                        <td class="text-nowrap">${Phai.esc(l.apd_acct)} <span class="text-secondary">${Phai.esc(l.ac_desc_th ?? '')}</span></td>
                        <td>${Phai.esc(l.apd_desc ?? '')}</td>
                        <td>${Phai.esc(l.apd_cc ?? '')}</td>
                        <td class="num">${money(l.apd_amt)}</td>
                        <td>${l.apd_wht_code ? Phai.esc(`${l.apd_wht_code} · ${Number(l.wht_pct)}%`) : ''}</td>
                    </tr>`;
            }).join('');
            const sign = h.ap_type === 'CM' ? -1 : 1;
            show(Number(h.ap_amt) - vat, vat, d.lines.filter((l) => l.apd_wht_code).map((l) => ({ code: l.apd_wht_code, pct: l.wht_pct, base: sign * Number(l.apd_amt) })));
            $('t-paid-row').classList.remove('d-none');
            $('t-paid').textContent = money(Number(h.ap_amt) - Number(h.ap_paid_amt)); // still owed (paid = settled by a voucher)

            const s = cfg.status[h.ap_status];
            const badge = $('ap-status');
            badge.className = `badge bg-${s.color}-lt`;
            badge.textContent = s.text;
            const gl = $('ap-gl');
            gl.hidden = false;
            gl.href = Phai.url('/journals/' + encodeURIComponent(`AP~${cfg.nbr}~${h.ap_date}`));
            const cancel = $('ap-cancel');
            cancel.hidden = !(cfg.canCancel && h.ap_status === 'O' && Number(h.ap_paid_amt) === 0);
            cancel.onclick = async () => {
                if (!(await Phai.confirm(cfg.text.confirm))) return;
                await Phai.api.post('/api/payables/' + encodeURIComponent(cfg.nbr) + '/cancel');
                location.reload();
            };
            const edit = $('ap-edit');
            edit.hidden = !h._editable;
            edit.onclick = () => editMode(d);
        })();
    }

    // ------------------------------------------------------------ change a payable still owed (not on a voucher)

    function editMode(d) {
        const h = d.head;
        Phai.viewFields(form, false);
        form.querySelectorAll('input, select').forEach((x) => { x.disabled = false; });
        for (const n of ['vendor', 'ap_type', 'ap_ref_ap', 'doc_nbr']) if (el[n]) el[n].disabled = true; // who and what stay
        ['ap-edit', 'ap-cancel', 'ap-gl'].forEach((id) => { $(id).hidden = true; });
        $('ap-save').hidden = false;
        $('ap-undo').hidden = false;
        $('ap-undo').onclick = () => location.reload(); // drop the changes: the saved payable again
        $('t-paid-row').classList.add('d-none');
        const vatAcct = cfg.vatAcct;
        // lines from goods receipts keep their amounts: only their withholding code can change
        const kept = d.lines.filter((l) => l.apd_po_nbr);
        form.querySelector('#ap-lines tbody').innerHTML = kept.map((l) => `
            <tr data-kept="${Number(l.apd_line)}" data-amt="${Number(l.apd_amt)}" data-vat="${l.apd_acct === vatAcct ? 1 : 0}">
                <td class="text-nowrap">${Phai.esc(l.apd_acct)} <span class="text-secondary">${Phai.esc(l.ac_desc_th ?? '')}</span></td>
                <td>${Phai.esc(l.apd_desc ?? '')}</td>
                <td>${Phai.esc(l.apd_cc ?? '')}</td>
                <td class="num">${money(l.apd_amt)}</td>
                <td>${l.apd_acct === vatAcct ? '' : whtSelect(`kept.${l.apd_line}`, l.apd_wht_code)}</td>
            </tr>`).join('');
        $('ap-card').classList.toggle('d-none', !kept.length);
        // the other charges are lines again; their VAT rate is found from the VAT line they made
        const sign = h.ap_type === 'CM' ? -1 : 1;
        const ex = d.lines.filter((l) => !l.apd_po_nbr && l.apd_acct !== vatAcct);
        const exVat = d.lines.filter((l) => !l.apd_po_nbr && l.apd_acct === vatAcct).reduce((s, l) => s + Number(l.apd_amt), 0);
        const exNet = ex.reduce((s, l) => s + Number(l.apd_amt), 0);
        const pct = exNet ? Math.round(exVat / exNet * 10000) / 100 : 0;
        const taxOpt = [...el.tax_code.options].find((o) => Math.abs(num(o.dataset.pct) - pct) < 0.01 && (pct > 0 || o.value === ''));
        el.tax_code.value = taxOpt ? taxOpt.value : '';
        $('ex-card').classList.remove('d-none');
        exBody.innerHTML = '';
        ex.forEach((l) => addLine({ acct: l.apd_acct, cc: l.apd_cc, desc: l.apd_desc, amt: sign * Number(l.apd_amt), wht: l.apd_wht_code }));
        vendorTerms = h.ap_cr_terms || ''; // changing the date moves the due date with the term again
        editing = { kept, sign };
        total();
    }

    // ------------------------------------------------------------ new payable



    function total() {
        let net = 0;
        let vat = 0;
        if (editing) {
            form.querySelectorAll('#ap-lines [data-kept]').forEach((tr) => {
                if (tr.dataset.vat === '1') vat += editing.sign * Number(tr.dataset.amt);
                else net += editing.sign * Number(tr.dataset.amt);
            });
        }
        // a goods return is a deduction on an invoice and the credit itself on a credit note (shown positive there)
        const retSign = (tr) => (el.ap_type.value === 'CM' && tr.dataset.ret ? -1 : 1);
        rcBody.querySelectorAll('input[type=checkbox]:checked').forEach((b) => {
            const tr = b.closest('tr');
            net += retSign(tr) * Number(tr.dataset.net);
            vat += retSign(tr) * Number(tr.dataset.vat);
        });
        let ex = 0;
        exBody.querySelectorAll('[data-amt]').forEach((x) => { ex += num(x.value); });
        const pct = num(el.tax_code.selectedOptions[0]?.dataset.pct);
        // the withholding each line asks for: receipt lines on their value before VAT, expense lines on their amount
        const wht = [];
        if (editing) {
            form.querySelectorAll('#ap-lines [data-kept]').forEach((tr) => {
                const s = tr.querySelector('[data-wht]');
                if (s?.value) wht.push({ code: s.value, base: editing.sign * Number(tr.dataset.amt) });
            });
        }
        rcBody.querySelectorAll('input[type=checkbox]:checked').forEach((b) => {
            const tr = b.closest('tr');
            const s = tr.querySelector('[data-wht]');
            if (s?.value) wht.push({ code: s.value, base: retSign(tr) * Number(tr.dataset.net) });
        });
        exBody.querySelectorAll('tr').forEach((tr) => {
            const s = tr.querySelector('[data-wht]');
            if (s?.value) wht.push({ code: s.value, base: num(tr.querySelector('[data-amt]')?.value) });
        });
        show(net + ex, vat + Math.round(ex * pct) / 100, wht);
    }

    function addLine(v = {}) {
        const i = row++;
        exBody.insertAdjacentHTML('beforeend', `
            <tr>
                <td><select class="form-select form-select-sm" name="lines.${i}.acct">${opts(cfg.accounts, true)}</select></td>
                <td><select class="form-select form-select-sm" name="lines.${i}.cc">${opts(cfg.costCenters)}</select></td>
                <td><input type="text" class="form-control form-control-sm" name="lines.${i}.desc"></td>
                <td><input type="text" class="form-control form-control-sm text-end" name="lines.${i}.amt" data-amt inputmode="decimal"></td>
                <td>${whtSelect(`lines.${i}.wht`, 'wht' in v ? v.wht : vendorWht)}</td>
                <td><button type="button" class="btn btn-sm btn-ghost-danger px-1" data-remove><i class="ti ti-x"></i></button></td>
            </tr>`);
        const tr = exBody.lastElementChild;
        if (v.acct) tr.querySelector('[name$=".acct"]').value = v.acct;
        if (v.cc) tr.querySelector('[name$=".cc"]').value = v.cc;
        if (v.desc) tr.querySelector('[name$=".desc"]').value = v.desc;
        if (v.amt !== undefined) tr.querySelector('[name$=".amt"]').value = money(v.amt);
    }

    // a credit note reduces an earlier payable of the vendor and has expense lines only (no receipts)
    el.ap_type.addEventListener('change', () => {
        const cm = el.ap_type.value === 'CM';
        $('ref-col').hidden = !cm;
        // a credit note takes only goods sent back (ใบส่งคืนสินค้า) from the receipts list
        rcBody.querySelectorAll('tr').forEach((tr) => {
            const off = cm && !tr.dataset.ret && !!tr.querySelector('input[type=checkbox]');
            tr.hidden = off;
            if (off) tr.querySelector('input[type=checkbox]').checked = false;
        });
        total();
    });
    // the payable a credit note reduces: open payables of the same vendor
    Phai.pick.source('ap-refs', {
        load: async () => {
            const vendor = el.vendor.value.trim().toUpperCase();
            if (!vendor) return [];
            const found = await Phai.api.get('/api/payables', { q: vendor, per_page: 50 });
            return found.rows.filter((r) => r.ap_vd_code === vendor && r.ap_status !== 'C' && Number(r.ap_amt) > 0)
                .map((r) => ({ value: r.ap_ref, label: (r.ap_inv_nbr ?? '') + ' · ' + money(r.ap_amt) }));
        },
    });

    async function loadVendor() {
        const code = el.vendor.value.trim().toUpperCase();
        el.vendor.value = code;
        const found = code ? (await Phai.api.get('/api/purchase/vendors', { q: code })).find((v) => v.vd_code === code) : null;
        if (found?.vd_tax_code) el.tax_code.value = found.vd_tax_code;
        vendorWht = found?.vd_wht_code || ''; // the vendor's usual withholding, for its receipts and new lines
        vendorTerms = found?.vd_terms || '';
        dueFromTerms();
        exBody.querySelectorAll('[data-wht]').forEach((s) => { if (!s.value) s.value = vendorWht; });
        el.ap_acct.value = found?.vd_ap_acct || cfg.apAcct; // the vendor's own payable account, else trade payables
        const rows = found ? await Phai.api.get('/api/payables/receipts', { vendor: code }) : [];
        rcBody.innerHTML = rows.length ? rows.map((r) => `
            <tr data-net="${Number(r.rc_net_amt)}" data-vat="${Number(r.rc_tax_amt)}"${r.ret ? ' data-ret="1"' : ''}${el.ap_type.value === 'CM' && !r.ret ? ' hidden' : ''}>
                <td><input type="checkbox" class="form-check-input m-0" value="${Phai.esc(r.rc_nbr)}"></td>
                <td><a href="${Phai.url('/receipts/' + encodeURIComponent(r.src || r.rc_nbr))}" target="_blank">${Phai.esc(r.src || r.rc_nbr)}</a>
                    ${r.kind ? `<span class="badge bg-azure-lt">${Phai.esc(r.kind)}</span>` : ''}
                    ${r.rc_inv_nbr ? `<span class="text-secondary small">${Phai.esc(r.rc_inv_nbr)}</span>` : ''}</td>
                <td>${Phai.esc(date(r.rc_date))}</td>
                <td>${Phai.esc(r.rc_po_nbr ?? '')}</td>
                <td class="num">${money(r.rc_net_amt)}</td>
                <td class="num">${money(r.rc_tax_amt)}</td>
                <td class="num">${money(r.rc_tot_amt)}</td>
                <td>${whtSelect(`receipt_wht.${r.rc_nbr}`, vendorWht)}</td>
            </tr>`).join('') : (found ? `<tr><td colspan="8" class="text-center text-secondary py-3">${Phai.esc(cfg.text.none)}</td></tr>` : '');
        total();
    }
    el.vendor.addEventListener('change', loadVendor);

    // opened from "receipts to invoice": ?vendor=V&receipts=RC1,RC2 ticks those receipts
    const pre = new URLSearchParams(location.search);
    if (!cfg.nbr && pre.get('vendor')) {
        el.vendor.value = pre.get('vendor');
        // ?type=CM: the vendor's credit note for goods sent back (from a purchase return)
        if (pre.get('type') === 'CM') {
            el.ap_type.value = 'CM';
            el.ap_type.dispatchEvent(new Event('change'));
            if (pre.get('ref')) el.ap_ref_ap.value = pre.get('ref'); // the payable of the receipt the goods came on
        }
        loadVendor().then(() => {
            const want = (pre.get('receipts') || '').split(',');
            rcBody.querySelectorAll('input[type=checkbox]').forEach((b) => {
                if (want.includes(b.value)) {
                    b.checked = true;
                    b.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });
    }

    // a receipt's supplier invoice number fills the header when it is empty
    rcBody.addEventListener('change', (e) => {
        const span = e.target.closest('tr')?.querySelector('.small');
        if (e.target.checked && span && !el.ap_inv_nbr.value) el.ap_inv_nbr.value = span.textContent.trim();
        total();
    });
    $('ex-add').addEventListener('click', () => { addLine(); total(); });
    exBody.addEventListener('click', (e) => {
        if (e.target.closest('[data-remove]')) {
            e.target.closest('tr').remove();
            total();
        }
    });
    exBody.addEventListener('input', total);
    form.querySelector('#ap-lines tbody').addEventListener('change', () => { if (editing) total(); }); // a receipt line's withholding
    el.tax_code.addEventListener('change', total);
    el.ap_date.addEventListener('change', dueFromTerms);
    el.ap_due_date.addEventListener('input', () => { dueTyped = el.ap_due_date.value !== ''; });
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') e.preventDefault();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $('ap-save');
        const lines = {};
        exBody.querySelectorAll('tr').forEach((tr) => {
            const get = (n) => tr.querySelector(`[name$=".${n}"]`);
            const i = get('acct').name.split('.')[1];
            lines[i] = { acct: get('acct').value, cc: get('cc').value, desc: get('desc').value, amt: get('amt').value.replace(/,/g, ''), wht: get('wht').value };
        });
        if (editing) {
            const kept = {};
            form.querySelectorAll('#ap-lines [data-kept] [data-wht]').forEach((s) => { kept[s.name.split('.')[1]] = s.value; });
            const put = {
                ap_inv_nbr: el.ap_inv_nbr.value, ap_date: el.ap_date.value, ap_due_date: el.ap_due_date.value, ap_remarks: el.ap_remarks.value,
                ap_acct: el.ap_acct.value, tax_code: el.tax_code.value, lines, kept,
            };
            Phai.form.errors(form, null);
            Phai.busy(btn, true);
            try {
                await Phai.api.put('/api/payables/' + encodeURIComponent(cfg.nbr), put);
                Phai.toast(cfg.text.saved);
                location.reload();
            } catch (err) {
                if (err.fields) Phai.form.errors(form, err.fields);
                Phai.busy(btn, false);
            }
            return;
        }
        const body = {
            vendor: el.vendor.value, ap_inv_nbr: el.ap_inv_nbr.value, ap_date: el.ap_date.value, ap_due_date: el.ap_due_date.value,
            ap_remarks: el.ap_remarks.value, tax_code: el.tax_code.value, lines,
            ap_type: el.ap_type.value, ap_ref_ap: el.ap_ref_ap.value, ap_acct: el.ap_acct.value,
            receipts: [...rcBody.querySelectorAll('input[type=checkbox]:checked')].map((b) => b.value),
            receipt_wht: Object.fromEntries([...rcBody.querySelectorAll('[data-wht]')].map((s) => [s.name.slice('receipt_wht.'.length), s.value])),
        };
        if (el.doc_nbr) { body.doc_nbr = el.doc_nbr.value; body.doc_type = 'AP'; }
        Phai.form.errors(form, null);
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post('/api/payables', body);
            location.href = Phai.url('/payables/' + encodeURIComponent(res.nbr));
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
            Phai.busy(btn, false);
        }
    });

    if (!cfg.nbr) {
        el.ap_date.value = cfg.today;
        el.ap_acct.value = cfg.apAcct;
        $('ap-save').hidden = false;
        el.vendor.focus();
        total();
    }
})();
