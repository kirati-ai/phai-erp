/**
 * ตรวจเอกสาร (views/esign/verify.php): pick a file, and the server says whether it is a signed document unchanged,
 * the original of one, or which pages differ from what was signed. ?n= shows that envelope first (page footer QR).
 */
(function () {
    'use strict';

    const T = JSON.parse(document.getElementById('esign-text').textContent);
    const D = JSON.parse(document.getElementById('esign-data').textContent);
    const esc = Phai.esc;
    const $ = (id) => document.getElementById(id);
    const tr = (s, p = {}) => Object.entries(p).reduce((a, [k, v]) => a.replace(':' + k, v), s || '');
    const scolor = { W: 'secondary', R: 'orange', S: 'green', X: 'red', C: 'secondary' };

    function summary(s) {
        if (!s) return '';
        if (!s.full) {
            return `<div class="card"><div class="card-body small">${esc(tr(T.verify_exists, { nbr: s.nbr, at: s.done, pages: s.pages }))}</div></div>`;
        }
        return `<div class="card"><div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2"><span class="badge bg-secondary-lt">${esc(s.nbr)}</span><b>${esc(s.title)}</b></div>
            <div class="small mb-2">${esc(T['estatus_' + s.status])}${s.done ? ' · ' + esc(s.done) : ''}${s.pages ? ' · ' + esc(tr(T.n_pages, { n: s.pages })) : ''}</div>
            ${s.sha256 ? `<div class="es-hash mb-2">SHA-256 ${esc(s.sha256)}</div>` : ''}
            <table class="table table-sm mb-0">${s.signers.map((x) => `<tr><td>${esc(x.name)}${x.position ? ` <span class="text-secondary small">${esc(x.position)}</span>` : ''}</td>
                <td><span class="badge bg-${scolor[x.status]}-lt">${esc(T['sstatus_' + x.status])}</span></td><td class="small">${esc(x.signed)}</td></tr>`).join('')}</table>
        </div></div>`;
    }

    function result(r) {
        const box = $('es-verify-result');
        const kind = { match: 'success', 'same-pages': 'success', original: 'info', changed: 'danger', unknown: 'warning' }[r.result];
        let text = T['verify_' + r.result.replace('-', '_')];
        if (r.result === 'changed') {
            text += ' ' + (r.count[0] !== r.count[1] ? tr(T.verify_count, { file: r.count[0], signed: r.count[1] }) : '') + ' ' + tr(T.verify_pages, { pages: r.pages.join(', ') });
        }
        if (r.result === 'original') text = tr(text, { name: r.name, nbr: r.nbr });
        box.innerHTML = `<div class="alert alert-${kind} mb-2">${esc(tr(text, { nbr: r.nbr || '' }))}</div><div class="es-hash small text-secondary">SHA-256 ${esc(r.sha256)}</div>`;
        $('es-summary').innerHTML = summary(r.summary);
    }

    $('es-verify-file').addEventListener('change', (e) => {
        const file = e.target.files[0];
        e.target.value = '';
        if (!file) return;
        const rd = new FileReader();
        rd.onload = async () => {
            $('es-verify-result').innerHTML = `<div class="small text-secondary">${esc(T.checking)}</div>`;
            try {
                result(await Phai.api.post('/api/envelopes/verify', { data: rd.result, nbr: D.nbr || '' }));
            } catch (err) {
                $('es-verify-result').innerHTML = '';
                if (err.fields) Phai.toast(Object.values(err.fields)[0], 'danger');
            }
        };
        rd.readAsDataURL(file);
    });
    $('es-summary').innerHTML = D.nbr && !D.summary ? `<div class="alert alert-warning">${esc(tr(T.verify_no_envelope, { nbr: D.nbr }))}</div>` : summary(D.summary);
})();
