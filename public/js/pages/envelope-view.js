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

    /** Where an outsider's turn stands at Phai Sign: not sent yet, sent, link expired or locked, or why sending failed. */
    function outside(s) {
        if (s.envs_kind !== 'E' || s.envs_status !== 'R') return '';
        const text = s.envs_ext_err ? tr(T.ext_err, { error: s.envs_ext_err }) : !s.envs_ext ? T.ext_wait : T['ext_' + (s.envs_ext_state || 'R')] || T.ext_R;
        const warn = s.envs_ext_err || ['E', 'L'].includes(s.envs_ext_state);
        return `<div class="small ${warn ? 'text-danger' : 'text-secondary'}"><i class="ti ti-world-share me-1"></i>${esc(text)}</div>`;
    }

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
        $('es-reopen').hidden = !D.can.reopen;
        $('es-final-card').hidden = !D.can.final;
        if (D.can.final && !$('es-final-frame').src) $('es-final-frame').src = `${base}/envelopes/${nbr}/final`;

        $('es-signers').innerHTML = D.signers.map((s) => `<tr data-signer="${s.envs_id}"${s.envs_status === 'C' ? ' class="text-secondary"' : ''}>
            <td>${s.envs_role === 'C' ? '' : s.envs_step}</td>
            <td><b>${esc(s.envs_name)}</b>${s.envs_kind === 'E' ? ` <span class="badge bg-azure-lt">${esc(T.ev_outside)}</span><div class="small text-secondary">${esc(s.envs_email || '')}</div>` : ''}${s.envs_position ? `<div class="small text-secondary">${esc(s.envs_position)}</div>` : ''}</td>
            <td>${esc(T['role_' + s.envs_role])}</td>
            <td><span class="badge bg-${scolor[s.envs_status]}-lt">${esc(T['sstatus_' + s.envs_status])}</span>${s.envs_note ? `<div class="small">${esc(s.envs_note)}</div>` : ''}${outside(s)}</td>
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
               ${e.env_witness_id ? `<div class="small text-success mt-1"><i class="ti ti-certificate me-1"></i>${esc(tr(T.witness_at, { at: D.witness, id: e.env_witness_id }))}</div>` : ''}
               <a class="small" href="${esc(base + '/envelopes/verify?n=' + nbr)}"><i class="ti ti-shield-check me-1"></i>${esc(T.verify)}</a>` : '';

        $('es-chain').innerHTML = D.chainBroken === null
            ? `<span class="badge bg-green-lt" title="${esc(T.chain_ok_hint)}"><i class="ti ti-link me-1"></i>${esc(T.chain_ok)}</span>`
            : `<span class="badge bg-red-lt"><i class="ti ti-unlink me-1"></i>${esc(T.chain_broken)}</span>`;
        $('es-events').innerHTML = (D.events || []).map((h) => `<li${h.signer ? ` data-signer="${h.signer}" data-who="${esc(h.who || '')}"` : ''}><span class="text-secondary">${esc(h.at)}</span><span>${esc(T['ev_' + h.event] || h.event)}${h.who ? ` · <b>${esc(h.who)}</b>` : ''}${h.detail?.reason ? ` · ${esc(h.detail.reason)}` : ''}</span></li>`).join('');
        renderDoc();
    }

    // ------------------------------------------------------------------ the document so far (until the final one exists)

    const byId = () => new Map(D.signers.map((s) => [+s.envs_id, s]));
    const FONTS = { noto: 'Noto Sans Thai', charm: 'Charm', charmonman: 'Charmonman', itim: 'Itim', mali: 'Mali', sriracha: 'Sriracha' };
    // a date field's format, as Envelope::dateAs() stamps it (envelope-edit.js and envelope-sign.js have the same)
    function dateAs(at, fmt) {
        const d = new Date(String(at).replace(' ', 'T'));
        const short = T.thai_short.split(',');
        const long = T.thai_long.split(',');
        const en = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const [day, m, y] = [d.getDate(), d.getMonth(), d.getFullYear()];
        const two = (n) => String(n).padStart(2, '0');
        return {
            th_long: `${day} ${long[m]} ${y + 543}`, num_be: `${two(day)}/${two(m + 1)}/${y + 543}`, num_ce: `${two(day)}/${two(m + 1)}/${y}`,
            iso: `${y}-${two(m + 1)}-${two(day)}`, en: `${day} ${en[m]} ${y}`,
        }[fmt] || `${day} ${short[m]} ${y + 543}`;
    }

    /** What a field shows here: what is signed and filled in so far, else what it is waiting for and whose it is. */
    function content(f, s) {
        const signed = s && s.envs_status === 'S';
        const p = f.envx_props || {};
        const wait = (label) => `<span class="es-lbl">${esc(label)}${s ? ' · ' + esc(s.envs_name) : ''}</span>`;
        switch (f.envx_type) {
            case 'signature':
            case 'initials':
                return signed && s.envs_image ? `<img src="${esc(s.envs_image)}" alt="">` : wait(T['type_' + f.envx_type]);
            case 'name': return `<span class="es-lbl">${esc(s?.envs_name || '')}</span>`;
            case 'position': return `<span class="es-lbl">${esc(s?.envs_position || '')}</span>`;
            case 'date': return signed && s.envs_sign_at ? `<span class="es-lbl">${esc(dateAs(s.envs_sign_at, p.datefmt))}</span>` : wait(T.type_date);
            case 'timeline': return `<span class="es-lbl">${esc(signed ? s.times || '' : T.type_timeline)}</span>`;
            case 'text': return f.envx_value ? `<span class="es-lbl">${esc(f.envx_value)}</span>` : wait(p.label || T.type_text);
            case 'checkbox': return f.envx_value === '1' ? '<i class="ti ti-check" style="margin:auto"></i>' : '';
            case 'stamp': return `<span class="es-lbl">${esc(T.type_stamp)}</span>`;
            default: return `<span class="es-lbl">${esc(p.text || '')}</span>`;
        }
    }

    let docView = null;
    function renderDoc() {
        const main = D.files.filter((f) => f.envf_kind === 'M');
        const show = !D.can.final && main.length > 0;
        $('es-doc-card').hidden = !show;
        if (!show) return;
        const box = $('es-pages');
        const fit = () => Math.max(0.4, Math.min(2, (box.clientWidth - 60) / Math.max(...(D.pages?.length ? D.pages.map((x) => x[0]) : [595]))));
        if (!docView) docView = PhaiPdf.pages(box, main.map((f) => ({ url: `${base}/envelopes/${nbr}/files/${f.envf_id}`, pages: f.envf_sizes })), fit());
        else docView.zoom(fit());
        const sc = docView.scale;
        const who = byId();
        docView.list.forEach((pg) => pg.layer.replaceChildren());
        D.fields.forEach((f) => {
            const pg = docView.list[f.envx_page - 1];
            if (!pg) return;
            const s = f.envx_signer === null ? null : who.get(+f.envx_signer);
            const p = f.envx_props || {};
            const el = document.createElement('div');
            el.className = 'es-field es-other' + (f.envx_signer === null ? ' es-sender' : '') + (s && s.envs_status === 'S' ? ' es-done' : '');
            el.dataset.signer = f.envx_signer ?? '';
            el.style.cssText = `left:${f.envx_x * sc}px;top:${f.envx_y * sc}px;width:${f.envx_w * sc}px;height:${f.envx_h * sc}px;font-size:${Math.max(6, Math.min((p.size || 10) * sc, f.envx_h * sc * 0.8))}px;cursor:default`;
            if (s) el.style.setProperty('--es-c', s.envs_color || '#1d6fd8');
            if (FONTS[p.font]) el.style.fontFamily = `'${FONTS[p.font]}', 'IBM Plex Sans Thai', sans-serif`;
            if (p.color) el.style.color = p.color;
            if (p.bold) el.style.fontWeight = '700';
            if (p.italic) el.style.fontStyle = 'italic';
            if (p.align) el.style.justifyContent = { center: 'center', right: 'flex-end' }[p.align] || 'flex-start';
            if (f.envx_type === 'mark') {
                el.style.color = p.color || '#c92a2a';
                el.style.justifyContent = 'center';
                el.style.borderStyle = 'solid';
            }
            el.innerHTML = content(f, s);
            pg.layer.append(el);
        });
    }

    /** A click on a signer or on one of their events: show where their fields are on the document, and flash them. */
    function showSigner(id) {
        const els = [...document.querySelectorAll(`#es-pages .es-field[data-signer="${id}"]`)];
        if (!els.length) return;
        els[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        els.forEach((el) => {
            el.classList.remove('es-need');
            void el.offsetWidth;
            el.classList.add('es-need');
        });
    }

    async function replace(id) {
        people ??= await Phai.api.get('/api/envelopes/people');
        const s = D.signers.find((x) => String(x.envs_id) === String(id));
        const list = people.filter((p) => p.us_userid !== s.envs_userid);
        const userid = await Phai.prompt(tr(T.replace_prompt, { name: s.envs_name }), {
            title: T.replace, options: list.map((p) => ({ value: p.us_userid, label: `${p.us_name} (${p.us_userid})` })),
            placeholder: T.replace_pick, required: true, ok: T.replace, icon: 'user-share',
        });
        if (!userid) return;
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
        else if (e.target.closest('tr[data-signer]')) showSigner(e.target.closest('tr[data-signer]').dataset.signer);
    });
    $('es-events').addEventListener('click', (e) => {
        const li = e.target.closest('li[data-signer]');
        if (!li) return;
        // an event from before the envelope was reopened names a signer row that was made again since: find them by name
        const now = D.signers.find((s) => String(s.envs_id) === li.dataset.signer) || D.signers.find((s) => s.envs_name === li.dataset.who && s.envs_status !== 'C');
        if (now) showSigner(now.envs_id);
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
    // withdrawn or declined: back to a draft and into the editor
    $('es-reopen').addEventListener('click', async () => {
        if (!(await Phai.confirm(tr(T.reopen_confirm, { nbr }), { title: T.act_reopen, ok: T.act_edit, icon: 'pencil' }))) return;
        await Phai.api.post(`/api/envelopes/${nbr}/reopen`);
        location.href = `${base}/envelopes/${nbr}/edit`;
    });
    render();
    // signers and the final document on the left, files and history on the right, the bar between them dragged
    window.PhaiSplit.init(document.querySelector('.es-split'), 'view', () => renderDoc());
})();
