/** e-Tax Invoice by Email settings (views/accounting/etax-settings.php): load, save, send a test email. */
(function () {
    'use strict';

    const form = document.getElementById('etax-settings');
    const testBtn = document.getElementById('test');

    function show(s) {
        Phai.form.fill(form, { ...s, pass: '' });
        form.pass.placeholder = s.pass_set ? '••••••••' : '';
        document.getElementById('not-writable').classList.toggle('d-none', s.writable);
        showCert(s.cert);
    }

    // ------------------------------------------------------------ signing certificate (.p12)
    const certForm = document.getElementById('etax-cert');
    const certInfo = document.getElementById('cert-info');
    const removeBtn = document.getElementById('cert-remove');
    const dmy = (v) => (v ? v.split('-').reverse().join('/') : '');

    function showCert(c) {
        removeBtn.hidden = !c;
        if (!c) {
            certInfo.className = 'mb-2 small text-secondary';
            certInfo.textContent = Phai.t('etax_cert_none');
        } else if (c.error) {
            certInfo.className = 'mb-2 small text-danger';
            certInfo.textContent = c.error;
        } else {
            certInfo.className = 'mb-2 small ' + (c.expired ? 'text-danger' : 'text-success');
            certInfo.textContent = `${c.subject} · ${c.issuer} · ${dmy(c.from)} – ${dmy(c.to)}${c.expired ? ' · ' + Phai.t('etax_cert_expired') : ''}`;
        }
    }

    certForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        Phai.form.errors(certForm, null);
        const file = certForm.p12.files[0];
        if (!file) {
            certForm.p12.click();
            return;
        }
        const btn = certForm.querySelector('[type="submit"]');
        Phai.busy(btn, true);
        try {
            const p12 = await new Promise((ok, fail) => {
                const r = new FileReader();
                r.onload = () => ok(r.result);
                r.onerror = fail;
                r.readAsDataURL(file);
            });
            const s = await Phai.api.put('/api/settings/etax/cert', { p12, password: certForm.cert_password.value });
            certForm.reset();
            showCert(s.cert);
            Phai.toast(Phai.t('saved'));
        } catch (err) {
            if (err.fields) Phai.form.errors(certForm, err.fields);
        } finally {
            Phai.busy(btn, false);
        }
    });

    removeBtn.addEventListener('click', async () => {
        if (!(await Phai.confirm(Phai.t('etax_cert_remove_confirm')))) return;
        try {
            showCert((await Phai.api.del('/api/settings/etax/cert')).cert);
            Phai.toast(Phai.t('deleted'));
        } catch (err) {
            // toast already shown
        }
    });

    Phai.api.get('/api/settings/etax').then(show);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const saved = await Phai.form.submit(form, 'PUT', '/api/settings/etax');
        if (saved) {
            show(saved);
            Phai.toast(Phai.t('saved'));
        }
    });

    testBtn.addEventListener('click', async () => {
        Phai.form.errors(form, null);
        Phai.busy(testBtn, true);
        try {
            const r = await Phai.api.post('/api/settings/etax/test', { to: form.test_to.value });
            Phai.toast(Phai.t('etax_test_sent', { to: r.to }));
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
        } finally {
            Phai.busy(testBtn, false);
        }
    });
})();
