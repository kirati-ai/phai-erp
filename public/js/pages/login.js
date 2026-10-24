(function () {
    'use strict';

    const form = document.getElementById('login');
    const pass = form.elements.password;
    const eye = document.getElementById('login-eye');
    const caps = document.getElementById('login-caps');

    // light / dark before signing in, kept in this browser: dark is ไผ่ยามค่ำ, light the colour theme last used here
    // (ไผ่ หมอก คราม อัญชัน ดินเผา); once in, the user's own theme counts
    const themeBtn = document.getElementById('login-theme');
    const names = JSON.parse(themeBtn.dataset.names);
    const lightTheme = () => {
        try { return names[localStorage.getItem('phai-theme-light')] ? localStorage.getItem('phai-theme-light') : 'light'; } catch (_) { return 'light'; }
    };
    const showTheme = () => {
        const dark = document.documentElement.dataset.bsTheme === 'dark';
        themeBtn.querySelector('.ti').className = 'ti ' + (dark ? 'ti-sun' : 'ti-moon');
        themeBtn.title = names[dark ? lightTheme() : 'dark'];
        themeBtn.setAttribute('aria-label', themeBtn.title);
    };
    themeBtn.addEventListener('click', () => {
        const root = document.documentElement;
        const next = root.dataset.bsTheme === 'dark' ? lightTheme() : 'dark';
        root.dataset.bsTheme = next === 'dark' ? 'dark' : 'light';
        if (next === 'light' || next === 'dark') delete root.dataset.phaiTheme; else root.dataset.phaiTheme = next;
        try { localStorage.setItem('phai-theme', next); } catch (_) {}
        showTheme();
    });
    showTheme();

    // ภาษา before signing in: kept in this browser (cookie phai_lang); once in, the user's own language counts
    document.getElementById('login-lang').addEventListener('click', (e) => {
        document.cookie = `phai_lang=${e.currentTarget.dataset.lang}; path=${Phai.url('/')}; max-age=31536000; samesite=lax`;
        location.reload();
    });

    // show / hide the password
    eye.addEventListener('click', () => {
        const show = pass.type === 'password';
        pass.type = show ? 'text' : 'password';
        eye.querySelector('.ti').className = 'ti ' + (show ? 'ti-eye-off' : 'ti-eye');
        pass.focus();
    });

    // warn when Caps Lock is on while typing the password
    const capsCheck = (e) => { if (e.getModifierState) caps.hidden = !e.getModifierState('CapsLock'); };
    pass.addEventListener('keydown', capsCheck);
    pass.addEventListener('keyup', capsCheck);
    pass.addEventListener('blur', () => { caps.hidden = true; });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Phai.form.read(form);
        data.next = new URLSearchParams(location.search).get('next') || '/';
        const btn = form.querySelector('[type=submit]');
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post('/api/login', data);
            location.href = res.next;
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
            pass.value = '';
            pass.type = 'password';
            Phai.busy(btn, false);
            form.classList.remove('phai-shake');
            void form.offsetWidth; // restart the animation
            form.classList.add('phai-shake');
            pass.focus();
        }
    });
})();
