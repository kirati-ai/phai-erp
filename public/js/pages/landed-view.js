/* Landed costs of one receipt (views/purchase/landed-view.php): view, Edit the costs, allocate them into item cost. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('lc-config').textContent);
    const form = document.getElementById('lc-form');
    const $ = (id) => document.getElementById(id);
    const api = '/api/landed-costs/' + encodeURIComponent(cfg.rc);
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const tbody = $('lc-costs');
    const num = (v) => Number(String(v ?? '').replace(/,/g, '')) || 0;
    let data = null;
    let editing = false;
    let rows = 0;

    /** Each line's share of the costs not allocated yet, by value; total of all costs. */
    function shares() {
        let waiting = 0;
        let sum = 0;
        tbody.querySelectorAll('tr').forEach((tr) => {
            const amount = tr.dataset.locked === '1' ? Number(tr.dataset.amount) : num(tr.querySelector('[name$=".amount"]')?.value);
            sum += amount;
            if (tr.dataset.locked !== '1') waiting += amount;
        });
        $('lc-sum').textContent = money(sum);
        const total = data.lines.reduce((s, l) => s + Number(l.value), 0);
        $('lc-lines').innerHTML = data.lines.map((l) => `
            <tr>
                <td>${Phai.esc(l.rcd_part)}</td>
                <td>${Phai.esc(l.pt_desc1 ?? '')}</td>
                <td class="num">${Phai.esc(Number(l.rcd_qty_rcvd).toLocaleString('en-US', { maximumFractionDigits: 5 }))}</td>
                <td class="num">${Phai.esc(money(l.value))}</td>
                <td class="num">${total > 0 && waiting ? Phai.esc(money(waiting * Number(l.value) / total)) : ''}</td>
            </tr>`).join('');
    }

    /** One cost line: plain values, or inputs while editing (allocated lines stay locked). */
    function row(c) {
        const edit = editing && !c.allocated;
        const n = `lines.${rows++}`;
        const tr = document.createElement('tr');
        tr.dataset.locked = c.allocated ? '1' : '';
        tr.dataset.amount = c.amount || 0;
        const types = Object.entries(cfg.types).map(([k, v]) => `<option value="${Phai.esc(k)}"${k === c.type ? ' selected' : ''}>${Phai.esc(v)}</option>`).join('');
        const apRef = c.ap_ref ? `<a href="${Phai.url('/payables/' + encodeURIComponent(c.ap_ref))}">${Phai.esc(c.ap_ref)}</a>` : '';
        tr.innerHTML = edit ? `
            <td class="text-secondary">${Phai.esc(c.line ?? '')}</td>
            <td><select class="form-select form-select-sm" name="${n}.type">${types}</select></td>
            <td><input class="form-control form-control-sm" name="${n}.ref" maxlength="50" value="${Phai.esc(c.ref ?? '')}"></td>
            <td><input class="form-control form-control-sm text-uppercase" name="${n}.vendor" data-pick="vendors" autocomplete="off" value="${Phai.esc(c.vendor ?? '')}"></td>
            <td><input class="form-control form-control-sm text-end" name="${n}.amount" inputmode="decimal" value="${c.amount ? Phai.esc(String(c.amount)) : ''}"></td>
            <td></td>
            <td><button type="button" class="btn btn-sm btn-ghost-danger btn-icon" data-remove aria-label="${Phai.esc(cfg.text.remove)}"><i class="ti ti-x"></i></button></td>` : `
            <td class="text-secondary">${Phai.esc(c.line ?? '')}</td>
            <td>${Phai.esc(cfg.types[c.type] ?? c.type)}${c.allocated ? ` <span class="badge bg-green-lt">${Phai.esc(cfg.text.allocated)}</span>` : ''}</td>
            <td>${Phai.esc(c.ref ?? '')}</td>
            <td>${Phai.esc(c.vendor ?? '')}</td>
            <td class="num">${money(c.amount)}</td>
            <td>${apRef}</td>
            <td></td>`;
        tbody.append(tr);
        return tr;
    }

    function render() {
        rows = 0;
        tbody.innerHTML = '';
        data.costs.forEach(row);
        if (editing && !data.costs.some((c) => !c.allocated)) row({ type: cfg.types.FREIGHT ? 'FREIGHT' : Object.keys(cfg.types)[0] });
        const any = data.costs.length > 0;
        const waiting = data.costs.some((c) => !c.allocated);
        const badge = $('lc-status');
        const st = !any ? ['secondary', cfg.text.none] : waiting ? ['yellow', cfg.text.waiting] : data.billed ? ['blue', cfg.text.billed] : ['green', cfg.text.allocated];
        badge.className = `badge bg-${st[0]}-lt`;
        badge.textContent = st[1];
        $('lc-allocate').hidden = editing || !(cfg.canAllocate && waiting);
        $('lc-edit').hidden = editing || !cfg.canEdit || data.billed;
        $('lc-save').hidden = !editing;
        $('lc-undo').hidden = !editing;
        $('lc-add-row').hidden = !editing;
        shares();
    }

    async function load() {
        data = await Phai.api.get(api);
        render();
    }

    $('lc-edit').addEventListener('click', () => {
        editing = true;
        render();
        form.querySelector('input:not([disabled])')?.focus();
    });

    // Cancel: back to the saved costs
    $('lc-undo').addEventListener('click', () => {
        editing = false;
        Phai.form.errors(form, null);
        render();
    });

    form.addEventListener('input', (e) => {
        if (e.target.name?.endsWith('.amount')) shares();
    });

    $('lc-add').addEventListener('click', () => {
        row({ type: Object.keys(cfg.types)[0] }).querySelector('select, input')?.focus();
    });

    tbody.addEventListener('click', (e) => {
        if (!e.target.closest('[data-remove]')) return;
        e.target.closest('tr').remove();
        shares();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = { lines: {} };
        tbody.querySelectorAll('[name^="lines."]').forEach((i) => {
            const [, r, f] = i.name.split('.');
            (body.lines[r] ??= {})[f] = i.value;
        });
        Phai.form.errors(form, null);
        Phai.busy($('lc-save'), true);
        try {
            data = await Phai.api.put(api, body);
            editing = false;
            Phai.toast(Phai.t('saved'));
            render();
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
        } finally {
            Phai.busy($('lc-save'), false);
        }
    });

    $('lc-allocate').addEventListener('click', async () => {
        if (!(await Phai.confirm(cfg.text.confirm))) return;
        const res = await Phai.api.post(api + '/allocate');
        Phai.toast(cfg.text.done.replace(':n', res.parts));
        await load();
    });

    load();
})();
