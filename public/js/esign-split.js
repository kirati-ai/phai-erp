/**
 * e-Document Sign pages split in two (the signing page, the envelope page): the document on the left, a panel on
 * the right, and a bar between them that is dragged to change the panel's width (double-click: back to the
 * default). The width is remembered in this browser. Both sides scroll on their own inside a box sized to the
 * window, so the bars above them stay put.
 *
 *   PhaiSplit.init(document.querySelector('.es-split'), 'sign', () => { ...the left side changed width... })
 */
(function () {
    'use strict';

    function init(el, key, onResize) {
        if (!el) return;
        const handle = el.querySelector('.es-handle');
        const store = 'phai-esign-side-' + key;
        const narrow = () => window.matchMedia('(max-width: 991px)').matches;
        const set = (px) => {
            const max = Math.max(260, el.clientWidth * 0.6);
            const w = Math.round(Math.max(220, Math.min(max, px)));
            el.style.setProperty('--es-side', w + 'px');
            return w;
        };
        let saved = null;
        try { saved = parseInt(localStorage.getItem(store) || '', 10); } catch (_) { /* storage off */ }
        if (saved) set(saved);

        let timer = null;
        const changed = () => {
            clearTimeout(timer);
            timer = setTimeout(() => onResize && onResize(), 60);
        };

        // the box fills the window below what is above it; on a narrow screen the two sides stack and the page scrolls
        function fit() {
            if (narrow()) {
                el.style.height = '';
                return;
            }
            const bar = document.querySelector('.phai-topbar');
            const barBottom = bar && getComputedStyle(bar).position !== 'static' ? bar.getBoundingClientRect().bottom : 0;
            document.querySelectorAll('.es-top').forEach((t) => t.style.setProperty('--es-top', Math.max(0, barBottom) + 'px'));
            const top = el.getBoundingClientRect().top + window.scrollY;
            el.style.height = Math.max(320, window.innerHeight - top - 16) + 'px';
        }
        fit();
        window.addEventListener('resize', () => { fit(); changed(); });
        const head = document.querySelector('.es-top');
        if (head && window.ResizeObserver) new ResizeObserver(fit).observe(head);

        if (!handle) return;
        handle.addEventListener('pointerdown', (e) => {
            if (narrow() || e.button > 0) return;
            e.preventDefault();
            handle.setPointerCapture(e.pointerId);
            el.classList.add('es-dragging');
            const right = el.getBoundingClientRect().right;
            const move = (ev) => { set(right - ev.clientX); changed(); };
            const up = () => {
                handle.removeEventListener('pointermove', move);
                handle.removeEventListener('pointerup', up);
                el.classList.remove('es-dragging');
                try { localStorage.setItem(store, parseInt(getComputedStyle(el).getPropertyValue('--es-side'), 10)); } catch (_) { /* storage off */ }
                changed();
            };
            handle.addEventListener('pointermove', move);
            handle.addEventListener('pointerup', up);
        });
        handle.addEventListener('dblclick', () => {
            el.style.removeProperty('--es-side');
            try { localStorage.removeItem(store); } catch (_) { /* storage off */ }
            changed();
        });
        // the keyboard moves it too
        handle.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
            e.preventDefault();
            const now = el.querySelector('.es-side-pane').getBoundingClientRect().width;
            const w = set(now + (e.key === 'ArrowLeft' ? 24 : -24));
            try { localStorage.setItem(store, w); } catch (_) { /* storage off */ }
            changed();
        });
    }

    window.PhaiSplit = { init };
})();
