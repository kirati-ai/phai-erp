/** Logo and address cards on the company screen (views/system/company-aside.php). */
(function () {
    'use strict';

    const text = JSON.parse(document.getElementById('co-logo-text').textContent);

    // ------------------------------------------------------------ address
    const adForm = document.getElementById('company-address');
    const adText = document.getElementById('co-ad-text');
    const adFields = document.getElementById('co-ad-fields');
    const adEdit = document.getElementById('co-ad-edit');
    const geo = Phai.geo(adForm);
    let address = null;

    function showAddress(d) {
        address = d;
        adText.textContent = d.text || text.noAddress;
        adText.classList.toggle('fst-italic', !d.text);
    }

    function editing(on) {
        adFields.hidden = !on;
        adText.hidden = on;
        if (adEdit) adEdit.hidden = on;
    }

    Phai.api.get('/api/company/address').then(showAddress);
    adEdit?.addEventListener('click', async () => {
        Phai.form.fill(adForm, address);
        await geo.load(address);
        editing(true);
        adForm.elements.ad_house_no.focus();
    });
    document.getElementById('co-ad-cancel')?.addEventListener('click', () => editing(false));
    adForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await Phai.form.submit(adForm, 'PUT', '/api/company/address');
        if (!res) return;
        showAddress(res);
        editing(false);
        Phai.toast(Phai.t('saved'));
    });

    // ------------------------------------------------------------ logo
    const change = document.getElementById('co-logo-change');
    if (!change) return; // view only
    const file = document.getElementById('co-logo-file');
    const img = document.getElementById('co-logo-img');
    const none = document.getElementById('co-logo-none');
    const remove = document.getElementById('co-logo-remove');

    function show(url) {
        img.hidden = !url;
        img.src = url || '';
        none.hidden = !!url;
        remove.hidden = !url;
    }

    change.addEventListener('click', () => file.click());
    file.addEventListener('change', () => {
        const f = file.files[0];
        file.value = '';
        if (!f) return;
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(f.type)) return Phai.toast(text.type, 'danger');
        (async () => {
            Phai.busy(change, true);
            try {
                // any size goes: the browser makes it at most 1000 px WebP (transparency kept) first
                const res = await Phai.api.put('/api/company/logo', { logo: await Phai.shrinkImage(f, 1000, .9) });
                show(res.url);
                Phai.toast(Phai.t('saved'));
            } catch (err) {
                Phai.toast(err.fields ? Object.values(err.fields).join(' ') : text.type, 'danger');
            } finally {
                Phai.busy(change, false);
            }
        })();
    });
    remove.addEventListener('click', async () => {
        if (!(await Phai.confirm(text.confirm))) return;
        await Phai.api.del('/api/company/logo');
        show(null);
    });
})();
