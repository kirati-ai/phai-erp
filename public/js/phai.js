/**
 * PhaiERP front-end helpers. Plain JavaScript, no build step.
 *
 *   Phai.api.get('/api/uoms', {q: 'box'})      -> data, or throws Phai.ApiError
 *   Phai.api.post('/api/uoms', {...})
 *   Phai.toast('บันทึกแล้ว')
 *   Phai.confirm('ลบ ?')                       -> Promise<boolean>
 *   Phai.form.read(form) / fill(form, row) / errors(form, fields)
 *   Phai.grid({table, url, columns, search, onClick})
 */
(function () {
    'use strict';

    const bs = window.bootstrap || window.tabler;
    const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
    const base = meta('phai-base').replace(/\/$/, '');
    const L = window.PHAI_LANG || {};

    function t(key, params = {}) {
        let s = L[key] ?? key;
        for (const [k, v] of Object.entries(params)) s = s.replace(':' + k, v);
        return s;
    }

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    // ---------------------------------------------------------------- API

    class ApiError extends Error {
        constructor(message, status, fields) {
            super(message);
            this.status = status;
            this.fields = fields || null;
        }
    }

    // ---------------------------------------------------------------- Loading: one place for every page
    // A thin bar at the top while any request is out (after 0.2 s, so quick answers do not flash); the button
    // just pressed shows a spinner while its save / send runs; tables still waiting for their first rows say so.
    let pending = 0;
    let barTimer = 0;
    let lastBtn = null;
    let lastAt = 0;
    const loadbar = () => document.getElementById('phai-loadbar') || document.body.appendChild(Object.assign(document.createElement('div'), { id: 'phai-loadbar' }));
    // the loader: a spinner over the page content while the page loads its data and while something is saved;
    // later look-ups (search as you type, pick lists) only show the bar
    let firstLoad = true;
    let saving = 0;
    const loader = () => document.getElementById('phai-loader') || document.querySelector('.phai-body')?.appendChild(Object.assign(document.createElement('div'), {
        id: 'phai-loader', className: 'phai-loader', innerHTML: `<div class="phai-loader-box"><span class="spinner-border text-primary"></span><span>${esc(t('loading'))}</span></div>`,
    }));
    function loading(delta, write = false) {
        pending = Math.max(0, pending + delta);
        if (write) saving = Math.max(0, saving + delta);
        clearTimeout(barTimer);
        if (pending > 0) {
            barTimer = setTimeout(() => {
                if (pending === 0) return;
                loadbar().classList.add('on');
                if (firstLoad || saving > 0) loader()?.classList.add('on');
            }, 200);
        } else {
            document.getElementById('phai-loadbar')?.classList.remove('on');
            document.getElementById('phai-loader')?.classList.remove('on');
            document.querySelectorAll('[data-loading-row]').forEach((r) => r.remove()); // nothing came for these tables
            if (document.readyState !== 'loading') firstLoad = false;
        }
        if (saving === 0 && !firstLoad) document.getElementById('phai-loader')?.classList.remove('on');
    }
    document.addEventListener('click', (e) => {
        lastBtn = e.target.closest('button');
        lastAt = Date.now();
    }, true);
    document.addEventListener('submit', (e) => {
        lastBtn = e.submitter || e.target.querySelector('[type=submit]');
        lastAt = Date.now();
    }, true);
    function placeholders() {
        const text = t('loading');
        document.querySelectorAll('.phai-body table > tbody:empty').forEach((tb) => {
            const cols = tb.closest('table').tHead?.rows[0]?.cells.length || 1;
            tb.innerHTML = `<tr data-loading-row><td colspan="${cols}" class="text-center text-secondary py-3"><span class="spinner-border spinner-border-sm me-2"></span>${esc(text)}</td></tr>`;
        });
        setTimeout(() => { if (pending === 0) { firstLoad = false; loading(0); } }, 400); // a page that asks for nothing clears them
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', placeholders); else setTimeout(placeholders);

    /** Calls the JSON API. Errors without field details are shown as a toast. */
    async function api(method, path, data) {
        // a save / send started by a button: that button waits with a spinner (pages that call busy() themselves keep it)
        let pressed = null;
        if (method !== 'GET' && lastBtn && lastBtn.isConnected && Date.now() - lastAt < 1500 && !lastBtn.disabled && !lastBtn.dataset.busy) {
            pressed = lastBtn;
            busy(pressed, true);
        }
        lastBtn = null;
        const write = method !== 'GET';
        loading(1, write);
        try {
            return await call(method, path, data);
        } finally {
            loading(-1, write);
            if (pressed && pressed.isConnected) busy(pressed, false);
        }
    }

    async function call(method, path, data) {
        let url = base + path;
        const opt = {
            method,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-Token': meta('csrf-token') },
        };
        if (data !== undefined) {
            if (method === 'GET') {
                const qs = new URLSearchParams(
                    Object.entries(data).filter(([, v]) => v !== '' && v !== null && v !== undefined)
                ).toString();
                if (qs) url += (url.includes('?') ? '&' : '?') + qs;
            } else {
                opt.headers['Content-Type'] = 'application/json';
                opt.body = JSON.stringify(data);
            }
        }

        let res;
        try {
            res = await fetch(url, opt);
        } catch (e) {
            toast(t('offline'), 'danger');
            throw new ApiError(t('offline'), 0);
        }

        let json = null;
        try {
            json = await res.json();
        } catch (e) {
            // not JSON
        }
        if (res.ok && json && json.ok) return json.data;

        // Session ended: back to the login page, then return here.
        if (res.status === 401) {
            const here = location.pathname.slice(base.length) || '/';
            location.href = base + '/login?next=' + encodeURIComponent(here);
        }

        const err = new ApiError((json && json.error) || t('error'), res.status, json && json.fields);
        if (!err.fields) toast(err.message, 'danger');
        throw err;
    }

    api.get = (path, query) => api('GET', path, query);
    api.post = (path, data) => api('POST', path, data ?? {});
    api.put = (path, data) => api('PUT', path, data ?? {});
    api.del = (path) => api('DELETE', path, {});

    // ---------------------------------------------------------------- Feedback

    function toast(message, type = 'success') {
        const box = document.getElementById('phai-toasts');
        if (!box || !bs) {
            window.alert(message);
            return;
        }
        const el = document.createElement('div');
        el.className = `toast phai-toast phai-toast-${type} align-items-center border-0`;
        el.setAttribute('role', 'status');
        const icon = { success: 'ti-circle-check', danger: 'ti-alert-circle', warning: 'ti-alert-triangle', info: 'ti-info-circle' }[type] || 'ti-bell';
        el.innerHTML = `<div class="d-flex align-items-center"><span class="phai-toast-icon"><i class="ti ${icon}"></i></span><div class="toast-body py-2"></div>`
            + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        el.querySelector('.toast-body').textContent = message;
        box.appendChild(el);
        el.addEventListener('hidden.bs.toast', () => el.remove());
        new bs.Toast(el, { delay: type === 'danger' || type === 'warning' ? 5000 : 2000 }).show();
    }

    /** Small yes/no dialog. Use only for actions that cannot be undone. */
    function confirmBox(message) {
        const el = document.getElementById('phai-confirm');
        if (!el || !bs) return Promise.resolve(window.confirm(message));

        return new Promise((resolve) => {
            el.querySelector('[data-message]').textContent = message;
            const okBtn = el.querySelector('[data-ok]');
            const modal = bs.Modal.getOrCreateInstance(el);
            let ok = false;
            const onOk = () => { ok = true; modal.hide(); };
            okBtn.addEventListener('click', onOk);
            el.addEventListener('shown.bs.modal', () => okBtn.focus(), { once: true });
            el.addEventListener('hidden.bs.modal', () => {
                okBtn.removeEventListener('click', onOk);
                resolve(ok);
            }, { once: true });
            modal.show();
        });
    }

    /** Disable a button and show a spinner while waiting. */
    function busy(btn, on) {
        if (!btn) return;
        if (on) {
            if (btn.dataset.busy) return; // already waiting (e.g. the page and the API both asked)
            btn.dataset.busy = '1';
            btn.dataset.html = btn.innerHTML;
            btn.style.minWidth = btn.offsetWidth + 'px';
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        } else {
            if (!btn.dataset.busy) return;
            delete btn.dataset.busy;
            btn.disabled = false;
            btn.innerHTML = btn.dataset.html ?? btn.innerHTML;
            btn.style.minWidth = '';
        }
    }

    // ---------------------------------------------------------------- Forms

    const form = {
        /** Form fields to a plain object. Checkboxes become true/false. */
        read(f) {
            const data = {};
            for (const el of f.elements) {
                if (!el.name || el.disabled) continue;
                if (el.type === 'checkbox') data[el.name] = el.checked;
                else if (el.type === 'radio') { if (el.checked) data[el.name] = el.value; }
                else data[el.name] = el.value;
            }
            return data;
        },

        /** Put a row into the form. Fields missing from row get their default. */
        fill(f, row) {
            form.errors(f, null);
            for (const el of f.elements) {
                if (!el.name) continue;
                const v = row ? row[el.name] : undefined;
                if (el.type === 'checkbox') {
                    el.checked = v === undefined || v === null ? el.defaultChecked : (v === true || Number(v) === 1);
                } else if (el.type === 'radio') {
                    el.checked = v === undefined ? el.defaultChecked : el.value === String(v);
                } else if (el.tagName === 'SELECT') {
                    // Selects have defaultSelected options, not an input's defaultValue.
                    el.value = v === undefined
                        ? ([...el.options].find((o) => o.defaultSelected)?.value ?? el.options[0]?.value ?? '')
                        : (v ?? '');
                } else {
                    el.value = v === undefined || v === null ? el.defaultValue : v;
                }
            }
        },

        /** Show {field: message} under each input; null clears. */
        errors(f, fields) {
            f.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            f.querySelectorAll('[data-phai-error]').forEach((el) => el.remove());
            if (!fields) return;

            let first = null;
            const unknown = [];
            for (const [name, message] of Object.entries(fields)) {
                const el = f.elements[name];
                if (!el || !el.classList) {
                    unknown.push(message);
                    continue;
                }
                el.classList.add('is-invalid');
                const fb = document.createElement('div');
                fb.className = 'invalid-feedback';
                fb.dataset.phaiError = '';
                fb.textContent = message;
                (el._pickBtn || el.closest('.input-group') || el).insertAdjacentElement('afterend', fb);
                first = first || el;
            }
            if (unknown.length) toast(unknown.join(' · '), 'danger');
            if (first) first.focus();
        },

        /**
         * Send the form to the API. Returns the saved data (true when the API
         * returns nothing), or false when it failed (errors are already shown).
         */
        async submit(f, method, path, btn) {
            btn = btn || f.querySelector('[type="submit"]');
            form.errors(f, null);
            busy(btn, true);
            try {
                return (await api(method, path, form.read(f))) ?? true;
            } catch (e) {
                if (e.fields) form.errors(f, e.fields);
                return false;
            } finally {
                busy(btn, false);
            }
        },
    };

    // ---------------------------------------------------------------- Grid

    /**
     * Table filled from a list API ({rows, total, page, per_page}).
     * Headers come from the HTML; <th data-sort="col"> makes a column sortable.
     *
     *   const g = Phai.grid({
     *       table: '#uoms', url: '/api/uoms', search: '#q',
     *       columns: [{key: 'um_um'}, {key: 'um_desc'}, {key: 'um_active', render: (r) => Phai.badge(r.um_active)}],
     *       onClick: (row) => edit(row),
     *       query: () => ({site: siteSelect.value}),   // extra filters sent with every request
     *   });
     *   g.reload();      // call again after a filter changes (g.state.page = 1 first)
     */
    function grid(opt) {
        const table = typeof opt.table === 'string' ? document.querySelector(opt.table) : opt.table;
        const search = typeof opt.search === 'string' ? document.querySelector(opt.search) : opt.search;
        const tbody = table.tBodies[0] || table.createTBody();
        const pageSizes = [10, 20, 50, 100, 300, 500];
        const state = { q: '', sort: opt.sort || '', dir: opt.dir || 'asc', page: 1, per_page: pageSizes.includes(opt.perPage) ? opt.perPage : 20 };
        let rows = [];
        let seq = 0;
        const initialHeaders = [...(table.tHead?.rows[0]?.cells || [])];
        // every row ends with icons: view, then edit / print / delete only when the screen gives them;
        // opt.files = {doc, key} puts a paperclip first: the row's attached files in a dialog (files.button)
        const acts = opt.actions || null;
        const supportedActs = [...(opt.files ? ['files'] : []), ...(acts ? ['view', 'print', 'edit', 'undo', 'delete'].filter((a) => !!acts[a]) : [])];
        const hasActions = supportedActs.length > 0;
        let actHead = null;
        if (hasActions) {
            actHead = document.createElement('th');
            actHead.className = 'phai-act';
            table.tHead?.rows[0]?.append(actHead);
        }

        const hasSeq = opt.seq !== false;
        let seqHead = null;
        if (hasSeq && table.tHead?.rows[0]) {
            seqHead = table.tHead.querySelector('th.phai-seq');
            if (!seqHead) {
                seqHead = document.createElement('th');
                seqHead.className = 'phai-seq text-center text-secondary';
                seqHead.textContent = t('seq');
                table.tHead.rows[0].prepend(seqHead);
            }
        }

        opt.columns.forEach((c, i) => {
            c.th = initialHeaders[i] || null;
            if (c.th) {
                c.th.dataset.colKey = c.key;
                if (!c.label) c.label = c.th.textContent.trim();
            }
            c.visible = c.hidden !== true;
        });

        const defaultOrderKeys = opt.columns.map((c) => c.key);
        const defaultVisibleMap = Object.fromEntries(opt.columns.map((c) => [c.key, c.hidden !== true]));
        const layoutTarget = { source: opt.url, table: table.id || 'grid' };
        let columnWidths = {};
        let saveQueue = Promise.resolve();
        let lastData = null;
        let isReordering = false;
        let backupColumnsOrder = null;
        let backupVisibleMap = null;
        let picker = null;
        let pickerSummary = null;
        let pickerMenu = null;
        let buildPicker = null;

        function attachResizer(th, col) {
            if (!th || th.querySelector('.phai-col-resizer')) return;
            const resizer = document.createElement('div');
            resizer.className = 'phai-col-resizer';
            th.append(resizer);
            resizer.addEventListener('mousedown', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const startX = e.pageX;
                const startWidth = th.offsetWidth;
                document.body.classList.add('phai-resizing');
                resizer.classList.add('is-active');

                function onMouseMove(ev) {
                    const w = Math.max(45, startWidth + (ev.pageX - startX));
                    th.style.width = w + 'px';
                    th.style.minWidth = w + 'px';
                    columnWidths[col.key] = w;
                    table.style.tableLayout = 'fixed';
                }
                function onMouseUp() {
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                    document.body.classList.remove('phai-resizing');
                    resizer.classList.remove('is-active');
                    saveColumns(false);
                }
                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });
        }
        let draggedColKey = null;

        function setupThDrag(th, col) {
            if (!th || th._hasDragEvents) return;
            th._hasDragEvents = true;

            th.addEventListener('dragstart', (e) => {
                if (!isReordering) {
                    e.preventDefault();
                    return;
                }
                if (e.target.closest('.phai-col-resizer')) {
                    e.preventDefault();
                    return;
                }
                draggedColKey = col.key;
                th.classList.add('phai-th-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', col.key);
            });

            th.addEventListener('dragend', () => {
                th.classList.remove('phai-th-dragging');
                table.querySelectorAll('th').forEach((cell) => {
                    cell.classList.remove('drag-target-left', 'drag-target-right');
                });
            });

            th.addEventListener('dragover', (e) => {
                if (!isReordering || !draggedColKey || draggedColKey === col.key) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                const rect = th.getBoundingClientRect();
                const isLeft = e.clientX < (rect.left + rect.width / 2);
                th.classList.toggle('drag-target-left', isLeft);
                th.classList.toggle('drag-target-right', !isLeft);
            });

            th.addEventListener('dragleave', () => {
                th.classList.remove('drag-target-left', 'drag-target-right');
            });

            th.addEventListener('drop', (e) => {
                if (!isReordering || !draggedColKey || draggedColKey === col.key) return;
                e.preventDefault();
                th.classList.remove('drag-target-left', 'drag-target-right');

                const rect = th.getBoundingClientRect();
                const isLeft = e.clientX < (rect.left + rect.width / 2);

                const fromIdx = opt.columns.findIndex((c) => c.key === draggedColKey);
                if (fromIdx < 0) return;
                const moved = opt.columns.splice(fromIdx, 1)[0];

                let toIdx = opt.columns.findIndex((c) => c.key === col.key);
                if (!isLeft) toIdx++;
                opt.columns.splice(toIdx, 0, moved);
                syncTable();
            });
        }

        opt.columns.forEach((c) => {
            if (c.th) {
                attachResizer(c.th, c);
                setupThDrag(c.th, c);
            }
        });

        function reorderDomHeaders() {
            const tr = table.tHead?.rows[0];
            if (!tr) return;
            opt.columns.forEach((c) => {
                if (c.th) tr.insertBefore(c.th, actHead || null);
            });
        }

        function syncTable() {
            reorderDomHeaders();
            if (rows && rows.length) {
                render(lastData || { total: rows.length, page: state.page, per_page: state.per_page });
            } else {
                applyColumns();
            }
            if (buildPicker) buildPicker();
        }

        const localKey = 'phai_grid_' + opt.url + '_' + (table.id || 'grid');
        let savedColData = null;

        function applyLayoutData(colData) {
            if (!colData || typeof colData !== 'object') return;
            savedColData = colData;

            if (colData.order && Array.isArray(colData.order)) {
                const map = new Map(opt.columns.map((c) => [c.key, c]));
                const ordered = [];
                for (const k of colData.order) {
                    if (map.has(k)) {
                        ordered.push(map.get(k));
                        map.delete(k);
                    }
                }
                for (const c of map.values()) ordered.push(c);
                opt.columns = ordered;
                reorderDomHeaders();
            }

            if (colData.widths && typeof colData.widths === 'object') {
                columnWidths = { ...colData.widths };
                let hasWidth = false;
                opt.columns.forEach((c) => {
                    if (columnWidths[c.key] && c.th) {
                        c.th.style.width = columnWidths[c.key] + 'px';
                        c.th.style.minWidth = columnWidths[c.key] + 'px';
                        hasWidth = true;
                    }
                });
                if (hasWidth) table.style.tableLayout = 'fixed';
            }

            const visMap = colData.visible && typeof colData.visible === 'object' ? colData.visible : (colData.order ? null : colData);
            if (visMap) {
                opt.columns.forEach((c) => {
                    if (typeof visMap[c.key] === 'boolean') {
                        c.visible = visMap[c.key];
                    } else if (c.hidden !== undefined) {
                        c.visible = c.hidden !== true;
                    }
                });
                if (!opt.columns.some((c) => c.visible)) {
                    opt.columns.forEach((c) => { c.visible = c.hidden !== true; });
                }
            }

            applyColumns();
            if (buildPicker) buildPicker();
        }

        try {
            const cached = localStorage.getItem(localKey);
            if (cached) applyLayoutData(JSON.parse(cached));
        } catch (e) {}

        function saveColumns(notify = true) {
            const visibleMap = Object.fromEntries(opt.columns.map((c) => [c.key, !!c.visible]));
            const orderList = opt.columns.map((c) => c.key);
            const colPayload = {
                visible: visibleMap,
                order: orderList,
                widths: columnWidths,
            };
            savedColData = colPayload;
            try { localStorage.setItem(localKey, JSON.stringify(colPayload)); } catch (e) {}

            const payload = {
                ...layoutTarget,
                columns: colPayload,
            };
            saveQueue = saveQueue.catch(() => {}).then(() => api.put('/api/grid-layout', payload)).then(() => {
                if (notify) toast(t('saved'));
            });
            saveQueue.catch(() => {});
        }

        function resetColumns() {
            savedColData = null;
            try { localStorage.removeItem(localKey); } catch (e) {}
            saveQueue = saveQueue.catch(() => {}).then(() => api.put('/api/grid-layout', { ...layoutTarget, columns: null })).then(() => {
                toast(t('reset_done'));
            });
            saveQueue.catch(() => {});
            columnWidths = {};
            table.style.tableLayout = '';
            opt.columns.forEach((c) => {
                if (c.th) {
                    c.th.style.width = '';
                    c.th.style.minWidth = '';
                }
            });
            const map = new Map(opt.columns.map((c) => [c.key, c]));
            opt.columns = defaultOrderKeys.map((k) => map.get(k)).filter(Boolean);
            opt.columns.forEach((c) => {
                c.visible = defaultVisibleMap[c.key] ?? (c.hidden !== true);
            });
            syncTable();
        }

        function applyColumns() {
            opt.columns.forEach((c) => {
                if (c.th) {
                    c.th.hidden = !c.visible;
                    c.th.classList.remove('phai-last-column');
                }
            });
            const visibleCols = opt.columns.filter((c) => c.visible);
            if (visibleCols.length) {
                visibleCols.at(-1)?.th?.classList.add('phai-last-column');
            }
            const dataOffset = hasSeq ? 1 : 0;
            [...tbody.rows].forEach((tr) => {
                if (tr.hasAttribute('data-row')) {
                    [...tr.cells].forEach((td, i) => {
                        if (td.classList.contains('phai-seq') || td.classList.contains('phai-act')) {
                            return;
                        }
                        const colIndex = i - dataOffset;
                        td.hidden = !opt.columns[colIndex]?.visible;
                    });
                } else if (tr.cells[0]) {
                    tr.cells[0].colSpan = visibleCols.length + (hasSeq ? 1 : 0) + (hasActions ? 1 : 0);
                }
            });
        }

        const layoutReady = opt.columnPicker === false ? Promise.resolve() : api.get('/api/grid-layout', layoutTarget).then((data) => {
            if (data.columns && typeof data.columns === 'object') {
                applyLayoutData(data.columns);
                try { localStorage.setItem(localKey, JSON.stringify(data.columns)); } catch (e) {}
            } else if (data.columns === null) {
                savedColData = null;
                try { localStorage.removeItem(localKey); } catch (e) {}
            }
        }).catch(() => {});

        let pickerWrap = null;
        let reorderToolbar = null;

        if (initialHeaders.length === opt.columns.length && opt.columnPicker !== false) {
            pickerWrap = document.createElement('div');
            pickerWrap.className = 'phai-header-picker-wrap';

            picker = document.createElement('details');
            picker.className = 'phai-columns phai-header-picker';
            document.addEventListener('click', (event) => {
                if (picker.open && !picker.contains(event.target)) {
                    picker.open = false;
                }
            });

            pickerSummary = document.createElement('summary');
            pickerSummary.className = 'btn btn-sm btn-icon phai-col-summary';
            pickerSummary.title = t('columns');
            pickerSummary.setAttribute('aria-label', t('columns'));
            pickerSummary.innerHTML = '<i class="ti ti-columns"></i>';

            pickerMenu = document.createElement('div');
            pickerMenu.className = 'phai-columns-menu';

            picker.append(pickerSummary, pickerMenu);

            reorderToolbar = document.createElement('div');
            reorderToolbar.className = 'phai-reorder-toolbar d-none';

            const saveBtn = document.createElement('button');
            saveBtn.type = 'button';
            saveBtn.className = 'btn btn-sm btn-icon btn-primary';
            saveBtn.title = t('save');
            saveBtn.setAttribute('aria-label', t('save'));
            saveBtn.innerHTML = '<i class="ti ti-device-floppy"></i>';
            saveBtn.addEventListener('click', () => {
                saveColumns(true);
                setReorderMode(false);
            });

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-sm btn-icon btn-outline-secondary';
            cancelBtn.title = t('cancel');
            cancelBtn.setAttribute('aria-label', t('cancel'));
            cancelBtn.innerHTML = '<i class="ti ti-x"></i>';
            cancelBtn.addEventListener('click', () => {
                if (backupColumnsOrder) opt.columns = [...backupColumnsOrder];
                if (backupVisibleMap) {
                    opt.columns.forEach((c) => {
                        if (backupVisibleMap[c.key] !== undefined) c.visible = backupVisibleMap[c.key];
                    });
                }
                syncTable();
                setReorderMode(false);
            });

            reorderToolbar.append(saveBtn, cancelBtn);

            // small icons left of the column chooser: Excel export, then the screen's own (opt.tools)
            const tools = [{ icon: 'file-upload', title: t('export_excel'), onClick: exportXlsx }, ...(opt.tools || [])];
            const toolBtns = tools.map((tool) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'phai-head-tool';
                b.setAttribute('aria-label', tool.title);
                b.dataset.bsToggle = 'tooltip';
                b.dataset.bsTitle = tool.title;
                b.innerHTML = `<i class="ti ti-${tool.icon}"></i>`;
                b.addEventListener('click', () => {
                    bs?.Tooltip.getInstance(b)?.hide();
                    tool.onClick(b);
                });
                if (bs?.Tooltip) new bs.Tooltip(b, { trigger: 'hover', placement: 'bottom' });
                return b;
            });
            pickerWrap.append(...toolBtns, picker, reorderToolbar);

            const card = table.closest('.card') || table.closest('.table-responsive');
            card.classList.add('phai-grid-card');
            card.style.setProperty('--phai-head-pad', `${(toolBtns.length + 1) * 24 + 16}px`);
            card.append(pickerWrap);

            function setReorderMode(active) {
                isReordering = active;
                if (active) {
                    backupColumnsOrder = [...opt.columns];
                    backupVisibleMap = Object.fromEntries(opt.columns.map((c) => [c.key, c.visible]));
                    picker.open = false;
                    picker.classList.add('d-none');
                    toolBtns.forEach((b) => b.classList.add('d-none'));
                    reorderToolbar.classList.remove('d-none');
                    card.classList.add('is-reordering');
                    table.classList.add('is-col-reordering');
                    opt.columns.forEach((c) => { if (c.th) c.th.draggable = true; });
                } else {
                    reorderToolbar.classList.add('d-none');
                    picker.classList.remove('d-none');
                    toolBtns.forEach((b) => b.classList.remove('d-none'));
                    card.classList.remove('is-reordering');
                    table.classList.remove('is-col-reordering');
                    opt.columns.forEach((c) => { if (c.th) c.th.draggable = false; });
                }
            }

            buildPicker = function () {
                pickerMenu.innerHTML = '';

                // Header single row with left and right buttons
                const topBar = document.createElement('div');
                topBar.className = 'd-flex align-items-center justify-content-between pb-2 mb-2 border-bottom';

                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'btn btn-sm btn-outline-primary py-0 px-2';
                editBtn.style.fontSize = '11px';
                editBtn.innerHTML = `<i class="ti ti-arrows-sort me-1"></i>${esc(t('edit_order'))}`;
                editBtn.addEventListener('click', () => {
                    setReorderMode(true);
                });

                const resetBtn = document.createElement('button');
                resetBtn.type = 'button';
                resetBtn.className = 'btn btn-sm btn-link text-secondary text-decoration-none py-0 px-1';
                resetBtn.style.fontSize = '11px';
                resetBtn.innerHTML = `<i class="ti ti-rotate-clockwise me-1"></i>${esc(t('reset_columns'))}`;
                resetBtn.addEventListener('click', () => {
                    resetColumns();
                });

                topBar.append(editBtn, resetBtn);
                pickerMenu.append(topBar);

                const list = document.createElement('div');
                list.className = 'phai-col-list';

                opt.columns.forEach((c) => {
                    const item = document.createElement('label');
                    item.className = 'phai-col-item m-0';

                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.className = 'form-check-input m-0';
                    input.checked = !!c.visible;

                    input.addEventListener('change', () => {
                        const checkedCount = opt.columns.filter((col) => col.visible).length;
                        if (!input.checked && checkedCount === 1) {
                            input.checked = true;
                            return;
                        }
                        c.visible = input.checked;
                        applyColumns();
                        saveColumns(false);
                    });

                    const label = document.createElement('span');
                    label.className = 'phai-col-name flex-fill text-truncate ms-2';
                    label.textContent = c.label || c.key;

                    item.append(input, label);
                    list.append(item);
                });

                pickerMenu.append(list);
            };

            buildPicker();
        }
        applyColumns();

        const pager = document.createElement('div');
        pager.className = 'phai-pager';
        (table.closest('.table-responsive') || table).insertAdjacentElement('afterend', pager);

        // the grid's card stays hidden until its first rows come, so an empty table with a spinner does not flash
        // on every page open; set only once reload() runs, so a grid that never loads is never left hidden
        const box = table.closest('.card') || table;
        let shown = false;

        async function reload() {
            if (!shown) box.classList.add('phai-wait');
            await layoutReady;
            const mine = ++seq;
            table.classList.add('is-loading');
            try {
                const data = await api.get(opt.url, { ...state, ...(opt.query ? opt.query() : {}), q: '', grid_search: state.q });
                if (mine !== seq) return; // a newer request is on its way
                addFields(data.fields || []);
                rows = data.rows;
                render(data);
            } catch (e) {
                // toast already shown
            } finally {
                if (mine === seq) {
                    table.classList.remove('is-loading');
                    box.classList.remove('phai-wait');
                    shown = true;
                }
            }
        }

        /** Every row of the current search and filters, as shown on screen, to a .xlsx file. */
        async function exportXlsx(btn) {
            await layoutReady;
            const cols = opt.columns.filter((c) => c.visible);
            const params = { ...state, ...(opt.query ? opt.query() : {}), q: '', grid_search: state.q, per_page: 500 };
            const all = [];
            busy(btn, true);
            try {
                for (let page = 1; ; page++) {
                    const data = await api.get(opt.url, { ...params, page });
                    all.push(...data.rows);
                    if (!data.rows.length || all.length >= data.total || all.length >= 50000) break;
                }
                const title = opt.exportTitle || document.title.split(' · ')[0];
                const now = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                const stamp = `${pad(now.getDate())}/${pad(now.getMonth() + 1)}/${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())}`;
                const note = [`${t('exported_at')} ${stamp}`, `${all.length} ${t('rows')}`, state.q ? `${t('search')}: ${state.q}` : '']
                    .filter(Boolean).join(' · ');
                // a screen may lay the sheet out itself (master data: the same columns its import reads)
                const blob = await window.Phai.xlsx.write(opt.exportSheets ? opt.exportSheets(all, { title, note }) : {
                    name: title, title, note,
                    columns: cols.map((c) => ({ label: c.label || c.key })),
                    rows: all.map((row) => cols.map((c) => exportCell(c, row))),
                });
                window.Phai.xlsx.save(blob, `${title} ${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}.xlsx`);
            } catch (e) {
                if (!(e instanceof ApiError)) toast(t('error'), 'danger');
            } finally {
                busy(btn, false);
            }
        }

        /** A cell as the screen shows it: dates and numbers stay typed so Excel can sum and sort them. */
        function exportCell(c, row) {
            const box = document.createElement('div');
            box.innerHTML = c.render ? c.render(row) : esc(row[c.key] ?? '');
            const text = box.textContent.trim();
            if (text === '') return null;
            const d = /^(\d{2})[/-](\d{2})[/-](\d{4})$/.exec(text);
            if (d) return { v: `${d[3]}-${d[2]}-${d[1]}`, t: 'date' };
            // a number on screen (1,890.00 / -5 / +30) whose value is a number, not a code like 0012
            const raw = String(row[c.key] ?? '').trim();
            const numeric = /(^|\s)(num|text-end)(\s|$)/.test(c.cls || '') || (raw !== '' && String(Number(raw)) === raw);
            if (numeric && /^[-+]?[\d,]*\.?\d+$/.test(text)) {
                return { v: Number(text.replace(/[,+]/g, '')), t: 'num', scale: (text.split('.')[1] || '').length };
            }
            return text;
        }

        function addFields(fields) {
            let added = false;
            const visMap = savedColData?.visible && typeof savedColData.visible === 'object'
                ? savedColData.visible
                : (savedColData && !savedColData.order ? savedColData : null);

            for (const field of fields) {
                if (opt.columns.some((c) => c.key === field.key)) continue;
                const isVis = visMap && typeof visMap[field.key] === 'boolean'
                    ? visMap[field.key]
                    : false;

                const c = {
                    key: field.key,
                    label: field.label,
                    hidden: true,
                    visible: isVis,
                    cls: field.type === 'number' ? 'num' : '',
                    render: (row) => {
                        const value = row[field.key];
                        if (value === null || value === undefined || value === '') return '';
                        if (field.type === 'date') return esc(String(value).slice(0, 10).split('-').reverse().join('/'));
                        if (field.type === 'number') {
                            const isAmt = field.key.endsWith('_amt') || field.scale === 2;
                            return esc(Number(value).toLocaleString('en-US', {
                                minimumFractionDigits: isAmt ? 2 : 0,
                                maximumFractionDigits: field.scale !== undefined && field.scale !== null ? field.scale : (isAmt ? 2 : 0)
                            }));
                        }
                        return esc(value);
                    }
                };
                opt.columns.push(c);
                defaultOrderKeys.push(c.key);
                defaultVisibleMap[c.key] = false;
                const th = document.createElement('th');
                th.textContent = c.label;
                th.dataset.sort = c.key;
                th.dataset.colKey = c.key;
                if (c.cls) th.className = 'text-end';
                table.tHead.rows[0].insertBefore(th, actHead || null);
                c.th = th;
                attachResizer(th, c);
                setupThDrag(th, c);
                added = true;
            }
            if (added) {
                if (savedColData) {
                    applyLayoutData(savedColData);
                } else if (buildPicker) {
                    buildPicker();
                }
            }
        }

        function render(data) {
            lastData = data;
            const startNum = ((data.page || 1) - 1) * (data.per_page || 20) + 1;
            const totalCols = (hasSeq ? 1 : 0) + opt.columns.length + (hasActions ? 1 : 0);
            if (!rows.length) {
                tbody.innerHTML = `<tr><td colspan="${totalCols}" class="text-center text-secondary py-3">${esc(t('empty'))}</td></tr>`;
            } else {
                tbody.innerHTML = rows.map((row, i) => {
                    const seqCell = hasSeq ? `<td class="phai-seq text-center text-secondary">${startNum + i}</td>` : '';
                    return '<tr tabindex="0" data-row="' + i + '">'
                        + seqCell
                        + opt.columns.map((c) => {
                            const html = c.render ? c.render(row) : esc(row[c.key]);
                            return `<td${c.cls ? ` class="${c.cls}"` : ''}>${html}</td>`;
                        }).join('')
                        + (hasActions ? actionCell(row) : '')
                        + '</tr>';
                }).join('');
            }

            applyColumns();
            if (opt.files && rows.length) files.mark(tbody);

            const from = data.total ? (data.page - 1) * data.per_page + 1 : 0;
            const to = Math.min(data.page * data.per_page, data.total);
            const last = Math.max(1, Math.ceil(data.total / data.per_page));
            pager.innerHTML = `<label class="phai-page-size">${esc(t('per_page'))}<select class="form-select form-select-sm w-auto" data-page-size aria-label="${esc(t('per_page'))}">${pageSizes.map((n) => `<option value="${n}" ${n === Number(data.per_page) ? 'selected' : ''}>${n}</option>`).join('')}</select></label><span>${from}–${to} ${esc(t('of'))} ${data.total}</span>`
                + '<div class="btn-group">'
                + `<button type="button" class="btn btn-sm btn-icon" data-page="${data.page - 1}" ${data.page <= 1 ? 'disabled' : ''}><i class="ti ti-chevron-left"></i></button>`
                + `<button type="button" class="btn btn-sm btn-icon" data-page="${data.page + 1}" ${data.page >= last ? 'disabled' : ''}><i class="ti ti-chevron-right"></i></button>`
                + '</div>';

            table.querySelectorAll('th[data-sort]').forEach((th) => {
                const icon = th.querySelector('.ti') || th.appendChild(document.createElement('i'));
                const on = th.dataset.sort === state.sort;
                icon.className = 'ti ms-1 ' + (on ? (state.dir === 'asc' ? 'ti-sort-ascending' : 'ti-sort-descending') : 'ti-arrows-sort');
            });
        }

        pager.addEventListener('change', (e) => {
            if (!e.target.matches('[data-page-size]')) return;
            const size = Number(e.target.value);
            if (!pageSizes.includes(size)) return;
            state.per_page = size;
            state.page = 1;
            reload();
        });

        pager.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-page]');
            if (!btn) return;
            state.page = Number(btn.dataset.page);
            reload();
        });

        table.tHead?.addEventListener('click', (e) => {
            if (isReordering) return;
            if (e.target.closest('.phai-col-resizer')) return;
            const th = e.target.closest('th[data-sort]');
            if (!th) return;
            state.dir = state.sort === th.dataset.sort && state.dir === 'asc' ? 'desc' : 'asc';
            state.sort = th.dataset.sort;
            state.page = 1;
            reload();
        });

        /**
         * Render only the action buttons that are supported by the current screen.
         * Row-level permissions enable or disable the button for each specific row.
         */
        function actionCell(row) {
            if (!hasActions) return '';
            let html = '';
            const btn = (act, icon, label, on) => `<button type="button" class="phai-act-btn${act === 'delete' ? ' is-danger' : ''}" data-act="${act}"`
                + ` title="${esc(t(label))}" aria-label="${esc(t(label))}"${on ? '' : ' disabled'}><i class="ti ti-${icon}"></i></button>`;

            for (const act of supportedActs) {
                if (act === 'files') {
                    const nbr = String(row[opt.files.key] ?? '');
                    html += files.button(opt.files.doc, nbr, String(row[opt.columns[0]?.key] ?? nbr)); // titled by the number the row shows
                } else if (act === 'view') {
                    const on = !acts.canView || acts.canView(row) !== false;
                    html += btn('view', 'eye', 'view', on);
                } else if (act === 'print') {
                    const printPath = typeof acts.print === 'function' ? acts.print(row) : acts.print;
                    const canPrint = !acts.canPrint || acts.canPrint(row) !== false;
                    html += btn('print', 'printer', 'print', !!(printPath && canPrint));
                } else if (act === 'edit') {
                    const on = !acts.canEdit || acts.canEdit(row) !== false;
                    html += btn('edit', 'pencil', 'edit', on);
                } else if (act === 'undo') {
                    const on = !acts.canUndo || acts.canUndo(row) !== false;
                    html += btn('undo', 'arrow-back-up', 'undo', on);
                } else if (act === 'delete') {
                    const on = !acts.canDelete || acts.canDelete(row) !== false;
                    html += btn('delete', 'trash', 'delete', on);
                }
            }

            return `<td class="phai-act">${html}</td>`;
        }

        /** Read-only page of a row: clean 4-column detail table without boxes. */
        function detail(row) {
            let dialog = document.getElementById('phai-row-view');
            if (!dialog) {
                dialog = document.createElement('div');
                dialog.id = 'phai-row-view'; dialog.className = 'modal'; dialog.tabIndex = -1;
                dialog.innerHTML = `<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">${esc(t('view_details'))}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${esc(t('close'))}"></button></div><div class="modal-body p-0"><div class="table-responsive"><table class="table table-sm table-bordered phai-detail-table mb-0"><tbody></tbody></table></div></div></div></div>`;
                document.body.append(dialog);
            }
            const pairs = [];
            for (let i = 0; i < opt.columns.length; i += 2) {
                const c1 = opt.columns[i];
                const c2 = opt.columns[i + 1];
                const v1 = c1.render ? c1.render(row) : esc(row[c1.key] ?? '');
                const l1 = esc(c1.label || c1.th?.textContent.trim() || c1.key);
                const isBlank1 = v1 === '' || v1 === null || v1 === undefined;
                if (c2) {
                    const v2 = c2.render ? c2.render(row) : esc(row[c2.key] ?? '');
                    const l2 = esc(c2.label || c2.th?.textContent.trim() || c2.key);
                    const isBlank2 = v2 === '' || v2 === null || v2 === undefined;
                    pairs.push('<tr>'
                        + `<th class="text-secondary fw-medium bg-surface-secondary text-nowrap" style="width:18%">${l1}</th>`
                        + `<td style="width:32%" class="${isBlank1 ? 'text-secondary opacity-75' : ''}">${isBlank1 ? '–' : v1}</td>`
                        + `<th class="text-secondary fw-medium bg-surface-secondary text-nowrap" style="width:18%">${l2}</th>`
                        + `<td style="width:32%" class="${isBlank2 ? 'text-secondary opacity-75' : ''}">${isBlank2 ? '–' : v2}</td>`
                        + '</tr>');
                } else {
                    pairs.push('<tr>'
                        + `<th class="text-secondary fw-medium bg-surface-secondary text-nowrap" style="width:18%">${l1}</th>`
                        + `<td colspan="3" class="${isBlank1 ? 'text-secondary opacity-75' : ''}">${isBlank1 ? '–' : v1}</td>`
                        + '</tr>');
                }
            }
            dialog.querySelector('.phai-detail-table tbody').innerHTML = pairs.join('');
            bs.Modal.getOrCreateInstance(dialog).show();
        }

        function openRow(row, tr) {
            if (opt.onClick) opt.onClick(row, tr);
            else detail(row);
        }
        tbody.addEventListener('click', (e) => {
            const tr = e.target.closest('tr[data-row]');
            if (!tr) return;
            const row = rows[Number(tr.dataset.row)];
            const b = e.target.closest('[data-act]');
            if (b) {
                if (b.disabled) return;
                // view never edits: a screen whose row click opens an editor gets the read-only detail
                if (b.dataset.act === 'view') (acts.view || (acts.edit ? detail : openRow))(row, tr);
                else if (b.dataset.act === 'print') {
                    const printPath = typeof acts.print === 'function' ? acts.print(row) : acts.print;
                    if (printPath) window.open(base + printPath, '_blank', 'noopener');
                }
                else if (acts[b.dataset.act]) acts[b.dataset.act](row, tr);
                return;
            }
            if (!e.target.closest('a, button, input, select')) openRow(row, tr);
        });
        tbody.addEventListener('keydown', (e) => {
            if (e.target.matches('tr[data-row]') && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault(); openRow(rows[Number(e.target.dataset.row)], e.target);
            }
        });

        if (search) {
            // a list opened with ?q=… (e.g. a QC number linked from a work order) starts searched
            const urlQ = new URLSearchParams(location.search).get('q');
            if (urlQ) {
                search.value = urlQ;
                state.q = urlQ.trim();
            }
            let timer;
            search.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    state.q = search.value.trim();
                    state.page = 1;
                    reload();
                }, 250);
            });
        }

        return { reload, state, rows: () => rows };
    }

    /** Small active / inactive badge. */
    // ------------------------------------------------------------ Thai address pickers

    /**
     * province -> district -> subdistrict selects, zip filled from the subdistrict.
     * Values saved are the Thai names; ids are only used to load the next list.
     * Fields carry data-geo="province|district|subdistrict|zip"; the installer passes its own API base.
     */
    function geo(f, base = '/api/geo') {
        const el = {
            province: f.querySelector('[data-geo="province"]'),
            district: f.querySelector('[data-geo="district"]'),
            subdistrict: f.querySelector('[data-geo="subdistrict"]'),
            zip: f.querySelector('[data-geo="zip"]'),
        };
        if (!el.province) return { load: async () => {} };

        function fill(sel, items, value) {
            sel.length = 1; // keep the empty option
            for (const it of items) {
                const o = new Option(it.name, it.name);
                o.dataset.id = it.id;
                if (it.zip) o.dataset.zip = it.zip;
                sel.add(o);
            }
            if (value && ![...sel.options].some((o) => o.value === value)) sel.add(new Option(value, value));
            sel.value = value || '';
        }

        const id = (sel) => sel.selectedOptions[0]?.dataset.id;
        let provinces = null;

        async function loadDistricts(value) {
            const pid = id(el.province);
            fill(el.district, pid ? await api.get(base + '/districts/' + pid) : [], value);
        }

        async function loadSubdistricts(value) {
            const did = id(el.district);
            if (el.subdistrict) fill(el.subdistrict, did ? await api.get(base + '/subdistricts/' + did) : [], value);
        }

        el.province.addEventListener('change', async () => {
            await loadDistricts('');
            await loadSubdistricts('');
            if (el.zip) el.zip.value = '';
        });
        el.district?.addEventListener('change', async () => {
            await loadSubdistricts('');
            if (el.zip) el.zip.value = '';
        });
        el.subdistrict?.addEventListener('change', () => {
            const zip = el.subdistrict.selectedOptions[0]?.dataset.zip;
            if (zip && el.zip) el.zip.value = zip;
        });

        return {
            /** Show a saved address (or empty pickers for a new record). */
            async load(row) {
                provinces ??= await api.get(base + '/provinces');
                fill(el.province, provinces, row ? row[el.province.name] : '');
                if (el.district) await loadDistricts(row ? row[el.district.name] : '');
                if (el.subdistrict) await loadSubdistricts(row ? row[el.subdistrict.name] : '');
            },
        };
    }

    /**
     * Taking out more than is on hand: the quantity box turns red with "คงเหลือไม่พอ (มี 5 EA)" under it
     * (the save is refused anyway — this says so first). Call on input and whenever the on-hand figure changes.
     */
    function stockShort(input, onHand, um = '') {
        if (!input) return false;
        const short = (Number(String(input.value).replace(/,/g, '')) || 0) > Number(onHand);
        let msg = input.parentElement.querySelector('[data-short]');
        if (!msg) {
            msg = document.createElement('div');
            msg.className = 'invalid-feedback';
            msg.dataset.short = '';
            input.after(msg);
        }
        msg.textContent = t('not_enough', { qty: Number(onHand).toLocaleString('en-US', { maximumFractionDigits: 5 }), um });
        input.classList.toggle('is-invalid', short);
        return short;
    }

    /**
     * ยกเลิก beside every save (submit) button, unless one is there already ([data-cancel], an *-undo button, a modal
     * dismiss) or the form / button says data-no-cancel. Cancel closes the modal it is in; else runs form.phaiCancel()
     * when the page set one; else reloads — what was typed is dropped and the saved state comes back. It is shown and
     * hidden with its save button. Pages that build forms later call Phai.cancelButtons(theirRoot).
     */
    function cancelButtons(root = document) {
        root.querySelectorAll('button[type=submit]').forEach((save) => {
            const f = save.form;
            if (!f || f.hasAttribute('data-no-cancel') || save.hasAttribute('data-no-cancel')) return;
            if (save.parentElement.querySelector('[data-cancel], [id$="-undo"], [data-bs-dismiss]')) return;
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-sm';
            b.dataset.cancel = '';
            b.innerHTML = `<i class="ti ti-x me-1"></i>${esc(t('cancel'))}`;
            if (save.classList.contains('ms-auto')) { // the pair sits where the save button did
                save.classList.remove('ms-auto');
                b.classList.add('ms-auto');
            }
            if (save.closest('.modal')) {
                b.setAttribute('data-bs-dismiss', 'modal');
            } else {
                b.addEventListener('click', () => {
                    form.errors(f, null);
                    if (typeof f.phaiCancel === 'function') f.phaiCancel();
                    else location.reload();
                });
            }
            b.hidden = save.hidden;
            new MutationObserver(() => { b.hidden = save.hidden; }).observe(save, { attributes: true, attributeFilter: ['hidden'] });
            save.before(b);
        });
    }
    document.addEventListener('DOMContentLoaded', () => cancelButtons());

    function badge(active) {
        return Number(active) === 1 || active === true
            ? `<span class="badge bg-green-lt">${esc(t('active'))}</span>`
            : `<span class="badge bg-secondary-lt">${esc(t('inactive'))}</span>`;
    }

    // ---------------------------------------------------------------- Keyboard

    // "/" jumps to the search box of the page.
    document.addEventListener('keydown', (e) => {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
        if (e.target.closest('input, textarea, select, [contenteditable]')) return;
        const box = document.querySelector('[data-search]');
        if (box) {
            e.preventDefault();
            box.focus();
            box.select();
        }
    });

    // Focus the first field when a modal opens.
    document.addEventListener('shown.bs.modal', (e) => {
        e.target.querySelector('input:not([type=hidden]):not([readonly]):not([disabled]), select, textarea')?.focus();
    });

    /** Render plain text in view mode while retaining controls for editing. */
    function viewFields(scope, viewing) {
        scope.classList.toggle('phai-view-mode', viewing);
        scope.querySelectorAll('[data-view-value]').forEach((node) => node.remove());
        scope.querySelectorAll('[data-view-hidden="1"]').forEach((node) => { node.hidden = false; delete node.dataset.viewHidden; });
        if (!viewing) return;
        scope.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((el) => {
            if (el.hidden) return;
            // a switch shows like the other fields: its label over yes / no
            const check = el.type === 'checkbox' ? el.closest('.form-check') : null;
            if (check) {
                const box = document.createElement('div');
                box.dataset.viewValue = '';
                box.innerHTML = `<div class="form-label">${esc(check.textContent.trim())}</div><div class="phai-view-value">${esc(t(el.checked ? 'yes' : 'no'))}</div>`;
                check.after(box); check.hidden = true; check.dataset.viewHidden = '1';
                return;
            }
            if (el.type === 'checkbox') {
                // a tick box of a table (pick lines, "all") has nothing to say on a saved document
                el.hidden = true; el.dataset.viewHidden = '1';
                return;
            }
            const text = document.createElement('div');
            text.dataset.viewValue = '';
            text.className = 'phai-view-value' + (el.classList.contains('text-end') ? ' text-end' : '');
            let value = el.tagName === 'SELECT' ? (el.value ? el.selectedOptions[0]?.textContent : '')
                : el.type === 'checkbox' ? t(el.checked ? 'active' : 'inactive') : el.value;
            if (el.type === 'date' && value) value = value.split('-').reverse().join('/');
            const name = pick.ensure(el) ? el._pickBtn.querySelector('.phai-combo-label')?.textContent : ''; // a picked code with its name
            if (value && name) value += ' · ' + name;
            if (!value) {
                text.classList.add('is-empty');
                text.textContent = '–';
            } else {
                text.textContent = value;
            }
            el.after(text); el.hidden = true; el.dataset.viewHidden = '1';
        });
    }

    /*
     * One select box for the whole system, with a search box inside its list.
     *
     * - <select>: the select stays the real field; only the browser's own popup is replaced.
     *   Opt out with class "phai-native" (or multiple / size).
     * - Fields that pick from data on the server are an <input data-pick="vendors"> (the input is
     *   hidden; a select box shows "code  name"). Picking sets input.value and fires 'input' + 'change',
     *   setting input.value from code updates the box. data-free lets Enter keep text that is not in
     *   the list (free items). Sources:
     *
     *   Phai.pick.source('open-pos', {url: '/api/receipts/open-pos', value: 'po_nbr', label: (r) => r.vd_name})
     *   Phai.pick.source('ap-refs', {load: async (q, input) => [{value, label}]})
     *   Phai.pick.source('uoms', {items: [{value, label}]})          // fixed list, searched here
     *   <input data-pick="items"> + Phai.pick.items(input, [{value, label}])  // a list of its own (lots)
     *   Phai.pick.label(input, 'name')                                // name for the value just set
     */
    const pick = (function picker() {
        const sources = {
            vendors: { url: '/api/purchase/vendors', value: 'vd_code', label: 'vd_name' },
            customers: { url: '/api/sales/customers', value: 'cm_code', label: 'cm_name' },
            parts: { url: '/api/stock/parts', value: 'pt_part', label: 'pt_desc1', params: (input) => (input.dataset.sale !== undefined ? { sale: 1 } : {}) },
            people: { url: '/api/people', value: 'emp_name', label: 'emp_dept' }, // a name; with data-free any name may be typed
        };
        const names = new Map();  // "source|code" -> name, so a code keeps showing its name
        const asking = new Set(); // names being looked up

        const panel = document.createElement('div');
        panel.className = 'phai-combo phai-select-panel';
        panel.hidden = true;
        panel.innerHTML = '<input type="search" class="form-control form-control-sm mb-1" autocomplete="off"><div role="listbox"></div>';
        const search = panel.firstChild;
        const box = panel.lastChild;
        const attach = () => { if (!panel.isConnected && document.body) document.body.append(panel); };
        let field = null;   // the <select> or the hidden <input> being picked for
        let items = [];
        let active = -1;
        let timer = 0;
        let asked = 0;      // drops answers to older searches

        const isInput = (el) => el instanceof HTMLInputElement;
        const usableSelect = (el) => el instanceof HTMLSelectElement && !el.multiple && el.size <= 1
            && !el.disabled && !el.classList.contains('phai-native');
        const keyOf = (input, value) => (input.dataset.pick === 'items' ? '' : input.dataset.pick) + '|' + value;

        // ---------------------------------------------------------- where the choices come from
        function rowsToItems(src, rows) {
            return rows.map((r) => ({
                value: String(r[src.value] ?? ''),
                label: String((typeof src.label === 'function' ? src.label(r) : r[src.label]) ?? ''),
            }));
        }
        async function fetchItems(input, q) {
            if (input.dataset.pick === 'items') return input._pickItems || [];
            const src = sources[input.dataset.pick];
            if (!src) return [];
            let list;
            if (src.items) list = typeof src.items === 'function' ? src.items(input) : src.items;
            else if (src.load) return src.load(q, input);
            else list = rowsToItems(src, await api.get(src.url, { q, ...(src.params ? src.params(input) : {}) }));
            return list;
        }
        const local = (input) => input.dataset.pick === 'items' || !!sources[input.dataset.pick]?.items;
        function remember(input, list) {
            if (input.dataset.pick === 'items') return; // a row's own list (lots) is read from the input
            for (const o of list) names.set(keyOf(input, o.value), o.label);
        }
        /** Name of the code in the box: known, or looked up once. */
        function nameOf(input, value) {
            if (input.dataset.pick === 'items') return (input._pickItems || []).find((o) => o.value === value)?.label || '';
            const key = keyOf(input, value);
            if (names.has(key)) return names.get(key);
            if (!asking.has(key) && sources[input.dataset.pick]) {
                asking.add(key);
                fetchItems(input, value).then((list) => {
                    remember(input, list);
                    if (!names.has(key)) names.set(key, '');
                    if (input.value === value) sync(input);
                }).catch(() => {});
            }
            return '';
        }

        // ---------------------------------------------------------- the box shown for an input
        function sync(input) {
            const btn = input._pickBtn;
            if (!btn) return;
            btn.disabled = input.disabled || input.readOnly;
            btn.hidden = input.hidden;
            btn.classList.toggle('is-invalid', input.classList.contains('is-invalid'));
            const v = input.value;
            const name = v ? nameOf(input, v) : '';
            btn.innerHTML = v
                ? `<span class="phai-combo-code">${esc(v)}</span>${name ? `<span class="phai-combo-label">${esc(name)}</span>` : ''}`
                : `<span class="phai-pick-placeholder">${esc(input.placeholder || '')}</span>`;
        }
        function enhance(input) {
            if (input._pickBtn || input.type === 'hidden') return;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'form-select phai-pick-btn' + (input.classList.contains('form-control-sm') ? ' form-select-sm' : '');
            btn.style.cssText = input.style.cssText;
            input.classList.add('phai-pick-src');
            input.after(btn);
            input._pickBtn = btn;
            btn._pickFor = input;
            sync(input);
            new MutationObserver(() => sync(input)).observe(input, { attributes: true, attributeFilter: ['disabled', 'readonly', 'class', 'hidden', 'placeholder'] });
        }
        /** Page code may set a value or draw the view before the observer below has run: enhance now. */
        function ensure(input) {
            if (input._pickBtn || input.dataset?.pick === undefined || !input.isConnected) return !!input._pickBtn;
            enhance(input);
            return true;
        }
        const scan = (root) => root.querySelectorAll?.('input[data-pick]').forEach(enhance);
        new MutationObserver((ms) => ms.forEach((m) => m.addedNodes.forEach((n) => {
            if (n.nodeType !== 1) return;
            if (n.matches('input[data-pick]')) enhance(n);
            scan(n);
        }))).observe(document.documentElement, { childList: true, subtree: true });
        const start = () => { attach(); scan(document); };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
        document.addEventListener('reset', (e) => setTimeout(() => e.target.querySelectorAll('.phai-pick-src').forEach(sync)));

        // code that sets input.value or focuses the input reaches the box
        const valueProp = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
        Object.defineProperty(HTMLInputElement.prototype, 'value', {
            configurable: true,
            enumerable: valueProp.enumerable,
            get() { return valueProp.get.call(this); },
            set(v) { valueProp.set.call(this, v); if (this._pickBtn || ensure(this)) sync(this); },
        });
        const focusInput = HTMLInputElement.prototype.focus;
        HTMLInputElement.prototype.focus = function (o) { return ensure(this) ? this._pickBtn.focus(o) : focusInput.call(this, o); };

        // ---------------------------------------------------------- the list
        function selectItems() {
            return [...field.options].filter((o) => !o.disabled && !o.hidden).map((o) => {
                const text = o.textContent.trim();
                const cut = text.indexOf(' · ');
                return { value: o.value, code: cut > 0 ? text.slice(0, cut) : text, label: cut > 0 ? text.slice(cut + 3) : '' };
            });
        }
        function draw(list) {
            if (!field) return;
            const q = search.value.trim().toLowerCase();
            const all = isInput(field) ? list.map((o) => ({ ...o, code: o.value })) : selectItems();
            // the server already searched; a fixed list is searched here
            items = (isInput(field) && !local(field) ? all : all.filter((o) => !q || o.code.toLowerCase().includes(q) || o.label.toLowerCase().includes(q))).slice(0, 100);
            if (isInput(field) && field.dataset.free !== undefined && q && !items.some((o) => o.value.toLowerCase() === q)) {
                const text = search.value.trim();
                items.push({ value: field.classList.contains('text-uppercase') ? text.toUpperCase() : text, code: text, label: '', free: true });
            }
            const current = field.value;
            active = q ? 0 : Math.max(0, items.findIndex((o) => o.value === current));
            box.innerHTML = items.map((o, i) => `<div class="phai-combo-item${i === active ? ' active' : ''}${o.free ? ' fst-italic' : ''}" data-i="${i}" role="option">`
                + `<span class="phai-combo-code">${o.code ? esc(o.code) : '&nbsp;'}</span><span class="phai-combo-label">${esc(o.label)}</span></div>`).join('')
                || `<div class="text-secondary small px-2 py-1">${esc(t('empty'))}</div>`;
            box.children[active]?.scrollIntoView({ block: 'nearest' });
            place();
        }
        function refill() {
            if (!isInput(field)) return draw();
            const el = field;
            const q = search.value.trim();
            const n = ++asked;
            clearTimeout(timer);
            timer = setTimeout(async () => {
                let list = [];
                try { list = await fetchItems(el, q); } catch (_) { /* shown as empty */ }
                if (n !== asked || field !== el) return;
                remember(el, list);
                draw(list);
            }, local(el) ? 0 : 150);
        }
        function place() {
            const r = (field._pickBtn || field).getBoundingClientRect();
            panel.style.minWidth = Math.max(r.width, 240) + 'px';
            panel.style.left = Math.max(4, Math.min(r.left, window.innerWidth - panel.offsetWidth - 4)) + 'px';
            const below = window.innerHeight - r.bottom;
            if (below < 260 && r.top > below) {
                panel.style.top = '';
                panel.style.bottom = (window.innerHeight - r.top + 2) + 'px';
            } else {
                panel.style.bottom = '';
                panel.style.top = (r.bottom + 2) + 'px';
            }
        }
        function open(el, text = '') {
            if (field) close(false);
            // inside an open dialog the list must live in it, or the dialog takes the focus back and the list closes
            const host = el.closest('.modal') || document.body;
            if (panel.parentElement !== host) host.append(panel);
            field = el;
            search.value = text;
            panel.hidden = false;
            box.innerHTML = '';
            if (isInput(el)) refill(); else draw();
            place();
            search.focus();
        }
        function close(refocus) {
            if (!field) return;
            const el = field;
            clearTimeout(timer);
            panel.hidden = true;
            field = null;
            if (refocus) (el._pickBtn || el).focus();
        }
        function choose(o) {
            const el = field;
            close(true);
            if (el.value === o.value) return;
            if (isInput(el)) remember(el, [o]);
            el.value = o.value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // ---------------------------------------------------------- opening
        document.addEventListener('mousedown', (e) => {
            if (!usableSelect(e.target) || e.button !== 0) return;
            e.preventDefault(); // no browser list
            if (field === e.target) return close(true);
            e.target.focus();
            open(e.target);
        });
        document.addEventListener('click', (e) => {
            const btn = e.target.closest?.('.phai-pick-btn');
            if (!btn || btn.disabled) return;
            if (field === btn._pickFor) return close(true);
            open(btn._pickFor);
        });
        document.addEventListener('keydown', (e) => {
            const btn = e.target.closest?.('.phai-pick-btn');
            const el = btn ? btn._pickFor : e.target;
            if (!(btn || usableSelect(el)) || e.ctrlKey || e.metaKey) return;
            if (['ArrowDown', 'ArrowUp', 'F4'].includes(e.key) || (!btn && (e.key === ' ' || (e.key === 'Enter' && !el.form)))) {
                e.preventDefault();
                open(el);
            } else if (e.key === 'Delete' && btn && el.value) {
                e.preventDefault(); // clear the box
                el.value = '';
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (e.key.length === 1 && e.key !== ' ' && !e.altKey) {
                e.preventDefault();
                open(el, e.key); // typing on a closed box starts the search
            }
        });
        search.addEventListener('input', refill);
        search.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                active = Math.max(0, Math.min(items.length - 1, active + (e.key === 'ArrowDown' ? 1 : -1)));
                box.querySelectorAll('.phai-combo-item').forEach((x, i) => x.classList.toggle('active', i === active));
                box.children[active]?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                if (items[active]) choose(items[active]);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation(); // close the list, not the dialog
                close(true);
            } else if (e.key === 'Tab') {
                close(false);
            }
        });
        search.addEventListener('blur', () => setTimeout(() => { if (field && !panel.contains(document.activeElement)) close(false); }, 0));
        box.addEventListener('mousedown', (e) => {
            e.preventDefault();
            const item = e.target.closest('.phai-combo-item');
            if (item) choose(items[Number(item.dataset.i)]);
        });
        window.addEventListener('resize', () => { if (field) place(); });
        document.addEventListener('scroll', (e) => { if (field && !panel.contains(e.target)) place(); }, true);

        return {
            source(name, def) { sources[name] = def; },
            ensure,
            items(input, list) { input._pickItems = list; remember(input, list); sync(input); },
            label(input, name) { if (input.value) remember(input, [{ value: input.value, label: name ?? '' }]); sync(input); },
        };
    })();

    // Every dialog (except the small confirm ones) gets a full-screen toggle; the choice is remembered in this browser.
    document.addEventListener('show.bs.modal', (e) => {
        const dialog = e.target.querySelector('.modal-dialog');
        const header = e.target.querySelector('.modal-header');
        if (!dialog || !header || dialog.classList.contains('modal-sm')) return;
        let max = false;
        try { max = localStorage.getItem('phai-modal-max') === '1'; } catch (_) {}
        let btn = header.querySelector('.phai-modal-max');
        const set = (on) => {
            dialog.classList.toggle('modal-fullscreen', on);
            btn.firstChild.className = 'ti ' + (on ? 'ti-arrows-minimize' : 'ti-arrows-maximize');
        };
        if (!btn) {
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'phai-modal-max';
            btn.setAttribute('aria-label', t('full_screen'));
            btn.innerHTML = '<i class="ti"></i>';
            btn.addEventListener('click', () => {
                const on = !dialog.classList.contains('modal-fullscreen');
                set(on);
                try { localStorage.setItem('phai-modal-max', on ? '1' : '0'); } catch (_) {}
            });
            header.append(btn);
        }
        set(max);
    });

    // Every password box gets an eye button to see what was typed (the sign-in page has its own).
    document.querySelectorAll('input[type="password"]:not(#login-pass)').forEach((input) => {
        const wrap = document.createElement('div');
        wrap.className = 'position-relative';
        input.before(wrap);
        wrap.append(input);
        input.classList.add('pe-5');
        const eye = document.createElement('button');
        eye.type = 'button';
        eye.className = 'phai-login-eye';
        eye.tabIndex = -1;
        eye.setAttribute('aria-label', t('show_password'));
        eye.innerHTML = '<i class="ti ti-eye"></i>';
        eye.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            eye.firstChild.className = 'ti ' + (show ? 'ti-eye-off' : 'ti-eye');
            input.focus();
        });
        wrap.append(eye);
    });

    // ---------------------------------------------------------------- dialogs open out of the click (like iOS)

    // Every Bootstrap modal grows (and turns a little) out of the point last clicked, and shrinks back there on close
    // (CSS .phai-zoom, --phai-from). No click just before (Esc, code) → the usual fade.
    let lastPoint = null;
    document.addEventListener('pointerdown', (e) => { lastPoint = { x: e.clientX, y: e.clientY, at: Date.now() }; }, true);
    document.addEventListener('show.bs.modal', (e) => {
        const modal = e.target;
        const dialog = modal.querySelector?.('.modal-dialog');
        modal.classList.remove('phai-zoom', 'phai-closing');
        delete modal.dataset.phaiShrunk;
        if (!dialog || !lastPoint || Date.now() - lastPoint.at > 1500 || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        // where the dialog will sit: lay it out for a moment without any transform
        modal.style.display = 'block';
        dialog.style.transform = 'none';
        const r = dialog.getBoundingClientRect();
        dialog.style.transform = '';
        modal.style.display = '';
        const dx = Math.round(lastPoint.x - (r.left + r.width / 2));
        const dy = Math.round(lastPoint.y - (r.top + Math.min(r.height, innerHeight) / 2));
        modal.style.setProperty('--phai-from', `translate(${dx}px, ${dy}px) scale(.08) rotate(${dx < 0 ? -12 : 12}deg)`);
        modal.classList.add('phai-zoom');
    });
    // closing: first shrink back into that point (.phai-closing), then let Bootstrap hide it
    document.addEventListener('hide.bs.modal', (e) => {
        const modal = e.target;
        if (!modal.classList.contains('phai-zoom') || modal.dataset.phaiShrunk) return;
        e.preventDefault();
        if (modal.classList.contains('phai-closing')) return; // already shrinking
        modal.classList.add('phai-closing');
        setTimeout(() => {
            modal.dataset.phaiShrunk = '1';
            (window.bootstrap || window.tabler).Modal.getOrCreateInstance(modal).hide();
        }, 320);
    });
    document.addEventListener('hidden.bs.modal', (e) => {
        e.target.classList.remove('phai-closing');
        delete e.target.dataset.phaiShrunk;
    });

    // inside a window (?embed=1) the pages it moves to stay without menu and top bar
    const embedded = document.body?.classList.contains('phai-embed');
    const url = (p) => (embedded && !p.startsWith('/api/') && !/\/print(\?|$)/.test(p) && !/[?&]embed=/.test(p)
        ? base + p + (p.includes('?') ? '&' : '?') + 'embed=1' : base + p);
    /**
     * A picture the user picked, made small in the browser before it is sent: the longer side at most $max px, as WebP
     * (keeps transparency) — a phone photo of several MB becomes a few dozen KB. Resolves to a data URL.
     */
    function shrinkImage(file, max = 512, quality = .86) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const src = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(src);
                const scale = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(16, Math.round(img.naturalWidth * scale));
                canvas.height = Math.max(16, Math.round(img.naturalHeight * scale));
                const ctx = canvas.getContext('2d');
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                let url = canvas.toDataURL('image/webp', quality);
                if (!url.startsWith('data:image/webp')) url = canvas.toDataURL('image/png'); // a browser without WebP output
                resolve(url);
            };
            img.onerror = () => { URL.revokeObjectURL(src); reject(new Error('image')); };
            img.src = src;
        });
    }

    /**
     * The month picker of a toolbar (views/partials/month-picker.php): ‹ › step a month, the label opens the months of a
     * year. The value is YYYY-MM (ค.ศ.) shown in พ.ศ.; '' = every month ("ทุกเดือน", or opt.empty as the label).
     *   const m = Phai.month('#month-picker', {value: '2026-10', change: (v) => ...});  m.value · m.set(v, notify)
     */
    function month(el, opt = {}) {
        const picker = typeof el === 'string' ? document.querySelector(el) : el;
        const panel = picker.querySelector('.phai-month-panel');
        const names = t('months').split(',');
        const be = (y) => y + Number(t('year_offset')) || y;
        const ym = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        const box = { value: opt.value ?? ym(new Date()) };
        let year = 0;
        const label = () => {
            const [y, m] = box.value.split('-').map(Number);
            picker.querySelector('.phai-month-label span').textContent = box.value ? `${names[m - 1]} ${be(y)}` : (opt.empty ?? t('all_months'));
        };
        box.set = (v, notify = true) => {
            box.value = v;
            panel.hidden = true;
            label();
            if (notify && opt.change) opt.change(v);
        };
        const step = (n) => {
            const [y, m] = (box.value || ym(new Date())).split('-').map(Number);
            box.set(ym(new Date(y, m - 1 + n, 1)));
        };
        const drawYear = () => {
            panel.querySelector('[data-year-text]').textContent = be(year);
            panel.querySelectorAll('[data-month]').forEach((b) => {
                b.classList.toggle('active', box.value === `${year}-${String(b.dataset.month).padStart(2, '0')}`);
            });
        };
        picker.querySelectorAll(':scope > [data-step]').forEach((b) => b.addEventListener('click', () => step(Number(b.dataset.step))));
        picker.querySelector('.phai-month-label').addEventListener('click', () => {
            year = Number((box.value || ym(new Date())).slice(0, 4));
            drawYear();
            panel.hidden = !panel.hidden;
        });
        panel.addEventListener('click', (e) => {
            const b = e.target.closest('button');
            if (!b) return;
            if (b.dataset.year) { year += Number(b.dataset.year); drawYear(); return; }
            if (b.dataset.month) box.set(`${year}-${String(b.dataset.month).padStart(2, '0')}`);
            if (b.hasAttribute('data-now')) box.set(ym(new Date()));
            if (b.hasAttribute('data-all')) box.set('');
        });
        document.addEventListener('click', (e) => { if (!picker.contains(e.target)) panel.hidden = true; });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') panel.hidden = true; });
        label();
        return box;
    }

    /**
     * Files attached to a document (System AttachmentController), two ways:
     * - a box under a saved document: <div data-files="PO" data-nbr="PO2026100001"> (views/partials/files.php) becomes a card
     *   with its files — a picture shows small, a PDF as its icon, a click opens it — with แนบไฟล์ and × as the document's own
     *   rights allow. Setting data-nbr (re)loads it; empty hides it; data-title is put after the heading; data-bare = no card.
     * - a paperclip in a table row: files.button(doc, nbr, title) opens the same box in a dialog; files.mark(root) puts the
     *   number of files on the paperclips (Phai.grid does both when it has opt.files).
     */
    const files = (function attachments() {
        const TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        const where = (doc, nbr) => `${doc}/${encodeURIComponent(nbr)}`;
        /** force = again after an upload or a removal; otherwise a box already showing this document is left as it is. */
        async function load(box, force = false) {
            const key = `${box.dataset.files}/${box.dataset.nbr}`;
            if (!force && box.phaiShown === key) return;
            box.phaiShown = key;
            if (!box.dataset.files || !box.dataset.nbr) {
                box.hidden = true;
                box.innerHTML = '';
                return;
            }
            const at = where(box.dataset.files, box.dataset.nbr);
            let d;
            try {
                d = await api.get(`/api/files/${at}`);
            } catch (_) {
                box.hidden = true;
                return;
            }
            if (box.phaiShown !== key) return; // another document was asked for meanwhile
            box.phaiCount = d.files.length;
            box.dispatchEvent(new CustomEvent('phai:files', { bubbles: true, detail: { count: d.files.length } })); // e.g. the paperclip that opened it
            const src = (f) => url(`/files/${at}/${f.att_id}`);
            const add = d.can.add ? `<label class="btn btn-sm ms-auto mb-0"><i class="ti ti-upload me-1"></i>${esc(t('files_add'))}<input type="file" accept="${TYPES.join(',')}" multiple hidden></label>` : '';
            const list = d.files.map((f) => `<div class="phai-pc-file">
                    <a href="${esc(src(f))}" target="_blank" rel="noopener" title="${esc(f.att_name)}">${f.att_mime.startsWith('image/') ? `<img src="${esc(src(f))}" alt="">` : '<i class="ti ti-file-type-pdf"></i>'}<span>${esc(f.att_name)}</span></a>
                    ${d.can.delete ? `<button type="button" class="btn btn-sm btn-ghost-danger btn-icon" data-file="${f.att_id}" aria-label="×"><i class="ti ti-x"></i></button>` : ''}</div>`).join('');
            const none = `<span class="text-secondary small">${esc(t('files_none'))}${d.hint ? ' — ' + esc(d.hint) : ''}</span>`;
            box.classList.add('phai-files');
            box.hidden = false;
            if (box.dataset.bare) {
                box.innerHTML = `<div class="d-flex align-items-center gap-2${d.files.length ? ' mb-2' : ''}">${d.files.length ? `<span class="text-secondary small">${esc(t('files_count', { n: d.files.length }))}</span>` : none}${add}</div>`
                    + (d.files.length ? `<div class="d-flex flex-wrap gap-2 align-items-center">${list}</div>` : '');
                return;
            }
            box.classList.add('card', 'mb-3');
            box.innerHTML = `<div class="card-header py-2 d-flex align-items-center">
                    <h3 class="card-title"><i class="ti ti-paperclip me-1"></i>${esc(t('files'))}${box.dataset.title ? ' · ' + esc(box.dataset.title) : ''}${d.files.length ? ` <span class="badge bg-secondary-lt ms-1">${d.files.length}</span>` : ''}</h3>${add}</div>
                <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">${d.files.length ? list : none}</div>`;
        }

        /** Send pictures (made smaller: a phone photo of a slip need not be 5 MB) or PDFs to a document; stops at the first refusal. */
        async function send(doc, nbr, list) {
            for (const f of list) {
                if (!TYPES.includes(f.type)) {
                    toast(t('files_type'), 'danger');
                    continue;
                }
                const data = f.type === 'application/pdf'
                    ? await new Promise((ok, bad) => { const r = new FileReader(); r.onload = () => ok(r.result); r.onerror = bad; r.readAsDataURL(f); })
                    : await shrinkImage(f, 1600, 0.85);
                await api.post(`/api/files/${where(doc, nbr)}`, { name: f.name, data });
            }
        }
        document.addEventListener('change', async (e) => {
            const input = e.target.closest?.('.phai-files input[type=file]');
            if (!input) return;
            const box = input.closest('[data-files]');
            const list = [...input.files];
            input.value = '';
            try {
                await send(box.dataset.files, box.dataset.nbr, list);
            } catch (_) { /* the toast says why */ }
            load(box, true);
        });
        document.addEventListener('click', async (e) => {
            const b = e.target.closest?.('.phai-files [data-file]');
            if (!b || !(await confirmBox(t('files_remove')))) return;
            const box = b.closest('[data-files]');
            try {
                await api.del(`/api/files/${where(box.dataset.files, box.dataset.nbr)}/${b.dataset.file}`);
            } catch (_) {
                return;
            }
            load(box, true);
        });

        /** The paperclip of a row; n = its number of files when the page knows it, otherwise mark() sets it. */
        const button = (doc, nbr, title = nbr, n = 0) => `<button type="button" class="phai-act-btn phai-files-btn${n ? ' has-files' : ''}" data-files-open="${esc(doc)}" data-nbr="${esc(nbr)}" data-title="${esc(title)}"`
            + ` data-count="${n || ''}" title="${esc(n ? t('files_count', { n }) : t('files'))}" aria-label="${esc(t('files'))}"><i class="ti ti-paperclip"></i></button>`;
        function count(b, n) {
            b.classList.toggle('has-files', n > 0);
            b.dataset.count = n || '';
            b.title = n ? t('files_count', { n }) : t('files');
        }
        /** The number of files on every paperclip under root: one request per kind of document. */
        async function mark(root) {
            const byDoc = {};
            root.querySelectorAll('[data-files-open]').forEach((b) => (byDoc[b.dataset.filesOpen] ||= []).push(b));
            for (const [doc, list] of Object.entries(byDoc)) {
                let n;
                try {
                    n = await api.get(`/api/files/${doc}`, { nbrs: [...new Set(list.map((b) => b.dataset.nbr))].join(',') });
                } catch (_) {
                    continue;
                }
                list.forEach((b) => count(b, n[b.dataset.nbr] || 0));
            }
        }

        // one dialog for every paperclip: the document's files, with แนบไฟล์ and × as its rights allow
        let dialog = null;
        let opener = null;
        function open(doc, nbr, title, from = null) {
            if (!dialog) {
                dialog = document.createElement('div');
                dialog.className = 'modal';
                dialog.tabIndex = -1;
                dialog.innerHTML = `<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${esc(t('close'))}"></button></div>
                    <div class="modal-body"><div data-files="" data-nbr="" data-bare="1"></div></div></div></div>`;
                document.body.append(dialog);
                // what is added or removed shows on the row's paperclip at once (a confirm box may hide this dialog meanwhile)
                dialog.addEventListener('phai:files', (e) => {
                    if (opener?.isConnected) count(opener, e.detail.count);
                    if (dialog.classList.contains('show') && !dialog.contains(document.activeElement)) dialog.focus(); // Esc still closes it after a removal
                });
            }
            opener = from;
            const box = dialog.querySelector('[data-files]');
            box.dataset.files = doc;
            box.dataset.nbr = nbr;
            dialog.querySelector('.modal-title').innerHTML = `<i class="ti ti-paperclip me-1"></i>${esc(t('files'))} · ${esc(title || nbr)}`;
            load(box, true);
            bs.Modal.getOrCreateInstance(dialog).show();
        }
        document.addEventListener('click', (e) => {
            const b = e.target.closest?.('[data-files-open]');
            if (!b) return;
            e.preventDefault();
            e.stopPropagation(); // not the row's own click (open the document)
            open(b.dataset.filesOpen, b.dataset.nbr, b.dataset.title, b);
        }, true);

        const scan = (root) => root.querySelectorAll?.('[data-files]').forEach((b) => load(b));
        new MutationObserver((ms) => ms.forEach((m) => {
            if (m.type === 'attributes') {
                if (m.target.matches('[data-files]')) load(m.target);
                return;
            }
            m.addedNodes.forEach((n) => {
                if (n.nodeType !== 1) return;
                if (n.matches('[data-files]')) load(n);
                scan(n);
            });
        })).observe(document.documentElement, { attributes: true, attributeFilter: ['data-nbr'], childList: true, subtree: true });
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => scan(document)); else scan(document);
        return { load: (box) => load(box, true), send, button, mark, open };
    })();

    /**
     * A "ลายเซ็น" button right after a document's print link: the same page as HTML (?html=1), where its signature
     * boxes are signed (System\Signature, js/doc-sign.js). It follows the link's href and hidden as the page sets them.
     */
    function signButton(print) {
        if (!print || print.nextElementSibling?.hasAttribute('data-sign-for')) return;
        const a = document.createElement('a');
        a.className = 'btn btn-sm';
        a.target = '_blank';
        a.rel = 'noopener';
        a.setAttribute('data-sign-for', '');
        a.innerHTML = `<i class="ti ti-writing-sign me-1"></i>${esc(t('sign_doc'))}`;
        const sync = () => {
            const href = print.getAttribute('href') || '';
            a.href = href.split('?')[0] + '?html=1';
            a.hidden = print.hidden || !href || href === '#';
        };
        new MutationObserver(sync).observe(print, { attributes: true, attributeFilter: ['href', 'hidden'] });
        sync();
        print.after(a);
    }

    window.Phai = { viewFields, pick, api, ApiError, toast, confirm: confirmBox, busy, form, grid, geo, badge, stockShort, cancelButtons, esc, t, url, shrinkImage, month, files, signButton };
})();
