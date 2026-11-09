/**
 * หน้าซองเอกสาร (views/esign/view.php): where the envelope stands. Signers by step with the times they received,
 * opened and signed; the events, with a warning if their hash chain is broken; the files; and for the sender:
 * remind, recall, or put someone else in place of a signer who has not signed. A finished envelope shows its final
 * document.
 */
(function () {
    'use strict';

    const T = JSON.parse(document.getElementById('esign-text').textContent);
    let D = JSON.parse(document.getElementById('esign-data').textContent);
    const esc = Phai.esc;
    const $ = (id) => document.getElementById(id);
    const base = (document.querySelector('meta[name="phai-base"]')?.content || '').replace(/\/$/, '');
    const tr = (s, p = {}) => Object.entries(p).reduce((a, [k, v]) => a.replace(':' + k, v), s || '');
    const nbr = D.env.env_nbr;
    const color = { D: 'secondary', S: 'orange', C: 'green', X: 'red', W: 'secondary' };
    const scolor = { W: 'secondary', R: 'orange', S: 'green', X: 'red', C: 'secondary' };
    let people = null;

    function render() {
        const e = D.env;
        $('es-nbr').textContent = nbr;
        $('es-status').className = `badge bg-${color[e.env_status]}-lt`;
        $('es-status').textContent = T['estatus_' + e.env_status] + (e.env_status === 'S' ? ' · ' + tr(T.step_n, { n: e.env_step }) : '');
        $('es-sign').hidden = !D.can.sign;
        $('es-sign').href = `${base}/envelopes/${nbr}/sign`;
        $('es-final').hidden = !D.can.final;
        $('es-final').href = `${base}/envelopes/${nbr}/final`;
        $('es-download').hidden = !D.can.final;
        $('es-download').href = `${base}/envelopes/${nbr}/final?download=1`;
        $('es-remind').hidden = !D.can.manage;
        $('es-recall').hidden = !D.can.manage;
        $('es-final-card').hidden = !D.can.final;
        if (D.can.final && !$('es-final-frame').src) $('es-final-frame').src = `${base}/envelopes/${nbr}/final`;

        $('es-signers').innerHTML = D.signers.map((s) => `<tr${s.envs_status === 'C' ? ' class="text-secondary"' : ''}>
            <td>${s.envs_role === 'C' ? '' : s.envs_step}</td>
            <td><b>${esc(s.envs_name)}</b>${s.envs_position ? `<div class="small text-secondary">${esc(s.envs_position)}</div>` : ''}</td>
            <td>${esc(T['role_' + s.envs_role])}</td>
            <td><span class="badge bg-${scolor[s.envs_status]}-lt">${esc(T['sstatus_' + s.envs_status])}</span>${s.envs_note ? `<div class="small">${esc(s.envs_note)}</div>` : ''}</td>
            <td class="small">${esc(s.times || '')}</td>
            <td class="text-end">${D.can.manage && ['W', 'R'].includes(s.envs_status) && s.envs_role !== 'C'
                ? `<button type="button" class="btn btn-sm btn-ghost-secondary" data-replace="${s.envs_id}" title="${esc(T.replace)}"><i class="ti ti-user-share"></i></button>` : ''}</td>
        </tr>`).join('');

        let k = 0;
        $('es-files').innerHTML = D.files.map((f) => `<div class="es-file"><i class="ti ${f.envf_kind === 'M' ? 'ti-file-text' : 'ti-paperclip'}"></i>
            <a class="es-name" href="${esc(base + '/envelopes/' + nbr + '/files/' + f.envf_id)}" target="_blank" rel="noopener">${esc(f.envf_name)}</a>
            <span class="small text-secondary text-nowrap">${esc(f.envf_kind === 'M' ? T.kind_M : tr(T.kind_A_n, { k: ++k }))} · ${f.envf_pages} ${esc(T.pages_short)}</span></div>`).join('');
        $('es-hash').innerHTML = e.env_final_hash
            ? `${esc(tr(T.final_summary, { pages: e.env_pages, at: D.done }))}<div class="es-hash mt-1">SHA-256 ${esc(e.env_final_hash)}</div>
               <a class="small" href="${esc(base + '/envelopes/verify?n=' + nbr)}"><i class="ti ti-shield-check me-1"></i>${esc(T.verify)}</a>` : '';

        $('es-chain').innerHTML = D.chainBroken === null
            ? `<span class="badge bg-green-lt" title="${esc(T.chain_ok_hint)}"><i class="ti ti-link me-1"></i>${esc(T.chain_ok)}</span>`
            : `<span class="badge bg-red-lt"><i class="ti ti-unlink me-1"></i>${esc(T.chain_broken)}</span>`;
        $('es-events').innerHTML = (D.events || []).map((h) => `<li><span class="text-secondary">${esc(h.at)}</span><span>${esc(T['ev_' + h.event] || h.event)}${h.who ? ` · <b>${esc(h.who)}</b>` : ''}${h.detail?.reason ? ` · ${esc(h.detail.reason)}` : ''}</span></li>`).join('');
    }

    async function replace(id) {
        people ??= await Phai.api.get('/api/envelopes/people');
        const s = D.signers.find((x) => String(x.envs_id) === String(id));
        const list = people.filter((p) => p.us_userid !== s.envs_userid);
        const pick = window.prompt(tr(T.replace_prompt, { name: s.envs_name }) + '\n' + list.map((p) => `${p.us_userid} — ${p.us_name}`).join('\n'), '');
        if (!pick) return;
        const userid = pick.trim().split(/\s/)[0];
        try {
            D = await Phai.api.post(`/api/envelopes/${nbr}/replace`, { signer: id, userid });
            render();
            Phai.toast(T.replaced);
        } catch (e) {
            if (e.fields) Phai.toast(Object.values(e.fields)[0], 'danger');
        }
    }

    $('es-signers').addEventListener('click', (e) => {
        const b = e.target.closest('[data-replace]');
        if (b) replace(b.dataset.replace);
    });
    $('es-remind').addEventListener('click', async () => {
        const r = await Phai.api.post(`/api/envelopes/${nbr}/remind`);
        D = r;
        render();
        Phai.toast(r.mailed ? tr(T.reminded_n, { n: r.mailed }) : T.reminded_none, r.mailed ? 'success' : 'warning');
    });
    $('es-recall').addEventListener('click', async () => {
        if (!(await Phai.confirm(T.recall_confirm))) return;
        D = await Phai.api.post(`/api/envelopes/${nbr}/recall`);
        render();
    });
    render();
})();
