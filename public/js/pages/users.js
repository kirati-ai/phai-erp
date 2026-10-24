(function () {
    'use strict';

    const modalEl = document.getElementById('edit');
    const modal = (window.bootstrap || window.tabler).Modal.getOrCreateInstance(modalEl);
    const form = modalEl.querySelector('form');
    const pwLabel = document.getElementById('password-label');
    const roleName = {};
    for (const o of form.elements.us_role.options) roleName[o.value] = o.textContent;
    let editing = null;

    const grid = Phai.grid({
        table: '#users',
        url: '/api/users',
        search: '#q',
        columns: [
            { key: 'us_userid', cls: 'fw-medium' },
            { key: 'us_name', render: (r) => `<span class="d-inline-flex align-items-center">${avatar(r, 'phai-avatar-sm')}${Phai.esc(r.us_name)}</span>` },
            { key: 'us_email', cls: 'd-none d-md-table-cell' },
            { key: 'us_role', render: (r) => Phai.esc(roleName[r.us_role] || r.us_role || '') },
            { key: 'us_last_login', cls: 'd-none d-lg-table-cell text-secondary', render: (r) => Phai.esc(dateTime(r.us_last_login)) },
            { key: 'us_active', render: (r) => Phai.badge(r.us_active) },
        ],
        onClick: open,
        actions: { edit: open },
    });
    grid.reload();

    /** The user's picture (set in their own profile), or the initials of the name on bamboo green. */
    function avatar(r, cls) {
        if (r.us_avatar) {
            return `<img src="${Phai.esc(Phai.url('/avatar/' + encodeURIComponent(r.us_userid)) + '?v=' + encodeURIComponent(r.updated_at || ''))}" alt="" class="phai-avatar ${cls}">`;
        }
        const ini = (String(r.us_name || '').trim().split(/\s+/).slice(0, 2).map((w) => [...w][0] || '').join('') || '?').toUpperCase();
        return `<span class="phai-avatar ${cls}">${Phai.esc(ini)}</span>`;
    }

    /** '2026-10-03 09:15:00' -> '03/10/2026 09:15' */
    function dateTime(v) {
        const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/.exec(v || '');
        return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}` : '';
    }

    function open(row) {
        editing = row ? row.us_userid : null;
        Phai.form.fill(form, row);
        form.elements.password.value = '';
        form.elements.us_userid.readOnly = !!row;
        pwLabel.textContent = row ? pwLabel.dataset.edit : pwLabel.dataset.new;
        // the picture is the user's own (their profile); here it only shows who this is
        const pic = document.getElementById('user-avatar');
        pic.hidden = !row;
        pic.innerHTML = row ? avatar(row, 'phai-avatar-lg') : '';
        modal.show();
    }

    document.getElementById('add').addEventListener('click', () => open(null));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const ok = editing
            ? await Phai.form.submit(form, 'PUT', '/api/users/' + encodeURIComponent(editing))
            : await Phai.form.submit(form, 'POST', '/api/users');
        if (!ok) return;
        modal.hide();
        Phai.toast(Phai.t('saved'));
        grid.reload();
    });
})();
