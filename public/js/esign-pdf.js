/**
 * Pages of an envelope's PDFs on screen, with pdf.js (public/vendor/pdfjs, loaded on first use):
 *   const view = PhaiPdf.pages(box, [{url, pages: [[w, h], ...]}], scale)
 *   view.list  -> [{el, layer, w, h, n}]  one per page in order; w, h in points as the page is shown
 *   view.zoom(scale)                       pixels per point; pages are drawn when scrolled near
 * The page sizes come from the server (Reader), so the layout is right before pdf.js has drawn anything.
 */
window.PhaiPdf = (function () {
    'use strict';

    const base = (document.querySelector('meta[name="phai-base"]')?.content || '').replace(/\/$/, '');
    let lib = null;

    async function load() {
        if (!lib) {
            lib = await import(base + '/vendor/pdfjs/pdf.min.js');
            lib.GlobalWorkerOptions.workerSrc = base + '/vendor/pdfjs/pdf.worker.min.js';
        }
        return lib;
    }

    async function open(url) {
        const pdfjs = await load();
        return pdfjs.getDocument({ url, isEvalSupported: false, withCredentials: true }).promise;
    }

    function pages(box, files, scale) {
        const list = [];
        let n = 0;
        let cur = scale;
        files.forEach((f) => {
            let doc = null;
            const getDoc = () => (doc ??= open(f.url));
            f.pages.forEach(([w, h], i) => {
                n++;
                const el = document.createElement('div');
                el.className = 'es-page';
                el.dataset.page = String(n);
                const canvas = document.createElement('canvas');
                const layer = document.createElement('div');
                layer.className = 'es-layer';
                layer.dataset.page = String(n);
                const no = document.createElement('div');
                no.className = 'es-no';
                no.textContent = String(n);
                el.append(canvas, layer, no);
                box.append(el);
                list.push({ el, layer, canvas, w, h, n, index: i + 1, getDoc, drawn: 0, busy: false });
            });
        });

        async function draw(p) {
            const s = cur;
            if (p.drawn === s || p.busy) return;
            p.busy = true;
            try {
                const doc = await p.getDoc();
                const page = await doc.getPage(p.index);
                const vp = page.getViewport({ scale: s * (window.devicePixelRatio || 1) });
                p.canvas.width = Math.round(vp.width);
                p.canvas.height = Math.round(vp.height);
                await page.render({ canvasContext: p.canvas.getContext('2d'), viewport: vp }).promise;
                p.drawn = s;
            } catch (e) {
                console.error(e);
            } finally {
                p.busy = false;
            }
            if (p.drawn !== cur && near(p)) draw(p);
        }

        const near = (p) => {
            const r = p.el.getBoundingClientRect();
            return r.bottom > -800 && r.top < window.innerHeight + 800;
        };
        const io = new IntersectionObserver((entries) => entries.forEach((e) => e.isIntersecting && draw(e.target.phaiPage)), { rootMargin: '800px' });
        list.forEach((p) => {
            p.el.phaiPage = p;
            io.observe(p.el);
        });

        function zoom(s) {
            cur = s;
            list.forEach((p) => {
                p.el.style.width = p.w * s + 'px';
                p.el.style.height = p.h * s + 'px';
            });
            list.forEach((p) => near(p) && draw(p));
        }
        zoom(cur);
        return { list, zoom, get scale() { return cur; } };
    }

    return { pages, open };
})();
