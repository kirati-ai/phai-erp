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
        body.innerHTML = rows.length ? rows.map(row).join('') : `<tr><td colspan="${cols}" class="text-secondary">${esc(empty)}</td></tr>`;
        const count = document.querySelector(`[data-count="${id}"]`);
        if (count) count.textContent = rows.length || '';
    }

    Phai.api.get('/api/envelopes').then((d) => {
        const add = document.getElementById('es-new');
        add.hidden = !d.canSend;
        fill('es-waiting', d.waiting, (r) => `<tr>
            <td>${link(`/envelopes/${r.env_nbr}/sign`, r.env_nbr)}</td><td>${link(`/envelopes/${r.env_nbr}/sign`, r.env_title)}</td>
            <td>${esc(r.sender || '')}</td><td>${esc(T['role_' + r.envs_role])}</td>
            <td class="text-nowrap">${esc(short(r.envs_recv_at))}</td><td class="text-nowrap">${r.envs_open_at ? esc(short(r.envs_open_at)) : `<span class="badge bg-orange-lt">${esc(T.unopened)}</span>`}</td></tr>`, T.none_waiting);
        fill('es-sent', d.sent, (r) => {
            const href = r.env_status === 'D' ? `/envelopes/${r.env_nbr}/edit` : `/envelopes/${r.env_nbr}`;
            const [done, n] = r.progress;
            return `<tr><td>${link(href, r.env_nbr)}</td><td>${link(href, r.env_title)}</td><td>${badge(r.env_status)}</td>
                <td>${n ? `${done} / ${n}` : ''}</td><td class="text-nowrap">${esc(short(r.env_sent_at))}</td><td class="text-nowrap">${esc(short(r.env_done_at))}</td></tr>`;
        }, T.none_sent);
        fill('es-done', d.done, (r) => `<tr><td>${link(`/envelopes/${r.env_nbr}`, r.env_nbr)}</td><td>${link(`/envelopes/${r.env_nbr}`, r.env_title)}</td>
            <td>${esc(r.sender || '')}</td><td class="text-nowrap">${esc(short(r.env_done_at))}</td></tr>`, T.none_done);
        if (!d.waiting.length && (d.sent.length || d.done.length)) {
            const tab = document.querySelector(`a[href="#${d.sent.length ? 'es-sent' : 'es-done'}"]`);
            const bs = window.bootstrap || window.tabler;
            if (tab && bs?.Tab) bs.Tab.getOrCreateInstance(tab).show();
        }
    });

    document.getElementById('es-new').addEventListener('click', async () => {
        const title = window.prompt(T.new_title, '');
        if (title === null || !title.trim()) return;
        try {
            const r = await Phai.api.post('/api/envelopes', { env_title: title.trim() });
            location.href = `${base}/envelopes/${r.nbr}/edit`;
        } catch (e) {
            if (e.fields) Phai.toast(Object.values(e.fields)[0], 'danger');
        }
    });
})();
