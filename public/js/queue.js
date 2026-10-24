/**
 * Work queues above a page (views/partials/queue.php): what the step before left for this page.
 * Each row opens the next document filled in (action), or several ticked rows go together (multi).
 */
(function () {
    'use strict';

    const fill = (tpl, row) => String(tpl).replace(/\{(\w+)\}/g, (m, k) => encodeURIComponent(row[k] ?? ''));
    const date = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '');
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 5 });

    function cell(c, r) {
        const v = r[c.key];
        if (c.type === 'badge') return `<td>${v ? `<span class="badge bg-${Phai.esc(r[c.key + '_color'] || 'secondary')}-lt">${Phai.esc(v)}</span>` : ''}</td>`;
        const text = c.type === 'date' ? date(v) : c.type === 'money' ? money(v) : c.type === 'num' ? num(v) : (v ?? '');
        const html = c.link && v ? `<a href="${Phai.esc(Phai.url(fill(c.link, r)))}">${Phai.esc(text)}</a>` : Phai.esc(text);
        return `<td${c.type === 'money' || c.type === 'num' ? ' class="num"' : ''}>${html}</td>`;
    }

    /** A document of the queue in a large window, to read only: its own page without menu, top bar or buttons (?embed=1). */
    function view(href, title) {
        let m = document.getElementById('phai-view');
        if (!m) {
            document.body.insertAdjacentHTML('beforeend', `
                <div class="modal" id="phai-view" tabindex="-1">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header py-2">
                                <h3 class="modal-title" data-title></h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-0"><iframe title="" class="phai-view-frame"></iframe></div>
                        </div>
                    </div>
                </div>`);
            m = document.getElementById('phai-view');
            m.addEventListener('hidden.bs.modal', () => { m.querySelector('iframe').src = 'about:blank'; });
        }
        m.querySelector('[data-title]').textContent = title;
        m.querySelector('iframe').src = href + (href.includes('?') ? '&' : '?') + 'embed=1';
        (window.bootstrap || window.tabler).Modal.getOrCreateInstance(m).show(); // Tabler's bundle exposes it as tabler
    }

    async function load(box) {
        const q = JSON.parse(box.dataset.queue);
        let rows = [];
        try {
            rows = await Phai.api.get(q.api, q.params || {});
        } catch (_) {
            return; // a queue that cannot load stays hidden; the page itself still works
        }
        rows = Array.isArray(rows) ? rows : (rows.rows || []);
        box.hidden = !rows.length;
        box.querySelector('[data-count]').textContent = rows.length;
        box.querySelector('tbody').innerHTML = rows.map((r, i) => `
            <tr data-i="${i}"${q.urgent && r[q.urgent] ? ' class="text-danger"' : ''}>
                <td class="phai-seq">${i + 1}</td>
                ${q.multi ? `<td><input type="checkbox" class="form-check-input m-0" data-pick-row="${i}"></td>` : ''}
                ${q.columns.map((c) => cell(c, r)).join('')}
                ${q.action ? `<td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-ghost-primary py-0" data-act="${i}">
                    <i class="${Phai.esc(q.action.icon || 'ti ti-arrow-right')} me-1"></i>${Phai.esc(q.action.label)}</button></td>` : ''}
            </tr>`).join('');

        const viewCol = q.columns.find((c) => c.link);
        box.querySelector('tbody').onclick = async (e) => {
            const btn = e.target.closest('[data-act]');
            if (!btn) {
                // a click on the row (or its number) opens its document in a window; Ctrl/⌘-click keeps the page link
                const tr = e.target.closest('tr[data-i]');
                if (!tr || !viewCol || e.target.closest('input, button') || e.ctrlKey || e.metaKey || e.shiftKey) return;
                const r = rows[tr.dataset.i];
                if (!r[viewCol.key]) return;
                e.preventDefault();
                view(Phai.url(fill(viewCol.link, r)), r[viewCol.key]);
                return;
            }
            const r = rows[btn.dataset.act];
            const a = q.action;
            if (a.href) {
                location.href = Phai.url(fill(a.href, r));
                return;
            }
            if (a.confirm && !(await Phai.confirm(a.confirm))) return;
            const body = Object.fromEntries(Object.entries(a.body || {}).map(([k, v]) => [k, String(v).replace(/\{(\w+)\}/g, (m, f) => r[f] ?? '')]));
            Phai.busy(btn, true);
            try {
                const res = await Phai.api.post(a.post, body);
                if (a.open) location.href = Phai.url(fill(a.open, res || {}));
                else location.reload();
            } catch (err) {
                // a refusal about a field of the document (e.g. no vendor) has no form here: say it
                if (err.fields) Phai.toast(Object.values(err.fields)[0] || err.message, 'danger');
                Phai.busy(btn, false);
            }
        };

        const multiBtn = box.querySelector('[data-multi]');
        if (!q.multi || !multiBtn) return;
        const picked = () => [...box.querySelectorAll('[data-pick-row]:checked')].map((x) => rows[x.dataset.pickRow]);
        box.querySelector('tbody').onchange = (e) => {
            if (!e.target.matches('[data-pick-row]')) return;
            const sel = picked();
            // ticked rows go on one document: they must share the same party
            if (q.multi.same && sel.length > 1 && sel.some((r) => r[q.multi.same] !== sel[0][q.multi.same])) {
                e.target.checked = false;
                Phai.toast(q.multi.sameText || Phai.t('queue_same'), 'error');
            }
            multiBtn.disabled = !picked().length;
        };
        multiBtn.onclick = async () => {
            const sel = picked();
            if (!sel.length) return;
            if (q.multi.post) {
                // the ticked rows' keys go in one request, e.g. planned purchases → one requisition
                Phai.busy(multiBtn, true);
                try {
                    const res = await Phai.api.post(q.multi.post, { keys: sel.map((r) => r[q.key]) });
                    if (q.multi.open) location.href = Phai.url(fill(q.multi.open, res || {}));
                    else location.reload();
                } catch (err) {
                    if (err.fields) Phai.toast(Object.values(err.fields)[0] || err.message, 'danger');
                    Phai.busy(multiBtn, false);
                }
                return;
            }
            const keys = sel.map((r) => r[q.key]).join(',');
            location.href = Phai.url(fill(q.multi.href.replace('{keys}', encodeURIComponent(keys)), sel[0]));
        };
    }

    document.querySelectorAll('[data-queue]').forEach(load);
})();
