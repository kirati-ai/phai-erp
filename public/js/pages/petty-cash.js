/** เงินสดย่อย (views/accounting/petty-cash.php): a box by month, its entries, the month-end count, files on an entry. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('pc-config').textContent);
    const T = cfg.text;
    const $ = (id) => document.getElementById(id);
    const bs = window.bootstrap || window.tabler;
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (v) => Number(String(v ?? '').replace(/,/g, '')) || 0;
    const dmy = (d) => (d ? String(d).slice(0, 10).split('-').reverse().join('/') : '');
    const esc = Phai.esc;

    // lists to pick from: kept on the page, searched here; holders with money come from the server
    Phai.pick.source('pc-banks', { items: cfg.banks });
    Phai.pick.source('pc-accounts', { items: cfg.accounts });
    Phai.pick.source('pc-ccs', { items: cfg.ccs });
    Phai.pick.source('pc-incomes', { items: cfg.incomes });
    Phai.pick.source('pc-whts', { items: cfg.whts });
    Phai.pick.source('pc-holders', { load: async () => Phai.api.get('/api/petty-cash/holders') });

    let holder = cfg.holder === cfg.me ? '' : cfg.holder; // the list: '' = everyone, or one holder
    let month = cfg.month || cfg.today.slice(0, 7);
    let box = null; // my own box (the card and its buttons)
    let listData = null; // the month on screen, for the Excel file

    // ------------------------------------------------------------ my box: cash in hand, the month it works in, what I can do
    async function loadMine() {
        box = await Phai.api.get('/api/petty-cash/box', { holder: cfg.me });
        $('pc-name').textContent = `${T.mine} · ${box.name}`;
        $('pc-balance').textContent = money(box.balance);
        $('pc-open').textContent = box.open_text || T.any;
        $('pc-last').textContent = box.last_text || T.none_yet;
        $('pc-actions').hidden = !cfg.canAdd;
        // only someone holding cash spends it: with nothing in the box, only the ways to take cash in are offered
        $('pc-actions').querySelectorAll('[data-type="EX"], [data-type="RT"], [data-type="IN"]').forEach((b) => { b.hidden = !(Number(box.balance) > 0); });
        $('pc-readonly').hidden = cfg.canAdd;
        const countBtn = $('pc-count-btn');
        countBtn.hidden = !box.can_count;
        countBtn.querySelector('span').textContent = T.count_month.replace(':month', box.count_month_text);
        $('pc-uncount-btn').hidden = !box.can_uncount;
        $('pc-uncount-btn').querySelector('span').textContent = T.uncount_month.replace(':month', box.uncount_text || '');
    }

    // ------------------------------------------------------------ everyone's month: brought forward, entries, what is left
    async function loadList() {
        const d = await Phai.api.get('/api/petty-cash/month', { month, holder });
        listData = d;
        month = d.month;
        document.querySelector('#pc-month span').textContent = d.month_text;
        const reg = $('pc-register');
        if (reg) reg.href = Phai.url(`/petty-cash/register/${d.month}/print?holder=${encodeURIComponent(holder || cfg.me)}`);
        const warn = $('pc-warn');
        warn.hidden = !d.uncounted.length;
        warn.querySelector('span').textContent = T.warn_uncounted.replace(':n', d.uncounted.length).replace(':names', d.uncounted.map((u) => `${u.name} (${u.month_text})`).join(' · '));

        const cols = 14;
        const n = (v) => (v ? money(v) : '');
        // counted: green, not yet counted: orange — on the brought-forward and the month-end rows
        const tag = (ok, text) => `<span class="badge ${ok ? 'bg-green-lt' : 'bg-orange-lt'} ms-1"><i class="ti ${ok ? 'ti-lock' : 'ti-clock'} me-1"></i>${esc(text)}</span>`;
        const sumRow = (label, h, cls, left, badge = '', state = '', seq = '') => `<tr class="${cls}"><td class="num text-secondary">${seq}</td><td class="text-secondary">${esc(left ? T.row_left : T.row_brought)}</td><td colspan="7">${esc(label)}${badge}</td>
            <td class="num text-success">${left ? money(h.in) : ''}</td><td class="num text-danger">${left ? money(h.out) : ''}</td>
            <td class="num fw-medium ${state}">${money(left ? h.closing : h.opening)}</td><td></td><td>${h.name ? esc(h.name) : ''}</td></tr>`;
        let html = '';
        d.holders.forEach((h, i) => {
            html += sumRow(T.brought.replace(':who', h.name), h, 'phai-pc-opening', false,
                h.from ? tag(h.from_counted, h.from) : '', h.from ? (h.from_counted ? 'text-success' : 'text-orange') : '', i + 1);
        });
        if (d.holders.length > 1) html += sumRow(T.brought_all, { ...d.total, name: '' }, 'phai-pc-opening phai-pc-total', false);
        // three parts, ruled off: brought forward, the entries (numbered), what is left
        if (!d.rows.length) html += `<tr class="phai-pc-sep"><td colspan="${cols}" class="text-center text-secondary py-4">${esc(T.nothing)}</td></tr>`;
        d.rows.forEach((r, i) => {
            const x = r.cb_status === 'X';
            html += `<tr data-nbr="${esc(r.cb_nbr)}" class="${i === 0 ? 'phai-pc-sep ' : ''}${x ? 'text-decoration-line-through text-secondary' : ''}" role="button">
                <td class="num text-secondary">${i + 1}</td>
                <td class="fw-medium">${esc(r.cb_nbr)}</td>
                <td>${dmy(r.cb_date)}</td>
                <td>${esc(T[`type_${r.cb_type}`] || r.cb_type)}${x ? ` <span class="badge bg-secondary-lt">${esc(T.cancelled)}</span>` : ''}</td>
                <td>${esc(r.text)}${r.cb_remarks ? `<div class="small text-secondary">${esc(r.cb_remarks)}</div>` : ''}</td>
                <td>${esc(r.cb_inv_nbr || r.cb_rcpt_nbr || '')}</td>
                <td class="num">${r.bill !== null ? money(r.bill) : ''}</td>
                <td class="num">${n(Number(r.cb_vat_amt))}</td>
                <td class="num">${n(Number(r.cb_wht_amt))}</td>
                <td class="num text-success">${r.amt > 0 ? money(r.amt) : ''}</td>
                <td class="num text-danger">${r.amt < 0 ? money(-r.amt) : ''}</td>
                <td class="num">${money(r.bal)}</td>
                <td class="phai-act">${Phai.files.button('PC', r.cb_nbr, r.cb_nbr, Number(r.files))}</td>
                <td class="text-nowrap">${esc(r.holder_name)}</td>
            </tr>`;
        });
        const monthOver = d.month < cfg.today.slice(0, 7);
        d.holders.forEach((h, i) => {
            const counted = h.counted !== null;
            const badge = counted
                ? tag(true, `${T.counted_ok} ${money(h.counted)}${h.diff ? ` · ${h.diff < 0 ? T.short : T.over} ${money(Math.abs(h.diff))}` : ''}`)
                : tag(false, monthOver ? T.not_counted : T.not_counted_yet);
            html += sumRow(T.left.replace(':who', h.name), h, `phai-pc-closing${i === 0 ? ' phai-pc-sep' : ''}`, true, badge, counted ? 'text-success' : 'text-orange', i + 1);
        });
        if (d.holders.length > 1) html += sumRow(T.left_all, { ...d.total, name: '' }, 'phai-pc-closing phai-pc-total', true);
        $('pc-rows').tBodies[0].innerHTML = html;
    }

    async function holders() {
        const list = await Phai.api.get('/api/petty-cash/boxes');
        const sel = $('pc-holder');
        sel.innerHTML = `<option value="">${esc(T.everyone)}</option>`
            + list.map((b) => `<option value="${esc(b.holder)}">${esc(b.name)}</option>`).join('');
        sel.value = holder;
    }

    // the month on screen as an Excel file: the same rows as the table (brought forward, entries, what is left)
    $('pc-export').addEventListener('click', async () => {
        const d = listData;
        if (!d) return;
        const num = (v) => (v === null || v === undefined || v === '' ? '' : { t: 'num', v: Number(v), scale: 2 });
        const rows = [];
        d.holders.forEach((h, i) => rows.push([i + 1, T.row_brought, '', '', T.brought.replace(':who', h.name) + (h.from ? ` · ${h.from}` : ''), '', '', '', '', '', '', num(h.opening), '', h.name]));
        if (d.holders.length > 1) rows.push(['', T.row_brought, '', '', T.brought_all, '', '', '', '', '', '', num(d.total.opening), '', '']);
        for (const [i, r] of d.rows.entries()) {
            rows.push([i + 1, r.cb_nbr, { t: 'date', v: r.cb_date }, T[`type_${r.cb_type}`] || r.cb_type,
                r.text + (r.cb_status === 'X' ? ` (${T.cancelled})` : '') + (r.cb_remarks ? ` · ${r.cb_remarks}` : ''), r.cb_inv_nbr || r.cb_rcpt_nbr || '',
                num(r.bill), num(Number(r.cb_vat_amt) || ''), num(Number(r.cb_wht_amt) || ''), num(r.amt > 0 ? r.amt : ''), num(r.amt < 0 ? -r.amt : ''), num(r.bal),
                Number(r.files) || '', r.holder_name]);
        }
        d.holders.forEach((h, i) => rows.push([i + 1, T.row_left, '', '', T.left.replace(':who', h.name), '', '', '', '', num(h.in), num(h.out), num(h.closing), '', h.name]));
        if (d.holders.length > 1) rows.push(['', T.row_left, '', '', T.left_all, '', '', '', '', num(d.total.in), num(d.total.out), num(d.total.closing), '', '']);
        const labels = [T.seq, T.nbr, T.date, T.type, T.what, T.receipt_col, T.bill, T.vat, T.wht, T.in, T.out, T.balance, T.files, T.holder];
        const blob = await Phai.xlsx.write({
            name: d.month, title: `${T.title} ${d.month_text}`, note: d.uncounted.length ? T.not_counted_n.replace(':n', d.uncounted.length) : '',
            columns: labels.map((label, i) => ({ label, width: [6, 14, 11, 16, 40, 16, 12, 10, 10, 12, 12, 12, 6, 18][i] })), rows,
        });
        Phai.xlsx.save(blob, `${T.title}-${d.month}.xlsx`);
    });

    $('pc-holder').addEventListener('change', (e) => { holder = e.target.value; loadList(); });
    const shift = (k) => {
        const [y, m] = month.split('-').map(Number);
        const d = new Date(y, m - 1 + k, 1);
        month = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        loadList();
    };
    $('pc-prev').addEventListener('click', () => shift(-1));
    $('pc-next').addEventListener('click', () => shift(1));
    const refresh = () => Promise.all([loadMine(), loadList(), holders()]);
    // ------------------------------------------------------------ a new entry
    const entryEl = $('pc-entry');
    const entry = entryEl.querySelector('form');
    const entryModal = bs.Modal.getOrCreateInstance(entryEl);
    const lineBody = entry.querySelector('.phai-pc-lines tbody');
    Phai.pick.source('pc-vats', { items: cfg.taxes.filter((t) => Number(t.pct) > 0).map((t) => ({ value: t.value, label: `${Number(t.pct)}%` })) });

    function addLine(l = {}) {
        const i = lineBody.rows.length;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" class="form-control form-control-sm" name="lines.${i}.desc" maxlength="255" placeholder="${esc(T.desc_hint)}"></td>
            <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end" name="lines.${i}.amt"></td>
            <td><input class="form-control form-control-sm" name="lines.${i}.vat" data-pick="pc-vats" placeholder="${esc(T.no_vat)}"></td>
            <td><input class="form-control form-control-sm" name="lines.${i}.cc" data-pick="pc-ccs"><input type="hidden" name="lines.${i}.acct"></td>
            <td><input class="form-control form-control-sm" name="lines.${i}.wht" data-pick="pc-whts"></td>
            <td><button type="button" class="btn btn-sm btn-ghost-danger btn-icon" data-remove aria-label="−"><i class="ti ti-x"></i></button></td>`;
        lineBody.appendChild(tr);
        for (const [k, v] of Object.entries(l)) {
            const el = tr.querySelector(`[name="lines.${i}.${k}"]`);
            if (el) el.value = v ?? '';
        }
        return tr;
    }
    $('pc-add-line').addEventListener('click', () => addLine().querySelector('input').focus()); // the description first
    lineBody.addEventListener('click', (e) => {
        if (!e.target.closest('[data-remove]')) return;
        e.target.closest('tr').remove();
        // keep the names in order (errors come back as lines.<i>.<field>)
        [...lineBody.rows].forEach((tr, i) => tr.querySelectorAll('[name^="lines."]').forEach((el) => { el.name = el.name.replace(/^lines\.\d+\./, `lines.${i}.`); }));
        sums();
    });

    function readLines() {
        return [...lineBody.rows].map((tr) => {
            const v = (k) => tr.querySelector(`[name$=".${k}"]`)?.value.trim() || '';
            // the account is not on the form: a line keeps the one it had (or gets ค่าใช้จ่ายเบ็ดเตล็ด)
            return { acct: v('acct'), cc: v('cc'), desc: v('desc'), amt: v('amt'), vat: v('vat'), wht: v('wht') };
        });
    }

    function sums() {
        const lines = readLines();
        const net = lines.reduce((s, l) => s + num(l.amt), 0);
        const pct = (code) => Number(cfg.taxes.find((t) => t.value === code)?.pct || 0);
        const vat = lines.reduce((s, l) => s + Math.round(num(l.amt) * pct(l.vat)) / 100, 0);
        showInv(lines.some((l) => l.vat));
        let wht = 0;
        for (const l of lines) {
            const w = cfg.whts.find((x) => x.value === l.wht);
            if (w) wht += Math.round(num(l.amt) * Number(w.pct)) / 100;
        }
        $('pc-s-net').textContent = money(net);
        $('pc-s-vat').textContent = money(vat);
        $('pc-s-wht').textContent = wht ? `−${money(wht)}` : money(0);
        $('pc-s-paid').textContent = money(net + vat - wht);
        $('pc-wht-form-box').hidden = !lines.some((l) => l.wht);
    }
    entry.addEventListener('input', sums);
    entry.addEventListener('change', sums);
    // the tax invoice fields show while a line has VAT
    function showInv(on) {
        entry.querySelectorAll('[data-inv]').forEach((el) => { el.hidden = !on; el.querySelectorAll('input,select').forEach((i) => { i.disabled = !on; }); });
    }

    let editing = null; // the number being corrected, or null for a new entry
    function openEntry(type, d = null) {
        editing = d ? d.head.cb_nbr : null;
        const h = d?.head;
        Phai.form.fill(entry, h ? {
            type, date: h.cb_date, bank: h.cb_bank, from: h.cb_from, amount: Math.abs(Number(h.cb_amt)).toFixed(2), payee: h.cb_payee, vendor: h.cb_vd_code,
            acct: h.cb_acct, rcpt_nbr: h.cb_rcpt_nbr,
            inv_nbr: h.cb_inv_nbr, inv_date: h.cb_inv_date,
            wht_form: h.cb_wht_form || 'PND53', remarks: h.cb_remarks,
        } : { type, acct: cfg.income, date: box.open && box.open < cfg.today.slice(0, 7) ? `${box.open}-${new Date(box.open.slice(0, 4), box.open.slice(5, 7), 0).getDate()}` : cfg.today });
        entryEl.querySelector('.modal-title').textContent = editing ? T.edit_title.replace(':nbr', editing) : T[`type_${type}`];
        // only an expense with its lines needs the wide window; the others are a few fields
        entryEl.querySelector('.modal-dialog').classList.toggle('modal-xl', type === 'EX');
        $('pc-entry-nbr').textContent = editing || cfg.docNbr;
        entry.querySelectorAll('[data-for]').forEach((el) => {
            el.hidden = !el.dataset.for.split(' ').includes(type);
            el.querySelectorAll('input,select').forEach((i) => { i.disabled = el.hidden; }); // what the kind does not use is not sent
        });
        const payeeLabel = entry.querySelector('label[for="pc-payee"]');
        payeeLabel.textContent = type === 'IN' ? payeeLabel.dataset.labelIn : payeeLabel.dataset.labelEx;
        lineBody.innerHTML = '';
        if (d) d.lines.forEach((l) => addLine({ acct: l.cbd_acct, cc: l.cbd_cc, desc: l.cbd_desc, amt: Number(l.cbd_amt).toFixed(2), vat: l.cbd_tax_code, wht: l.cbd_wht_code }));
        else if (type === 'EX') addLine();
        sums();
        $('pc-files').value = '';
        entryModal.show();
        setTimeout(() => entry.querySelector(type === 'EX' || type === 'IN' ? '[name="payee"]' : type === 'TF' ? '[name="from"]' : '[name="bank"]')?.focus(), 300);
    }
    $('pc-actions').addEventListener('click', (e) => {
        const b = e.target.closest('[data-type]');
        if (b) openEntry(b.dataset.type);
    });

    // vendor chosen: its name, tax id and branch fill in (the payee can still be changed)
    entry.elements.vendor.addEventListener('change', async () => {
        const code = entry.elements.vendor.value;
        if (!code) return;
        try {
            const v = await Phai.api.get('/api/vendors/row', { vd_code: code });
            if (!entry.elements.payee.value) entry.elements.payee.value = v.vd_name || '';
            // the vendor's own withholding starts on lines without one
            if (v.vd_wht_code) [...lineBody.rows].forEach((tr) => { const w = tr.querySelector('[name$=".wht"]'); if (!w.value) w.value = v.vd_wht_code; });
            sums();
        } catch (_) { /* a vendor the user may not read: typed by hand */ }
    });

    entry.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Phai.form.read(entry);
        for (const k of Object.keys(data)) if (k.startsWith('lines.')) delete data[k];
        data.lines = data.type === 'EX' ? readLines() : [];
        const btn = entry.querySelector('[type="submit"]');
        Phai.form.errors(entry, null);
        Phai.busy(btn, true);
        try {
            const res = editing ? await Phai.api.put(`/api/petty-cash/${encodeURIComponent(editing)}`, data) : await Phai.api.post('/api/petty-cash', data);
            const files = [...$('pc-files').files];
            if (files.length) await Phai.files.send('PC', res.nbr, files);
            entryModal.hide();
            Phai.toast(`${T.saved} ${res.nbr}`);
            refresh();
        } catch (err) {
            if (err.fields) Phai.form.errors(entry, err.fields);
        } finally {
            Phai.busy(btn, false);
        }
    });

    // ------------------------------------------------------------ month-end count
    const countEl = $('pc-count');
    const count = countEl.querySelector('form');
    const countModal = bs.Modal.getOrCreateInstance(countEl);
    function countSums() {
        let total = 0;
        count.querySelectorAll('[data-note]').forEach((el) => {
            const v = num(el.value) * Number(el.dataset.note);
            total += v;
            count.querySelector(`[data-sum="${el.dataset.note}"]`).textContent = v ? money(v) : '';
        });
        total = Math.round(total * 100) / 100;
        const diff = Math.round((total - box.count_book) * 100) / 100;
        $('pc-c-counted').textContent = money(total);
        $('pc-c-diff').textContent = diff === 0 ? T.exact : `${diff < 0 ? T.short : T.over} ${money(Math.abs(diff))}`;
        $('pc-c-diff').classList.toggle('text-danger', diff !== 0);
    }
    count.addEventListener('input', countSums);
    $('pc-count-btn').addEventListener('click', () => {
        Phai.form.fill(count, { month: box.count_month });
        countEl.querySelector('.modal-title').textContent = $('pc-count-btn').querySelector('span').textContent;
        $('pc-c-book').textContent = money(box.count_book);
        countSums();
        countModal.show();
    });
    count.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!(await Phai.confirm(T.count_confirm))) return;
        const notes = {};
        count.querySelectorAll('[data-note]').forEach((el) => { notes[el.dataset.note] = el.value; });
        const btn = count.querySelector('[type="submit"]');
        Phai.form.errors(count, null);
        Phai.busy(btn, true);
        try {
            await Phai.api.post('/api/petty-cash/count', { month: count.elements.month.value, notes, remarks: count.elements.remarks.value });
            countModal.hide();
            Phai.toast(T.saved);
            month = '';
            refresh();
        } catch (err) {
            if (err.fields) Phai.form.errors(count, err.fields);
        } finally {
            Phai.busy(btn, false);
        }
    });
    $('pc-uncount-btn').addEventListener('click', async () => {
        if (!(await Phai.confirm(T.uncount_confirm))) return;
        await Phai.api.post('/api/petty-cash/count/cancel');
        month = box.uncount_month; // back to the month to correct
        holder = '';
        refresh();
    });

    // ------------------------------------------------------------ one entry to read
    const viewEl = $('pc-view');
    const viewModal = bs.Modal.getOrCreateInstance(viewEl);
    let viewing = null;
    async function show(nbr) {
        const d = await Phai.api.get(`/api/petty-cash/${encodeURIComponent(nbr)}`);
        viewing = d;
        const h = d.head;
        viewEl.querySelector('.modal-title').textContent = `${h.cb_nbr} · ${T[`type_${h.cb_type}`]}${h.cb_status === 'X' ? ` (${T.cancelled})` : ''}`;
        const row = (k, v) => (v === null || v === undefined || v === '' ? '' : `<tr><th class="text-secondary fw-normal">${esc(k)}</th><td>${v}</td></tr>`);
        let html = `<table class="table table-sm mb-2">${row(T.date, dmy(h.cb_date))}${row(T.holder, esc(h.holder_name))}
            ${row(T.what, esc(h.text))}${row(T.payee, esc(h.cb_payee || ''))}${row(T.inv_nbr, h.cb_inv_nbr ? esc(`${h.cb_inv_nbr} · ${dmy(h.cb_inv_date)}`) : '')}
            ${row(T.amount, `<b>${money(Math.abs(h.cb_amt))}</b>`)}${row(T.remarks, esc(h.cb_remarks || ''))}</table>`;
        if (d.lines.length) {
            html += `<table class="table table-sm mb-0"><thead><tr><th>${esc(T.acct)}</th><th>${esc(T.desc)}</th><th class="text-end">${esc(T.amt)}</th><th>${esc(T.wht_code)}</th></tr></thead><tbody>
                ${d.lines.map((l) => `<tr><td>${esc(`${l.cbd_acct} ${l.ac_desc_th || ''}`)}${l.cbd_cc ? ` <span class="text-secondary">${esc(l.cbd_cc)}</span>` : ''}</td><td>${esc(l.cbd_desc || '')}</td>
                    <td class="num">${money(l.cbd_amt)}</td><td>${esc(l.cbd_wht_code ? `${l.cbd_wht_code} ${Number(l.wht_pct)}%` : '')}</td></tr>`).join('')}</tbody></table>
                <table class="table table-sm phai-pc-sum ms-auto mt-1 mb-0">
                <tr><th>${esc(T.net)}</th><td class="num">${money(h.cb_net_amt)}</td></tr><tr><th>${esc(T.vat)}</th><td class="num">${money(h.cb_vat_amt)}</td></tr>
                <tr><th>${esc(T.wht)}</th><td class="num">${Number(h.cb_wht_amt) ? `−${money(h.cb_wht_amt)}` : money(0)}</td></tr>
                <tr class="fw-bold"><th>${esc(T.paid)}</th><td class="num">${money(h.cb_amt)}</td></tr></table>`;
        }
        $('pc-v-body').innerHTML = html;
        const box = $('pc-v-files'); // the shared files box: แนบไฟล์ and × only for the holder while the month is open
        box.dataset.nbr = h.cb_nbr;
        Phai.files.load(box);
        $('pc-v-cancel').hidden = !d.can.cancel;
        $('pc-v-edit').hidden = !d.can.edit;
        $('pc-v-print').hidden = !d.can.print;
        $('pc-v-print').href = Phai.url(`/petty-cash/${encodeURIComponent(h.cb_nbr)}/print`);
        $('pc-v-wht').hidden = !d.wht;
        if (d.wht) $('pc-v-wht').href = Phai.url(`/wht/${encodeURIComponent(d.wht)}/print`);
        viewModal.show();
    }
    // files added or removed in the entry: the month's table shows the new number once the entry is closed
    let filesChanged = false;
    $('pc-v-files').addEventListener('phai:files', (e) => { if (viewing && e.detail.count !== viewing.files.length) filesChanged = true; });
    $('pc-v-files').closest('.modal').addEventListener('hidden.bs.modal', () => {
        if (!filesChanged || document.querySelector('.modal.show')) return; // only when it is really closed, not under a confirm box
        filesChanged = false;
        refresh();
    });
    $('pc-v-edit').addEventListener('click', () => {
        viewModal.hide();
        openEntry(viewing.head.cb_type, viewing);
    });
    $('pc-v-cancel').addEventListener('click', async () => {
        if (!(await Phai.confirm(T.cancel_confirm))) return;
        await Phai.api.post(`/api/petty-cash/${encodeURIComponent(viewing.head.cb_nbr)}/cancel`);
        viewModal.hide();
        refresh();
    });
    $('pc-rows').addEventListener('click', (e) => {
        const tr = e.target.closest('tr[data-nbr]');
        if (tr) show(tr.dataset.nbr);
    });

    refresh();
})();
