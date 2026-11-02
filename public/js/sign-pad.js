/**
 * Signature pad: draw a signature with a mouse or a finger, or upload a picture of one, and get a transparent PNG
 * trimmed to the ink and scaled to fit w × h (600 × 200 for a signature, 400 × 400 for a stamp — what printed
 * documents show at 40 × 15 mm and 26 × 26 mm). A photo of a signature on white paper works: the paper goes
 * transparent, the ink keeps its colour.
 *   PhaiSign.open({title, text, w, h, image, save: async (png) => {...}, remove: async () => {...}})
 *   PhaiSign.fromFile(file, w, h) -> PNG data URL
 * The text is the "sign" language group (lang/th/sign.php), passed in by the page.
 */
window.PhaiSign = (function () {
    'use strict';

    const INK = '#1d2b7a';

    /** Paper to transparent: what is near white goes, darker keeps its colour with more and more opacity. */
    function whiten(data) {
        const px = data.data;
        let seeThrough = 0;
        for (let i = 3; i < px.length; i += 4) if (px[i] < 250) seeThrough++;
        if (seeThrough > px.length / 4 * 0.02) return; // already transparent: leave it be
        for (let i = 0; i < px.length; i += 4) {
            const lum = 0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2];
            px[i + 3] = Math.round(px[i + 3] * Math.max(0, Math.min(1, (225 - lum) / 85)));
        }
    }

    /** The source (image or canvas) as a trimmed, transparent PNG no bigger than w × h; null when nothing is on it. */
    function process(src, w, h) {
        const sw = src.naturalWidth || src.width;
        const sh = src.naturalHeight || src.height;
        const fit = Math.min(1, 2000 / Math.max(sw, sh));
        const c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(sw * fit));
        c.height = Math.max(1, Math.round(sh * fit));
        const ctx = c.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(src, 0, 0, c.width, c.height);
        const data = ctx.getImageData(0, 0, c.width, c.height);
        whiten(data);
        ctx.putImageData(data, 0, 0);
        // trim to the ink, with a little room around it
        const px = data.data;
        let x0 = c.width, y0 = c.height, x1 = -1, y1 = -1;
        for (let y = 0; y < c.height; y++) {
            for (let x = 0; x < c.width; x++) {
                if (px[(y * c.width + x) * 4 + 3] > 24) {
                    if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y;
                }
            }
        }
        if (x1 < 0) return null;
        const pad = Math.round(Math.max(x1 - x0, y1 - y0) * 0.03) + 2;
        x0 = Math.max(0, x0 - pad); y0 = Math.max(0, y0 - pad);
        x1 = Math.min(c.width - 1, x1 + pad); y1 = Math.min(c.height - 1, y1 + pad);
        const bw = x1 - x0 + 1, bh = y1 - y0 + 1;
        const scale = Math.min(w / bw, h / bh);
        const out = document.createElement('canvas');
        out.width = Math.max(16, Math.round(bw * scale));
        out.height = Math.max(16, Math.round(bh * scale));
        const octx = out.getContext('2d');
        octx.imageSmoothingQuality = 'high';
        octx.drawImage(c, x0, y0, bw, bh, 0, 0, out.width, out.height);
        return out.toDataURL('image/png');
    }

    function fromFile(file, w, h) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const src = URL.createObjectURL(file);
            img.onload = () => { URL.revokeObjectURL(src); const png = process(img, w, h); png ? resolve(png) : reject(new Error('empty')); };
            img.onerror = () => { URL.revokeObjectURL(src); reject(new Error('image')); };
            img.src = src;
        });
    }

    /** A dialog with a drawing area, upload, clear, remove and save. */
    function open(opt) {
        const tx = opt.text;
        const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
        const el = document.createElement('div');
        el.className = 'modal';
        el.tabIndex = -1;
        el.innerHTML = `
<div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">${esc(opt.title || tx.pad_title)}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="phai-sign-current mb-2" ${opt.image ? '' : 'hidden'}><img alt="" style="max-height: 70px; max-width: 100%"></div>
    <div class="small text-secondary mb-1">${esc(tx.pad_draw)}</div>
    <canvas width="600" height="200" style="width: 100%; aspect-ratio: 3 / 1; border: 1px dashed #aab2bd; border-radius: 6px; touch-action: none; background: #fff; cursor: crosshair"></canvas>
    <div class="small text-secondary mt-2">${esc(tx.pad_hint)}</div>
  </div>
  <div class="modal-footer d-flex">
    <input type="file" accept="image/png,image/jpeg,image/webp" hidden>
    <button type="button" class="btn btn-sm" data-upload><i class="ti ti-upload me-1"></i>${esc(tx.pad_upload)}</button>
    <button type="button" class="btn btn-sm" data-clear><i class="ti ti-eraser me-1"></i>${esc(tx.pad_clear)}</button>
    ${opt.remove && opt.image ? `<button type="button" class="btn btn-sm btn-ghost-danger" data-remove><i class="ti ti-trash me-1"></i>${esc(tx.pad_remove)}</button>` : ''}
    <button type="button" class="btn btn-sm btn-primary ms-auto" data-save><i class="ti ti-check me-1"></i>${esc(tx.pad_save)}</button>
  </div>
</div></div>`;
        document.body.appendChild(el);
        if (opt.image) el.querySelector('.phai-sign-current img').src = opt.image;
        const modal = (window.bootstrap || window.tabler).Modal.getOrCreateInstance(el);
        el.addEventListener('hidden.bs.modal', () => el.remove());

        const canvas = el.querySelector('canvas');
        const ctx = canvas.getContext('2d');
        let drawn = false, last = null;
        const at = (e) => {
            const r = canvas.getBoundingClientRect();
            return [(e.clientX - r.left) * canvas.width / r.width, (e.clientY - r.top) * canvas.height / r.height];
        };
        canvas.addEventListener('pointerdown', (e) => { canvas.setPointerCapture(e.pointerId); last = at(e); });
        canvas.addEventListener('pointermove', (e) => {
            if (!last) return;
            const p = at(e);
            ctx.strokeStyle = INK; ctx.lineWidth = 4; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
            ctx.beginPath(); ctx.moveTo(last[0], last[1]); ctx.lineTo(p[0], p[1]); ctx.stroke();
            last = p;
            drawn = true;
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach((t) => canvas.addEventListener(t, () => { last = null; }));

        const busy = (btn, on) => (window.Phai?.busy ? Phai.busy(btn, on) : (btn.disabled = on));
        const fail = (msg) => (window.Phai?.toast ? Phai.toast(msg, 'danger') : alert(msg));
        const save = async (png, btn) => {
            busy(btn, true);
            try {
                await opt.save(png);
                modal.hide();
            } catch (err) {
                fail(err.fields ? Object.values(err.fields).join(' ') : (err.message || tx.pad_bad));
            } finally {
                busy(btn, false);
            }
        };
        const file = el.querySelector('input[type=file]');
        el.querySelector('[data-upload]').addEventListener('click', () => file.click());
        file.addEventListener('change', async () => {
            const f = file.files[0];
            file.value = '';
            if (!f) return;
            try {
                await save(await fromFile(f, opt.w, opt.h), el.querySelector('[data-upload]'));
            } catch (_) {
                fail(tx.pad_bad);
            }
        });
        el.querySelector('[data-clear]').addEventListener('click', () => { ctx.clearRect(0, 0, canvas.width, canvas.height); drawn = false; });
        el.querySelector('[data-remove]')?.addEventListener('click', async (e) => {
            busy(e.currentTarget, true);
            try { await opt.remove(); modal.hide(); } finally { busy(e.currentTarget, false); }
        });
        el.querySelector('[data-save]').addEventListener('click', (e) => {
            const png = drawn ? process(canvas, opt.w, opt.h) : null;
            if (!png) return fail(tx.pad_empty);
            save(png, e.currentTarget);
        });
        modal.show();
    }

    return { open, fromFile, process };
})();
