/**
 * ตัวแก้ซองเอกสาร (views/esign/edit.php): files, signers in steps, and fields placed on the pages of the main
 * documents. Everything is in points from the page's top left, as the page is shown; the screen draws it at
 * `scale` pixels per point. Changes save themselves (PUT /api/envelopes/{nbr}) a moment after the last one.
 *
 * Fields: a tool from the toolbox, then a click on a page. Select by clicking (Shift adds), or drag a box around
 * them; drag to move, the corner to resize, arrows to nudge (Shift = 10 pt). Ctrl+C / V / D copy, paste, duplicate;
 * Ctrl+Z / Y undo, redo; Delete removes. The panel copies a field to other pages (all, all but the first...), as
 * linked copies that move together when wanted, and removes such copies again.
 */
(function () {
    'use strict';

    const T = JSON.parse(document.getElementById('esign-text').textContent);
    const D = JSON.parse(document.getElementById('esign-data').textContent);
    const esc = Phai.esc;
    const $ = (id) => document.getElementById(id);
    const nbr = D.env.env_nbr;
    const base = (document.querySelector('meta[name="phai-base"]')?.content || '').replace(/\/$/, '');
    const tr = (s, p = {}) => Object.entries(p).reduce((a, [k, v]) => a.replace(':' + k, v), s || '');

    const SIGNER_TYPES = ['signature', 'initials', 'name', 'position', 'date', 'timeline', 'stamp', 'text', 'checkbox'];
    const SENDER_TYPES = ['label', 'mark'];
    const SIZES = {
        signature: [150, 40], initials: [50, 30], name: [140, 16], position: [140, 16], date: [90, 16], timeline: [230, 14],
        stamp: [70, 70], text: [140, 18], checkbox: [14, 14], label: [160, 20], mark: [110, 30],
    };
    const ICONS = {
        signature: 'signature', initials: 'letter-case', name: 'user', position: 'id-badge-2', date: 'calendar', timeline: 'clock',
        stamp: 'rosette', text: 'forms', checkbox: 'checkbox', label: 'typography', mark: 'stamp',
    };
    const TEXTY = ['label', 'mark', 'text', 'name', 'position', 'date', 'timeline'];
    const COLORS = ['#1d6fd8', '#d6336c', '#2f9e44', '#e8590c', '#7048e8', '#0c8599', '#c2255c', '#5c940d'];
    const GRID = 5;
    const MM = 72 / 25.4;

    // ------------------------------------------------------------------ state

    let files = D.files;
    let pageSizes = D.pages;
    let problems = D.problems || [];
    let signers = D.signers.filter((s) => s.envs_status !== 'C').map((s) => ({
        key: 's' + s.envs_id, step: +s.envs_step, role: s.envs_role, kind: s.envs_kind, userid: s.envs_userid,
        name: s.envs_name, email: s.envs_email || '', position: s.envs_position || '', color: s.envs_color,
    }));
    let fields = D.fields.map(fromServer);
    let active = signers[0]?.key ?? null;
    let tool = null;
    let viewAs = '';
    let selected = new Set();
    let clipboard = [];
    let people = [];
    let view = null;
    let seq = 0;
    const undoStack = [];
    const redoStack = [];

    function fromServer(f) {
        return {
            id: 'f' + f.envx_id, signer: f.envx_signer === null ? null : 's' + f.envx_signer, page: +f.envx_page, type: f.envx_type,
            x: +f.envx_x, y: +f.envx_y, w: +f.envx_w, h: +f.envx_h, props: f.envx_props || {}, link: f.envx_link || null,
        };
    }
    const newId = () => 'n' + Date.now().toString(36) + (++seq);
    const signer = (key) => signers.find((s) => s.key === key);
    const field = (id) => fields.find((f) => f.id === id);
    const snapOn = () => $('es-snap').checked;
    const snap = (v) => (snapOn() ? Math.round(v / GRID) * GRID : Math.round(v * 100) / 100);
    const pageSize = (n) => pageSizes[n - 1] || [595, 842];
    const clamp = (f) => {
        const [pw, ph] = pageSize(f.page);
        f.w = Math.max(6, Math.min(pw, f.w));
        f.h = Math.max(6, Math.min(ph, f.h));
        f.x = Math.max(0, Math.min(pw - f.w, f.x));
        f.y = Math.max(0, Math.min(ph - f.h, f.y));
        return f;
    };

    // ------------------------------------------------------------------ history and saving

    function snapshot() {
        return JSON.stringify({ signers, fields });
    }
    function remember() {
        undoStack.push(snapshot());
        if (undoStack.length > 100) undoStack.shift();
        redoStack.length = 0;
    }
    function restore(json) {
        const s = JSON.parse(json);
        signers = s.signers;
        fields = s.fields;
        selected = new Set([...selected].filter((id) => field(id)));
        if (!signer(active)) active = signers[0]?.key ?? null;
        renderAll();
        save();
    }
    function undo() {
        if (!undoStack.length) return;
        redoStack.push(snapshot());
        restore(undoStack.pop());
    }
    function redo() {
        if (!redoStack.length) return;
        undoStack.push(snapshot());
        restore(redoStack.pop());
    }

    let saveTimer = null;
    let saving = null;
    function save(now = false) {
        clearTimeout(saveTimer);
        $('es-saved').textContent = T.saving;
        if (now) return flush();
        saveTimer = setTimeout(flush, 1200);
        return null;
    }
    async function flush() {
        clearTimeout(saveTimer);
        if (saving) await saving.catch(() => {});
        const body = {
            env_title: $('es-title').value, env_note: $('es-note').value,
            signers: signers.map((s) => ({ key: s.key, step: s.step, role: s.role, kind: s.kind, userid: s.userid, name: s.name, email: s.email, position: s.position, color: s.color })),
            fields: fields.map((f) => ({ signer: f.signer, page: f.page, type: f.type, x: f.x, y: f.y, w: f.w, h: f.h, props: f.props, link: f.link })),
        };
        saving = Phai.api.put(`/api/envelopes/${nbr}`, body);
        try {
            const d = await saving;
            problems = d.problems || [];
            pageSizes = d.pages;
            $('es-saved').textContent = tr(T.saved_at, { time: new Date().toTimeString().slice(0, 5) });
            renderProblems();
            return d;
        } catch (e) {
            $('es-saved').textContent = T.not_saved;
            if (e.fields) Phai.toast(Object.values(e.fields)[0], 'danger');
            throw e;
        } finally {
            saving = null;
        }
    }
    /** After a change: remember it (already done by the caller), redraw, save. */
    function changed(redraw = true) {
        propagateLinks();
        if (redraw) renderFields();
        renderProps();
        save();
    }

    /** Linked copies keep the place, size, signer and look of the one changed last. */
    function propagateLinks() {
        for (const id of selected) {
            const f = field(id);
            if (!f || !f.link) continue;
            fields.forEach((o) => {
                if (o !== f && o.link === f.link) {
                    Object.assign(o, { x: f.x, y: f.y, w: f.w, h: f.h, signer: f.signer, type: f.type, props: { ...f.props } });
                    clamp(o);
                }
            });
        }
    }

    // ------------------------------------------------------------------ files

    function renderFiles() {
        const box = $('es-files');
        if (!files.length) {
            box.innerHTML = `<div class="small text-secondary">${esc(T.no_files)}</div>`;
            return;
        }
        let k = 0;
        box.innerHTML = files.map((f) => {
            const kind = f.envf_kind === 'M' ? T.kind_M : tr(T.kind_A_n, { k: ++k });
            return `<div class="es-file" data-id="${f.envf_id}">
                <i class="ti ${f.envf_kind === 'M' ? 'ti-file-text' : 'ti-paperclip'}"></i>
                <a class="es-name" href="${esc(base + '/envelopes/' + nbr + '/files/' + f.envf_id)}" target="_blank" rel="noopener" title="${esc(f.envf_name)}">${esc(f.envf_name)}</a>
                <span class="small text-secondary text-nowrap">${esc(kind)} · ${f.envf_pages} ${esc(T.pages_short)}</span>
                <button type="button" class="btn btn-sm btn-ghost-secondary" data-file="up" title="${esc(T.move_up)}"><i class="ti ti-chevron-up"></i></button>
                <button type="button" class="btn btn-sm btn-ghost-secondary" data-file="kind" title="${esc(f.envf_kind === 'M' ? T.make_attach : T.make_main)}"><i class="ti ti-switch-horizontal"></i></button>
                <button type="button" class="btn btn-sm btn-ghost-danger" data-file="remove" title="${esc(T.remove)}"><i class="ti ti-x"></i></button>
            </div>`;
        }).join('');
    }

    async function afterFiles(d) {
        files = d.files;
        pageSizes = d.pages;
        problems = d.problems || [];
        // the server may have dropped fields whose pages moved: take its fields, keeping the editor's signer keys
        // (it saved the signers in the editor's order, so the n-th row is the n-th signer here)
        const byId = new Map(d.signers.map((s, i) => [String(s.envs_id), signers[i]?.key]));
        fields = d.fields.map((f) => ({ ...fromServer(f), signer: f.envx_signer === null ? null : (byId.get(String(f.envx_signer)) ?? 's' + f.envx_signer) }));
        selected.clear();
        renderFiles();
        buildPages();
        renderProblems();
    }

    async function upload(input) {
        const list = [...input.files];
        input.value = '';
        for (const file of list) {
            const data = await new Promise((resolve, reject) => {
                const r = new FileReader();
                r.onload = () => resolve(r.result);
                r.onerror = reject;
                r.readAsDataURL(file);
            });
            try {
                await save(true);
                Phai.toast(tr(T.uploading, { name: file.name }), 'info');
                const d = await Phai.api.post(`/api/envelopes/${nbr}/files`, { kind: input.dataset.kind, name: file.name, data });
                await afterFiles(d);
            } catch (e) {
                if (e.fields) Phai.toast(`${file.name}: ${Object.values(e.fields)[0]}`, 'danger');
            }
        }
    }

    async function fileAction(id, what) {
        const f = files.find((x) => String(x.envf_id) === String(id));
        if (!f) return;
        if (what === 'remove' && !(await Phai.confirm(tr(T.remove_file_confirm, { name: f.envf_name })))) return;
        await save(true);
        const body = what === 'remove' ? { remove: true } : what === 'kind' ? { kind: f.envf_kind === 'M' ? 'A' : 'M' } : { dir: -1 };
        const before = fields.length;
        const d = await Phai.api.put(`/api/envelopes/${nbr}/files/${id}`, body);
        await afterFiles(d);
        if (before && !fields.length) Phai.toast(T.fields_dropped, 'warning');
    }

    // ------------------------------------------------------------------ signers

    function renderSigners() {
        const box = $('es-signers');
        signers.sort((a, b) => a.step - b.step);
        if (!signers.length) {
            box.innerHTML = `<div class="small text-secondary">${esc(T.no_signers)}</div>`;
        } else {
            let step = 0;
            box.innerHTML = signers.map((s, i) => {
                const head = s.step !== step ? `<div class="es-step">${esc(tr(T.step_n, { n: s.step }))}</div>` : '';
                step = s.step;
                const who = s.kind === 'U'
                    ? `<select class="form-select form-select-sm" data-s="userid"><option value="">${esc(T.pick_user)}</option>${people.map((p) =>
                        `<option value="${esc(p.us_userid)}"${p.us_userid === s.userid ? ' selected' : ''}>${esc(p.us_name || p.us_userid)}</option>`).join('')}</select>`
                    : `<input class="form-control form-control-sm mb-1" data-s="name" placeholder="${esc(T.name)}" value="${esc(s.name)}">
                       <input class="form-control form-control-sm" data-s="email" type="email" placeholder="${esc(T.email)}" value="${esc(s.email)}">
                       <div class="small text-warning mt-1">${esc(T.outside_hint)}</div>`;
                return `${head}<div class="es-signer${s.key === active ? ' es-active' : ''}" style="--es-c:${esc(s.color)}" data-key="${esc(s.key)}">
                    <div class="row align-items-center">
                        <div class="col-auto"><input class="form-check-input" type="radio" name="es-active" ${s.key === active ? 'checked' : ''} title="${esc(T.active_hint)}"></div>
                        <div class="col">${who}</div>
                    </div>
                    <div class="row mt-1">
                        <div class="col-7"><input class="form-control form-control-sm" data-s="position" placeholder="${esc(T.position)}" value="${esc(s.position)}"></div>
                        <div class="col-5"><select class="form-select form-select-sm" data-s="role">${['S', 'A', 'C'].map((r) =>
                            `<option value="${r}"${r === s.role ? ' selected' : ''}>${esc(T['role_' + r])}</option>`).join('')}</select></div>
                    </div>
                    <div class="d-flex align-items-center gap-1 mt-1">
                        <span class="small text-secondary">${esc(T.step)}</span>
                        <button type="button" class="btn btn-sm btn-ghost-secondary px-1" data-s="earlier" title="${esc(T.earlier)}"><i class="ti ti-arrow-up"></i></button>
                        <b class="small">${s.step}</b>
                        <button type="button" class="btn btn-sm btn-ghost-secondary px-1" data-s="later" title="${esc(T.later)}"><i class="ti ti-arrow-down"></i></button>
                        <span class="ms-auto small text-secondary">${fields.filter((f) => f.signer === s.key).length} ${esc(T.fields_short)}</span>
                        <button type="button" class="btn btn-sm btn-ghost-danger px-1" data-s="remove" title="${esc(T.remove)}"><i class="ti ti-x"></i></button>
                    </div>
                </div>`;
            }).join('');
        }
        renderToolHead();
        renderViewAs();
    }

    function addSigner(kind) {
        remember();
        const used = new Set(signers.map((s) => s.color));
        const step = signers.length ? Math.max(...signers.map((s) => s.step)) + 1 : 1;
        const s = { key: newId(), step, role: 'S', kind, userid: null, name: '', email: '', position: '', color: COLORS.find((c) => !used.has(c)) || COLORS[signers.length % COLORS.length] };
        signers.push(s);
        active = s.key;
        renderSigners();
        save();
    }

    function signerInput(el) {
        const row = el.closest('.es-signer');
        const s = signer(row?.dataset.key);
        if (!s) return;
        const what = el.dataset.s;
        if (['userid', 'name', 'email', 'position', 'role'].includes(what)) {
            remember();
            s[what] = el.value;
            if (what === 'userid') {
                const p = people.find((x) => x.us_userid === el.value);
                s.name = p ? p.us_name || p.us_userid : '';
                s.email = p?.us_email || '';
            }
            if (what === 'role') renderSigners();
            renderToolHead();
            renderViewAs();
            renderFields();
            save();
        }
    }

    async function signerButton(el) {
        const row = el.closest('.es-signer');
        const s = signer(row?.dataset.key);
        if (!s) return;
        const what = el.dataset.s;
        if (what === 'earlier' || what === 'later') {
            remember();
            s.step = Math.max(1, Math.min(99, s.step + (what === 'earlier' ? -1 : 1)));
            // keep steps 1, 2, 3... without gaps
            const steps = [...new Set(signers.map((x) => x.step))].sort((a, b) => a - b);
            signers.forEach((x) => { x.step = steps.indexOf(x.step) + 1; });
            renderSigners();
            save();
        } else if (what === 'remove') {
            const n = fields.filter((f) => f.signer === s.key).length;
            if (n && !(await Phai.confirm(tr(T.remove_signer_confirm, { name: s.name || T.signer, n })))) return;
            remember();
            signers = signers.filter((x) => x !== s);
            fields = fields.filter((f) => f.signer !== s.key);
            if (active === s.key) active = signers[0]?.key ?? null;
            renderAll();
            save();
        }
    }

    // ------------------------------------------------------------------ toolbox

    function renderTools() {
        $('es-tools-signer').innerHTML = SIGNER_TYPES.map((t) =>
            `<button type="button" class="btn btn-sm" data-tool="${t}"><i class="ti ti-${ICONS[t]} me-1"></i>${esc(T['type_' + t])}</button>`).join('');
        $('es-tools-sender').innerHTML = SENDER_TYPES.map((t) =>
            `<button type="button" class="btn btn-sm" data-tool="${t}"><i class="ti ti-${ICONS[t]} me-1"></i>${esc(T['type_' + t])}</button>`).join('');
        markTool();
    }
    function renderToolHead() {
        const s = signer(active);
        $('es-active-dot').style.setProperty('--es-c', s?.color || '#888');
        $('es-active-name').textContent = s ? (s.name || T.signer) : T.no_active;
        document.querySelectorAll('#es-tools-signer [data-tool]').forEach((b) => { b.disabled = !s || s.role === 'C'; });
    }
    function markTool() {
        document.querySelectorAll('[data-tool]').forEach((b) => b.classList.toggle('active', b.dataset.tool === tool));
        view?.list.forEach((p) => p.layer.classList.toggle('es-placing', !!tool));
    }
    function renderViewAs() {
        const sel = $('es-view');
        sel.innerHTML = `<option value="">${esc(T.view_all)}</option>` + signers.map((s) =>
            `<option value="${esc(s.key)}"${s.key === viewAs ? ' selected' : ''}>${esc(s.name || T.signer)}</option>`).join('');
        if (viewAs && !signer(viewAs)) viewAs = '';
    }

    // ------------------------------------------------------------------ pages and fields

    function buildPages() {
        const box = $('es-pages');
        box.querySelectorAll('.es-page').forEach((el) => el.remove());
        const main = files.filter((f) => f.envf_kind === 'M');
        $('es-empty').hidden = main.length > 0;
        if (!main.length) {
            view = null;
            return;
        }
        view = PhaiPdf.pages(box, main.map((f) => ({ url: `${base}/envelopes/${nbr}/files/${f.envf_id}`, pages: f.envf_sizes })), fitScale());
        view.list.forEach((p) => {
            p.layer.addEventListener('pointerdown', (e) => layerDown(e, p));
        });
        markTool();
        renderFields();
    }

    function fitScale() {
        const w = $('es-pages').clientWidth - 70;
        const widest = Math.max(...(pageSizes.length ? pageSizes.map((s) => s[0]) : [595]));
        return Math.max(0.4, Math.min(2.5, w / widest));
    }

    function label(f) {
        const s = signer(f.signer);
        if (f.type === 'label' || f.type === 'mark') return f.props.text || T['type_' + f.type];
        if (f.type === 'name' && s?.name) return s.name;
        if (f.type === 'position' && s?.position) return s.position;
        if (f.type === 'checkbox') return '';
        return T['type_' + f.type] + (s?.name ? ' · ' + s.name : '');
    }

    function renderFields() {
        if (!view) return;
        const s = view.scale;
        view.list.forEach((p) => {
            p.layer.querySelectorAll('.es-field').forEach((el) => el.remove());
        });
        fields.forEach((f) => {
            const p = view.list[f.page - 1];
            if (!p) return;
            if (viewAs && f.signer !== viewAs) return;
            const el = document.createElement('div');
            const sg = signer(f.signer);
            el.className = 'es-field' + (f.signer === null ? ' es-sender' : '') + (selected.has(f.id) ? ' es-sel' : '') + (f.props.locked ? ' es-locked' : '')
                + (f.type === 'label' ? ' es-label' : '') + (f.type === 'mark' ? ' es-mark' : '');
            el.dataset.id = f.id;
            el.style.cssText = `left:${f.x * s}px;top:${f.y * s}px;width:${f.w * s}px;height:${f.h * s}px;`;
            if (sg) el.style.setProperty('--es-c', sg.color);
            if (f.type === 'mark' || f.type === 'label') {
                el.style.color = f.props.color || (f.type === 'mark' ? '#c92a2a' : '#222');
                if (f.type === 'mark') el.style.setProperty('--es-c', f.props.color || '#c92a2a');
            }
            const size = (f.props.size || (f.type === 'mark' ? 12 : 10)) * s;
            el.style.fontSize = Math.max(6, Math.min(size, f.h * s * 0.8)) + 'px';
            if (TEXTY.includes(f.type)) {
                el.style.fontWeight = f.props.bold ? '700' : '';
                el.style.fontStyle = f.props.italic ? 'italic' : '';
                el.style.justifyContent = { center: 'center', right: 'flex-end' }[f.props.align] || (f.type === 'mark' ? 'center' : 'flex-start');
            }
            el.innerHTML = `<span class="es-lbl">${esc(label(f))}</span><span class="es-h"></span>${f.link ? '<i class="ti ti-link es-link"></i>' : ''}`;
            el.title = `${T['type_' + f.type]}${sg ? ' · ' + (sg.name || T.signer) : ''}${f.props.required ? ' · ' + T.required : ''}`;
            el.addEventListener('pointerdown', (e) => fieldDown(e, f, p));
            p.layer.append(el);
        });
        renderSignerCounts();
    }
    function renderSignerCounts() {
        document.querySelectorAll('.es-signer').forEach((row) => {
            const span = row.querySelector('.ms-auto.small');
            if (span) span.textContent = `${fields.filter((f) => f.signer === row.dataset.key).length} ${T.fields_short}`;
        });
    }

    function place(p, x, y) {
        const type = tool;
        const isSender = SENDER_TYPES.includes(type);
        const s = signer(active);
        if (!isSender && (!s || s.role === 'C')) {
            Phai.toast(T.need_active, 'warning');
            return;
        }
        remember();
        const [w, h] = SIZES[type];
        const f = clamp({
            id: newId(), signer: isSender ? null : s.key, page: p.n, type, x: snap(x - w / 2), y: snap(y - h / 2), w, h,
            props: type === 'mark' ? { text: (T.marks || '').split('|')[0], color: '#c92a2a', bold: true, size: 12 }
                : type === 'label' ? { text: T.type_label, size: 10 }
                : ['signature', 'initials'].includes(type) ? { required: true } : {},
            link: null,
        });
        fields.push(f);
        selected = new Set([f.id]);
        tool = null;
        markTool();
        changed();
    }

    function layerDown(e, p) {
        if (e.target !== p.layer || e.button > 0) return;
        const s = view.scale;
        const r = p.layer.getBoundingClientRect();
        const x = (e.clientX - r.left) / s;
        const y = (e.clientY - r.top) / s;
        if (tool) {
            place(p, x, y);
            return;
        }
        // drag a box to select what it touches
        if (!e.shiftKey) selected.clear();
        const band = document.createElement('div');
        band.className = 'es-band';
        p.layer.append(band);
        const start = { x: e.clientX, y: e.clientY };
        const move = (ev) => {
            const x0 = Math.min(start.x, ev.clientX) - r.left;
            const y0 = Math.min(start.y, ev.clientY) - r.top;
            band.style.cssText = `left:${x0}px;top:${y0}px;width:${Math.abs(ev.clientX - start.x)}px;height:${Math.abs(ev.clientY - start.y)}px`;
        };
        const up = (ev) => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            band.remove();
            const a = { x: (Math.min(start.x, ev.clientX) - r.left) / s, y: (Math.min(start.y, ev.clientY) - r.top) / s };
            const b = { x: (Math.max(start.x, ev.clientX) - r.left) / s, y: (Math.max(start.y, ev.clientY) - r.top) / s };
            if (b.x - a.x > 2 || b.y - a.y > 2) {
                fields.forEach((f) => {
                    if (f.page === p.n && (!viewAs || f.signer === viewAs) && f.x < b.x && f.x + f.w > a.x && f.y < b.y && f.y + f.h > a.y) selected.add(f.id);
                });
            }
            renderFields();
            renderProps();
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    function fieldDown(e, f, p) {
        if (e.button > 0) return;
        e.stopPropagation();
        e.preventDefault();
        if (tool) {
            tool = null;
            markTool();
        }
        if (e.shiftKey) {
            selected.has(f.id) ? selected.delete(f.id) : selected.add(f.id);
            renderFields();
            renderProps();
            return;
        }
        if (!selected.has(f.id)) selected = new Set([f.id]);
        const resizing = e.target.classList.contains('es-h');
        const s = view.scale;
        const moving = [...selected].map(field).filter((x) => x && !x.props.locked && x.page === f.page);
        const start = { x: e.clientX, y: e.clientY, items: moving.map((x) => ({ f: x, x: x.x, y: x.y, w: x.w, h: x.h })) };
        let touched = false;
        const move = (ev) => {
            const dx = (ev.clientX - start.x) / s;
            const dy = (ev.clientY - start.y) / s;
            if (!touched && Math.abs(dx) + Math.abs(dy) < 1) return;
            if (!touched) {
                remember();
                touched = true;
            }
            start.items.forEach((it) => {
                if (resizing) {
                    if (it.f !== f) return;
                    it.f.w = snap(it.w + dx);
                    it.f.h = snap(it.h + dy);
                } else {
                    it.f.x = snap(it.x + dx);
                    it.f.y = snap(it.y + dy);
                }
                clamp(it.f);
                const el = p.layer.querySelector(`.es-field[data-id="${it.f.id}"]`);
                if (el) el.style.cssText += `;left:${it.f.x * s}px;top:${it.f.y * s}px;width:${it.f.w * s}px;height:${it.f.h * s}px`;
            });
            renderGeometry();
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            if (touched) changed();
            else {
                renderFields();
                renderProps();
            }
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    // ------------------------------------------------------------------ selection panel

    const sel = () => [...selected].map(field).filter(Boolean);
    const mm = (pt) => (pt / MM).toFixed(1);

    function renderProps() {
        const box = $('es-props');
        const list = sel();
        if (!list.length) {
            box.innerHTML = `<h4>${esc(T.selection)}</h4><div class="small text-secondary">${esc(T.select_hint)}</div>`;
            return;
        }
        const f = list[0];
        const one = list.length === 1;
        const isSignerField = SIGNER_TYPES.includes(f.type);
        const p = f.props;
        let html = `<h4>${esc(one ? T['type_' + f.type] : tr(T.n_selected, { n: list.length }))} <span class="small text-secondary">· ${esc(tr(T.page_n, { n: f.page }))}</span></h4>`;
        if (one && isSignerField) {
            html += `<label>${esc(T.signer)}</label><select class="form-select form-select-sm mb-2" data-p="signer">${signers.filter((s) => s.role !== 'C').map((s) =>
                `<option value="${esc(s.key)}"${s.key === f.signer ? ' selected' : ''}>${esc(s.name || T.signer)}</option>`).join('')}</select>`;
        }
        if (one) {
            html += `<div class="row mb-2">${['x', 'y', 'w', 'h'].map((k) =>
                `<div class="col-3"><label>${esc(T['geo_' + k])}</label><input class="form-control form-control-sm px-1" type="number" step="0.5" data-geo="${k}" value="${mm(f[k])}"></div>`).join('')}</div>`;
        }
        if (one && (f.type === 'label' || f.type === 'mark')) {
            if (f.type === 'mark') {
                html += `<label>${esc(T.mark_preset)}</label><select class="form-select form-select-sm mb-1" data-p="preset"><option value="">—</option>${(T.marks || '').split('|').map((m) =>
                    `<option${m === p.text ? ' selected' : ''}>${esc(m)}</option>`).join('')}</select>`;
            }
            html += `<label>${esc(T.text)}</label><textarea class="form-control form-control-sm mb-2" rows="2" data-p="text">${esc(p.text || '')}</textarea>`;
        }
        if (TEXTY.includes(f.type)) {
            html += `<div class="row mb-2 align-items-end">
                <div class="col-4"><label>${esc(T.font_size)}</label><input class="form-control form-control-sm px-1" type="number" min="5" max="72" step="0.5" data-p="size" value="${p.size || (f.type === 'mark' ? 12 : 10)}"></div>
                <div class="col-8"><div class="es-iconbar">
                    <button type="button" class="btn btn-sm${p.bold ? ' active' : ''}" data-toggle="bold" title="${esc(T.bold)}"><i class="ti ti-bold"></i></button>
                    <button type="button" class="btn btn-sm${p.italic ? ' active' : ''}" data-toggle="italic" title="${esc(T.italic)}"><i class="ti ti-italic"></i></button>
                    <button type="button" class="btn btn-sm${p.underline ? ' active' : ''}" data-toggle="underline" title="${esc(T.underline)}"><i class="ti ti-underline"></i></button>
                    <input type="color" class="form-control form-control-sm form-control-color p-0" style="width:1.8rem;height:1.6rem" data-p="color" value="${esc(p.color || (f.type === 'mark' ? '#c92a2a' : '#111111'))}" title="${esc(T.color)}">
                </div></div>
            </div>
            <div class="es-iconbar mb-2">${['left', 'center', 'right'].map((a) =>
                `<button type="button" class="btn btn-sm${(p.align || 'left') === a ? ' active' : ''}" data-align="${a}" title="${esc(T['align_' + a])}"><i class="ti ti-align-${a}"></i></button>`).join('')}</div>`;
        }
        if (['text', 'checkbox', 'signature', 'initials'].includes(f.type)) {
            html += `<div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="es-req" data-toggle="required"${p.required ? ' checked' : ''}><label class="form-check-label small" for="es-req">${esc(T.required)}</label></div>`;
        }
        if (list.length > 1) {
            html += `<label>${esc(T.arrange)}</label><div class="es-iconbar mb-2">
                ${[['left', 'layout-align-left'], ['center', 'layout-align-center'], ['right', 'layout-align-right'], ['top', 'layout-align-top'], ['middle', 'layout-align-middle'], ['bottom', 'layout-align-bottom']].map(([k, i]) =>
                    `<button type="button" class="btn btn-sm" data-arrange="${k}" title="${esc(T['arr_' + k])}"><i class="ti ti-${i}"></i></button>`).join('')}
                <button type="button" class="btn btn-sm" data-arrange="hspace" title="${esc(T.arr_hspace)}"><i class="ti ti-layout-distribute-vertical"></i></button>
                <button type="button" class="btn btn-sm" data-arrange="vspace" title="${esc(T.arr_vspace)}"><i class="ti ti-layout-distribute-horizontal"></i></button>
                <button type="button" class="btn btn-sm" data-arrange="size" title="${esc(T.arr_size)}"><i class="ti ti-dimensions"></i></button>
            </div>`;
        }
        if (one) {
            html += `<label>${esc(T.copy_to)}</label>
                <div class="d-flex gap-1 mb-1">
                    <select class="form-select form-select-sm" id="es-copy-where">${['all', 'not_first', 'not_last', 'odd', 'even', 'custom'].map((k) => `<option value="${k}">${esc(T['pages_' + k])}</option>`).join('')}</select>
                    <button type="button" class="btn btn-sm" data-act="copy"><i class="ti ti-copy"></i></button>
                </div>
                <input class="form-control form-control-sm mb-1" id="es-copy-custom" placeholder="${esc(T.pages_custom_hint)}" hidden>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="es-copy-link" checked><label class="form-check-label small" for="es-copy-link">${esc(T.linked)}</label></div>
                <label>${esc(T.delete_from)}</label>
                <div class="d-flex gap-1 mb-2">
                    <select class="form-select form-select-sm" id="es-del-where">${['all', 'not_this', 'not_first', 'custom'].map((k) => `<option value="${k}">${esc(T['del_' + k])}</option>`).join('')}</select>
                    <button type="button" class="btn btn-sm btn-ghost-danger" data-act="delfrom"><i class="ti ti-trash"></i></button>
                </div>
                <input class="form-control form-control-sm mb-2" id="es-del-custom" placeholder="${esc(T.pages_custom_hint)}" hidden>`;
            if (f.link) html += `<button type="button" class="btn btn-sm w-100 mb-2" data-act="unlink"><i class="ti ti-unlink me-1"></i>${esc(T.unlink)}</button>`;
        }
        html += `<div class="es-iconbar">
            <button type="button" class="btn btn-sm" data-act="duplicate" title="${esc(T.duplicate)} (Ctrl+D)"><i class="ti ti-copy-plus"></i></button>
            <button type="button" class="btn btn-sm${list.every((x) => x.props.locked) ? ' active' : ''}" data-act="lock" title="${esc(T.lock)}"><i class="ti ti-lock"></i></button>
            <button type="button" class="btn btn-sm btn-ghost-danger ms-auto" data-act="delete" title="${esc(T.remove)} (Delete)"><i class="ti ti-trash me-1"></i>${esc(T.remove)}</button>
        </div>`;
        box.innerHTML = html;
    }

    function renderGeometry() {
        const list = sel();
        if (list.length !== 1) return;
        document.querySelectorAll('#es-props [data-geo]').forEach((i) => {
            if (document.activeElement !== i) i.value = mm(list[0][i.dataset.geo]);
        });
    }

    /** Pages for "copy to" / "delete from", other than the field's own unless said. */
    function pagesFor(where, own, custom) {
        const n = pageSizes.length;
        const all = Array.from({ length: n }, (_, i) => i + 1);
        if (where === 'custom') {
            const out = new Set();
            String(custom || '').split(',').forEach((part) => {
                const m = part.trim().match(/^(\d+)\s*(?:-\s*(\d+))?$/);
                if (!m) return;
                const a = +m[1];
                const b = m[2] ? +m[2] : a;
                for (let i = Math.min(a, b); i <= Math.max(a, b); i++) if (i >= 1 && i <= n) out.add(i);
            });
            return [...out];
        }
        return all.filter((p) => ({
            all: true, not_first: p !== 1, not_last: p !== n, odd: p % 2 === 1, even: p % 2 === 0, not_this: p !== own,
        })[where]);
    }

    /** Fields that are "the same one" on other pages: linked copies, or the same kind at the same place. */
    function twins(f) {
        return fields.filter((o) => o.link && o.link === f.link
            || (!f.link && o.type === f.type && o.signer === f.signer && Math.abs(o.x - f.x) < 1 && Math.abs(o.y - f.y) < 1));
    }

    function panelAction(act) {
        const list = sel();
        if (!list.length) return;
        const f = list[0];
        if (act === 'copy') {
            const where = $('es-copy-where').value;
            const targets = pagesFor(where, f.page, $('es-copy-custom').value).filter((p) => p !== f.page && !twins(f).some((o) => o.page === p));
            if (!targets.length) {
                Phai.toast(T.no_pages, 'warning');
                return;
            }
            remember();
            const linked = $('es-copy-link').checked;
            if (linked && !f.link) f.link = newId().slice(0, 20);
            targets.forEach((p) => fields.push(clamp({ ...f, id: newId(), page: p, props: { ...f.props }, link: linked ? f.link : null })));
            Phai.toast(tr(T.copied_n, { n: targets.length }));
            changed();
        } else if (act === 'delfrom') {
            const where = $('es-del-where').value;
            const pages = new Set(pagesFor(where, f.page, $('es-del-custom').value));
            const gone = twins(f).filter((o) => pages.has(o.page));
            if (!gone.length) {
                Phai.toast(T.no_pages, 'warning');
                return;
            }
            remember();
            const ids = new Set(gone.map((o) => o.id));
            fields = fields.filter((o) => !ids.has(o.id));
            selected = new Set([...selected].filter((id) => !ids.has(id)));
            Phai.toast(tr(T.removed_n, { n: gone.length }));
            changed();
        } else if (act === 'unlink') {
            remember();
            twins(f).forEach((o) => { o.link = null; });
            changed();
        } else if (act === 'duplicate') {
            duplicate();
        } else if (act === 'lock') {
            remember();
            const on = !list.every((x) => x.props.locked);
            list.forEach((x) => { x.props = { ...x.props, locked: on || undefined }; });
            changed();
        } else if (act === 'delete') {
            removeSelected();
        }
    }

    function arrange(how) {
        const list = sel();
        if (list.length < 2) return;
        remember();
        const first = list[0];
        const minX = Math.min(...list.map((f) => f.x));
        const maxX = Math.max(...list.map((f) => f.x + f.w));
        const minY = Math.min(...list.map((f) => f.y));
        const maxY = Math.max(...list.map((f) => f.y + f.h));
        list.forEach((f) => {
            if (how === 'left') f.x = minX;
            if (how === 'right') f.x = maxX - f.w;
            if (how === 'center') f.x = (minX + maxX) / 2 - f.w / 2;
            if (how === 'top') f.y = minY;
            if (how === 'bottom') f.y = maxY - f.h;
            if (how === 'middle') f.y = (minY + maxY) / 2 - f.h / 2;
            if (how === 'size') {
                f.w = first.w;
                f.h = first.h;
            }
            clamp(f);
        });
        if (how === 'hspace' || how === 'vspace') {
            const k = how === 'hspace' ? 'x' : 'y';
            const d = how === 'hspace' ? 'w' : 'h';
            const sorted = [...list].sort((a, b) => a[k] - b[k]);
            const span = sorted[sorted.length - 1][k] + sorted[sorted.length - 1][d] - sorted[0][k];
            const gap = (span - sorted.reduce((a, f) => a + f[d], 0)) / (sorted.length - 1);
            let at = sorted[0][k];
            sorted.forEach((f) => {
                f[k] = at;
                at += f[d] + gap;
                clamp(f);
            });
        }
        changed();
    }

    function propInput(el) {
        const list = sel();
        if (!list.length) return;
        remember();
        if (el.dataset.geo) {
            const f = list[0];
            const v = parseFloat(el.value);
            if (!Number.isFinite(v)) return;
            f[el.dataset.geo] = Math.round(v * MM * 100) / 100;
            clamp(f);
        } else {
            const k = el.dataset.p;
            list.forEach((f) => {
                if (k === 'signer') f.signer = el.value;
                else if (k === 'preset') {
                    if (el.value) f.props = { ...f.props, text: el.value };
                } else if (k === 'size') f.props = { ...f.props, size: Math.max(5, Math.min(72, parseFloat(el.value) || 10)) };
                else f.props = { ...f.props, [k]: el.value };
            });
            if (k === 'preset') renderProps();
        }
        changed();
    }

    function propToggle(k, el) {
        const list = sel();
        remember();
        const on = el.type === 'checkbox' ? el.checked : !list.every((f) => f.props[k]);
        list.forEach((f) => { f.props = { ...f.props, [k]: on || undefined }; });
        changed();
    }

    // ------------------------------------------------------------------ keyboard

    function removeSelected() {
        if (!selected.size) return;
        remember();
        fields = fields.filter((f) => !selected.has(f.id));
        selected.clear();
        changed();
    }
    function duplicate() {
        const list = sel();
        if (!list.length) return;
        remember();
        const copies = list.map((f) => clamp({ ...f, id: newId(), x: f.x + 10, y: f.y + 10, props: { ...f.props }, link: null }));
        fields.push(...copies);
        selected = new Set(copies.map((f) => f.id));
        changed();
    }
    function paste() {
        if (!clipboard.length) return;
        remember();
        const page = sel()[0]?.page || clipboard[0].page;
        const copies = clipboard.map((f) => clamp({ ...f, id: newId(), page, x: f.x + 10, y: f.y + 10, props: { ...f.props }, link: null,
            signer: f.signer && !signer(f.signer) ? active : f.signer }));
        fields.push(...copies);
        selected = new Set(copies.map((f) => f.id));
        changed();
    }

    document.addEventListener('keydown', (e) => {
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || '');
        const ctrl = e.ctrlKey || e.metaKey;
        if (ctrl && e.key.toLowerCase() === 'z' && !typing) {
            e.preventDefault();
            e.shiftKey ? redo() : undo();
            return;
        }
        if (ctrl && e.key.toLowerCase() === 'y' && !typing) {
            e.preventDefault();
            redo();
            return;
        }
        if (typing) return;
        if (e.key === 'Escape') {
            tool = null;
            selected.clear();
            markTool();
            renderFields();
            renderProps();
        } else if ((e.key === 'Delete' || e.key === 'Backspace') && selected.size) {
            e.preventDefault();
            removeSelected();
        } else if (ctrl && e.key.toLowerCase() === 'c' && selected.size) {
            clipboard = sel().map((f) => ({ ...f, props: { ...f.props } }));
        } else if (ctrl && e.key.toLowerCase() === 'v') {
            e.preventDefault();
            paste();
        } else if (ctrl && e.key.toLowerCase() === 'd') {
            e.preventDefault();
            duplicate();
        } else if (ctrl && e.key.toLowerCase() === 'a' && selected.size) {
            e.preventDefault();
            const page = sel()[0].page;
            fields.forEach((f) => f.page === page && selected.add(f.id));
            renderFields();
            renderProps();
        } else if (e.key.startsWith('Arrow') && selected.size) {
            e.preventDefault();
            remember();
            const step = e.shiftKey ? 10 : 1;
            sel().forEach((f) => {
                if (f.props.locked) return;
                if (e.key === 'ArrowLeft') f.x -= step;
                if (e.key === 'ArrowRight') f.x += step;
                if (e.key === 'ArrowUp') f.y -= step;
                if (e.key === 'ArrowDown') f.y += step;
                clamp(f);
            });
            changed();
        }
    });

    // ------------------------------------------------------------------ checks and sending

    function warnings() {
        const out = [];
        for (let i = 0; i < fields.length; i++) {
            for (let j = i + 1; j < fields.length; j++) {
                const a = fields[i];
                const b = fields[j];
                if (a.page === b.page && a.x < b.x + b.w - 1 && b.x < a.x + a.w - 1 && a.y < b.y + b.h - 1 && b.y < a.y + a.h - 1) {
                    out.push(tr(T.overlap, { page: a.page }));
                    return out;
                }
            }
        }
        return out;
    }

    function renderProblems() {
        const list = [...problems.map((p) => ['danger', p]), ...warnings().map((w) => ['warning', w])];
        $('es-problems').innerHTML = list.length
            ? list.map(([c, m]) => `<li class="text-${c}">${esc(m)}</li>`).join('')
            : `<li class="text-success list-unstyled ms-n3"><i class="ti ti-circle-check me-1"></i>${esc(T.ready)}</li>`;
    }

    async function send() {
        try {
            await save(true);
        } catch (e) {
            return;
        }
        if (problems.length) {
            Phai.toast(problems[0], 'danger');
            return;
        }
        const first = signers.filter((s) => s.role !== 'C').sort((a, b) => a.step - b.step)[0];
        if (!(await Phai.confirm(tr(T.send_confirm, { name: first?.name || '' })))) return;
        await Phai.api.post(`/api/envelopes/${nbr}/send`);
        location.href = `${base}/envelopes/${nbr}`;
    }

    // ------------------------------------------------------------------ wiring

    function renderAll() {
        renderSigners();
        renderFields();
        renderProps();
        renderProblems();
    }

    $('es-nbr').textContent = nbr;
    $('es-title').value = D.env.env_title;
    $('es-note').value = D.env.env_note || '';
    $('es-title').addEventListener('input', () => save());
    $('es-note').addEventListener('input', () => save());
    document.querySelectorAll('input[type=file][data-kind]').forEach((i) => i.addEventListener('change', () => upload(i)));
    $('es-files').addEventListener('click', (e) => {
        const b = e.target.closest('[data-file]');
        if (b) fileAction(b.closest('.es-file').dataset.id, b.dataset.file);
    });
    $('es-add-signer').addEventListener('click', () => addSigner('U'));
    $('es-add-outside').addEventListener('click', () => addSigner('E'));
    $('es-signers').addEventListener('change', (e) => {
        if (e.target.name === 'es-active') {
            active = e.target.closest('.es-signer').dataset.key;
            renderSigners();
        } else if (e.target.dataset.s) signerInput(e.target);
    });
    $('es-signers').addEventListener('click', (e) => {
        const b = e.target.closest('button[data-s]');
        if (b) signerButton(b);
        else if (e.target.closest('.es-signer') && !e.target.closest('input,select,button')) {
            active = e.target.closest('.es-signer').dataset.key;
            renderSigners();
        }
    });
    document.querySelectorAll('.es-tools').forEach((box) => box.addEventListener('click', (e) => {
        const b = e.target.closest('[data-tool]');
        if (!b) return;
        tool = tool === b.dataset.tool ? null : b.dataset.tool;
        markTool();
    }));
    $('es-view').addEventListener('change', (e) => {
        viewAs = e.target.value;
        selected.clear();
        renderFields();
        renderProps();
    });
    $('es-snap').addEventListener('change', () => {});
    $('es-props').addEventListener('change', (e) => {
        const el = e.target;
        if (el.id === 'es-copy-where') {
            $('es-copy-custom').hidden = el.value !== 'custom';
            return;
        }
        if (el.id === 'es-del-where') {
            $('es-del-custom').hidden = el.value !== 'custom';
            return;
        }
        if (el.dataset.toggle) propToggle(el.dataset.toggle, el);
        else if (el.dataset.p || el.dataset.geo) propInput(el);
    });
    $('es-props').addEventListener('input', (e) => {
        if (e.target.dataset.p === 'text') {
            const list = sel();
            list.forEach((f) => { f.props = { ...f.props, text: e.target.value }; });
            propagateLinks();
            renderFields();
            save();
        }
    });
    $('es-props').addEventListener('focusin', (e) => {
        if (e.target.dataset.p === 'text') remember();
    });
    $('es-props').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        if (b.dataset.toggle) propToggle(b.dataset.toggle, b);
        else if (b.dataset.align) {
            remember();
            sel().forEach((f) => { f.props = { ...f.props, align: b.dataset.align }; });
            changed();
        } else if (b.dataset.arrange) arrange(b.dataset.arrange);
        else if (b.dataset.act) panelAction(b.dataset.act);
    });
    document.querySelectorAll('[data-zoom]').forEach((b) => b.addEventListener('click', () => {
        if (!view) return;
        const z = +b.dataset.zoom;
        view.zoom(z === 0 ? fitScale() : Math.max(0.3, Math.min(4, view.scale * (z > 0 ? 1.2 : 1 / 1.2))));
        renderFields();
    }));
    $('es-undo').addEventListener('click', undo);
    $('es-redo').addEventListener('click', redo);
    $('es-send').addEventListener('click', send);
    $('es-delete').addEventListener('click', async () => {
        if (!(await Phai.confirm(T.delete_confirm))) return;
        clearTimeout(saveTimer);
        await Phai.api.del(`/api/envelopes/${nbr}`);
        location.href = `${base}/envelopes`;
    });
    window.addEventListener('beforeunload', (e) => {
        if (saveTimer !== null && $('es-saved').textContent === T.saving) {
            flush();
            e.preventDefault();
        }
    });

    renderFiles();
    renderTools();
    renderAll();
    buildPages();
    Phai.api.get('/api/envelopes/people').then((list) => {
        people = list;
        renderSigners();
    });
})();
