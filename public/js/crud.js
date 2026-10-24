/**
 * Script for every Resource screen (views/crud/index.php).
 * Reads its settings from <script id="crud-config">.
 */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('crud-config').textContent);
    const form = document.getElementById('crud-form');
    const fieldType = Object.fromEntries(cfg.fields.map((f) => [f.name, f.type]));
    const geo = Phai.geo(form);

    // ------------------------------------------------------------ formatting

    function number(v, scale) {
        if (v === null || v === undefined || v === '') return '';
        const d = Math.min(scale, 2);
        return Number(v).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: Math.max(d, scale > 2 ? 4 : d) });
    }

    function date(v) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v || '');
        return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
    }

    /** On/off flags (`_active`, `_status`) show ใช้งาน/ไม่ใช้งาน; any other yes/no column shows a tick. */
    function onOff(name) {
        return /_(active|status)$/.test(name);
    }

    function yes(v) {
        return Number(v) === 1 || v === true;
    }

    /** The words a yes/no column is written with in Excel, read back by the import. */
    function words(name) {
        return onOff(name) ? [Phai.t('active'), Phai.t('inactive')] : [Phai.t('yes'), Phai.t('no')];
    }

    function cell(col, row) {
        const v = row[col.key];
        switch (col.type) {
            case 'bool': return onOff(col.key) ? Phai.badge(v) : (yes(v) ? '<i class="ti ti-check text-green"></i>' : '');
            case 'number': return Phai.esc(number(v, col.scale));
            case 'int': return Phai.esc(v ?? '');
            case 'date': return Phai.esc(date(v));
            default: return Phai.esc(col.options[v] ?? v ?? '');
        }
    }

    /** Row from the API -> form values (numbers without trailing zeros). */
    function toForm(row) {
        const out = { ...row };
        for (const [name, type] of Object.entries(fieldType)) {
            if (type === 'number' && out[name] !== null && out[name] !== undefined) out[name] = String(Number(out[name]));
        }
        return out;
    }

    /** Make sure a select can show a saved value that is no longer in its list. */
    function keepValue(row) {
        for (const sel of form.querySelectorAll('select[name]:not([data-geo])')) {
            const v = row ? row[sel.name] : null;
            if (v !== null && v !== undefined && v !== '' && ![...sel.options].some((o) => o.value === String(v))) {
                sel.add(new Option(String(v), String(v)));
            }
        }
    }

    function keyQuery(row) {
        return '?' + new URLSearchParams(cfg.key.map((k) => [k, row[k]])).toString();
    }

    // ------------------------------------------------------------ one-row screens

    if (cfg.single) {
        (async () => {
            const row = await Phai.api.get(cfg.api + '/row');
            keepValue(row);
            Phai.form.fill(form, toForm(row));
            await geo.load(row);
        })();
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (await Phai.form.submit(form, 'PUT', cfg.api + '/row')) Phai.toast(Phai.t('saved'));
        });
        return;
    }

    // ------------------------------------------------------------ list + modal

    const modalEl = document.getElementById('crud-modal');
    const modal = (window.bootstrap || window.tabler).Modal.getOrCreateInstance(modalEl);
    const fieldset = modalEl.querySelector('fieldset');
    const saveBtn = document.getElementById('save');
    const delBtn = document.getElementById('del');
    const editBtn = document.createElement('button');
    editBtn.type = 'button'; editBtn.className = 'btn btn-sm btn-primary';
    editBtn.innerHTML = `<i class="ti ti-pencil me-1"></i>${Phai.esc(Phai.t('edit'))}`; saveBtn.before(editBtn);
    // while editing a saved row: Cancel drops the changes and shows the row as saved again
    const undoBtn = document.createElement('button');
    undoBtn.type = 'button'; undoBtn.className = 'btn btn-sm'; undoBtn.dataset.cancel = '';
    undoBtn.innerHTML = `<i class="ti ti-x me-1"></i>${Phai.esc(Phai.t('cancel'))}`; saveBtn.before(undoBtn);
    undoBtn.hidden = true;
    undoBtn.addEventListener('click', () => {
        Phai.form.errors(form, null);
        if (editing) open(editing); else modal.hide(); // a new row: nothing to go back to, close
    });
    /** แก้ไข: the dialog's plain values become inputs. */
    function startEdit() {
        if (!editing || !cfg.can.edit) return;
        Phai.viewFields(fieldset, false);
        document.getElementById('crud-badge').hidden = true;
        fieldset.disabled = false; saveBtn.hidden = false; editBtn.hidden = true; undoBtn.hidden = false;
        if (delBtn) delBtn.hidden = !cfg.can.delete;
    }
    editBtn.addEventListener('click', startEdit);
    let editing = null; // row being viewed, null when adding
    const filters = document.querySelectorAll('[data-filter]');

    const grid = Phai.grid({
        table: '#crud-table',
        url: cfg.api,
        search: '#q',
        query: () => Object.fromEntries([...filters].map((s) => [s.dataset.filter, s.value])),
        columns: cfg.columns.map((c) => ({
            key: c.key,
            hidden: c.hidden,
            label: c.label,
            cls: c.type === 'number' || c.type === 'int' ? 'num' : '',
            render: (row) => cell(c, row),
        })),
        onClick: (row) => { if (row._link) location.href = Phai.url(row._link); else open(row); },
        exportSheets: (rows, head) => workbook(rows, head),
        files: cfg.files ? { doc: cfg.files, key: cfg.key[0] } : null,
        tools: cfg.can.add || cfg.can.edit ? [{ icon: 'file-download', title: Phai.t('import_excel'), onClick: openImport }] : [],
        actions: {
            view: (row) => open(row), // the dialog in view mode
            print: cfg.print ? (row) => cfg.print.replace('{key}', encodeURIComponent(row[cfg.key[0]])) : null,
            edit: cfg.can.edit ? (row) => open(row, true) : null, // the dialog ready to edit
            delete: cfg.can.delete ? (row) => remove(row) : null,
        },
    });
    filters.forEach((s) => s.addEventListener('change', () => { grid.state.page = 1; grid.reload(); }));
    grid.reload();

    // ?edit=<code> (e.g. from the stock item page) opens that record (to view; แก้ไข in the dialog edits it)
    const editKey = new URLSearchParams(location.search).get('edit');
    if (editKey && cfg.key.length === 1 && cfg.can.edit) {
        Phai.api.get(cfg.api + '/row' + keyQuery({ [cfg.key[0]]: editKey })).then((row) => open(row));
    }

    /** The dialog for a saved row (to view: แก้ไข turns it into inputs; edit = straight to the inputs) or a new one (no row). */
    async function open(row, edit = false) {
        Phai.viewFields(fieldset, false);
        editing = row || null;
        const canSave = row ? cfg.can.edit : cfg.can.add;
        keepValue(row);
        Phai.form.fill(form, row ? toForm(row) : null);
        form.querySelectorAll('[data-key]').forEach((el) => { el.readOnly = !!row; });
        // viewing hides the inputs (viewFields) but keeps the tabs clickable, so the fieldset stays enabled
        fieldset.disabled = !row && !canSave;
        saveBtn.hidden = !!row || !canSave;
        editBtn.hidden = !row || !cfg.can.edit;
        undoBtn.hidden = !!row || !canSave; // adding: ยกเลิก beside บันทึก closes the dialog
        document.getElementById('crud-back').hidden = !row; // adding has ยกเลิก instead
        if (delBtn) delBtn.hidden = true;
        const firstTab = modalEl.querySelector('[data-bs-toggle="tab"]');
        if (firstTab) (window.bootstrap || window.tabler).Tab.getOrCreateInstance(firstTab).show();
        // the mode is set before the dialog shows, so its fields never flash from inputs to text or back
        Phai.viewFields(fieldset, !!row);
        const files = fieldset.querySelector('[data-files]');
        if (files) files.dataset.nbr = row ? String(row[cfg.key[0]] ?? '') : ''; // a new record has none yet
        modal.show();
        await geo.load(row);
        if (row) Phai.viewFields(fieldset, true); // again, now with the address names loaded
        showBadge(row);
        if (row && edit) startEdit();
    }

    /** View: the status column (Resource::$badge) is a badge beside the title instead of a field, like a document view. */
    function showBadge(row) {
        const badge = document.getElementById('crud-badge');
        const [col, colors] = Object.entries(cfg.badge || {})[0] || [];
        badge.hidden = true;
        if (!row || !col || !form.elements[col]) return;
        const el = form.elements[col];
        badge.className = `badge bg-${colors[row[col]] || 'secondary'}-lt`;
        badge.textContent = el.selectedOptions?.[0]?.textContent || row[col] || '';
        badge.hidden = !badge.textContent;
        const box = el.closest('[class*="col-"]');
        if (box) { box.hidden = true; box.dataset.viewHidden = '1'; } // viewFields(false) on Edit shows it again
    }

    document.getElementById('add')?.addEventListener('click', () => open(null));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const saved = editing
            ? await Phai.form.submit(form, 'PUT', cfg.api + '/row' + keyQuery(editing))
            : await Phai.form.submit(form, 'POST', cfg.api);
        if (!saved) {
            showErrorTab();
            return;
        }
        modal.hide();
        Phai.toast(Phai.t('saved'));
        grid.reload();
    });

    /** With tabs, jump to the tab holding the first field error. */
    function showErrorTab() {
        const bad = form.querySelector('.is-invalid');
        const pane = bad?.closest('.tab-pane');
        if (!pane) return;
        const tab = modalEl.querySelector(`[data-bs-target="#${pane.id}"]`);
        (window.bootstrap || window.tabler).Tab.getOrCreateInstance(tab).show();
        bad.focus();
    }

    /** Delete a row (from the dialog or the row's trash icon), after asking. */
    async function remove(row) {
        const name = cfg.key.map((k) => row[k]).join(' / ');
        if (!(await Phai.confirm(Phai.t('confirm_delete', { name })))) return;
        try {
            await Phai.api.del(cfg.api + '/row' + keyQuery(row));
        } catch (err) {
            return;
        }
        modal.hide();
        Phai.toast(Phai.t('deleted'));
        grid.reload();
    }
    delBtn?.addEventListener('click', () => remove(editing));

    // ------------------------------------------------------------ Excel import

    const importEl = document.getElementById('import-modal');
    const importForm = document.getElementById('import-form');
    const importErrors = importEl?.querySelector('.phai-import-errors');
    const sheetFields = cfg.fields.filter((f) => !f.readonly || f.key);
    const title = document.title.split(' · ')[0];

    const drop = importEl?.querySelector('[data-drop]');
    const dropName = drop?.querySelector('[data-drop-name]');
    const fileInput = importForm?.elements.file;

    function showFile() {
        const file = fileInput.files[0];
        dropName.textContent = file ? file.name : dropName.dataset.empty;
        drop.classList.toggle('has-file', !!file);
    }
    fileInput?.addEventListener('change', () => { showImportErrors(null); showFile(); });
    drop?.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('is-over'); });
    drop?.addEventListener('dragleave', () => drop.classList.remove('is-over'));
    drop?.addEventListener('drop', (e) => {
        e.preventDefault();
        drop.classList.remove('is-over');
        if (!e.dataTransfer.files.length) return;
        fileInput.files = e.dataTransfer.files;
        showImportErrors(null);
        showFile();
    });

    function openImport() {
        importForm.reset();
        showFile();
        showImportErrors(null);
        (window.bootstrap || window.tabler).Modal.getOrCreateInstance(importEl).show();
    }

    function showImportErrors(list) {
        importErrors.hidden = !list;
        importErrors.innerHTML = (list || []).map((m) => `<li>${Phai.esc(m)}</li>`).join('');
    }

    /** Empty sheet with the columns of the form (required ones red) and a sheet of allowed values. */
    /** A value as the import reads it back: codes for dropdowns, active words, real dates and numbers. */
    function sheetCell(f, row) {
        const v = row[f.name];
        if (f.type === 'bool') return words(f.name)[yes(v) ? 0 : 1];
        if (v === null || v === undefined || v === '') return null;
        if (f.type === 'date') return { v: String(v).slice(0, 10), t: 'date' };
        if (f.type === 'int') return { v: Number(v), t: 'num', scale: 0 };
        if (f.type === 'number') return { v: Number(v), t: 'num', scale: f.scale === 2 ? 2 : 3 };
        return String(v);
    }

    /** Export and template share one layout: every form field in form order, then the allowed values. */
    function workbook(rows, head = {}) {
        const choices = [];
        for (const f of sheetFields) {
            if (f.type === 'bool') choices.push(...words(f.name).map((w) => [f.label, w, '']));
            for (const o of f.options || []) {
                const [code, name] = String(o.label).includes(' · ') ? String(o.label).split(' · ') : [o.value, o.label];
                choices.push([f.label, String(code), name === code ? '' : name]);
            }
        }
        const sheets = [{
            ...head, name: title,
            columns: sheetFields.map((f) => ({ label: f.label, red: f.required })),
            rows: rows.map((row) => sheetFields.map((f) => sheetCell(f, row))),
        }];
        if (choices.length) {
            sheets.push({ name: Phai.t('choices'), columns: [{ label: Phai.t('field') }, { label: Phai.t('value') }, { label: Phai.t('meaning') }], rows: choices });
        }
        return sheets;
    }

    document.getElementById('import-template')?.addEventListener('click', async () => {
        Phai.xlsx.save(await Phai.xlsx.write(workbook([])), `${title} - ${Phai.t('import_template')}.xlsx`);
    });

    /** Sheet rows -> {_row, field: value}; the header is the row (of the first 15) naming most fields. */
    function sheetRows(cells) {
        const norm = (v) => String(v ?? '').trim().replace(/\*$/, '').trim().toLowerCase();
        const byName = new Map();
        for (const f of sheetFields) {
            byName.set(norm(f.label), f.name);
            byName.set(norm(f.name), f.name);
        }
        let head = -1;
        let best = 0;
        cells.slice(0, 15).forEach((row, i) => {
            const hits = row.filter((v) => byName.has(norm(v))).length;
            if (hits > best) { best = hits; head = i; }
        });
        if (head < 0) throw new Error(Phai.t('import_no_header'));
        const cols = cells[head].map((v) => byName.get(norm(v)) || null);
        const out = [];
        for (let i = head + 1; i < cells.length; i++) {
            const row = cells[i];
            if (!row.some((v) => v !== null && v !== '')) continue;
            const item = { _row: i + 1 };
            cols.forEach((name, c) => { if (name) item[name] = row[c] ?? null; });
            out.push(item);
        }
        if (!out.length) throw new Error(Phai.t('import_empty'));
        return out;
    }

    importForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const file = fileInput.files[0];
        if (!file) {
            fileInput.click();
            return;
        }
        const btn = importForm.querySelector('[type="submit"]');
        showImportErrors(null);
        Phai.busy(btn, true);
        try {
            let rows;
            try {
                rows = sheetRows(await Phai.xlsx.read(file));
            } catch (err) {
                showImportErrors([err.message && !/zip|sheet/.test(err.message) ? err.message : Phai.t('import_bad_file')]);
                return;
            }
            const done = await Phai.api.post(cfg.api + '/import', { rows });
            (window.bootstrap || window.tabler).Modal.getOrCreateInstance(importEl).hide();
            Phai.toast(Phai.t('imported', done));
            grid.reload();
        } catch (err) {
            if (err.fields) showImportErrors([err.message, ...Object.entries(err.fields).map(([k, m]) => `${k}: ${m}`)]);
        } finally {
            Phai.busy(btn, false);
        }
    });
})();
