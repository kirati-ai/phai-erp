// User menu in the top bar: change password and log out.
(function () {
    'use strict';

    const modalEl = document.getElementById('phai-password');
    if (!modalEl) return;
    const form = modalEl.querySelector('form');
    const modal = (window.bootstrap || window.tabler).Modal.getOrCreateInstance(modalEl);

    modalEl.addEventListener('show.bs.modal', () => Phai.form.fill(form, null));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!(await Phai.form.submit(form, 'PUT', '/api/me/password'))) return;
        modal.hide();
        Phai.toast(Phai.t('password_changed'));
    });

    // my profile: name, e-mail, phones and picture; the name and picture in the top bar follow at once
    const profileEl = document.getElementById('phai-profile');
    if (profileEl) {
        const profile = profileEl.querySelector('form');
        const profileModal = (window.bootstrap || window.tabler).Modal.getOrCreateInstance(profileEl);
        const initials = (name) => (name.trim().split(/\s+/).slice(0, 2).map((w) => [...w][0] || '').join('') || '?').toUpperCase();
        const showAvatar = (url) => {
            const name = profile.elements.us_name.value;
            document.querySelectorAll('[data-avatar]').forEach((old) => {
                let el = old;
                if (url && old.tagName !== 'IMG') {
                    el = Object.assign(document.createElement('img'), { alt: '', className: old.className });
                    el.setAttribute('data-avatar', '');
                    old.replaceWith(el);
                } else if (!url && old.tagName === 'IMG') {
                    el = Object.assign(document.createElement('span'), { className: old.className });
                    el.setAttribute('data-avatar', '');
                    old.replaceWith(el);
                }
                if (url) el.src = url; else el.textContent = initials(name);
            });
            document.getElementById('phai-avatar-remove').hidden = !url;
        };
        profile.addEventListener('submit', async (e) => {
            e.preventDefault();
            const saved = await Phai.form.submit(profile, 'PUT', '/api/me/profile');
            if (!saved) return;
            document.querySelectorAll('[data-user-name]').forEach((x) => { x.textContent = saved.us_name; });
            document.querySelectorAll('[data-user-email]').forEach((x) => { x.textContent = saved.us_email; });
            document.querySelectorAll('span[data-avatar]').forEach((x) => { x.textContent = initials(saved.us_name); });
            profileModal.hide();
            Phai.toast(profileEl.dataset.saved || Phai.t('saved'));
        });
        const pick = document.getElementById('phai-avatar-file');
        const changeBtn = document.getElementById('phai-avatar-change');
        changeBtn.addEventListener('click', () => pick.click());
        pick.addEventListener('change', () => {
            const f = pick.files[0];
            pick.value = '';
            if (!f) return;
            (async () => {
                Phai.busy(changeBtn, true);
                try {
                    // any size goes: the browser makes it 512 px WebP first
                    const avatar = await Phai.shrinkImage(f, 512);
                    showAvatar((await Phai.api.put('/api/me/avatar', { avatar })).url);
                    Phai.toast(Phai.t('saved'));
                } catch (err) {
                    Phai.toast(err.fields ? Object.values(err.fields).join(' ') : profileEl.dataset.badImage, 'danger');
                } finally {
                    Phai.busy(changeBtn, false);
                }
            })();
        });
        document.getElementById('phai-avatar-remove').addEventListener('click', async () => {
            await Phai.api.del('/api/me/avatar');
            showAvatar(null);
        });
    }

    // ลายเซ็นของฉัน: drawn or uploaded once, then put in the signature boxes of documents (js/sign-pad.js, js/doc-sign.js)
    document.getElementById('phai-my-sign')?.addEventListener('click', async (e) => {
        e.preventDefault();
        const text = JSON.parse(document.getElementById('phai-sign-text').textContent);
        const { image } = await Phai.api.get('/api/me/sign');
        PhaiSign.open({
            text, w: 600, h: 200, image,
            save: async (png) => { await Phai.api.put('/api/me/sign', { image: png }); Phai.toast(Phai.t('saved')); },
            remove: async () => { await Phai.api.del('/api/me/sign'); Phai.toast(Phai.t('deleted')); },
        });
    });

    // ธีม (AuthController::THEMES): a dot per theme in the user menu; a click shows it at once (the menu stays open to try
    // another) and keeps it with the user (us_theme) — ไผ่ยามค่ำ is dark (data-bs-theme), the colours are data-phai-theme
    const themePick = document.getElementById('phai-theme');
    themePick?.addEventListener('click', async (e) => {
        const dot = e.target.closest('[data-theme]');
        if (!dot) return;
        e.stopPropagation(); // not a menu item: the menu stays open
        const theme = dot.dataset.theme;
        const root = document.documentElement;
        root.dataset.bsTheme = theme === 'dark' ? 'dark' : 'light';
        if (theme === 'light' || theme === 'dark') delete root.dataset.phaiTheme; else root.dataset.phaiTheme = theme;
        themePick.querySelectorAll('[data-theme]').forEach((d) => {
            d.classList.toggle('active', d === dot);
            d.setAttribute('aria-pressed', d === dot ? 'true' : 'false');
        });
        themePick.querySelector('[data-theme-name]').textContent = dot.title;
        try { // the login page shows it too
            localStorage.setItem('phai-theme', theme);
            if (theme !== 'dark') localStorage.setItem('phai-theme-light', theme);
        } catch (_) {}
        try {
            await Phai.api.put('/api/me/theme', { theme });
        } catch (_) { /* the toast says why; the page keeps what was picked until it reloads */ }
    });

    // ภาษา: the flag beside ช่วยเหลือ switches Thai / English, kept with the user (us_lang) and in this browser for the
    // login page; every text comes from the server, so the page loads again
    document.getElementById('phai-lang')?.addEventListener('click', async (e) => {
        const lang = e.currentTarget.dataset.lang;
        try {
            await Phai.api.put('/api/me/lang', { lang });
        } catch (_) {
            return; // the toast says why
        }
        document.cookie = `phai_lang=${lang}; path=${Phai.url('/')}; max-age=31536000; samesite=lax`;
        location.reload();
    });

    document.getElementById('phai-logout').addEventListener('click', async (e) => {
        e.preventDefault();
        const res = await Phai.api.post('/api/logout');
        location.href = res.next;
    });
})();
