/**
 * Signing on a printed document's page (views/doc/print.php opened with ?html=1): under each of our boxes, what this
 * user may do there (System\Signature::boxes: can.sign / request / reject / withdraw / cancel). Nothing here prints.
 * After any change the page loads again, so what shows is what the server keeps.
 */
(function () {
    'use strict';

    const docEl = document.querySelector('.doc[data-sign-path]');
    const dataEl = document.getElementById('doc-sign-data');
    if (!docEl || !dataEl) return;
    const path = docEl.dataset.signPath;
    const { boxes, text: tx } = JSON.parse(dataEl.textContent);
    let mine = JSON.parse(dataEl.textContent).mine;
    const esc = Phai.esc;
    const fmt = (s, v) => s.replace(/:(\w+)/g, (m, k) => (k in v ? v[k] : m));
    const Modal = (window.bootstrap || window.tabler).Modal;

    const style = document.createElement('style');
    style.textContent = `
        .doc-sign-tools { margin-top: 4px; display: flex; flex-wrap: wrap; gap: 4px; justify-content: center; }
        .doc-sign-tools .btn { font-size: 10.5px; padding: 1px 7px; }
        .doc-sign-state { font-size: 10px; color: #6b7380; margin-top: 3px; }
        .doc-sign-state.bad { color: #b42318; }
        .doc-sign[data-slot] { border-radius: 6px; }
        @media screen { .doc-sign[data-slot]:hover { background: #f5faf7; } }
        @media print { .doc-sign-tools, .doc-sign-state { display: none !important; } }`;
    document.head.appendChild(style);

    async function act(url, body, btn) {
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post(url, { path, ...body });
            return res;
        } finally {
            Phai.busy(btn, false);
        }
    }

    /** A small dialog: the body HTML and an OK button; resolves with the dialog's form, or null when closed. */
    function dialog(title, body, ok, danger = false) {
        return new Promise((resolve) => {
            const el = document.createElement('div');
            el.className = 'modal';
            el.tabIndex = -1;
            el.innerHTML = `<div class="modal-dialog modal-dialog-centered"><form class="modal-content" novalidate>
                <div class="modal-header"><h5 class="modal-title">${esc(title)}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">${body}</div>
                <div class="modal-footer"><button type="button" class="btn btn-sm" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>${esc(Phai.t('cancel'))}</button>
                <button type="submit" class="btn btn-sm ${danger ? 'btn-danger' : 'btn-primary'}"><i class="ti ti-check me-1"></i>${esc(ok)}</button></div>
            </form></div>`;
            document.body.appendChild(el);
            const modal = Modal.getOrCreateInstance(el);
            let done = false;
            el.querySelector('form').addEventListener('submit', (e) => {
                e.preventDefault();
                done = true;
                modal.hide();
                resolve(el.querySelector('form'));
            });
            el.addEventListener('hidden.bs.modal', () => { if (!done) resolve(null); setTimeout(() => el.remove(), 0); });
            modal.show();
        });
    }

    /** No signature yet: set one now, then sign. */
    function setMine() {
        return new Promise((resolve) => {
            PhaiSign.open({
                text: tx, w: 600, h: 200,
                save: async (png) => { mine = (await Phai.api.put('/api/me/sign', { image: png })).image; resolve(true); },
            });
        });
    }

    async function sign(b, btn) {
        if (!mine && !(await setMine())) return;
        const ok = await dialog(tx.sign, `<p class="mb-2">${esc(fmt(tx.sign_confirm, { box: b.label }))}</p>
            <div class="text-center"><img src="${esc(mine)}" alt="" style="max-height: 60px; max-width: 100%"></div>`, tx.sign);
        if (!ok) return;
        await act('/api/signatures/sign', { slot: b.slot }, btn);
        location.reload();
    }

    async function request(b, btn) {
        const people = await Phai.api.get('/api/signatures/people?path=' + encodeURIComponent(path));
        const options = people.map((p) => `<option value="${esc(p.us_userid)}">${esc(p.us_name)}${p.role_desc ? ' · ' + esc(p.role_desc) : ''}${Number(p.has_sign) ? '' : ' ' + esc(tx.no_sign_yet)}</option>`).join('');
        const form = await dialog(fmt(tx.request_title, { box: b.label }), `
            <label class="form-label">${esc(tx.request_who)}</label>
            <select class="form-select form-select-sm mb-2" name="userid">${options}</select>
            <label class="form-label">${esc(tx.request_note)}</label>
            <textarea class="form-control form-control-sm" name="note" rows="2" maxlength="255"></textarea>`, tx.request_send);
        if (!form) return;
        const res = await act('/api/signatures/request', { slot: b.slot, userid: form.elements.userid.value, note: form.elements.note.value }, btn);
        Phai.toast(res.mailed ? tx.requested_mail : tx.requested);
        setTimeout(() => location.reload(), 700);
    }

    async function reject(b, btn) {
        const form = await dialog(fmt(tx.reject_title, { box: b.label }), `
            <label class="form-label">${esc(tx.reject_reason)} <span class="text-danger">*</span></label>
            <textarea class="form-control form-control-sm" name="note" rows="2" maxlength="255" required></textarea>`, tx.reject, true);
        if (!form) return;
        await act('/api/signatures/reject', { slot: b.slot, note: form.elements.note.value }, btn);
        location.reload();
    }

    async function withdraw(b, btn, own) {
        const ok = await dialog(own ? tx.withdraw : tx.cancel_request, `<p class="mb-0">${esc(own ? fmt(tx.withdraw_confirm, { box: b.label }) : tx.cancel_request + ' ?')}</p>`,
            own ? tx.withdraw : tx.cancel_request, true);
        if (!ok) return;
        await act('/api/signatures/withdraw', { slot: b.slot }, btn);
        location.reload();
    }

    for (const b of boxes) {
        const el = docEl.querySelector(`.doc-sign[data-slot="${b.slot}"]`);
        if (!el) continue;
        // what is on the box now, when the paper does not show it
        let state = '';
        let bad = false;
        if (b.status === 'R') state = fmt(tx.waiting_for, { name: b.name || b.userid });
        else if (b.status === 'X') { state = fmt(tx.rejected_by, { name: b.name || b.userid, note: b.note || '' }); bad = true; }
        else if (b.stale) { state = tx.stale; bad = true; }
        else if (!b.signed && b.approve && !b.can?.sign) state = tx.approve_only;
        if (state) el.insertAdjacentHTML('beforeend', `<div class="doc-sign-state no-print${bad ? ' bad' : ''}">${esc(state)}</div>`);

        const tools = document.createElement('div');
        tools.className = 'doc-sign-tools no-print';
        const add = (label, cls, fn) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `btn btn-sm ${cls}`;
            btn.textContent = label;
            btn.addEventListener('click', () => fn(b, btn).catch(() => {})); // Phai.api shows why
            tools.appendChild(btn);
        };
        const can = b.can || {};
        if (can.sign) add(tx.sign, 'btn-primary', sign);
        if (can.request) add(tx.request, '', request);
        if (can.reject) add(tx.reject, 'btn-ghost-danger', reject);
        if (can.withdraw) add(tx.withdraw, 'btn-ghost-secondary', (x, btn) => withdraw(x, btn, true));
        if (can.cancel) add(tx.cancel_request, 'btn-ghost-secondary', (x, btn) => withdraw(x, btn, false));
        if (tools.children.length) el.appendChild(tools);
    }
})();
