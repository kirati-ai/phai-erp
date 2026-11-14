/**
 * หน้าเซ็นซองเอกสาร (views/esign/sign.php): the pages with every field drawn where the sender put it. My fields are
 * filled here (text, ticks; name, position and dates fill themselves; signatures take my saved signature or one
 * drawn now); earlier signers' signatures show where they signed. Sign / approve sends it all, or decline with a
 * reason. Opening this page is the "opened" time (EnvelopeController::signPage).
 */
(function () {
    'use strict';

    const T = JSON.parse(document.getElementById('esign-text').textContent);
    const D = JSON.parse(document.getElementById('esign-data').textContent);
    const SIGN = JSON.parse(document.getElementById('phai-sign-text')?.textContent || '{}');
    const esc = Phai.esc;
    const $ = (id) => document.getElementById(id);
    const base = (document.querySelector('meta[name="phai-base"]')?.content || '').replace(/\/$/, '');
    const tr = (s, p = {}) => Object.entries(p).reduce((a, [k, v]) => a.replace(':' + k, v), s || '');
    const nbr = D.env.env_nbr;
    const me = D.me;
    // a date field's format, as Envelope::dateAs() stamps it on the PDF (envelope-edit.js has the same)
    function dateAs(at, fmt) {
        const d = new Date(String(at).replace(' ', 'T'));
        const short = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        const long = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        const en = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const [day, m, y] = [d.getDate(), d.getMonth(), d.getFullYear()];
        const two = (n) => String(n).padStart(2, '0');
        return {
            th_long: `${day} ${long[m]} ${y + 543}`, num_be: `${two(day)}/${two(m + 1)}/${y + 543}`, num_ce: `${two(day)}/${two(m + 1)}/${y}`,
            iso: `${y}-${two(m + 1)}-${two(day)}`, en: `${day} ${en[m]} ${y}`,
        }[fmt] || `${day} ${short[m]} ${y + 543}`;
    }
    // a text box asking for a kind of answer gets the matching keyboard / picker (the server checks it again)
    const INPUTS = { email: 'type="email"', tel: 'type="tel"', number: 'type="text" inputmode="decimal"', numeric: 'type="text" inputmode="numeric" pattern="[0-9]*"', date: 'type="date"' };
    const mineId = me ? +me.envs_id : null;
    const byId = new Map(D.signers.map((s) => [+s.envs_id, s]));
    const values = {};
    let saved = null; // my saved signature
    let drawn = null; // drawn on this page

    $('es-nbr').textContent = nbr;
    $('es-head').textContent = D.env.env_title;
    $('es-envelope').href = `${base}/envelopes/${nbr}`;
    // the sender's message, in the panel on the right
    if (D.env.env_note) {
        $('es-note-box').hidden = false;
        $('es-note-text').textContent = D.env.env_note;
    }

    // what to do here, at the top of the panel
    const msg = (text, type = 'info') => {
        $('es-msg').hidden = false;
        $('es-msg').className = `alert alert-${type} py-2 small mb-2`;
        $('es-msg').textContent = text;
    };
    if (D.can.sign) {
        $('es-submit').hidden = false;
        $('es-decline').hidden = false;
        $('es-submit').querySelector('span').textContent = me.envs_role === 'A' ? T.approve : T.sign;
        msg(tr(T.sign_intro, { role: T['role_' + me.envs_role] }));
    } else if (me && me.envs_status === 'S') {
        msg(tr(T.you_signed, { at: me.signed }), 'success');
    } else if (D.env.env_status !== 'S') {
        msg(T['estatus_' + D.env.env_status] || '', 'secondary');
    } else {
        msg(T.not_your_turn, 'warning');
    }

    // files
    let k = 0;
    $('es-files-n').textContent = D.files.length || '';
    $('es-files').innerHTML =D.files.map((f) => `<div class="es-file"><i class="ti ${f.envf_kind === 'M' ? 'ti-file-text' : 'ti-paperclip'}"></i>
        <a class="es-name" href="${esc(base + '/envelopes/' + nbr + '/files/' + f.envf_id)}" target="_blank" rel="noopener">${esc(f.envf_name)}</a>
        <span class="small text-secondary text-nowrap">${esc(f.envf_kind === 'M' ? T.kind_M : tr(T.kind_A_n, { k: ++k }))} · ${f.envf_pages} ${esc(T.pages_short)}</span></div>`).join('');

    const myFields = D.fields.filter((f) => f.envx_signer === mineId);
    const needsSignature = myFields.some((f) => ['signature', 'initials'].includes(f.envx_type));
    $('es-sigcard').hidden = !(D.can.sign && needsSignature);

    const image = () => drawn || saved;
    function sigPreview() {
        const img = image();
        $('es-sigbox').innerHTML = img ? `<img src="${esc(img)}" alt="">` : `<span class="small text-secondary">${esc(T.no_signature)}</span>`;
        document.querySelectorAll('.es-mine[data-sig]').forEach((el) => {
            el.innerHTML = img ? `<img src="${esc(img)}" alt="">` : `<span class="es-lbl">${esc(T.click_to_sign)}</span>`;
            el.classList.toggle('es-filled', !!img);
        });
    }

    function draw() {
        window.PhaiSign.open({
            title: T.draw, text: SIGN, w: 600, h: 200, image: image(),
            save: async (png) => {
                drawn = png;
                sigPreview();
            },
        });
    }

    // what other signers' fields show
    function otherContent(f) {
        const s = byId.get(f.envx_signer);
        const signed = s && s.envs_status === 'S';
        switch (f.envx_type) {
            case 'signature':
            case 'initials':
                return signed && s.envs_image ? `<img src="${esc(s.envs_image)}" alt="">` + (f.envx_type === 'signature' ? `<span class="es-cap">${esc(s.envs_name)} · ${esc(s.times)}</span>` : '') : `<span class="es-lbl">${esc(T['type_' + f.envx_type])} · ${esc(s?.envs_name || '')}</span>`;
            case 'name': return `<span class="es-lbl">${esc(s?.envs_name || '')}</span>`;
            case 'position': return `<span class="es-lbl">${esc(s?.envs_position || '')}</span>`;
            case 'date': return `<span class="es-lbl">${esc(!signed ? T.type_date : f.envx_props.datefmt && s.envs_sign_at ? dateAs(s.envs_sign_at, f.envx_props.datefmt) : (s.signed || '').replace(/\s+\d\d:\d\d(:\d\d)?$/, ''))}</span>`;
            case 'timeline': return `<span class="es-lbl">${esc(signed ? s.times : T.type_timeline)}</span>`;
            case 'text': return `<span class="es-lbl">${esc(f.envx_value ?? '')}</span>`;
            case 'checkbox': return f.envx_value === '1' ? '<i class="ti ti-check" style="margin:auto"></i>' : '';
            case 'stamp': return `<span class="es-lbl">${esc(T.type_stamp)}</span>`;
            default: return `<span class="es-lbl">${esc(f.envx_props.text || '')}</span>`;
        }
    }

    function myContent(f) {
        switch (f.envx_type) {
            case 'text': return `<input ${INPUTS[f.envx_props.input] || 'type="text"'} maxlength="500" data-f="${f.envx_id}" placeholder="${esc(f.envx_props.label || T.type_text)}${f.envx_props.required ? ' *' : ''}">`;
            case 'checkbox': return `<input type="checkbox" data-f="${f.envx_id}">`;
            case 'name': return `<span class="es-lbl">${esc(me.envs_name)}</span>`;
            case 'position': return `<span class="es-lbl">${esc(me.envs_position || '')}</span>`;
            case 'date': return `<span class="es-lbl">${esc(T.type_date)}</span>`;
            case 'timeline': return `<span class="es-lbl">${esc(T.type_timeline)}</span>`;
            case 'stamp': return `<span class="es-lbl">${esc(T.type_stamp)}</span>`;
            default: return '';
        }
    }

    const files = D.files.filter((f) => f.envf_kind === 'M');
    const box = $('es-pages');
    const fit = () => Math.max(0.5, Math.min(2, (box.clientWidth - 60) / Math.max(...(D.pages.length ? D.pages.map((p) => p[0]) : [595]))));
    const view = PhaiPdf.pages(box, files.map((f) => ({ url: `${base}/envelopes/${nbr}/files/${f.envf_id}`, pages: f.envf_sizes })), fit());

    function renderFields() {
        const s = view.scale;
        view.list.forEach((p) => p.layer.replaceChildren());
        D.fields.forEach((f) => {
            const p = view.list[f.envx_page - 1];
            if (!p) return;
            const mine = D.can.sign && f.envx_signer === mineId;
            const el = document.createElement('div');
            const sg = byId.get(f.envx_signer);
            el.className = 'es-field ' + (mine ? 'es-mine' : 'es-other') + (f.envx_signer === null ? ' es-sender' : '');
            el.style.cssText = `left:${f.envx_x * s}px;top:${f.envx_y * s}px;width:${f.envx_w * s}px;height:${f.envx_h * s}px;font-size:${Math.max(7, Math.min((f.envx_props.size || 10) * s, f.envx_h * s * 0.8))}px`;
            if (sg) el.style.setProperty('--es-c', sg.envs_color || '#1d6fd8');
            // the field's font, as the final PDF draws it (Envelope::FONTS)
            const font = { noto: 'Noto Sans Thai', charm: 'Charm', charmonman: 'Charmonman', itim: 'Itim', mali: 'Mali', sriracha: 'Sriracha' }[f.envx_props.font];
            if (font) el.style.fontFamily = `'${font}', 'IBM Plex Sans Thai', sans-serif`;
            // the text's look, as the final PDF draws it
            if (f.envx_props.color) el.style.color = f.envx_props.color;
            if (f.envx_props.bold) el.style.fontWeight = '700';
            if (f.envx_props.italic) el.style.fontStyle = 'italic';
            if (f.envx_props.underline) el.style.textDecoration = 'underline';
            if (f.envx_type === 'mark') {
                el.style.color = f.envx_props.color || '#c92a2a';
                el.style.justifyContent = 'center';
                el.style.borderStyle = 'solid';
            }
            if (mine && ['signature', 'initials'].includes(f.envx_type)) {
                el.dataset.sig = '1';
                el.addEventListener('click', draw);
            } else {
                el.innerHTML = mine ? myContent(f) : otherContent(f);
            }
            el.dataset.id = f.envx_id;
            p.layer.append(el);
        });
        // keep what was typed when the pages are drawn again
        document.querySelectorAll('[data-f]').forEach((i) => {
            const v = values[i.dataset.f];
            if (v !== undefined) i.type === 'checkbox' ? (i.checked = v === '1') : (i.value = v);
        });
        sigPreview();
    }
    renderFields();
    // pages on the left, the panel on the right; dragging the bar between them (or a new window size) refits the pages
    window.PhaiSplit.init(document.querySelector('.es-split'), 'sign', () => {
        view.zoom(fit());
        renderFields();
    });
    document.querySelectorAll('[data-zoom]').forEach((b) => b.addEventListener('click', () => {
        const z = +b.dataset.zoom;
        view.zoom(z === 0 ? fit() : Math.max(0.3, Math.min(4, view.scale * (z > 0 ? 1.2 : 1 / 1.2))));
        renderFields();
    }));
    box.addEventListener('input', (e) => {
        const i = e.target.closest('[data-f]');
        if (i) values[i.dataset.f] = i.type === 'checkbox' ? (i.checked ? '1' : '0') : i.value;
    });
    box.addEventListener('change', (e) => {
        const i = e.target.closest('[data-f]');
        if (i) values[i.dataset.f] = i.type === 'checkbox' ? (i.checked ? '1' : '0') : i.value;
    });

    function flag(id) {
        const el = document.querySelector(`.es-field[data-id="${id}"]`);
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.remove('es-need');
        void el.offsetWidth;
        el.classList.add('es-need');
    }

    $('es-draw').addEventListener('click', draw);
    $('es-submit').addEventListener('click', async () => {
        for (const f of myFields) {
            const v = values[f.envx_id] ?? '';
            if (f.envx_props.required && ((f.envx_type === 'text' && !String(v).trim()) || (f.envx_type === 'checkbox' && v !== '1'))) {
                Phai.toast(T.fill_required, 'warning');
                flag(f.envx_id);
                return;
            }
        }
        if (needsSignature && !image()) {
            draw();
            return;
        }
        if (!(await Phai.confirm(me.envs_role === 'A' ? T.approve_confirm : T.sign_confirm))) return;
        try {
            await Phai.api.post(`/api/envelopes/${nbr}/sign`, { values, image: drawn || '' });
            location.href = `${base}/envelopes/${nbr}`;
        } catch (e) {
            if (e.fields) {
                const [key, text] = Object.entries(e.fields)[0];
                Phai.toast(text, 'danger');
                if (key.startsWith('f')) flag(key.slice(1));
            }
        }
    });
    $('es-decline').addEventListener('click', async () => {
        const note = await Phai.prompt(T.decline_reason, {
            title: T.decline_title, placeholder: T.decline_hint, rows: 3, required: true, maxlength: 255, ok: T.decline, icon: 'circle-x',
        });
        if (!note) return;
        try {
            await Phai.api.post(`/api/envelopes/${nbr}/decline`, { note });
            location.href = `${base}/envelopes/${nbr}`;
        } catch (e) {
            if (e.fields) Phai.toast(Object.values(e.fields)[0], 'danger');
        }
    });

    if (D.can.sign && needsSignature) {
        Phai.api.get('/api/me/sign').then((d) => {
            saved = d.image;
            sigPreview();
        });
    }
})();
