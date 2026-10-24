/** Installer wizard (views/install/index.php): one step at a time, each checked by the server. */
(function () {
    'use strict';

    const text = JSON.parse(document.getElementById('install-text').textContent);
    const form = document.getElementById('install');
    const el = form.elements;
    const sections = [...form.querySelectorAll('.wiz-step')];
    const stepItems = [...document.querySelectorAll('#wiz-steps .step-item')];
    const back = document.getElementById('wiz-back');
    const next = document.getElementById('wiz-next');
    const count = document.getElementById('wiz-count');
    const dbStatus = document.getElementById('db-status');
    const LAST = 4; // confirm
    const fieldStep = (name) => (name.startsWith('db_') ? 1 : name.startsWith('co_') || name.startsWith('ad_') ? 2 : 3);
    // province / district / sub-district pickers, read from the installer (no database yet)
    const geo = Phai.geo(form, '/api/install/geo');
    let geoLoaded = false;
    let step = 0;
    let reached = 0;

    function data() {
        const all = Phai.form.read(form);
        delete all.password2;
        all.co_logo = el.co_logo.value || null;
        return all;
    }

    function show(n) {
        step = n;
        reached = Math.max(reached, n);
        sections.forEach((s) => {
            const on = Number(s.dataset.step) === n;
            s.hidden = !on;
            if (on) {
                s.classList.remove('wiz-in');
                void s.offsetWidth; // restart the animation
                s.classList.add('wiz-in');
            }
        });
        stepItems.forEach((li, i) => {
            li.classList.toggle('active', i === Math.min(n, LAST));
            li.classList.toggle('done', i < n);
            li.style.cursor = i <= reached && n <= LAST ? 'pointer' : '';
        });
        const working = n > LAST;
        document.getElementById('wiz-nav').classList.toggle('d-none', working); // d-flex beats [hidden]
        back.hidden = n === 0;
        count.textContent = text.of.replace(':n', Math.min(n + 1, LAST + 1)).replace(':total', LAST + 1);
        next.querySelector('[data-label]').textContent = n === LAST ? text.install : text.next;
        next.querySelector('.ti').className = 'ti ms-1 ' + (n === LAST ? 'ti-rocket' : 'ti-chevron-right');
        if (n === LAST) summary();
        if (n === 2 && !geoLoaded) {
            geoLoaded = true;
            geo.load(null).catch(() => { geoLoaded = false; });
        }
        const first = sections[n].querySelector('input:not([type=hidden]):not([type=checkbox]), textarea');
        if (first && !working) setTimeout(() => first.focus(), 50);
    }

    function summary() {
        const d = data();
        document.getElementById('sum-company').textContent = d.co_name;
        document.getElementById('sum-company-sub').textContent = [d.co_tax_id, d.co_phone].filter(Boolean).join(' · ');
        document.getElementById('sum-address').textContent = [d.ad_line1, d.ad_sub_district, d.ad_city, d.ad_state, d.ad_zip].filter(Boolean).join(' ');
        document.getElementById('sum-db').textContent = `${d.db_name} @ ${d.db_host}:${d.db_port} (${d.db_user})`;
        document.getElementById('sum-admin').textContent = `${d.us_userid} · ${d.us_name} · ${d.us_email}`;
        const img = document.getElementById('sum-logo');
        img.hidden = !d.co_logo;
        if (d.co_logo) img.src = d.co_logo;
    }

    /** Server check of the current step; true when it may move on. */
    async function check() {
        Phai.form.errors(form, null);
        if (step === 1) {
            const d = data();
            const res = await Phai.api.post('/api/install/db', Object.fromEntries(['db_host', 'db_port', 'db_name', 'db_user', 'db_pass'].map((k) => [k, d[k]])));
            dbStatus.className = 'small mt-2 text-success';
            dbStatus.innerHTML = `<i class="ti ti-circle-check me-1"></i>${Phai.esc(`${text.ok} (MySQL ${res.version}) · ${res.exists ? text.empty : text.new}`)}`;
            await new Promise((r) => setTimeout(r, 400)); // let the green tick show
            return true;
        }
        if (step === 2 || step === 3) {
            if (step === 3 && el.password.value !== el.password2.value) {
                Phai.form.errors(form, { password2: text.mismatch });
                return false;
            }
            await Phai.api.post('/api/install/check', { ...data(), step: step === 2 ? 'company' : 'admin' });
        }
        return true;
    }

    async function install() {
        const d = data();
        const lines = [...text.running, ...(d.demo ? [text.runningDemo] : [])];
        const runText = document.getElementById('run-text');
        let i = 0;
        runText.textContent = lines[0];
        const timer = setInterval(() => { i = Math.min(i + 1, lines.length - 1); runText.textContent = lines[i]; }, 1500);
        show(5);
        try {
            const res = await Phai.api.post('/api/install/run', d);
            document.getElementById('done-sub').textContent = d.demo && !res.demo_error ? text.doneDemo : text.doneSub;
            document.getElementById('go-login').href = res.login;
            if (res.demo_error) {
                const w = document.getElementById('demo-error');
                w.hidden = false;
                w.textContent = res.demo_error;
            }
            if (!res.config_written) {
                document.getElementById('config-box').hidden = false;
                document.getElementById('config-text').value = res.config;
            }
            show(6);
            stepItems.forEach((li) => { li.classList.remove('active'); li.classList.add('done'); });
        } catch (err) {
            // back to the step with the problem, errors under its fields
            const fields = err.fields ? Object.keys(err.fields) : [];
            show(fields.length ? fieldStep(fields[0]) : LAST);
            if (err.fields) Phai.form.errors(form, err.fields);
        } finally {
            clearInterval(timer);
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (next.disabled || step > LAST) return;
        if (step === LAST) {
            install();
            return;
        }
        let passed = false;
        Phai.busy(next, true);
        try {
            passed = await check();
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
        } finally {
            Phai.busy(next, false); // restores the button, so change step after it
        }
        if (passed) show(step + 1);
    });

    back.addEventListener('click', () => show(step - 1));
    stepItems.forEach((li, i) => li.addEventListener('click', () => {
        if (i <= reached && step <= LAST) show(i);
    }));
    // a changed database setting must be tested again
    ['db_host', 'db_port', 'db_name', 'db_user', 'db_pass'].forEach((n) => el[n].addEventListener('input', () => {
        dbStatus.textContent = '';
        reached = Math.min(reached, 1);
    }));

    // ------------------------------------------------------------ logo: pick or drop an image
    const drop = document.getElementById('logo-drop');
    const file = document.getElementById('logo-file');
    const preview = document.getElementById('logo-preview');
    const clear = document.getElementById('logo-clear');

    function setLogo(url) {
        el.co_logo.value = url || '';
        preview.hidden = !url;
        preview.src = url || '';
        document.getElementById('logo-empty').hidden = !!url;
        clear.hidden = !url;
    }

    function take(f) {
        if (!f) return;
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(f.type)) {
            Phai.toast(text.logoType, 'danger');
            return;
        }
        if (f.size > text.maxLogo) {
            Phai.toast(text.logoSize, 'danger');
            return;
        }
        const reader = new FileReader();
        reader.onload = () => setLogo(reader.result);
        reader.readAsDataURL(f);
    }

    drop.addEventListener('click', () => file.click());
    drop.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            file.click();
        }
    });
    file.addEventListener('change', () => { take(file.files[0]); file.value = ''; });
    drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('over'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('over'));
    drop.addEventListener('drop', (e) => {
        e.preventDefault();
        drop.classList.remove('over');
        take(e.dataTransfer.files[0]);
    });
    clear.addEventListener('click', () => setLogo(null));

    show(0);
})();
