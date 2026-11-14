/** ซองเอกสาร (views/esign/list.php): waiting for me, sent by me, finished; a row opens the envelope (or its signing page). */
(function () {
    'use strict';

    const T = JSON.parse(document.getElementById('esign-text').textContent);
    const esc = Phai.esc;
    const base = (document.querySelector('meta[name="phai-base"]')?.content || '').replace(/\/$/, '');
    const color = { D: 'secondary', S: 'orange', C: 'green', X: 'red', W: 'secondary' };
    const badge = (s) => `<span class="badge bg-${color[s]}-lt">${esc(T['estatus_' + s])}</span>`;
    const short = (v) => (v ? v.slice(0, 10).split('-').reverse().join('/') + ' ' + v.slice(11, 16) : '');
    const link = (href, text) => `<a href="${esc(base + href)}">${esc(text)}</a>`;

    function fill(id, rows, row, empty) {
        const body = document.querySelector(`#${id} tbody`);
        const cols = document.querySelectorAll(`#${id} thead th`).length;
        body.innerHTML = rows.length ? rows.map(row).join('') : `<tr><td colspan="${cols}" class="text-center text-secondary py-3">${esc(empty)}</td></tr>`;
        const count = document.querySelector(`[data-count="${id}"]`);
        if (count) count.textContent = rows.length || '';
    }

    // Envelopes their sender may delete: draft, withdrawn, declined (Envelope::DELETABLE). One being signed is
    // withdrawn first; a finished one is evidence and has no delete button.
    const DELETABLE = ['D', 'W', 'X'];
    const icon = (attr, i, title, extra = '') => `<button type="button" class="btn btn-sm btn-ghost-${extra || 'secondary'} px-1" ${attr} title="${esc(title)}" aria-label="${esc(title)}"><i class="ti ti-${i}"></i></button>`;

    function load(keepTab = false) {
        return Phai.api.get('/api/envelopes').then((d) => {
            const add = document.getElementById('es-new');
            add.hidden = !d.canSend;
            document.getElementById('es-remote').hidden = !d.admin;
            // every row opens on a click anywhere in it (data-href); the links stay for the keyboard and a new tab
            fill('es-waiting', d.waiting, (r) => `<tr data-href="/envelopes/${esc(r.env_nbr)}/sign">
                <td>${link(`/envelopes/${r.env_nbr}/sign`, r.env_nbr)}</td><td>${esc(r.env_title)}</td>
                <td>${esc(r.sender || '')}</td><td>${esc(T['role_' + r.envs_role])}</td>
                <td class="text-nowrap">${esc(short(r.envs_recv_at))}</td><td class="text-nowrap">${r.envs_open_at ? esc(short(r.envs_open_at)) : `<span class="badge bg-orange-lt">${esc(T.unopened)}</span>`}</td></tr>`, T.none_waiting);
            fill('es-sent', d.sent, (r) => {
                const draft = r.env_status === 'D';
                const href = draft ? `/envelopes/${r.env_nbr}/edit` : `/envelopes/${r.env_nbr}`;
                const [done, n] = r.progress;
                // withdrawn or declined: back to a draft and into the editor (the first round's history stays)
                const reopen = r.env_status === 'W' || r.env_status === 'X';
                const acts = (draft ? icon(`data-go="${esc(href)}"`, 'pencil', T.act_edit) : icon(`data-go="${esc(href)}"`, 'eye', T.act_view))
                    + (reopen ? icon(`data-reopen="${esc(r.env_nbr)}"`, 'pencil', T.act_reopen) : '')
                    + (DELETABLE.includes(r.env_status) ? icon(`data-del="${esc(r.env_nbr)}" data-title="${esc(r.env_title)}"`, 'trash', T.act_delete, 'danger') : '');
                return `<tr data-href="${esc(href)}"><td>${link(href, r.env_nbr)}</td><td>${esc(r.env_title)}</td><td>${badge(r.env_status)}</td>
                    <td>${n ? `${done} / ${n}` : ''}</td><td class="text-nowrap">${esc(short(r.env_sent_at))}</td><td class="text-nowrap">${esc(short(r.env_done_at))}</td>
                    <td class="text-end text-nowrap">${acts}</td></tr>`;
            }, T.none_sent);
            fill('es-done', d.done, (r) => `<tr data-href="/envelopes/${esc(r.env_nbr)}"><td>${link(`/envelopes/${r.env_nbr}`, r.env_nbr)}</td><td>${esc(r.env_title)}</td>
                <td>${esc(r.sender || '')}</td><td class="text-nowrap">${esc(short(r.env_done_at))}</td></tr>`, T.none_done);
            if (!keepTab && !d.waiting.length && (d.sent.length || d.done.length)) {
                const tab = document.querySelector(`a[href="#${d.sent.length ? 'es-sent' : 'es-done'}"]`);
                const bs = window.bootstrap || window.tabler;
                if (tab && bs?.Tab) bs.Tab.getOrCreateInstance(tab).show();
            }
        });
    }
    load();

    document.querySelectorAll('.es-rows tbody').forEach((tb) => tb.addEventListener('click', async (e) => {
        const del = e.target.closest('[data-del]');
        if (del) {
            const tr2 = (s, p) => Object.entries(p).reduce((a, [k, v]) => a.replace(':' + k, v), s || '');
            if (!(await Phai.confirm(tr2(T.delete_row_confirm, { nbr: del.dataset.del, title: del.dataset.title })))) return;
            try {
                await Phai.api.del(`/api/envelopes/${encodeURIComponent(del.dataset.del)}`);
                Phai.toast(T.deleted, 'success');
                load(true);
            } catch (_) { /* Phai.api has shown why (e.g. being signed: withdraw it first) */ }
            return;
        }
        const re = e.target.closest('[data-reopen]');
        if (re) {
            const nbr = re.dataset.reopen;
            if (!(await Phai.confirm(T.reopen_confirm.replace(':nbr', nbr), { title: T.act_reopen, ok: T.act_edit, icon: 'pencil' }))) return;
            try {
                await Phai.api.post(`/api/envelopes/${encodeURIComponent(nbr)}/reopen`);
                location.href = `${base}/envelopes/${encodeURIComponent(nbr)}/edit`;
            } catch (_) { /* Phai.api has shown why */ }
            return;
        }
        const go = e.target.closest('[data-go]');
        if (go) {
            location.href = base + go.dataset.go;
            return;
        }
        // a click anywhere else in the row opens it, unless it was on the link itself (which opens anyway)
        const row = e.target.closest('tr[data-href]');
        if (row && !e.target.closest('a')) location.href = base + row.dataset.href;
    }));

    // ---------------------------------------------------------------- Phai Sign (outside signers), ADMIN
    const $ = (id) => document.getElementById(id);
    const tr = (s, p = {}) => Object.entries(p).reduce((a, [k, v]) => a.replace(':' + k, v), s || '');
    let modal = null;

    function showRemote(r) {
        $('es-rm-state').className = 'alert py-2 small ' + (r.registered ? (r.on ? 'alert-success' : 'alert-secondary') : 'alert-warning');
        $('es-rm-state').textContent = r.registered ? tr(T.remote_registered, { name: r.name || '', id: r.server || '' }) + (r.on ? '' : ' · ' + T.remote_not_ready) : T.remote_not_registered;
        $('es-rm-register').hidden = r.registered;
        $('es-rm-settings').hidden = !r.registered;
        $('es-rm-foot').hidden = !r.registered;
        $('es-rm-url1').value = r.url;
        $('es-rm-name').value ||= r.company;
        $('es-rm-contact').value ||= r.email;
        $('es-rm-on').checked = r.on;
        $('es-rm-url').value = r.url;
        $('es-rm-days').value = r.days;
        $('es-rm-witness').checked = r.witness;
        $('es-rm-last').textContent = r.sync ? tr(T.remote_last, { at: r.sync }) : '';
    }
    const fail = (e) => Phai.toast(e.fields ? Object.values(e.fields)[0] : e.message, 'danger');

    $('es-remote').addEventListener('click', async () => {
        showRemote(await Phai.api.get('/api/envelopes/remote'));
        const bs = window.bootstrap || window.tabler;
        modal ??= bs.Modal.getOrCreateInstance($('es-remote-modal'));
        modal.show();
    });
    $('es-rm-do-register').addEventListener('click', async () => {
        try {
            showRemote(await Phai.api.post('/api/envelopes/remote/register', { url: $('es-rm-url1').value, name: $('es-rm-name').value, contact: $('es-rm-contact').value, code: $('es-rm-code').value }));
        } catch (e) {
            fail(e);
        }
    });
    $('es-rm-save').addEventListener('click', async () => {
        try {
            showRemote(await Phai.api.put('/api/envelopes/remote', { on: $('es-rm-on').checked, url: $('es-rm-url').value, days: +$('es-rm-days').value, witness: $('es-rm-witness').checked }));
            Phai.toast(T.remote_saved, 'success');
        } catch (e) {
            fail(e);
        }
    });
    $('es-rm-ping').addEventListener('click', async () => {
        try {
            const r = await Phai.api.post('/api/envelopes/remote/ping');
            Phai.toast(tr(T.remote_ping_ok, { name: r.name }), 'success');
        } catch (e) {
            fail(e);
        }
    });
    $('es-rm-sync').addEventListener('click', async () => {
        try {
            const r = await Phai.api.post('/api/envelopes/sync');
            Phai.toast(tr(T.remote_synced, r), 'success');
            showRemote(await Phai.api.get('/api/envelopes/remote'));
        } catch (e) {
            fail(e);
        }
    });

    // A new envelope starts from the documents to sign: choose them first, then name it (the first file's name is offered),
    // and the editor opens with them already in. Without a file chosen nothing is created.
    const picker = $('es-new-files');
    $('es-new').addEventListener('click', () => picker.click());
    picker.addEventListener('change', async () => {
        const list = [...picker.files];
        picker.value = '';
        if (!list.length) return;
        const title = await Phai.prompt(tr(T.new_files, { names: list.map((f) => f.name).join(', ') }), {
            title: T.new_title, value: list[0].name.replace(/\.[^.]+$/, ''), required: true, maxlength: 255, ok: T.new_create, icon: 'mail-plus',
        });
        if (!title) return;
        const btn = $('es-new');
        Phai.busy(btn, true);
        try {
            const r = await Phai.api.post('/api/envelopes', { env_title: title });
            for (const file of list) {
                const data = await new Promise((resolve, reject) => {
                    const fr = new FileReader();
                    fr.onload = () => resolve(fr.result);
                    fr.onerror = reject;
                    fr.readAsDataURL(file);
                });
                Phai.toast(tr(T.uploading, { name: file.name }), 'info');
                try {
                    await Phai.api.post(`/api/envelopes/${r.nbr}/files`, { kind: 'M', name: file.name, data });
                } catch (e) {
                    // the envelope is there; the editor shows what came in, and a file can be added again from there
                    if (e.fields) Phai.toast(`${file.name}: ${Object.values(e.fields)[0]}`, 'danger');
                }
            }
            location.href = `${base}/envelopes/${r.nbr}/edit`;
        } catch (e) {
            Phai.busy(btn, false);
            if (e.fields) Phai.toast(Object.values(e.fields)[0], 'danger');
        }
    });
})();
