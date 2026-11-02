/* Delivery schedule of a scheduled order (views/doc/schedule.php): its releases; view one (against the release before),
   issue a new one by hand or (purchase side) from MRP, print it. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('sc-config').textContent);
    const T = cfg.text;
    const $ = (id) => document.getElementById(id);
    const form = $('sc-form');
    const head = $('sc-head');
    const box = $('sc-lines');
    const api = '/api/schedules/' + cfg.type + '/' + encodeURIComponent(cfg.nbr);
    const qty = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 5 });
    const date = (v) => (v ? v.split('-').reverse().join('/') : '');
    const num = (v) => Number(String(v ?? '').replace(/,/g, '')) || 0;
    const plusDays = (iso, n) => {
        const d = new Date(iso + 'T00:00:00');
        d.setDate(d.getDate() + n);
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    };
    let data = null;     // the release shown (GET api)
    let edit = null;     // while issuing a new release: {source, lines: {line: rows}, prev: {line|date: qty}}
    let seq = 0;

    const fppsBadge = (f) => `<span class="badge ${f === 'F' ? 'bg-green-lt' : 'bg-azure-lt'}">${Phai.esc(f === 'F' ? T.fpps_F : T.fpps_P)}</span>`;
    const prevCell = (prev, q) => (prev === null || prev === undefined
        ? `<span class="badge bg-blue-lt">${Phai.esc(T.new_row)}</span>`
        : (Math.abs(prev - q) > 0.000001 ? `<span class="text-orange">${Phai.esc(qty(prev))}</span>` : `<span class="text-secondary">${Phai.esc(qty(prev))}</span>`));
    const firm = () => form.querySelector('[name="firm_date"]')?.value || data.release?.firm_date || '';

    /** What is received / shipped, given to the earliest instalments first. */
    function allocate(line, rows) {
        let left = line.done;
        return [...rows].sort((a, b) => String(a.date).localeCompare(String(b.date))).map((r) => {
            const d = Math.min(r.qty, Math.max(0, left));
            left -= d;
            return { ...r, done: d };
        });
    }

    function headView() {
        const r = data.release;
        if (!r) return '';
        const active = r.active;
        const item = (label, value) => (value ? `<span><span class="text-secondary">${Phai.esc(label)}</span> ${value}</span>` : '');
        return `<div class="d-flex flex-wrap gap-3 small align-items-center">
            ${item(T.rlse, `<b>${Phai.esc(r.id)}</b> <span class="badge ${active ? 'bg-green-lt' : 'bg-secondary-lt'}">${Phai.esc(active ? T.in_use : T.old)}</span>`)}
            ${item(T.rlse_date, Phai.esc(date(r.date)))}
            ${item(T.firm_date, `<b>${Phai.esc(date(r.firm_date))}</b>`)}
            ${item(T.source, Phai.esc(T['source_' + r.source] ?? r.source))}
            ${item(T.ref, Phai.esc(r.ref ?? ''))}
            ${item(T.by, Phai.esc(r.by ?? ''))}
            ${item(T.rmks, Phai.esc(r.rmks ?? ''))}</div>`;
    }

    function headEdit() {
        const sources = cfg.sources.filter((s) => s !== 'MRP' || edit.source === 'MRP');
        const field = (label, html, cls = 'col-sm-6 col-lg-2') => `<div class="${cls}"><label class="form-label small mb-0">${Phai.esc(label)}</label>${html}</div>`;
        return `<div class="row g-2">
            ${field(T.rlse_date, `<input type="date" class="form-control form-control-sm" name="date" value="${Phai.esc(cfg.today)}">`)}
            ${field(T.firm_date, `<input type="date" class="form-control form-control-sm" name="firm_date" value="${Phai.esc(edit.firm)}">`)}
            ${field(T.source, sources.length > 1
                ? `<select class="form-select form-select-sm" name="source">${sources.map((s) => `<option value="${s}"${s === edit.source ? ' selected' : ''}>${Phai.esc(T['source_' + s])}</option>`).join('')}</select>`
                : `<input type="hidden" name="source" value="${Phai.esc(edit.source)}"><div class="form-control-plaintext form-control-sm">${Phai.esc(T['source_' + edit.source])}</div>`)}
            ${field(T.ref, '<input class="form-control form-control-sm" name="ref" maxlength="50">')}
            ${field(T.rmks, '<input class="form-control form-control-sm" name="rmks" maxlength="255">', 'col-lg-4')}</div>`;
    }

    /** One instalment: plain values, or inputs while issuing a release (one fully received / shipped stays as it is). */
    function row(l, r, prev) {
        const n = `lines.${l.line}.rows.${seq++}`;
        const tr = document.createElement('tr');
        const locked = r.done > 0 && r.done >= r.qty;
        tr.dataset.done = r.done || 0;
        const done = `<td class="num">${r.done ? Phai.esc(qty(r.done)) : ''}</td><td class="num" data-left>${r.qty ? Phai.esc(qty(r.qty - (r.done || 0))) : ''}</td>`;
        if (edit && !locked) {
            tr.innerHTML = `
                <td><input type="date" class="form-control form-control-sm" name="${n}.date" value="${Phai.esc(r.date ?? '')}"></td>
                <td><input class="form-control form-control-sm text-end" name="${n}.qty" inputmode="decimal" value="${r.qty ? Phai.esc(String(r.qty)) : ''}"></td>
                <td data-fpps></td><td class="num" data-prev></td>${done}
                <td><button type="button" class="btn btn-sm btn-ghost-danger btn-icon" data-remove aria-label="${Phai.esc(T.remove)}"><i class="ti ti-x"></i></button></td>`;
        } else {
            tr.innerHTML = `
                <td>${Phai.esc(date(r.date))}${edit ? `<input type="hidden" name="${n}.date" value="${Phai.esc(r.date)}">` : ''}</td>
                <td class="num">${Phai.esc(qty(r.qty))}${edit ? `<input type="hidden" name="${n}.qty" value="${Phai.esc(String(r.qty))}">` : ''}</td>
                <td data-fpps>${edit ? '' : fppsBadge(r.fpps)}</td><td class="num" data-prev>${edit || !prev ? '' : prevCell(r.prev, r.qty)}</td>${done}<td></td>`;
        }
        return tr;
    }

    /** While editing: each row's type from the firm date, and what the release in use had on that date. */
    function refresh() {
        if (!edit) return;
        const f = firm();
        box.querySelectorAll('[data-line] tbody tr').forEach((tr) => {
            const d = tr.querySelector('[name$=".date"]')?.value;
            const line = tr.closest('[data-line]').dataset.line;
            const q = num(tr.querySelector('[name$=".qty"]')?.value);
            tr.querySelector('[data-fpps]').innerHTML = d ? fppsBadge(d <= f ? 'F' : 'P') : '';
            tr.querySelector('[data-prev]').innerHTML = d ? prevCell(edit.prev[line + '|' + d] ?? null, q) : '';
            tr.querySelector('[data-left]').textContent = q ? qty(q - num(tr.dataset.done)) : '';
        });
        box.querySelectorAll('[data-line]').forEach(totals);
    }

    function render() {
        seq = 0;
        const releases = data.head.releases;
        const sel = $('sc-release');
        sel.innerHTML = releases.map((r, i) => `<option value="${Phai.esc(r.id)}"${r.id === data.release?.id ? ' selected' : ''}>${Phai.esc(r.id)} · ${Phai.esc(date(r.date))} · ${Phai.esc(T['source_' + r.source] ?? r.source)}${i === 0 ? ' · ' + Phai.esc(T.in_use) : ''}</option>`).join('');
        sel.hidden = !!edit || releases.length < 2;
        head.innerHTML = edit ? headEdit() : headView();
        const index = releases.findIndex((r) => r.id === data.release?.id);
        const prev = !edit && index >= 0 && index < releases.length - 1;
        box.innerHTML = '';
        data.lines.forEach((l) => {
            const card = document.createElement('div');
            card.className = 'card mb-3';
            card.dataset.line = l.line;
            const rows = edit ? allocate(l, edit.lines[l.line] ?? []) : l.rows;
            card.innerHTML = `
                <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-baseline">
                    <strong>${l.line}. ${Phai.esc(l.part)}</strong><span class="text-secondary">${Phai.esc(l.desc ?? '')}</span>
                    <span class="ms-auto small">${Phai.esc(T.total)} <b data-total></b> ${Phai.esc(l.um)}
                        · ${Phai.esc(T.done)} ${Phai.esc(qty(l.done))}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-vcenter card-table mb-0">
                        <thead><tr><th style="width: 11rem">${Phai.esc(T.date)}</th><th class="text-end" style="width: 9rem">${Phai.esc(T.qty)}</th>
                            <th style="width: 8rem">${Phai.esc(T.fpps)}</th><th class="text-end" style="width: 8rem">${edit || prev ? Phai.esc(T.prev) : ''}</th>
                            <th class="text-end" style="width: 8rem">${Phai.esc(T.done)}</th><th class="text-end" style="width: 8rem">${Phai.esc(T.left)}</th><th></th></tr></thead>
                        <tbody></tbody>
                        <tfoot><tr class="phai-card-total"><td>${Phai.esc(T.sum)}</td><td class="num" data-sum="qty"></td><td class="small" data-sum="fpps"></td>
                            <td class="num" data-sum="prev"></td><td class="num" data-sum="done"></td><td class="num" data-sum="left"></td><td></td></tr></tfoot>
                    </table>
                </div>
                <div class="card-footer py-2" ${edit ? '' : 'hidden'}>
                    <button type="button" class="btn btn-sm btn-ghost-primary" data-add><i class="ti ti-plus me-1"></i>${Phai.esc(T.add)}</button>
                </div>`;
            const tbody = card.querySelector('tbody');
            rows.forEach((r) => tbody.append(row(l, r, prev)));
            if (!edit) {
                (l.removed || []).forEach((r) => tbody.insertAdjacentHTML('beforeend', `<tr class="text-secondary"><td><s>${Phai.esc(date(r.date))}</s></td>
                    <td class="num"><s>${Phai.esc(qty(r.qty))}</s></td><td><span class="badge bg-red-lt">${Phai.esc(T.dropped)}</span></td>
                    <td class="num">${Phai.esc(qty(r.qty))}</td><td></td><td></td><td></td></tr>`));
            }
            box.append(card);
            totals(card);
        });
        const can = cfg.canEdit && data.head.open && !edit;
        $('sc-edit').hidden = !can;
        if ($('sc-mrp')) $('sc-mrp').hidden = !can;
        if ($('sc-print')) {
            $('sc-print').hidden = !!edit || !data.release;
            $('sc-print').href = Phai.url(`/schedules/${cfg.type}/${encodeURIComponent(cfg.nbr)}/print?` + new URLSearchParams({ rlse: data.release?.id ?? '' }));
            Phai.signButton($('sc-print'));
        }
        $('sc-undo').hidden = !edit;
        $('sc-save').hidden = !edit;
        refresh();
    }

    /** The line's totals: all instalments (firm / planned), the release before, received / shipped, still open. */
    function totals(card) {
        const line = card.dataset.line;
        const l = data.lines.find((x) => String(x.line) === line);
        let rows;
        let prev;
        if (edit) {
            const f = firm();
            rows = [...card.querySelectorAll('tbody tr')].map((tr) => {
                const d = tr.querySelector('[name$=".date"]')?.value ?? '';
                return { qty: num(tr.querySelector('[name$=".qty"]')?.value), fpps: d && d <= f ? 'F' : 'P' };
            });
            prev = Object.entries(edit.prev).filter(([k]) => k.startsWith(line + '|')).reduce((s, [, v]) => s + v, 0);
        } else {
            rows = l?.rows ?? [];
            prev = rows.reduce((s, r) => s + (r.prev ?? 0), 0) + (l?.removed ?? []).reduce((s, r) => s + r.qty, 0);
        }
        const sum = rows.reduce((s, r) => s + r.qty, 0);
        const firmQty = rows.filter((r) => r.fpps === 'F').reduce((s, r) => s + r.qty, 0);
        const set = (k, v) => { card.querySelector(`[data-sum="${k}"]`).innerHTML = v; };
        card.querySelector('[data-total]').textContent = qty(sum);
        set('qty', Phai.esc(qty(sum)));
        set('fpps', `${Phai.esc(T.fpps_F)} ${Phai.esc(qty(firmQty))} · ${Phai.esc(T.fpps_P)} ${Phai.esc(qty(sum - firmQty))}`);
        set('prev', edit || (l?.rows ?? []).some((r) => r.prev !== null) ? Phai.esc(qty(prev)) : '');
        set('done', Phai.esc(qty(l?.done ?? 0)));
        set('left', Phai.esc(qty(sum - (l?.done ?? 0))));
    }

    async function load(rlse = '') {
        data = await Phai.api.get(api, rlse ? { rlse } : {});
        render();
    }

    /** Start a new release from the one in use (by hand) or from MRP's draft. */
    async function start(fromMrp) {
        if (!data.release?.active) await load();
        const today = cfg.today;
        const f = plusDays(today, cfg.firmDays);
        const prev = {};
        data.lines.forEach((l) => l.rows.forEach((r) => { prev[l.line + '|' + r.date] = (prev[l.line + '|' + r.date] ?? 0) + r.qty; }));
        let lines = Object.fromEntries(data.lines.map((l) => [l.line, l.rows.map((r) => ({ date: r.date, qty: r.qty }))]));
        if (fromMrp) {
            const d = await Phai.api.get(api + '/from-mrp', { date: today, firm_date: f });
            lines = d.lines;
        }
        edit = { source: fromMrp ? 'MRP' : cfg.sources[0], firm: f, lines, prev };
        render();
        form.querySelector('[name="firm_date"]')?.focus();
    }

    $('sc-edit').addEventListener('click', () => start(false));
    $('sc-mrp')?.addEventListener('click', (e) => {
        const btn = e.currentTarget;
        Phai.busy(btn, true);
        start(true).catch(() => {}).finally(() => Phai.busy(btn, false));
    });
    $('sc-release').addEventListener('change', (e) => load(e.target.value));

    // Cancel: back to the release shown
    $('sc-undo').addEventListener('click', () => {
        edit = null;
        Phai.form.errors(form, null);
        render();
    });

    box.addEventListener('click', (e) => {
        const card = e.target.closest('[data-line]');
        if (!card) return;
        if (e.target.closest('[data-add]')) {
            const l = data.lines.find((x) => String(x.line) === card.dataset.line);
            const tr = row(l, { date: '', qty: 0, done: 0 }, false);
            card.querySelector('tbody').append(tr);
            tr.querySelector('input')?.focus();
        } else if (e.target.closest('[data-remove]')) {
            e.target.closest('tr').remove();
            totals(card);
        }
    });

    form.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' || !e.target.matches('input')) return;
        e.preventDefault();
        const fields = [...form.querySelectorAll('input:not([type=hidden]), select')].filter((i) => !i.disabled && i.offsetParent);
        fields[fields.indexOf(e.target) + 1]?.focus();
    });
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!edit || !(await Phai.confirm(T.confirm))) return;
        const body = { lines: {} };
        form.querySelectorAll('[name]').forEach((i) => {
            if (!i.name.startsWith('lines.')) {
                body[i.name] = i.value;
                return;
            }
            const [, line, , r, f] = i.name.split('.');
            ((((body.lines[line] ??= { rows: {} }).rows)[r]) ??= {})[f] = i.value;
        });
        data.lines.forEach((l) => { body.lines[l.line] ??= { rows: {} }; }); // a line with every row removed
        Phai.form.errors(form, null);
        Phai.busy($('sc-save'), true);
        try {
            data = await Phai.api.put(api, body);
            edit = null;
            Phai.toast(Phai.t('saved'));
            render();
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
        } finally {
            Phai.busy($('sc-save'), false);
        }
    });

    load();
})();
