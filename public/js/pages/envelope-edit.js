/**
 * ตัวแก้ซองเอกสาร (views/esign/edit.php): files, signers in steps, and fields placed on the pages of the main
 * documents. Everything is in points from the page's top left, as the page is shown; the screen draws it at
 * `scale` pixels per point. Changes save themselves (PUT /api/envelopes/{nbr}) a moment after the last one.
 *
 * Layout: a toolbar on top (documents, signers and the message in dialogs; Add signer opens a dialog that adds the
 * person and places a block of their fields), and a format bar for what is selected; the pages take the full width.
 *
 * Fields: a tool from the toolbar, then a click on a page. Select by clicking (Shift adds), or drag a box around
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
    // what the sender places, owned by no signer; the company stamp is one of them (it belongs to the document)
    const SENDER_TYPES = ['stamp', 'label', 'mark'];
    const SIZES = {
        signature: [150, 40], initials: [50, 30], name: [140, 16], position: [140, 16], date: [90, 16], timeline: [230, 14],
        stamp: [70, 70], text: [140, 18], checkbox: [14, 14], label: [160, 20], mark: [110, 30],
    };
    const ICONS = {
        signature: 'signature', initials: 'letter-case', name: 'user', position: 'id-badge-2', date: 'calendar', timeline: 'clock',
        stamp: 'rosette', text: 'forms', checkbox: 'checkbox', label: 'typography', mark: 'rubber-stamp',
    };
    const TEXTY = ['label', 'mark', 'text', 'name', 'position', 'date', 'timeline'];
    // Ready-made text boxes for the signer to fill in (what e-sign services offer as standard fields): the label is
    // what the signer sees in the box, `input` the kind of answer the signing page asks for and the server checks.
    const TEXT_BOXES = {
        text: { icon: 'forms' },
        email: { icon: 'mail', input: 'email', w: 160 },
        tel: { icon: 'phone', input: 'tel', w: 110 },
        company: { icon: 'building', w: 180 },
        taxid: { icon: 'file-certificate', input: 'numeric', w: 120 },
        address: { icon: 'map-pin', w: 230 },
        amount: { icon: 'cash', input: 'number', w: 100 },
        date: { icon: 'calendar-event', input: 'date', w: 90 },
        note: { icon: 'note', w: 200 },
    };
    // fonts a field can use: only ones the final PDF can draw offline (bundled with PhaiERP, or on Phai Server)
    // (handwriting ones: public/fonts/handwriting; the overlay that stamps the PDF loads the same files)
    const FONT_NAMES = { plex: 'IBM Plex Sans Thai', noto: 'Noto Sans Thai', charm: 'Charm', charmonman: 'Charmonman', itim: 'Itim', mali: 'Mali', sriracha: 'Sriracha' };
    const FONTS = Object.fromEntries(Object.entries(FONT_NAMES).map(([k, n]) => [k, `'${n}', 'IBM Plex Sans Thai', sans-serif`]));
    // how a signing-date field shows the day; the same as Envelope::dateAs() on the server, which stamps the PDF
    const DATE_FORMATS = ['th_short', 'th_long', 'num_be', 'num_ce', 'iso', 'en'];
    function dateAs(d, fmt) {
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
    // the field buttons on the toolbar, in this order; text boxes have their own menu beside them
    const QUICK = ['signature', 'initials', 'name', 'position', 'date', 'timeline', 'checkbox'];
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
    let preset = null; // which ready-made text box, when tool is 'text'
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
    let changes = 0; // counts changes, so a save that finishes knows whether newer ones are still waiting
    function save(now = false) {
        clearTimeout(saveTimer);
        $('es-saved').textContent = T.saving;
        $('es-save').classList.add('es-dirty');
        changes++;
        if (now) return flush();
        saveTimer = setTimeout(flush, 1200);
        return null;
    }
    async function flush() {
        clearTimeout(saveTimer);
        if (saving) await saving.catch(() => {});
        const upTo = changes;
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
            // the button stays tinted when something changed again while this was on its way
            if (upTo === changes) {
                $('es-saved').textContent = tr(T.saved_at, { time: new Date().toTimeString().slice(0, 5) });
                $('es-save').classList.remove('es-dirty');
            }
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
        $('es-files-n').textContent = files.length || '';
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
        $('es-signers-n').textContent = signers.length || '';
        if (!signers.length) {
            box.innerHTML = `<div class="small text-secondary">${esc(T.no_signers)}</div>`;
        } else {
            // names in signing order, nothing to edit here: who they are was set when they were added; only the order
            // changes (up / down), and a person can be taken out
            let step = 0;
            const last = signers.length - 1;
            box.innerHTML = signers.map((s, i) => {
                const head = s.step !== step ? `<div class="es-step">${esc(tr(T.step_n, { n: s.step }))}</div>` : '';
                step = s.step;
                const about = [s.position, T['role_' + s.role], s.kind === 'E' ? T.who_outside : ''].filter(Boolean).join(' · ');
                return `${head}<div class="es-signer es-srow${s.key === active ? ' es-active' : ''}" style="--es-c:${esc(s.color)}" data-key="${esc(s.key)}">
                    <span class="es-dot"></span>
                    <span class="es-sname"><b>${esc(s.name || T.signer)}</b> <small>${esc(about)}</small></span>
                    <span class="small text-secondary text-nowrap">${fields.filter((f) => f.signer === s.key).length} ${esc(T.fields_short)}</span>
                    <button type="button" class="btn btn-sm btn-ghost-secondary" data-s="earlier" title="${esc(T.earlier)}"${i === 0 && signers.filter((x) => x.step === s.step).length === 1 ? ' disabled' : ''}><i class="ti ti-arrow-up"></i></button>
                    <button type="button" class="btn btn-sm btn-ghost-secondary" data-s="later" title="${esc(T.later)}"${i === last && signers.filter((x) => x.step === s.step).length === 1 ? ' disabled' : ''}><i class="ti ti-arrow-down"></i></button>
                    <button type="button" class="btn btn-sm btn-ghost-danger" data-s="remove" title="${esc(T.remove)}"><i class="ti ti-x"></i></button>
                </div>`;
            }).join('');
        }
        renderToolHead();
        renderViewAs();
    }

    /**
     * Up / down in the signing order. Alone in a step, the person swaps places with the step before / after; sharing
     * a step (signing together), they leave it for a step of their own just before / after it.
     */
    function moveSigner(s, dir) {
        const steps = [...new Set(signers.map((x) => x.step))].sort((a, b) => a - b);
        if (signers.filter((x) => x.step === s.step).length === 1) {
            const other = steps[steps.indexOf(s.step) + dir];
            if (other === undefined) return false;
            const mine = s.step;
            signers.forEach((x) => { if (x.step === other) x.step = mine; });
            s.step = other;
        } else {
            signers.forEach((x) => { if (x !== s && (dir < 0 ? x.step >= s.step : x.step > s.step)) x.step += 1; });
            if (dir > 0) s.step += 1;
        }
        // keep steps 1, 2, 3... without gaps
        const now = [...new Set(signers.map((x) => x.step))].sort((a, b) => a - b);
        signers.forEach((x) => { x.step = now.indexOf(x.step) + 1; });
        return true;
    }

    async function signerButton(el) {
        const row = el.closest('.es-signer');
        const s = signer(row?.dataset.key);
        if (!s) return;
        const what = el.dataset.s;
        if (what === 'earlier' || what === 'later') {
            const before = snapshot();
            if (!moveSigner(s, what === 'earlier' ? -1 : 1)) return;
            undoStack.push(before);
            redoStack.length = 0;
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
        $('es-tools-signer').innerHTML = QUICK.map((t) =>
            `<button type="button" class="btn btn-sm" data-tool="${t}" title="${esc(T['type_' + t])}" aria-label="${esc(T['type_' + t])}"><i class="ti ti-${ICONS[t]}"></i></button>`).join('');
        $('es-tools-text').innerHTML = `<h6 class="dropdown-header">${esc(T.text_boxes)}</h6>` + Object.entries(TEXT_BOXES).map(([k, b]) =>
            `<button type="button" class="dropdown-item" data-tool="text" data-preset="${k}"><i class="ti ti-${b.icon} me-2"></i>${esc(T['tb_' + k])}</button>`).join('');
        $('es-tools-sender').innerHTML = SENDER_TYPES.map((t) =>
            `<button type="button" class="btn btn-sm" data-tool="${t}" title="${esc(T['type_' + t])}" aria-label="${esc(T['type_' + t])}"><i class="ti ti-${ICONS[t]}"></i></button>`).join('');
        markTool();
    }
    /** "Fields for": whose the next placed field is (not those who only acknowledge: they have none). */
    function renderToolHead() {
        const can = signers.filter((s) => s.role !== 'C');
        if (!can.some((s) => s.key === active)) active = can[0]?.key ?? null;
        const s = signer(active);
        $('es-active-dot').style.setProperty('--es-c', s?.color || '#888');
        $('es-active').innerHTML = can.length
            ? can.map((x) => `<option value="${esc(x.key)}"${x.key === active ? ' selected' : ''}>${esc(x.name || T.signer)}</option>`).join('')
            : `<option value="">${esc(T.no_active)}</option>`;
        $('es-active').disabled = !can.length;
        document.querySelectorAll('#es-tools-signer [data-tool]').forEach((b) => { b.disabled = !s; });
        $('es-add-text').disabled = !s;
    }
    function markTool() {
        document.querySelectorAll('[data-tool]').forEach((b) => b.classList.toggle('active', b.dataset.tool === tool && (b.dataset.preset || null) === (tool === 'text' ? preset : null)));
        $('es-add-text').classList.toggle('active', tool === 'text');
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
        if (f.type === 'text' && f.props.label) return f.props.label + (s?.name ? ' · ' + s.name : '');
        if (f.type === 'date') return dateAs(new Date(), f.props.datefmt) + (s?.name ? ' · ' + s.name : '');
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
                el.style.textDecoration = f.props.underline ? 'underline' : '';
                el.style.fontFamily = FONTS[f.props.font] || '';
                // the text colour, as the final PDF draws it (the box keeps its signer's colour)
                if (f.props.color && f.type !== 'mark' && f.type !== 'label') el.style.color = f.props.color;
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
        const box = type === 'text' && preset && preset !== 'text' ? TEXT_BOXES[preset] : null;
        const [w, h] = box?.w ? [box.w, SIZES.text[1]] : SIZES[type];
        const f = clamp({
            id: newId(), signer: isSender ? null : s.key, page: p.n, type, x: snap(x - w / 2), y: snap(y - h / 2), w, h,
            props: type === 'mark' ? { text: (T.marks || '').split('|')[0], color: '#c92a2a', bold: true, size: 12 }
                : type === 'label' ? { text: T.type_label, size: 10 }
                : box ? { label: T['tb_' + preset], ...(box.input ? { input: box.input } : {}) }
                : ['signature', 'initials'].includes(type) ? { required: true } : {},
            link: null,
        });
        fields.push(f);
        selected = new Set([f.id]);
        tool = null;
        preset = null;
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
        // drag a box to select what it touches (Shift / Ctrl / Cmd adds to what is selected)
        if (!(e.shiftKey || e.ctrlKey || e.metaKey)) selected.clear();
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
        // Shift, Ctrl or Cmd + click adds a field to the selection, or takes it out
        if (e.shiftKey || e.ctrlKey || e.metaKey) {
            selected.has(f.id) ? selected.delete(f.id) : selected.add(f.id);
            renderFields();
            renderProps();
            return;
        }
        if (!selected.has(f.id)) selected = new Set([f.id]);
        const resizing = e.target.classList.contains('es-h');
        const s = view.scale;
        const moving = [...selected].map(field).filter((x) => x && !x.props.locked && x.page === f.page);
        const start = { x: e.clientX, y: e.clientY, items: moving.map((x) => ({ f: x, x: x.x, y: x.y, w: x.w, h: x.h, size: x.props.size || (x.type === 'mark' ? 12 : 10) })) };
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
                    clamp(it.f);
                    // text grows and shrinks with the box's height, in half points
                    if (TEXTY.includes(it.f.type)) {
                        const size = Math.max(5, Math.min(72, Math.round(it.size * (it.f.h / it.h) * 2) / 2));
                        it.f.props = { ...it.f.props, size };
                        const el = p.layer.querySelector(`.es-field[data-id="${it.f.id}"]`);
                        if (el) el.style.fontSize = Math.max(6, Math.min(size * s, it.f.h * s * 0.8)) + 'px';
                    }
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

    /**
     * The format bar under the toolbar, shown while something is selected: font, alignment, position and size,
     * copy to / remove from other pages, then duplicate, lock and delete. Small panels open as dropdowns; one that
     * was open stays open when the bar is drawn again after a change.
     */
    function renderProps() {
        const box = $('es-props');
        const open = box.querySelector('.dropdown-menu.show')?.dataset.dd;
        const list = sel();
        // the bar keeps its place when nothing is selected, so the pages under it do not jump
        if (!list.length) {
            box.innerHTML = `<span class="es-hint"><i class="ti ti-pointer me-1"></i>${esc(T.format_hint)}</span>`;
            return;
        }
        const f = list[0];
        const one = list.length === 1;
        // font and alignment come from the first text-like field, so a selected block (signature + name...) gets them too
        const tf = list.find((x) => TEXTY.includes(x.type));
        const p = (tf || f).props;
        const btn =(attr, icon, title, on = false, extra = '') => `<button type="button" class="btn btn-sm${on ? ' active' : ''}${extra}" ${attr} title="${esc(title)}"><i class="ti ti-${icon}"></i></button>`;
        const dd = (name, icon, label, body, end = false) => `<div class="dropdown">
                <button type="button" class="btn btn-sm" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="${esc(label)}" aria-label="${esc(label)}"><i class="ti ti-${icon}"></i></button>
                <div class="dropdown-menu ${end ? 'dropdown-menu-end ' : ''}p-2 es-menu" data-dd="${name}">${body}</div>
            </div>`;
        const sep = '<span class="es-sep"></span>';

        let html = `<span class="es-what">${esc(one ? T['type_' + f.type] : tr(T.n_selected, { n: list.length }))} <small>· ${esc(tr(T.page_n, { n: f.page }))}</small></span>`;
        if (one && f.signer !== null) {
            html += `<select class="form-select form-select-sm w-auto" data-p="signer" title="${esc(T.signer)}">${signers.filter((s) => s.role !== 'C').map((s) =>
                `<option value="${esc(s.key)}"${s.key === f.signer ? ' selected' : ''}>${esc(s.name || T.signer)}</option>`).join('')}</select>`;
        }
        if (one && (f.type === 'label' || f.type === 'mark')) {
            html += dd('text', 'pencil', T.text, `${f.type === 'mark' ? `<label>${esc(T.mark_preset)}</label><select class="form-select form-select-sm mb-2" data-p="preset"><option value="">—</option>${(T.marks || '').split('|').map((m) =>
                `<option${m === p.text ? ' selected' : ''}>${esc(m)}</option>`).join('')}</select>` : ''}
                <label>${esc(T.text)}</label><textarea class="form-control form-control-sm" rows="3" data-p="text">${esc(p.text || '')}</textarea>`);
        }
        if (one && f.type === 'text') {
            const kinds = { '': T.tb_text, email: T.tb_email, tel: T.tb_tel, number: T.tb_amount, numeric: T.tb_taxid, date: T.tb_date };
            html += dd('label', 'pencil', T.field_label, `<label>${esc(T.field_label)}</label><input class="form-control form-control-sm mb-2" maxlength="100" data-p="label" value="${esc(f.props.label || '')}">
                <label>${esc(T.text_boxes)}</label><select class="form-select form-select-sm" data-p="input">${Object.entries(kinds).map(([k, v]) =>
                    `<option value="${k}"${(f.props.input || '') === k ? ' selected' : ''}>${esc(v)}</option>`).join('')}</select>`);
        }
        if (list.every((x) => x.type === 'date')) {
            const now = new Date();
            html += `<select class="form-select form-select-sm w-auto" data-p="datefmt" title="${esc(T.date_format)}">${DATE_FORMATS.map((k) =>
                `<option value="${k}"${(f.props.datefmt || 'th_short') === k ? ' selected' : ''}>${esc(dateAs(now, k))}</option>`).join('')}</select>`;
        }
        if (tf) {
            const mark = tf.type === 'mark';
            // font sizes in pt as word processors list them; a size set some other way (A- / A+) stays in the list
            const size = +(p.size || (mark ? 12 : 10));
            const sizes = [...new Set([6, 7, 8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48, 72, size])].sort((a, b) => a - b);
            html += sep + `<select class="form-select form-select-sm w-auto" data-p="font" title="${esc(T.font)}">${Object.keys(FONTS).map((k) =>
                    `<option value="${k}"${(p.font || 'plex') === k ? ' selected' : ''} style="font-family:${esc(FONTS[k])}">${esc(FONT_NAMES[k])}</option>`).join('')}</select>`
                + `<select class="form-select form-select-sm es-size" data-p="size" title="${esc(T.font_size)}">${sizes.map((n) =>
                    `<option value="${n}"${n === size ? ' selected' : ''}>${n}</option>`).join('')}</select>`
                + btn('data-size="-1"', 'text-decrease', T.font_smaller) + btn('data-size="1"', 'text-increase', T.font_bigger)
                + btn('data-toggle="bold"', 'bold', T.bold, p.bold) + btn('data-toggle="italic"', 'italic', T.italic, p.italic) + btn('data-toggle="underline"', 'underline', T.underline, p.underline)
                + `<input type="color" class="form-control form-control-color" data-p="color" value="${esc(p.color || (mark ? '#c92a2a' : '#111111'))}" title="${esc(T.color)}">`
                + sep + ['left', 'center', 'right'].map((a) => btn(`data-align="${a}"`, `align-${a}`, T['align_' + a], (p.align || (mark ? 'center' : 'left')) === a)).join('');
        }
        if (list.some((x) => ['text', 'checkbox', 'signature', 'initials'].includes(x.type))) {
            html += sep + btn('data-toggle="required"', 'asterisk', T.required, list.every((x) => x.props.required));
        }
        // Arrange: to each other when several are selected (align, spread, same size), to the page always, and in front / behind
        const many = list.length > 1;
        const arr = (k, i) => btn(`data-arrange="${k}"`, i, T['arr_' + k]);
        html += sep + (many ? arr('left', 'layout-align-left') + arr('center', 'layout-align-center') + arr('top', 'layout-align-top') : '')
            + dd('arr', 'layout-board', T.arrange_menu, `${many ? `<h6 class="dropdown-header">${esc(T.arr_selection)}</h6>
                <div class="es-arr mb-2">${arr('left', 'layout-align-left')}${arr('center', 'layout-align-center')}${arr('right', 'layout-align-right')}${arr('top', 'layout-align-top')}${arr('middle', 'layout-align-middle')}${arr('bottom', 'layout-align-bottom')}
                    ${arr('hspace', 'layout-distribute-vertical')}${arr('vspace', 'layout-distribute-horizontal')}${arr('width', 'arrows-horizontal')}${arr('height', 'arrows-vertical')}${arr('size', 'dimensions')}</div>` : ''}
                <h6 class="dropdown-header">${esc(T.arr_page)}</h6>
                <div class="es-arr mb-2">${arr('page_center', 'layout-align-center')}${arr('page_middle', 'layout-align-middle')}</div>
                <div class="es-arr">${arr('front', 'stack-front')}${arr('back', 'stack-back')}</div>`);
        html += sep;
        if (one) {
            html += dd('geo', 'ruler-measure', T.geo, `<div class="row">${['x', 'y', 'w', 'h'].map((k) =>
                `<div class="col-6"><label>${esc(T['geo_' + k])}</label><input class="form-control form-control-sm" type="number" step="0.5" data-geo="${k}" value="${mm(f[k])}"></div>`).join('')}</div>`);
            html += dd('copy', 'copy', T.copy_to, `<select class="form-select form-select-sm mb-1" id="es-copy-where">${['all', 'not_first', 'not_last', 'odd', 'even', 'custom'].map((k) => `<option value="${k}">${esc(T['pages_' + k])}</option>`).join('')}</select>
                <input class="form-control form-control-sm mb-1" id="es-copy-custom" placeholder="${esc(T.pages_custom_hint)}" hidden>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="es-copy-link" checked><label class="form-check-label small" for="es-copy-link">${esc(T.linked)}</label></div>
                <button type="button" class="btn btn-sm btn-primary w-100" data-act="copy"><i class="ti ti-copy me-1"></i>${esc(T.copy_go)}</button>`);
            html += dd('del', 'trash-x', T.delete_from, `<select class="form-select form-select-sm mb-1" id="es-del-where">${['all', 'not_this', 'not_first', 'custom'].map((k) => `<option value="${k}">${esc(T['del_' + k])}</option>`).join('')}</select>
                <input class="form-control form-control-sm mb-1" id="es-del-custom" placeholder="${esc(T.pages_custom_hint)}" hidden>
                <button type="button" class="btn btn-sm btn-danger w-100 mt-1" data-act="delfrom"><i class="ti ti-trash me-1"></i>${esc(T.del_go)}</button>`);
            if (f.link) html += btn('data-act="unlink"', 'unlink', T.unlink);
        }
        html += '<span class="es-grow"></span>'
            + btn('data-act="duplicate"', 'copy-plus', `${T.duplicate} (Ctrl+D)`)
            + btn('data-act="lock"', 'lock', T.lock, list.every((x) => x.props.locked))
            + btn('data-act="delete"', 'trash', `${T.remove} (Delete)`, false, ' btn-ghost-danger');
        box.innerHTML = html;
        if (open) {
            const menu = box.querySelector(`[data-dd="${open}"]`);
            const bs = window.bootstrap || window.tabler;
            if (menu && bs?.Dropdown) bs.Dropdown.getOrCreateInstance(menu.previousElementSibling).show();
        }
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
        if (!list.length) return;
        // to the page, and in front / behind: work on one field as well as on several
        if (['page_center', 'page_middle', 'front', 'back'].includes(how)) {
            remember();
            if (how === 'front' || how === 'back') {
                const ids = new Set(list.map((f) => f.id));
                const rest = fields.filter((f) => !ids.has(f.id));
                fields = how === 'front' ? [...rest, ...list] : [...list, ...rest];
            } else {
                // the selection moves as one group, so a signature block stays together
                const [pw, ph] = pageSize(list[0].page);
                const minX = Math.min(...list.map((f) => f.x));
                const minY = Math.min(...list.map((f) => f.y));
                const dx = how === 'page_center' ? (pw - (Math.max(...list.map((f) => f.x + f.w)) - minX)) / 2 - minX : 0;
                const dy = how === 'page_middle' ? (ph - (Math.max(...list.map((f) => f.y + f.h)) - minY)) / 2 - minY : 0;
                list.forEach((f) => { f.x += dx; f.y += dy; clamp(f); });
            }
            changed();
            return;
        }
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
            if (how === 'size' || how === 'width') f.w = first.w;
            if (how === 'size' || how === 'height') f.h = first.h;
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
        const n = $('es-check-n');
        n.textContent = list.length || '✓';
        n.className = `badge ms-1 ${problems.length ? 'bg-red-lt' : list.length ? 'bg-orange-lt' : 'bg-green-lt'}`;
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
            const bs = window.bootstrap || window.tabler;
            if (bs?.Dropdown) bs.Dropdown.getOrCreateInstance($('es-check-btn')).show();
            return;
        }
        const first = signers.filter((s) => s.role !== 'C').sort((a, b) => a.step - b.step)[0];
        if (!(await Phai.confirm(tr(T.send_confirm, { name: first?.name || '' })))) return;
        await Phai.api.post(`/api/envelopes/${nbr}/send`);
        location.href = `${base}/envelopes/${nbr}`;
    }

    // ------------------------------------------------------------------ add a signer with a block of fields

    // What a new signer can get on the page, in the order the block stacks them; the first four are ticked at first,
    // after that whatever was ticked last time in this browser.
    const BLOCK = ['signature', 'name', 'position', 'date', 'initials', 'timeline', 'checkbox'];
    const BLOCK_KEY = 'phai-esign-block';
    let addModal = null;

    function lastChoice() {
        try {
            const v = JSON.parse(localStorage.getItem(BLOCK_KEY) || 'null');
            if (Array.isArray(v) && v.length) return v;
        } catch (_) { /* first time, or storage off */ }
        return ['signature', 'name', 'position', 'date'];
    }

    function addKind() {
        return document.querySelector('input[name="es-add-kind"]:checked').value;
    }
    function addShow() {
        const kind = addKind();
        document.querySelectorAll('[data-kind-only]').forEach((el) => { el.hidden = el.dataset.kindOnly !== kind; });
        const cc = $('es-add-role').value === 'C';
        $('es-add-fields-box').hidden = cc;
        $('es-add-cc').hidden = !cc;
    }

    function openAddSign() {
        if (!files.some((f) => f.envf_kind === 'M')) {
            Phai.toast(T.empty_add_sign, 'warning');
            return;
        }
        const bs = window.bootstrap || window.tabler;
        bs.Modal.getInstance($('es-signers-modal'))?.hide();
        $('es-add-u').checked = true;
        const taken = new Set(signers.map((s) => s.userid).filter(Boolean));
        $('es-add-user').innerHTML = `<option value="">${esc(T.pick_user)}</option>` + people.filter((p) => !taken.has(p.us_userid)).map((p) =>
            `<option value="${esc(p.us_userid)}">${esc(p.us_name || p.us_userid)}${p.us_email ? ' · ' + esc(p.us_email) : ''}</option>`).join('');
        ['es-add-name', 'es-add-email', 'es-add-position'].forEach((id) => { $(id).value = ''; $(id).classList.remove('is-invalid'); });
        $('es-add-user').classList.remove('is-invalid');
        $('es-add-role').value = 'S';
        const steps = [...new Set(signers.map((s) => s.step))].sort((a, b) => a - b);
        const next = steps.length ? steps[steps.length - 1] + 1 : 1;
        $('es-add-step').innerHTML = `<option value="${next}">${esc(tr(T.step_new, { n: next }))}</option>`
            + steps.map((n) => `<option value="${n}">${esc(tr(T.step_with, { n }))}</option>`).join('');
        const on = new Set(lastChoice());
        $('es-add-fields').innerHTML = BLOCK.map((t) => `<label class="form-check"><input class="form-check-input" type="checkbox" value="${t}"${on.has(t) ? ' checked' : ''}>
            <span class="form-check-label"><i class="ti ti-${ICONS[t]} me-1"></i>${esc(T['type_' + t])}</span></label>`).join('');
        const n = pageSizes.length;
        $('es-add-page').innerHTML = Array.from({ length: n }, (_, i) => i + 1).reverse().map((p) =>
            `<option value="${p}">${esc(p === n ? tr(T.page_last, { n }) : tr(T.page_n, { n: p }))}</option>`).join('');
        addShow();
        addModal ??= bs.Modal.getOrCreateInstance($('es-add-modal'));
        addModal.show();
    }

    /** A free place for a block w × h on the page: along the bottom first, left to right, then rows above. */
    function freeSpot(page, w, h) {
        const [pw, ph] = pageSize(page);
        const m = 36;
        const busy = fields.filter((f) => f.page === page);
        for (let row = 0; row < 8; row++) {
            const y = ph - m - h - row * (h + 16);
            if (y < m) break;
            for (let x = m; x + w <= pw - m; x += w + 20) {
                if (!busy.some((f) => f.x < x + w + 4 && x < f.x + f.w + 4 && f.y < y + h + 4 && y < f.y + f.h + 4)) return { x: snap(x), y: snap(y) };
            }
        }
        return { x: snap((pw - w) / 2), y: snap((ph - h) / 2) };
    }

    /** The fields of one signer's block, stacked and centred: the signature, then name, position and date under it. */
    function block(types, key, page) {
        const items = types.map((t) => {
            const [w, h] = SIZES[t];
            return { t, w: t === 'signature' || (TEXTY.includes(t) && t !== 'timeline') ? 170 : w, h };
        });
        const width = Math.max(...items.map((i) => i.w));
        const height = items.reduce((a, i) => a + i.h, 0) + 3 * (items.length - 1);
        const at = freeSpot(page, width, height);
        let y = at.y;
        return items.map((i) => {
            const f = clamp({
                id: newId(), signer: key, page, type: i.t, x: at.x + (width - i.w) / 2, y, w: i.w, h: i.h,
                props: ['signature', 'initials'].includes(i.t) ? { required: true } : TEXTY.includes(i.t) ? { align: 'center' } : {}, link: null,
            });
            y += i.h + 3;
            return f;
        });
    }

    function createSigner(e) {
        e.preventDefault();
        const kind = addKind();
        let userid = null;
        let name;
        let email;
        if (kind === 'U') {
            userid = $('es-add-user').value;
            if (!userid) {
                $('es-add-user').classList.add('is-invalid');
                return;
            }
            const p = people.find((x) => x.us_userid === userid);
            name = p?.us_name || userid;
            email = p?.us_email || '';
        } else {
            name = $('es-add-name').value.trim();
            email = $('es-add-email').value.trim();
            $('es-add-name').classList.toggle('is-invalid', !name);
            $('es-add-email').classList.toggle('is-invalid', !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email));
            if (!name || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
                Phai.toast(T.need_outsider, 'warning');
                return;
            }
        }
        const role = $('es-add-role').value;
        const types = role === 'C' ? [] : [...$('es-add-fields').querySelectorAll('input:checked')].map((i) => i.value);
        if (types.length) {
            try { localStorage.setItem(BLOCK_KEY, JSON.stringify(types)); } catch (_) { /* storage off */ }
        }
        remember();
        const used = new Set(signers.map((s) => s.color));
        const s = {
            key: newId(), step: +$('es-add-step').value, role, kind, userid, name, email, position: $('es-add-position').value.trim(),
            color: COLORS.find((c) => !used.has(c)) || COLORS[signers.length % COLORS.length],
        };
        signers.push(s);
        const page = +$('es-add-page').value;
        const added = types.length ? block(types, s.key, page) : [];
        fields.push(...added);
        if (role !== 'C') active = s.key;
        selected = new Set(added.map((f) => f.id));
        renderAll();
        save();
        // show where the block went, once the dialog is gone (closing it puts the focus back on the button above)
        $('es-add-modal').addEventListener('hidden.bs.modal', () => {
            const first = added[0] && view?.list[page - 1]?.layer.querySelector(`.es-field[data-id="${added[0].id}"]`);
            first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, { once: true });
        addModal.hide();
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
    $('es-add-sign').addEventListener('click', openAddSign);
    document.querySelector('[data-add-sign]').addEventListener('click', openAddSign);
    $('es-add-form').addEventListener('submit', createSigner);
    document.querySelectorAll('input[name="es-add-kind"]').forEach((r) => r.addEventListener('change', addShow));
    $('es-add-role').addEventListener('change', addShow);
    $('es-add-user').addEventListener('change', (e) => e.target.classList.remove('is-invalid'));
    $('es-active').addEventListener('change', (e) => {
        active = e.target.value;
        renderSigners();
    });
    const noteDot = () => { $('es-note-dot').hidden = !$('es-note').value.trim(); };
    $('es-note').addEventListener('input', noteDot);
    noteDot();
    $('es-signers').addEventListener('click', (e) => {
        const b = e.target.closest('button[data-s]');
        if (b) signerButton(b);
        else if (e.target.closest('.es-signer')) {
            // a click on a name makes new fields theirs (unless they only acknowledge)
            const s = signer(e.target.closest('.es-signer').dataset.key);
            if (s && s.role !== 'C') active = s.key;
            renderSigners();
        }
    });
    document.querySelectorAll('.es-tools').forEach((box) => box.addEventListener('click', (e) => {
        const b = e.target.closest('[data-tool]');
        if (!b) return;
        if (SIGNER_TYPES.includes(b.dataset.tool) && !signer(active)) {
            Phai.toast(T.need_active, 'warning');
            return;
        }
        const again = tool === b.dataset.tool && preset === (b.dataset.preset || null);
        tool = again ? null : b.dataset.tool;
        preset = again ? null : (b.dataset.preset || null);
        markTool();
        if (tool) Phai.toast(tr(T.place_hint, { type: preset ? T['tb_' + preset] : T['type_' + tool] }), 'info');
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
        if (el.dataset.p === 'color') {
            // already applied while picking (below); now the picker is closed, so the bar may be drawn again
            colorPicking = false;
            changed();
            return;
        }
        if (el.dataset.toggle) propToggle(el.dataset.toggle, el);
        else if (el.dataset.p || el.dataset.geo) propInput(el);
    });
    // The colour picker sends "input" while it is open and "change" only when it closes: apply each colour at once,
    // without drawing the bar again (that would close the picker), and remember the state before the first one.
    let colorPicking = false;
    $('es-props').addEventListener('input', (e) => {
        const k = e.target.dataset.p;
        if (k === 'color') {
            if (!colorPicking) {
                remember();
                colorPicking = true;
            }
            sel().forEach((f) => { f.props = { ...f.props, color: e.target.value }; });
            propagateLinks();
            renderFields();
            save();
            return;
        }
        if (k === 'text' || k === 'label') {
            const list = sel();
            list.forEach((f) => { f.props = { ...f.props, [k]: e.target.value }; });
            propagateLinks();
            renderFields();
            save();
        }
    });
    $('es-props').addEventListener('focusin', (e) => {
        if (e.target.dataset.p === 'text' || e.target.dataset.p === 'label') remember();
    });
    $('es-props').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        if (b.dataset.toggle) propToggle(b.dataset.toggle, b);
        else if (b.dataset.align) {
            remember();
            sel().forEach((f) => { f.props = { ...f.props, align: b.dataset.align }; });
            changed();
        } else if (b.dataset.size) {
            // one step smaller / bigger for every text-like field selected
            remember();
            sel().filter((f) => TEXTY.includes(f.type)).forEach((f) => {
                const now = f.props.size || (f.type === 'mark' ? 12 : 10);
                f.props = { ...f.props, size: Math.max(5, Math.min(72, now + (+b.dataset.size) * (now >= 14 ? 2 : 1))) };
            });
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
    // Save now (changes also save themselves a moment after the last one, so nothing is lost if this is forgotten)
    $('es-save').addEventListener('click', async () => {
        const b = $('es-save');
        Phai.busy(b, true);
        try {
            await save(true);
            Phai.toast(tr(T.saved_at, { time: new Date().toTimeString().slice(0, 5) }), 'success');
        } catch (_) { /* flush showed why */ } finally {
            Phai.busy(b, false);
        }
    });
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

    /** The pages box fills the window below the bars and scrolls on its own, so pages never slide under the bars. */
    function fitHeight() {
        // the bars also stick just under the app's top bar, in case the window itself scrolls (a short window)
        const bar = document.querySelector('.phai-topbar');
        const barBottom = bar && getComputedStyle(bar).position !== 'static' ? bar.getBoundingClientRect().bottom : 0;
        document.querySelector('.es-top').style.setProperty('--es-top', Math.max(0, barBottom) + 'px');
        const box = $('es-pages');
        const top = box.getBoundingClientRect().top + window.scrollY;
        box.style.height = Math.max(320, window.innerHeight - top - 16) + 'px';
    }
    window.addEventListener('resize', fitHeight);
    // the format bar can wrap onto a second line as the selection changes
    new ResizeObserver(fitHeight).observe(document.querySelector('.es-top'));

    renderFiles();
    renderTools();
    renderAll();
    fitHeight();
    buildPages();
    Phai.api.get('/api/envelopes/people').then((list) => {
        people = list;
        renderSigners();
    });
})();
