/** เอกสารรอเซ็น (views/system/signatures.php): each row opens the document's page, where it is signed. */
(function () {
    'use strict';

    const text = JSON.parse(document.getElementById('sig-text').textContent);
    const esc = Phai.esc;
    const color = { R: 'orange', S: 'green', X: 'red' };
    const when = (v) => (v ? v.slice(0, 10).split('-').reverse().join('/') + ' ' + v.slice(11, 16) : '');
    const open = (r) => `<a href="${esc(Phai.url(r.sig_path) + '?html=1')}" target="_blank" rel="noopener">${esc(r.sig_title)}</a>`;
    const empty = (msg) => `<tr><td colspan="5" class="text-secondary">${esc(msg)}</td></tr>`;

    Phai.api.get('/api/signatures').then((d) => {
        document.getElementById('sig-waiting').innerHTML = d.waiting.length ? d.waiting.map((r) => `<tr>
            <td>${open(r)}</td><td>${esc(r.sig_label)}</td><td>${esc(r.asker || r.sig_req_by || '')}</td>
            <td class="text-nowrap">${esc(when(r.created_at))}</td><td class="small">${esc(r.sig_note || '')}</td></tr>`).join('') : empty(text.none_waiting);
        document.getElementById('sig-asked').innerHTML = d.asked.length ? d.asked.map((r) => `<tr>
            <td>${open(r)}</td><td>${esc(r.sig_label)}</td><td>${esc(r.signer || r.sig_userid)}</td>
            <td><span class="badge bg-${color[r.sig_status]}-lt">${esc(text.status[r.sig_status])}</span>${r.sig_status === 'X' && r.sig_note ? ` <span class="small">${esc(r.sig_note)}</span>` : ''}</td>
            <td class="text-nowrap">${esc(when(r.sig_at || r.created_at))}</td></tr>`).join('') : empty(text.none_asked);
    });
})();
