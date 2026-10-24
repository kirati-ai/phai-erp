/** Receipt from a customer / payment to a vendor (views/accounting/settle.php). */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('settle-config').textContent);
    const form = document.getElementById('settle');
    const el = form.elements;
    const pay = cfg.party === 'vendor';
    const p = pay ? 'vo' : 'cr';
    const tbody = form.querySelector('#st-lines tbody');
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (v) => Number(String(v ?? '').replace(/,/g, '')) || 0;
    const date = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '');
    const party = document.getElementById('party');
    const cols = pay ? 6 : 7;
    const whtBody = document.querySelector('#st-wht-rows tbody');
    const pctText = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 2 });

    /** A rate withheld on a payment; a saved one ({code, desc, pct, base, tax}) is shown as plain values. */
    function whtRow(v, editable) {
        if (!editable) {
            return `<tr data-wht-row data-tax="${Number(v.tax)}"><td>${Phai.esc(v.code + ' · ' + (v.desc ?? ''))}</td>
                <td class="num">${money(v.base)}</td><td class="num">${pctText(v.pct)}</td><td class="num">${money(v.tax)}</td><td></td></tr>`;
        }
        const opts = '<option value="" data-pct="0">—</option>' + (cfg.whtCodes || []).map((o) => `<option value="${Phai.esc(o.wht_code)}"
            data-pct="${Number(o.wht_pct)}">${Phai.esc(o.wht_code + ' · ' + o.wht_desc)}</option>`).join('');
        return `<tr data-wht-row>
            <td><select class="form-select form-select-sm" data-wht-code>${v.code ? opts.replace(`value="${Phai.esc(v.code)}"`, `value="${Phai.esc(v.code)}" selected`) : opts}</select></td>
            <td><input type="text" class="form-control form-control-sm text-end" data-wht-base inputmode="decimal" value="${v.base ? money(v.base) : ''}"></td>
            <td class="num" data-wht-pct></td>
            <td class="num" data-wht-tax></td>
            <td><button type="button" class="btn btn-sm btn-ghost-secondary py-0 px-1" data-wht-del><i class="ti ti-x"></i></button></td>
        </tr>`;
    }

    /** Input names follow the row order, as the API names its errors (wht.<i>.code / wht.<i>.base). */
    function whtNames() {
        whtBody.querySelectorAll('tr[data-wht-row]').forEach((tr, i) => {
            const c = tr.querySelector('[data-wht-code]');
            if (c) c.name = `wht.${i}.code`;
            const b = tr.querySelector('[data-wht-base]');
            if (b) b.name = `wht.${i}.base`;
        });
    }

    /** Tax withheld over all rates; the first rate's base follows the payment (part before VAT) until typed over. */
    function whtTotal() {
        let wht = 0;
        whtBody.querySelectorAll('tr[data-wht-row]').forEach((tr, i) => {
            const sel = tr.querySelector('[data-wht-code]');
            if (!sel) {
                wht += num(tr.dataset.tax);
                return;
            }
            const base = tr.querySelector('[data-wht-base]');
            if (i === 0 && !base.dataset.touched) base.value = sel.value ? money(suggestedBase()) : '';
            const pct = num(sel.selectedOptions[0]?.dataset.pct);
            const tax = sel.value ? Math.round(num(base.value) * pct) / 100 : 0;
            tr.querySelector('[data-wht-pct]').textContent = sel.value ? pctText(pct) : '';
            tr.querySelector('[data-wht-tax]').textContent = sel.value ? money(tax) : '';
            wht += tax;
        });
        return Math.round(wht * 100) / 100;
    }

    /** WHT base for a payment: the part of each payment before VAT. */
    function suggestedBase() {
        let base = 0;
        tbody.querySelectorAll('tr[data-doc]').forEach((tr) => {
            base += num(tr.querySelector('[data-amt]')?.value) * Number(tr.dataset.ratio || 1);
        });
        return Math.round(base * 100) / 100;
    }

    // the payables' lines say what to withhold (their withholding codes): the table follows the amounts paid
    // until it is changed by hand
    let whtManual = false;
    let whtSig = '';
    function autoWht() {
        const map = {};
        tbody.querySelectorAll('tr[data-doc]').forEach((tr) => {
            const paid = num(tr.querySelector('[data-amt]')?.value);
            const amount = Number(tr.dataset.amount || 0);
            if (!paid || !amount) return;
            for (const [code, base] of Object.entries(JSON.parse(tr.dataset.whtLines || '{}'))) {
                map[code] = (map[code] || 0) + Number(base) * paid / amount;
            }
        });
        const codes = Object.keys(map).filter((c) => Math.round(map[c] * 100) > 0);
        const sig = codes.map((c) => c + ':' + map[c].toFixed(2)).join(',');
        if (sig === whtSig) return;
        if (!codes.length && !whtSig) return; // nothing from the lines: the table stays as typed / suggested
        whtSig = sig;
        whtBody.innerHTML = codes.length
            ? codes.map((c) => whtRow({ code: c, base: Math.round(map[c] * 100) / 100 }, true)).join('')
            : whtRow({}, true);
        whtBody.querySelectorAll('[data-wht-base]').forEach((b) => { if (codes.length) b.dataset.touched = '1'; });
        whtNames();
    }

    function total() {
        let amt = 0;
        let wht = 0;
        if (pay && !cfg.nbr && !whtManual) autoWht();
        tbody.querySelectorAll('tr[data-doc]').forEach((tr) => {
            const a = tr.querySelector('[data-amt]');
            const w = tr.querySelector('[data-wht]');
            amt += a ? num(a.value) : num(tr.dataset.applied);
            if (!pay) wht += w ? num(w.value) : num(tr.dataset.wht);
        });
        if (pay) {
            wht = cfg.nbr ? num(cfg.whtAmt) : whtTotal();
            document.getElementById('st-net').textContent = money(amt - wht);
        } else {
            document.getElementById('st-settled').textContent = money(amt + wht); // what the invoices are cleared by
        }
        document.getElementById('st-total').textContent = money(amt);
        document.getElementById('st-wht-total').textContent = (wht ? '-' : '') + money(wht);
    }

    function row(r, editable) {
        const cells = editable
            ? `<td><input type="text" class="form-control form-control-sm text-end" name="lines.${Phai.esc(r.doc)}.amt" data-amt inputmode="decimal"></td>`
              + (pay ? '' : `<td><input type="text" class="form-control form-control-sm text-end" name="lines.${Phai.esc(r.doc)}.wht" data-wht inputmode="decimal"></td>`)
            : `<td class="num">${money(r.applied)}</td>` + (pay ? '' : `<td class="num">${money(r.wht)}</td>`);
        return `
            <tr data-doc="${Phai.esc(r.doc)}" data-bal="${Number(r.balance ?? 0)}" data-ratio="${Number(r.ratio ?? 1)}"
                data-applied="${Number(r.applied ?? 0)}" data-wht="${pay ? 0 : Number(r.wht ?? 0)}" data-amount="${Number(r.amount ?? 0)}"
                data-wht-lines="${pay ? Phai.esc(JSON.stringify(r.wht || {})) : '{}'}">
                <td class="text-nowrap">${r.link ? `<a href="${Phai.esc(Phai.url(r.link))}">${Phai.esc(r.doc)}</a>` : Phai.esc(r.doc)}
                    ${editable ? `<button type="button" class="btn btn-sm btn-ghost-primary py-0 px-1 ms-1" data-full title="${Phai.esc(cfg.text.full)}"><i class="ti ti-arrow-right"></i></button>` : ''}</td>
                <td>${Phai.esc(date(r.date))}</td>
                <td>${Phai.esc(date(r.due))}</td>
                <td class="num">${money(r.amount)}</td>
                <td class="num">${r.balance === undefined ? '' : money(r.balance)}</td>
                ${cells}
            </tr>`;
    }

    // ------------------------------------------------------------ view a saved document

    if (cfg.nbr) {
        (async () => {
            const d = await Phai.api.get(cfg.api + '/' + encodeURIComponent(cfg.nbr));
            const h = d.head;
            if (el.doc_nbr) el.doc_nbr.value = cfg.nbr;
            party.value = h.party;
            Phai.pick.label(party, h.party_name);
            el[p + '_date'].value = h.date;
            el[p + '_bank'].value = h.bank;
            el[p + '_remarks'].value = h.remarks ?? '';
            if (pay) {
                el.wht_form.value = h.wht_form || 'PND53';
                cfg.whtAmt = h.wht_amt;
                whtBody.innerHTML = (h.wht || []).map((w) => whtRow(w, false)).join('');
                document.getElementById('st-wht-card').hidden = !(h.wht || []).length;
                document.getElementById('st-wht-add-row').hidden = true;
            }
            form.querySelectorAll('input, select').forEach((x) => { x.disabled = true; });
            Phai.viewFields(form, true); // a saved document reads as plain values (ux/ui.md "View pages")
            tbody.innerHTML = d.lines.map((l) => row(l, false)).join('');
            total();

            const s = cfg.status[h.status];
            const badge = document.getElementById('st-status');
            badge.className = `badge bg-${s.color}-lt`;
            badge.textContent = s.text;
            const print = document.getElementById('st-print');
            print.hidden = !cfg.canPrint;
            print.href = Phai.url(cfg.page + '/' + encodeURIComponent(cfg.nbr) + '/print');
            if (h.whtc_nbr && cfg.canPrint) {
                const w = document.getElementById('st-wht');
                w.hidden = false;
                w.href = Phai.url('/wht/' + encodeURIComponent(h.whtc_nbr) + '/print');
            }
            const xml = document.getElementById('st-xml');
            xml.hidden = !(cfg.xml && h.status === 'P');
            if (cfg.xml) xml.href = Phai.url(cfg.xml.replace('{nbr}', encodeURIComponent(cfg.nbr)));
            const cancel = document.getElementById('st-cancel');
            cancel.hidden = !(cfg.canCancel && h.status === 'P' && !h.reconciled); // reconciled with the bank: the next step is done
            cancel.onclick = async () => {
                if (!(await Phai.confirm(cfg.text.confirm))) return;
                await Phai.api.post(cfg.api + '/' + encodeURIComponent(cfg.nbr) + '/cancel');
                location.reload();
            };
        })();
        return;
    }

    // ------------------------------------------------------------ new document

    el[p + '_date'].value = cfg.today;
    document.getElementById('st-save').hidden = false;
    party.focus();

    Phai.pick.source('party', { url: cfg.partyApi, value: cfg.partyKey, label: cfg.partyName });

    async function loadParty() {
        const code = party.value.trim().toUpperCase();
        party.value = code;
        const found = code ? (await Phai.api.get(cfg.partyApi, { q: code })).find((c) => c[cfg.partyKey] === code) : null;
        const rows = found ? await Phai.api.get(cfg.api + '/open', { [cfg.party]: code }) : [];
        tbody.innerHTML = rows.length
            ? rows.map((r) => row(r, true)).join('')
            : (found ? `<tr><td colspan="${cols}" class="text-center text-secondary py-3">${Phai.esc(cfg.text.none)}</td></tr>` : '');
        whtBody?.querySelectorAll('[data-wht-base]').forEach((b) => { delete b.dataset.touched; });
        whtManual = false; // another vendor: its payables' lines say what to withhold again
        total();
    }
    party.addEventListener('change', loadParty);

    // the arrow fills in the whole balance
    tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-full]');
        if (!btn) return;
        const tr = btn.closest('tr');
        const w = tr.querySelector('[data-wht]');
        tr.querySelector('[data-amt]').value = money(Number(tr.dataset.bal) - (w ? num(w.value) : 0));
        total();
    });
    // withheld tax reduces what is received for the same balance
    tbody.addEventListener('change', (e) => {
        if (e.target.matches('[data-wht]')) {
            const tr = e.target.closest('tr');
            const a = tr.querySelector('[data-amt]');
            if (num(a.value) + num(e.target.value) > Number(tr.dataset.bal)) a.value = money(Number(tr.dataset.bal) - num(e.target.value));
        }
        total();
    });
    // opened from a document or a work queue: ?vendor=V&vo=VO1,VO2 / ?customer=C&inv=IV1 fills those in full
    const pre = new URLSearchParams(location.search);
    if (pre.get(cfg.party)) {
        party.value = pre.get(cfg.party);
        loadParty().then(() => {
            for (const doc of (pre.get('vo') || pre.get('inv') || '').split(',').filter(Boolean)) {
                const tr = tbody.querySelector(`tr[data-doc="${CSS.escape(doc)}"]`);
                if (tr) tr.querySelector('[data-amt]').value = money(Number(tr.dataset.bal));
            }
            total();
        });
    }
    if (pay) {
        const addWht = () => {
            // a further rate starts with what is left of the base
            const used = [...whtBody.querySelectorAll('[data-wht-base]')].reduce((s, b) => s + num(b.value), 0);
            whtBody.insertAdjacentHTML('beforeend', whtRow({ base: whtBody.children.length ? Math.max(0, suggestedBase() - used) : 0 }, true));
            const last = whtBody.lastElementChild.querySelector('[data-wht-base]');
            if (whtBody.children.length > 1) last.dataset.touched = '1';
            whtNames();
            total();
        };
        addWht();
        document.getElementById('st-wht-add').addEventListener('click', () => { whtManual = true; addWht(); });
        whtBody.addEventListener('change', () => { whtManual = true; total(); });
        whtBody.addEventListener('input', (e) => {
            whtManual = true;
            if (e.target.matches('[data-wht-base]')) e.target.dataset.touched = '1';
            total();
        });
        whtBody.addEventListener('click', (e) => {
            if (!e.target.closest('[data-wht-del]')) return;
            whtManual = true;
            e.target.closest('tr').remove();
            if (!whtBody.children.length) addWht();
            whtNames();
            total();
        });
    }
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') e.preventDefault();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('st-save');
        const lines = {};
        tbody.querySelectorAll('tr[data-doc]').forEach((tr) => {
            const a = tr.querySelector('[data-amt]').value;
            const w = tr.querySelector('[data-wht]')?.value ?? '';
            if (a.trim() !== '' || w.trim() !== '') lines[tr.dataset.doc] = { amt: a.replace(/,/g, ''), wht: w.replace(/,/g, '') };
        });
        const body = { [cfg.party]: party.value, lines };
        if (el.doc_nbr) { body.doc_nbr = el.doc_nbr.value; body.doc_type = pay ? 'PV' : 'CR'; }
        for (const n of ['date', 'bank', 'remarks']) body[`${p}_${n}`] = el[`${p}_${n}`].value;
        if (pay) {
            body.vo_wht_form = el.wht_form.value;
            body.wht = [...whtBody.querySelectorAll('tr[data-wht-row]')].map((tr) => ({
                code: tr.querySelector('[data-wht-code]').value,
                base: tr.querySelector('[data-wht-base]').value.replace(/,/g, ''),
            }));
        }
        Phai.form.errors(form, null);
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post(cfg.api, body);
            location.href = Phai.url(cfg.page + '/' + encodeURIComponent(res.nbr));
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
            Phai.busy(btn, false);
        }
    });
})();
