/** App shell (views/layouts/app.php): menu groups, icon-only sidebar on wide screens, slide-in sidebar on phones. */
(function () {
    'use strict';

    // shown inside a window (?embed=1): links within the app stay without menu and top bar
    if (document.body.classList.contains('phai-embed')) {
        document.addEventListener('click', (e) => {
            const a = e.target.closest('a[href]');
            if (!a || a.target || e.ctrlKey || e.metaKey || a.getAttribute('href').startsWith('#')) return;
            const u = new URL(a.href, location.href);
            if (u.origin !== location.origin || u.pathname.endsWith('/print') || u.pathname.includes('/api/')) return;
            u.searchParams.set('embed', '1');
            a.href = u.toString();
        }, true);
    }

    const root = document.documentElement;
    const side = document.getElementById('phai-side');
    const narrow = () => window.matchMedia('(max-width: 991.98px)').matches;

    // the hamburger: slide-in menu on phones, icon-only switch on wide screens
    const toggle = document.getElementById('phai-side-toggle');
    if (!toggle || toggle.dataset.shellBound === '1') return;
    toggle.dataset.shellBound = '1';
    const syncToggle = () => toggle.setAttribute('aria-expanded', String(narrow() ? root.classList.contains('side-open') : !root.classList.contains('side-mini')));
    const setOpen = (on) => { root.classList.toggle('side-open', on); syncToggle(); };
    toggle.setAttribute('aria-controls', 'phai-side');
    syncToggle();
    document.getElementById('phai-side-toggle').addEventListener('click', () => {
        if (narrow()) setOpen(!root.classList.contains('side-open'));
        else {
            const mini = root.classList.toggle('side-mini');
            try { localStorage.setItem('phai-side-mini', mini ? '1' : '0'); } catch (_) {}
        }
        syncToggle();
    });
    document.getElementById('phai-side-backdrop').addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && root.classList.contains('side-open')) setOpen(false);
    });


    // Resize the expanded desktop sidebar using its divider.
    const divider = document.getElementById('phai-side-resize');
    const widthKey = 'phai-side-width';
    const minimum = 180;
    const maximum = () => Math.min(480, Math.floor(window.innerWidth * .4));
    let preferredWidth = root.classList.contains('side-mini') ? 240 : side.getBoundingClientRect().width;
    try {
        const saved = Number(localStorage.getItem(widthKey));
        if (Number.isFinite(saved) && saved >= minimum) preferredWidth = saved;
    } catch (_) {}
    function setWidth(width, save = false) {
        const value = Math.round(Math.max(minimum, Math.min(maximum(), width)));
        root.style.setProperty('--phai-side-w', value + 'px');
        divider.setAttribute('aria-valuemin', String(minimum));
        divider.setAttribute('aria-valuemax', String(maximum()));
        divider.setAttribute('aria-valuenow', String(value));
        if (save) {
            preferredWidth = value;
            try { localStorage.setItem(widthKey, String(value)); } catch (_) {}
        }
    }
    if (!narrow()) setWidth(preferredWidth);
    let drag = null;
    divider.addEventListener('pointerdown', (e) => {
        if (e.button !== 0 || narrow() || root.classList.contains('side-mini')) return;
        e.preventDefault();
        drag = { id: e.pointerId, x: e.clientX, width: side.getBoundingClientRect().width };
        divider.setPointerCapture(e.pointerId);
        root.classList.add('side-resizing');
    });
    divider.addEventListener('pointermove', (e) => {
        if (drag && e.pointerId === drag.id) setWidth(drag.width + e.clientX - drag.x);
    });
    function finishResize(e) {
        if (!drag || e.pointerId !== drag.id) return;
        setWidth(side.getBoundingClientRect().width, true);
        drag = null;
        root.classList.remove('side-resizing');
    }
    divider.addEventListener('pointerup', finishResize);
    divider.addEventListener('pointercancel', finishResize);
    divider.addEventListener('lostpointercapture', finishResize);
    divider.addEventListener('keydown', (e) => {
        if (narrow() || root.classList.contains('side-mini')) return;
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            e.preventDefault();
            setWidth(side.getBoundingClientRect().width + (e.key === 'ArrowLeft' ? -10 : 10), true);
        }
    });
    window.addEventListener('resize', () => {
        if (!narrow()) setWidth(preferredWidth);
        else root.style.removeProperty('--phai-side-w');
    });

    // groups fold open and closed: close other groups when opening one
    side.querySelectorAll('.phai-nav-toggle').forEach((btn) => btn.addEventListener('click', () => {
        const group = btn.closest('.phai-nav-group');
        const wasMini = !narrow() && root.classList.contains('side-mini');
        if (wasMini) {
            root.classList.remove('side-mini');
            try { localStorage.setItem('phai-side-mini', '0'); } catch (_) {}
            syncToggle();
            updateClock();
        }
        const open = wasMini || !group.classList.contains('open');
        if (open) {
            side.querySelectorAll('.phai-nav-group').forEach((other) => {
                if (other === group) return;
                other.classList.remove('open');
                other.querySelector('.phai-nav-toggle')?.setAttribute('aria-expanded', 'false');
            });
        }
        group.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', String(open));
    }));

    // ตั้งค่า headings fold; the ones opened are remembered in this browser
    const foldKey = 'phai-side-folds';
    let folds = [];
    try { folds = JSON.parse(localStorage.getItem(foldKey) || '[]'); } catch (_) {}
    function setFold(btn, open) {
        btn.nextElementSibling.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', String(open));
    }
    side.querySelectorAll('.phai-nav-fold-toggle').forEach((btn) => {
        if (folds.includes(btn.dataset.fold)) setFold(btn, true);
        btn.addEventListener('click', () => {
            const open = btn.getAttribute('aria-expanded') !== 'true';
            setFold(btn, open);
            folds = folds.filter((f) => f !== btn.dataset.fold);
            if (open) folds.push(btn.dataset.fold);
            try { localStorage.setItem(foldKey, JSON.stringify(folds)); } catch (_) {}
        });
    });

    // ปักหมุด: the pin on a menu link copies it under เรียนรู้ ERP (kept with the user, us_pins); the pin there takes it off
    const pinBox = document.getElementById('phai-nav-pins');
    function pinIcon(icon, on) {
        icon.className = 'ti ' + (on ? 'ti-pinned on' : 'ti-pin') + ' phai-pin';
        icon.setAttribute('aria-label', on ? pinBox.dataset.unpin : pinBox.dataset.pin);
    }
    side.addEventListener('click', async (e) => {
        const icon = e.target.closest('.phai-pin');
        if (!icon || !pinBox) return;
        e.preventDefault();
        e.stopPropagation();
        const code = icon.closest('a').dataset.code;
        const inBox = pinBox.querySelector(`a[data-code="${CSS.escape(code)}"]`);
        const source = side.querySelector(`.phai-nav-group a[data-code="${CSS.escape(code)}"]`);
        if (inBox) inBox.remove();
        else if (source) {
            const copy = source.cloneNode(true);
            copy.removeAttribute('data-search');
            pinIcon(copy.querySelector('.phai-pin'), true);
            pinBox.append(copy);
        }
        if (source) pinIcon(source.querySelector('.phai-pin'), !inBox);
        pinBox.hidden = !pinBox.children.length;
        await Phai.api.put('/api/me/pins', { pins: [...pinBox.children].map((a) => a.dataset.code) });
    });

    // wide screens: sidebar opens only through a toggle, remembered in this browser
    const clock = document.getElementById('phai-side-clock');
    const formatter = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Bangkok', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
    });
    // signing out when idle (co_idle_min on the company page, data-idle on the clock), counted quietly (nothing shown):
    // mouse, keys or touch in any tab of this browser start the count again; the server ends the session too (Auth),
    // told the user is busy by /api/me/alive
    const idleMin = Number(clock?.dataset.idle || 0);
    const activeKey = 'phai-active';
    let lastActive = Date.now();
    let lastPing = Date.now();
    let leaving = false;
    function busy() {
        const now = Date.now();
        if (now - lastActive < 1000) return;
        lastActive = now;
        try { localStorage.setItem(activeKey, String(now)); } catch (_) {}
        if (now - lastPing > 60000) {
            lastPing = now;
            Phai.api.get('/api/me/alive').catch(() => {});
        }
    }
    if (idleMin > 0) ['pointermove', 'pointerdown', 'keydown', 'wheel', 'touchstart'].forEach((ev) => document.addEventListener(ev, busy, { passive: true, capture: true }));
    function idleLeft() {
        let last = lastActive;
        try { last = Math.max(last, Number(localStorage.getItem(activeKey)) || 0); } catch (_) {}
        return idleMin * 60 - Math.floor((Date.now() - last) / 1000);
    }

    function updateClock() {
        const now = new Date();
        const p = Object.fromEntries(formatter.formatToParts(now).map((part) => [part.type, part.value]));
        // the month in words and the Buddhist year in Thai, as on the home page greeting (10 ต.ค. 2569 06:49:20)
        const year = Number(p.year) + Number(Phai.t('year_offset') || 0);
        const full = `${Number(p.day)} ${Phai.t('months_short').split(',')[Number(p.month) - 1] || p.month} ${year} ${p.hour}:${p.minute}:${p.second}`;
        if (!clock) return;
        const mini = root.classList.contains('side-mini') && !narrow();
        clock.textContent = mini ? `${p.hour}:${p.minute}` : full;
        clock.dateTime = now.toISOString();
        clock.title = full + ' (Asia/Bangkok)';
        if (idleMin > 0 && idleLeft() <= 0 && !leaving) {
            leaving = true;
            Phai.api.post('/api/logout').catch(() => {}).finally(() => { location.href = Phai.url('/login?idle=1'); });
        }
    }
    document.getElementById('phai-side-toggle').addEventListener('click', updateClock);
    updateClock();
    setInterval(updateClock, 1000);
    window.addEventListener('resize', updateClock);

    // a long menu keeps where it was scrolled across pages; the current link is always in view
    const nav = side.querySelector('.phai-side-nav');
    const scrollKey = 'phai-side-scroll';
    window.addEventListener('pagehide', () => {
        try { sessionStorage.setItem(scrollKey, String(nav.scrollTop)); } catch (_) {}
    });
    function restoreScroll() {
        let top = null;
        try { top = sessionStorage.getItem(scrollKey); } catch (_) {}
        if (top !== null) nav.scrollTop = Number(top);
        const active = side.querySelector('.phai-nav-sublink.active, .phai-nav-link.active');
        if (!active) return;
        const a = active.getBoundingClientRect();
        const n = nav.getBoundingClientRect();
        if (top === null || a.top < n.top || a.bottom > n.bottom) active.scrollIntoView({ block: 'center' });
    }
    restoreScroll();
    // the open group grows over .2s: settle again once it has its full height
    setTimeout(restoreScroll, 250);
})();
