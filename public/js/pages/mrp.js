/* MRP (views/production/mrp.php): run, planned orders → requisition / work orders, action messages, the MRP record per item. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('mrp-config').textContent);
    const tbody = document.querySelector('#mrp tbody');
    const num = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 5 });
    const date = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '');

    const T = cfg.text;
    const blank = (v) => (Math.abs(Number(v || 0)) < 0.000001 ? '' : num(v));
    const typeBadge = (type) => `<span class="badge ${type === 'WO' ? 'bg-purple-lt' : 'bg-blue-lt'}">${Phai.esc(type === 'WO' ? T.make : T.buy)}</span>`;
    const msgColor = { RELEASE: 'blue', IN: 'red', OUT: 'orange', CANCEL: 'secondary' };
    let data = { items: [], weeks: [], messages: [] };

    function render(d) {
        data = d;
        document.getElementById('mrp-ran').textContent = d.ran_at
            ? T.ran.replace(':at', date(d.ran_at) + ' ' + String(d.ran_at).slice(11, 16)).replace(':by', d.ran_by ?? '') : T.never;
        tbody.innerHTML = d.rows.length ? d.rows.map((r) => `
            <tr data-type="${Phai.esc(r.mrp_type)}" data-part="${Phai.esc(r.mrp_part)}" data-due="${Phai.esc(r.mrp_due_date)}">
                <td><input type="checkbox" class="form-check-input m-0"></td>
                <td>${typeBadge(r.mrp_type)}</td>
                <td>${Phai.esc(r.mrp_part)}</td>
                <td>${Phai.esc(r.pt_desc1 ?? '')}</td>
                <td class="num">${Phai.esc(num(r.mrp_qty))}</td>
                <td class="text-secondary">${Phai.esc(r.pt_um ?? '')}</td>
                <td>${Phai.esc(date(r.mrp_rel_date))}</td>
                <td>${Phai.esc(date(r.mrp_due_date))}${r.mrp_due_date < cfg.today ? ` <span class="badge bg-red-lt">${Phai.esc(T.late)}</span>` : ''}</td>
                <td class="text-secondary">${Phai.esc(r.mrp_ref ?? '')}</td>
            </tr>`).join('')
            : `<tr><td colspan="9" class="text-center text-secondary py-3">${Phai.esc(Phai.t('empty'))}</td></tr>`;
        document.getElementById('mrp-all').checked = false;
        sync();
        drawMessages();
        drawItems();
    }

    // action messages: what the planner should do with each order
    function drawMessages() {
        const body = document.querySelector('#mrp-messages tbody');
        body.innerHTML = data.messages.length ? data.messages.map((m) => `<tr>
                <td><span class="badge bg-${msgColor[m.type]}-lt">${Phai.esc(T.msg[m.type])}</span></td>
                <td>${Phai.esc(m.part)}</td>
                <td>${Phai.esc(m.desc ?? '')}</td>
                <td>${m.type === 'RELEASE' ? Phai.esc(T['plan_' + m.kind]) : `${Phai.esc(T.kind[m.kind])} ${Phai.esc(m.ref)}`}</td>
                <td class="num">${num(m.qty)} <span class="text-secondary">${Phai.esc(m.um ?? '')}</span></td>
                <td${(m.type === 'RELEASE' && m.date < cfg.today) || m.type === 'IN' ? ' class="text-danger"' : ''}>${Phai.esc(T.say[m.type].replace(':date', date(m.date)).replace(':to', date(m.to)))}</td>
            </tr>`).join('')
            : `<tr><td colspan="6" class="text-center text-secondary py-3">${Phai.esc(Phai.t('empty'))}</td></tr>`;
    }

    // the MRP record of each item: a summary row; a click opens the weekly grid and the detail behind it
    const itemBody = document.querySelector('#mrp-items tbody');
    const opened = new Set();
    function weekHead(w, i) {
        if (w === null) return T.past;
        const label = date(w).slice(0, 5);
        return data.more && i === data.weeks.length - 1 ? '≥ ' + label : label;
    }
    function grid(it) {
        const line = (key, strong) => `<tr${strong ? ' class="fw-semibold"' : ''}><td class="text-nowrap">${Phai.esc(T['g_' + key])}</td>
            <td class="num">${key === 'poh' ? num(it.onhand) : ''}</td>
            ${it.grid[key].map((v) => `<td class="num${key === 'poh' && v < it.safety ? ' text-danger' : ''}${key === 'net' && v > 0 ? ' text-orange' : ''}">${key === 'poh' ? num(v) : blank(v)}</td>`).join('')}</tr>`;
        return `<table class="table table-sm mb-2 phai-mrp-grid">
            <thead><tr><th></th><th class="text-end">${Phai.esc(T.start)}</th>${data.weeks.map((w, i) => `<th class="text-end">${Phai.esc(weekHead(w, i))}</th>`).join('')}</tr></thead>
            <tbody>${line('gross')}${line('sched')}${line('poh', true)}${line('net')}${line('receipt')}${line('release', true)}</tbody></table>`;
    }
    function pegging(it) {
        const rows = it.rows.map((r) => {
            const plan = r.kind === 'PLAN';
            return `<tr><td>${Phai.esc(date(r.date))}</td>
                <td>${Phai.esc(T.kind[r.kind] ?? r.kind)} <span class="text-secondary">${Phai.esc(plan ? T.order_by.replace(':date', date(r.ref)) : r.ref)}</span></td>
                <td class="num">${!plan && r.qty < 0 ? num(-r.qty) : ''}</td><td class="num">${!plan && r.qty > 0 ? num(r.qty) : ''}</td>
                <td class="num">${plan ? num(r.qty) : ''}</td>
                <td class="num${r.balance < it.safety ? ' text-danger' : ''}">${num(r.balance)}</td></tr>`;
        }).join('');
        return `<div class="small text-secondary mb-1">${Phai.esc(T.pegging)}</div>
            <table class="table table-sm mb-0 phai-mrp-grid">
            <thead><tr><th>${Phai.esc(T.date)}</th><th>${Phai.esc(T.doc)}</th><th class="text-end">${Phai.esc(T.need)}</th>
                <th class="text-end">${Phai.esc(T.coming)}</th><th class="text-end">${Phai.esc(T.order)}</th><th class="text-end">${Phai.esc(T.end)}</th></tr></thead>
            <tbody><tr class="text-secondary"><td></td><td>${Phai.esc(T.stock)}</td><td></td><td></td><td></td><td class="num">${num(it.onhand)}</td></tr>${rows}</tbody></table>`;
    }
    function drawItems() {
        itemBody.innerHTML = data.items.length ? data.items.map((it) => {
            const msgs = Object.entries(it.messages || {});
            const badges = msgs.length
                ? msgs.map(([type, n]) => `<span class="badge bg-${msgColor[type]}-lt me-1">${Phai.esc(T.msg[type])}${n > 1 ? ' ' + n : ''}</span>`).join('')
                : (it.order ? '' : `<span class="badge bg-green-lt">${Phai.esc(T.ok)}</span>`);
            const shown = opened.has(it.part);
            let html = `<tr data-item="${Phai.esc(it.part)}"${shown ? ' class="phai-card-item"' : ''} style="cursor: pointer">
                <td><i class="ti ti-chevron-${shown ? 'down' : 'right'} text-secondary me-1"></i>${Phai.esc(it.part)}</td>
                <td>${Phai.esc(it.desc ?? '')} <span class="text-secondary">${Phai.esc(it.um ?? '')}</span></td>
                <td>${typeBadge(it.type)}</td>
                <td class="num">${it.level}</td>
                <td class="num">${num(it.onhand)}</td><td class="num">${blank(it.safety)}</td>
                <td class="num">${num(it.need)}</td><td class="num">${num(it.coming)}</td>
                <td class="num">${blank(it.order)}</td><td class="num${it.end < it.safety ? ' text-danger' : ''}">${num(it.end)}</td><td>${badges}</td></tr>`;
            if (shown) {
                const info = `${T.level} ${it.level} · ${T.lead.replace(':days', it.lead)} · ${T.lot}: ${it.lot} · ${T.safety} ${num(it.safety)} · ${T.onhand} ${num(it.onhand)} ${it.um ?? ''}`;
                html += `<tr><td colspan="11" class="px-3 py-2">
                    <div class="small text-secondary mb-1">${Phai.esc(info)}</div>
                    <div class="table-responsive">${grid(it)}</div>
                    <div class="table-responsive">${pegging(it)}</div></td></tr>`;
            }
            return html;
        }).join('') : `<tr><td colspan="11" class="text-center text-secondary py-3">${Phai.esc(Phai.t('empty'))}</td></tr>`;
    }
    itemBody.addEventListener('click', (e) => {
        const tr = e.target.closest('tr[data-item]');
        if (!tr) return;
        if (!opened.delete(tr.dataset.item)) opened.add(tr.dataset.item);
        drawItems();
    });

    /** Each button works on its own kind of row: buy → requisition, make → work orders; off until one of them is ticked. */
    function sync() {
        const pr = document.getElementById('mrp-pr');
        const wo = document.getElementById('mrp-wo');
        if (pr) pr.disabled = !tbody.querySelector('tr[data-type="PO"] input:checked');
        if (wo) wo.disabled = !tbody.querySelector('tr[data-type="WO"] input:checked');
    }
    tbody.addEventListener('change', sync);

    const load = async () => render(await Phai.api.get('/api/mrp'));

    document.getElementById('mrp-all').addEventListener('change', (e) => {
        tbody.querySelectorAll('input[type=checkbox]').forEach((b) => { b.checked = e.target.checked; });
        sync();
    });

    document.getElementById('mrp-run')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget; // gone from the event after the await
        Phai.busy(btn, true);
        try {
            render(await Phai.api.post('/api/mrp/run'));
        } finally {
            Phai.busy(btn, false);
            sync();
        }
    });

    /** Ticked rows of one type, as keys for the API. */
    const picked = (type) => [...tbody.querySelectorAll(`tr[data-type="${type}"] input:checked`)]
        .map((b) => b.closest('tr')).map((tr) => ({ part: tr.dataset.part, due: tr.dataset.due }));

    document.getElementById('mrp-pr')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post('/api/mrp/pr', { keys: picked('PO') });
            Phai.toast(cfg.text.pr_made.replace(':nbr', res.nbr));
            await load();
        } catch (err) {
            // toast already shown
        } finally {
            Phai.busy(btn, false);
            sync();
        }
    });

    document.getElementById('mrp-wo')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post('/api/mrp/wo', { keys: picked('WO') });
            Phai.toast(cfg.text.wo_made.replace(':list', res.wos.join(', ')));
            await load();
        } catch (err) {
            // toast already shown
        } finally {
            Phai.busy(btn, false);
            sync();
        }
    });

    load();
})();
