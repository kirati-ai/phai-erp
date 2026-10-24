/**
 * "Send e-Tax" on an invoice or credit note page (doc action with event 'etax'): a small dialog with
 * the buyer's email and the subject the ETDA system expects, then POST /api/etax/{nbr}/send.
 */
(function () {
    'use strict';

    const bs = window.bootstrap || window.tabler;
    const dmyt = (v) => {
        const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(v || '');
        return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : '';
    };
    let el = null;

    function dialog() {
        if (el) return el;
        el = document.createElement('div');
        el.className = 'modal';
        el.tabIndex = -1;
        el.innerHTML = `<div class="modal-dialog modal-dialog-centered"><form class="modal-content" novalidate autocomplete="off">
            <div class="modal-header"><h5 class="modal-title">${Phai.esc(Phai.t('etax_send'))}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="row g-2">
                <div class="col-12"><label class="form-label" for="etax-to">${Phai.esc(Phai.t('etax_to'))}</label>
                    <input type="email" class="form-control form-control-sm" id="etax-to" name="to" required></div>
                <div class="col-12"><label class="form-label">${Phai.esc(Phai.t('etax_cc'))}</label><div class="phai-view-value" data-cc></div></div>
                <div class="col-12"><label class="form-label">${Phai.esc(Phai.t('etax_subject'))}</label><div class="phai-view-value" data-subject></div></div>
                <div class="col-12 small" data-last></div>
            </div></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal">${Phai.esc(Phai.t('cancel'))}</button>
                <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-mail-forward me-1"></i>${Phai.esc(Phai.t('etax_send_btn'))}</button>
            </div></form></div>`;
        document.body.append(el);
        return el;
    }

    document.addEventListener('doc:etax', async (e) => {
        const { nbr } = e.detail;
        let p;
        try {
            p = await Phai.api.get('/api/etax/' + encodeURIComponent(nbr));
        } catch (err) {
            return;
        }
        const box = dialog();
        const f = box.querySelector('form');
        Phai.form.errors(f, null);
        f.to.value = p.to || '';
        box.querySelector('[data-cc]').textContent = p.cc.join(', ') || '–';
        box.querySelector('[data-subject]').textContent = p.subject;
        const last = box.querySelector('[data-last]');
        last.className = 'col-12 small ' + (p.last ? (p.last.etax_status === 'S' ? 'text-success' : 'text-danger') : '');
        last.textContent = p.last
            ? `${Phai.t(p.last.etax_status === 'S' ? 'etax_last_sent' : 'etax_last_failed')} ${dmyt(p.last.created_at)} → ${p.last.etax_to}${p.last.etax_msg ? ' · ' + p.last.etax_msg : ''}`
            : '';
        f.onsubmit = async (ev) => {
            ev.preventDefault();
            const done = await Phai.form.submit(f, 'POST', '/api/etax/' + encodeURIComponent(nbr) + '/send');
            if (!done) return;
            bs.Modal.getOrCreateInstance(box).hide();
            Phai.toast(Phai.t('etax_sent', { to: done.to }));
        };
        box.addEventListener('shown.bs.modal', () => f.to.focus(), { once: true });
        bs.Modal.getOrCreateInstance(box).show();
    });
})();
