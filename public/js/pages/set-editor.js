/**
 * สูตรการผลิต (views/production/sets.php): the materials (BOM) and steps (routing) of one item, in two tabs.
 * Pick the item on the left or type its code; save replaces the lines of each tab the user may change.
 * One unit's cost: materials = qty per × (1 + scrap %) × unit cost; steps = hours a piece × work center rate.
 */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('set-config').textContent);
    const form = document.getElementById('set');
    const parentInput = document.getElementById('parent');
    const list = document.getElementById('parents');
    const saveBtn = document.getElementById('set-save');
    const delBtn = document.getElementById('set-delete');
    const plain = (v) => (v === null || v === undefined || v === '' ? '' : String(Number(v)));
    const num = (v) => Number(String(v ?? '').replace(/,/g, '')) || 0;
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const kinds = {
        bom: { ...cfg.bom, api: '/api/boms' },
        routing: { ...cfg.routing, api: '/api/routings' },
    };
    for (const k of Object.keys(kinds)) {
        const pane = form.querySelector(`[data-pane="${k}"]`);
        if (!pane) { delete kinds[k]; continue; }
        kinds[k].pane = pane;
        kinds[k].tbody = pane.querySelector('tbody');
        kinds[k].canEdit = kinds[k].add || kinds[k].edit;
        kinds[k].had = 0;
    }
    let parent = null;
    let seq = 0;

    // ------------------------------------------------------------ tabs

    function tab(k) {
        if (!kinds[k]) return;
        form.querySelectorAll('[data-tab]').forEach((a) => a.classList.toggle('active', a.dataset.tab === k));
        for (const [n, kind] of Object.entries(kinds)) kind.pane.hidden = n !== k;
        const url = new URL(location.href);
        url.searchParams.set('tab', k);
        history.replaceState(null, '', url);
    }
    form.querySelector('.nav-tabs')?.addEventListener('click', (e) => {
        const a = e.target.closest('[data-tab]');
        if (!a) return;
        e.preventDefault();
        tab(a.dataset.tab);
    });

    // ------------------------------------------------------------ items on the left

    async function loadList() {
        const rows = await Phai.api.get('/api/production-sets', { q: document.getElementById('q').value.trim() });
        list.innerHTML = rows.map((r) => `
            <a href="#" class="list-group-item list-group-item-action py-2${r.parent === parent ? ' active' : ''}" data-parent="${Phai.esc(r.parent)}">
                <span class="fw-medium">${Phai.esc(r.parent)}</span>
                <span class="d-block small text-secondary text-truncate">${Phai.esc(r.descr ?? '')}</span>
            </a>`).join('');
    }
    list.addEventListener('click', (e) => {
        const a = e.target.closest('[data-parent]');
        if (!a) return;
        e.preventDefault();
        open(a.dataset.parent);
    });
    let qTimer;
    document.getElementById('q').addEventListener('input', () => {
        clearTimeout(qTimer);
        qTimer = setTimeout(loadList, 250);
    });
    parentInput.addEventListener('change', () => open(parentInput.value.trim().toUpperCase()));

    // ------------------------------------------------------------ lines

    function input(c, name, v) {
        switch (c.type) {
            case 'part':
                return `<input class="form-control form-control-sm text-uppercase" name="${name}" value="${Phai.esc(v ?? '')}" autocomplete="off" data-part data-pick="parts">`;
            case 'number':
                return `<input class="form-control form-control-sm text-end" name="${name}" value="${Phai.esc(plain(v))}" inputmode="decimal">`;
            case 'date':
                return `<input type="date" class="form-control form-control-sm" name="${name}" value="${Phai.esc(v ?? '')}">`;
            case 'select':
                return `<select class="form-select form-select-sm" name="${name}"><option value=""></option>${(c.options || [])
                    .map((o) => `<option value="${Phai.esc(o.value)}"${String(o.value) === String(v ?? '') ? ' selected' : ''}>${Phai.esc(o.label)}</option>`).join('')}</select>`;
            case 'static':
                return `<span class="small text-secondary" data-cell="${c.name}">${Phai.esc(v ?? '')}</span>`;
            case 'money':
                return `<span class="d-block text-end" data-cell="${c.name}"></span>`;
            default:
                return `<input class="form-control form-control-sm" name="${name}" value="${Phai.esc(v ?? '')}" autocomplete="off">`;
        }
    }

    function addRow(k, row, focus) {
        const kind = kinds[k];
        const i = seq++;
        const tr = document.createElement('tr');
        tr.dataset.i = i;
        tr.dataset.unit = row?.pt_cost ?? 0;
        const more = kind.more || [];
        const hasMore = more.some((c) => row && row[c.name]);
        tr.innerHTML = '<td class="text-secondary small" data-n></td>'
            + kind.columns.map((c) => `<td>${input(c, `lines.${i}.${c.name}`, row ? row[c.name] : '')}</td>`).join('')
            + '<td class="text-nowrap">'
            + (more.length ? `<button type="button" class="btn btn-sm btn-icon btn-ghost-${hasMore ? 'primary' : 'secondary'}" data-more title="${Phai.esc(cfg.text.more)}" tabindex="-1"><i class="ti ti-dots"></i></button>` : '')
            + (kind.canEdit ? '<button type="button" class="btn btn-sm btn-icon btn-ghost-secondary" data-remove tabindex="-1"><i class="ti ti-x"></i></button>' : '')
            + '</td>';
        kind.tbody.appendChild(tr);
        if (more.length) {
            // dates and remarks, seldom used: a row of their own, opened from the dots
            const sub = document.createElement('tr');
            sub.dataset.moreOf = i;
            sub.hidden = !hasMore;
            sub.innerHTML = `<td></td><td colspan="${kind.columns.length + 1}"><div class="row g-2">${more.map((c) => `
                <div class="col-sm-${c.type === 'text' ? 6 : 3}"><label class="form-label small mb-0">${Phai.esc(c.label)}</label>${input(c, `lines.${i}.${c.name}`, row ? row[c.name] : '')}</div>`).join('')}</div></td>`;
            kind.tbody.appendChild(sub);
        }
        if (!kind.canEdit) kind.tbody.querySelectorAll(`[name^="lines.${i}."]`).forEach((x) => { x.disabled = true; });
        renumber(k);
        if (focus) tr.querySelector('input, select')?.focus();
    }

    function rows(k) {
        return [...kinds[k].tbody.querySelectorAll('tr[data-i]')];
    }

    function renumber(k) {
        rows(k).forEach((tr, n) => { tr.querySelector('[data-n]').textContent = n + 1; });
    }

    const field = (tr, name) => tr.closest('tbody').querySelector(`[name="lines.${tr.dataset.i}.${name}"]`);

    /** Cost of one unit, per line and per tab. */
    function costs() {
        const sum = { bom: 0, routing: 0 };
        if (kinds.bom) {
            for (const tr of rows('bom')) {
                const c = num(field(tr, 'ps_qty_per')?.value) * (1 + num(field(tr, 'ps_scrap')?.value) / 100) * Number(tr.dataset.unit || 0);
                tr.querySelector('[data-cell="cost"]').textContent = field(tr, 'ps_comp').value ? money(c) : '';
                sum.bom += c;
            }
        }
        if (kinds.routing) {
            for (const tr of rows('routing')) {
                const wc = field(tr, 'ro_wkctr')?.value;
                const c = num(field(tr, 'ro_run_time')?.value) * Number(cfg.rates[wc] || 0);
                tr.querySelector('[data-cell="cost"]').textContent = wc ? money(c) : '';
                sum.routing += c;
            }
        }
        const parts = [];
        if (kinds.bom) parts.push(`${Phai.esc(cfg.text.materials)} ${money(sum.bom)}`);
        if (kinds.routing) parts.push(`${Phai.esc(cfg.text.steps)} ${money(sum.routing)}`);
        document.getElementById('set-cost').innerHTML = parts.join(' · ')
            + ` · <span class="text-body fw-medium">${Phai.esc(cfg.text.total)} ${money(sum.bom + sum.routing)}</span>`;
        for (const k of Object.keys(kinds)) {
            const n = rows(k).filter((tr) => (field(tr, k === 'bom' ? 'ps_comp' : 'ro_wkctr')?.value || '') !== '').length;
            const span = form.querySelector(`[data-count="${k}"]`);
            if (span) span.textContent = n ? `(${n})` : '';
        }
    }

    for (const [k, kind] of Object.entries(kinds)) {
        kind.tbody.addEventListener('change', async (e) => {
            if (e.target.matches('[data-part]')) {
                e.target.value = e.target.value.trim().toUpperCase();
                const tr = e.target.closest('tr');
                let p = null;
                try { p = e.target.value ? await Phai.api.get('/api/production-sets/part', { part: e.target.value }) : null; } catch (_) { p = null; }
                const um = tr.querySelector('[data-cell="pt_um"]');
                if (um) um.textContent = p ? p.pt_um : '';
                tr.dataset.unit = p ? p.pt_cost : 0;
            }
            costs();
        });
        kind.tbody.addEventListener('input', costs);
        kind.tbody.addEventListener('click', (e) => {
            const tr = e.target.closest('tr');
            if (e.target.closest('[data-more]')) {
                const sub = kind.tbody.querySelector(`[data-more-of="${tr.dataset.i}"]`);
                if (sub) sub.hidden = !sub.hidden;
                return;
            }
            if (!e.target.closest('[data-remove]')) return;
            kind.tbody.querySelector(`[data-more-of="${tr.dataset.i}"]`)?.remove();
            tr.remove();
            if (!rows(k).length) addRow(k, null, true);
            renumber(k);
            costs();
        });
        // Enter: next field; at the end of the last line, a new line
        kind.tbody.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const tr = e.target.closest('tr[data-i]');
            if (!tr) return;
            const fields = [...tr.querySelectorAll('input, select')];
            const at = fields.indexOf(e.target);
            if (at < fields.length - 1) { fields[at + 1].focus(); return; }
            if (!kind.canEdit) return;
            const next = rows(k)[rows(k).indexOf(tr) + 1];
            if (next) next.querySelector('input, select')?.focus();
            else addRow(k, null, true);
        });
        kind.pane.querySelector(`[data-add="${k}"]`)?.addEventListener('click', () => addRow(k, null, true));
    }

    // ------------------------------------------------------------ open / save / delete

    async function open(code) {
        if (!code) return;
        const data = {};
        try {
            const keys = Object.keys(kinds);
            const got = await Promise.all(keys.map((k) => Phai.api.get(kinds[k].api + '/' + encodeURIComponent(code))));
            keys.forEach((k, n) => { data[k] = got[n]; });
        } catch (err) {
            return;
        }
        parent = code;
        parentInput.value = code;
        const any = Object.values(data)[0];
        Phai.pick.label(parentInput, `${any.parent.pt_desc1 ?? ''} (${any.parent.pt_um ?? ''})`);
        for (const [k, kind] of Object.entries(kinds)) {
            kind.tbody.innerHTML = '';
            kind.had = data[k].lines.length;
            data[k].lines.forEach((l) => addRow(k, l, false));
            if (!data[k].lines.length) addRow(k, null, false);
        }
        document.getElementById('set-empty').hidden = true;
        document.getElementById('set-body').hidden = false;
        if (saveBtn) saveBtn.hidden = false;
        if (delBtn) delBtn.hidden = !Object.values(kinds).some((kind) => kind.had);
        Phai.form.errors(form, null);
        list.querySelectorAll('[data-parent]').forEach((a) => a.classList.toggle('active', a.dataset.parent === code));
        costs();
    }

    /** The lines of a tab as the API takes them; an empty line is left out. */
    function body(k) {
        const kind = kinds[k];
        const out = {};
        for (const tr of rows(k)) {
            const line = {};
            for (const c of [...kind.columns, ...(kind.more || [])]) {
                const el = field(tr, c.name);
                if (el) line[c.name] = el.value;
            }
            if (Object.values(line).some((v) => String(v).trim() !== '')) out[tr.dataset.i] = line;
        }
        return out;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!parent) return;
        Phai.form.errors(form, null);
        Phai.busy(saveBtn, true);
        let failed = null;
        for (const [k, kind] of Object.entries(kinds)) {
            if (!kind.canEdit) continue;
            const lines = body(k);
            try {
                if (Object.keys(lines).length) await Phai.api.put(kind.api + '/' + encodeURIComponent(parent), { lines });
                else if (kind.had && kind.delete) await Phai.api.del(kind.api + '/' + encodeURIComponent(parent));
            } catch (err) {
                if (err.fields) Phai.form.errors(form, err.fields);
                failed ??= k;
            }
        }
        Phai.busy(saveBtn, false);
        if (failed) {
            tab(failed);
            return;
        }
        Phai.toast(Phai.t('saved'));
        await loadList();
        await open(parent);
    });
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target !== parentInput && !e.target.closest('tbody')) e.preventDefault();
    });

    delBtn?.addEventListener('click', async () => {
        if (!(await Phai.confirm(cfg.text.confirm.replace(':name', parent)))) return;
        for (const kind of Object.values(kinds)) {
            if (kind.delete && kind.had) await Phai.api.del(kind.api + '/' + encodeURIComponent(parent));
        }
        Phai.toast(Phai.t('deleted'));
        await loadList();
        await open(parent);
    });

    tab(cfg.tab);
    loadList();
})();
