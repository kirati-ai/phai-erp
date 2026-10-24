(function () {
    'use strict';

    const ADMIN = 'ADMIN';
    const actions = JSON.parse(document.getElementById('perm-actions').textContent);
    const adminNote = JSON.parse(document.getElementById('admin-note').textContent);

    const list = document.getElementById('role-list');
    const title = document.getElementById('role-title');
    const editBtn = document.getElementById('edit-role');
    const saveBtn = document.getElementById('save-perms');
    const note = document.getElementById('perms-note');
    const box = document.getElementById('perms-box');
    const table = document.getElementById('perms');
    const tbody = table.tBodies[0];

    const modalEl = document.getElementById('role-modal');
    const modal = (window.bootstrap || window.tabler).Modal.getOrCreateInstance(modalEl);
    const form = modalEl.querySelector('form');
    const delBtn = document.getElementById('del');

    let roles = [];
    let current = null; // selected role row
    let editing = null; // role code in the modal, null when adding

    // ------------------------------------------------------------ role list

    async function loadRoles(select) {
        roles = (await Phai.api.get('/api/roles')).rows;
        list.innerHTML = roles.map((r) => `
            <a href="#" class="list-group-item list-group-item-action py-2 d-flex align-items-center" data-code="${Phai.esc(r.role_code)}">
                <span class="flex-fill">
                    <span class="fw-medium">${Phai.esc(r.role_desc)}</span>
                    <span class="d-block small text-secondary">${Phai.esc(r.role_code)}</span>
                </span>
                ${Number(r.role_active) === 1 ? '' : Phai.badge(0)}
            </a>`).join('');
        const pick = roles.find((r) => r.role_code === select) || null;
        if (pick) choose(pick);
        else if (current && !roles.find((r) => r.role_code === current.role_code)) reset();
    }

    function reset() {
        current = null;
        title.textContent = '';
        editBtn.hidden = saveBtn.hidden = box.hidden = true;
        note.hidden = false;
    }

    list.addEventListener('click', (e) => {
        const a = e.target.closest('[data-code]');
        if (!a) return;
        e.preventDefault();
        choose(roles.find((r) => r.role_code === a.dataset.code));
    });

    // ------------------------------------------------------------ permissions

    async function choose(role) {
        current = role;
        list.querySelectorAll('[data-code]').forEach((a) => a.classList.toggle('active', a.dataset.code === role.role_code));
        title.textContent = role.role_desc;
        const isAdmin = role.role_code === ADMIN;
        editBtn.hidden = isAdmin;

        if (isAdmin) {
            note.textContent = adminNote;
            note.hidden = false;
            box.hidden = saveBtn.hidden = true;
            return;
        }

        const perms = await Phai.api.get('/api/roles/' + encodeURIComponent(role.role_code) + '/perms');
        if (current !== role) return;
        let group = null;
        let g = -1; // index of the menu group: its heading row ticks one right for the whole group
        tbody.innerHTML = perms.map((p) => {
            let head = '';
            if (p.group !== group) {
                group = p.group;
                g++;
                head = `<tr class="bg-body-tertiary" data-group-head="${g}"><td class="small fw-semibold py-1">${Phai.esc(group)}</td>`
                    + actions.map((a) => `<td class="text-center py-1"><input type="checkbox" class="form-check-input m-0" data-group-all="${Phai.esc(a)}"></td>`).join('')
                    + '</tr>';
            }
            return head + `<tr data-prog="${Phai.esc(p.prog_code)}" data-group="${g}"><td>${Phai.esc(p.prog_name)}</td>`
                + actions.map((a) => `<td class="text-center"><input type="checkbox" class="form-check-input m-0" data-act="${Phai.esc(a)}"${p[a] ? ' checked' : ''}></td>`).join('')
                + '</tr>';
        }).join('');
        syncAll();
        note.hidden = true;
        box.hidden = saveBtn.hidden = false;
    }

    /** A heading box (all screens, or one menu group) shows ticked / some ticked / none of the boxes under it. */
    function mark(head, boxes) {
        const on = boxes.filter((b) => b.checked).length;
        head.checked = on > 0 && on === boxes.length;
        head.indeterminate = on > 0 && on < boxes.length;
    }

    function syncAll() {
        for (const a of actions) {
            mark(table.querySelector(`[data-all="${a}"]`), [...tbody.querySelectorAll(`[data-act="${a}"]`)]);
            tbody.querySelectorAll(`[data-group-all="${a}"]`).forEach((head) => {
                const g = head.closest('tr').dataset.groupHead;
                mark(head, [...tbody.querySelectorAll(`tr[data-group="${g}"] [data-act="${a}"]`)]);
            });
        }
    }

    /** Tick or untick right $a on these screen rows; any right needs "view", and no "view" means no right at all. */
    function setRight(rows, a, on) {
        rows.forEach((tr) => {
            tr.querySelector(`[data-act="${a}"]`).checked = on;
            if (on && a !== 'view') tr.querySelector('[data-act="view"]').checked = true;
            if (!on && a === 'view') tr.querySelectorAll('[data-act]').forEach((b) => { b.checked = false; });
        });
        syncAll();
    }

    table.tHead.addEventListener('change', (e) => {
        const a = e.target.dataset.all;
        if (a) setRight(tbody.querySelectorAll('tr[data-prog]'), a, e.target.checked);
    });

    tbody.addEventListener('change', (e) => {
        const groupAll = e.target.dataset.groupAll;
        if (groupAll) { // a menu group's heading: that right for every screen of the group
            setRight(tbody.querySelectorAll(`tr[data-group="${e.target.closest('tr').dataset.groupHead}"]`), groupAll, e.target.checked);
            return;
        }
        // Any right on a screen needs "view" too.
        const tr = e.target.closest('tr');
        if (e.target.checked && e.target.dataset.act !== 'view') tr.querySelector('[data-act="view"]').checked = true;
        if (!e.target.checked && e.target.dataset.act === 'view') tr.querySelectorAll('[data-act]').forEach((b) => { b.checked = false; });
        syncAll();
    });

    saveBtn.addEventListener('click', async () => {
        const perms = {};
        tbody.querySelectorAll('tr[data-prog]').forEach((tr) => {
            const on = [...tr.querySelectorAll('[data-act]:checked')].map((b) => b.dataset.act);
            if (on.length) perms[tr.dataset.prog] = on;
        });
        Phai.busy(saveBtn, true);
        try {
            await Phai.api.put('/api/roles/' + encodeURIComponent(current.role_code) + '/perms', { perms });
            Phai.toast(Phai.t('saved'));
        } catch (err) {
            // toast already shown
        } finally {
            Phai.busy(saveBtn, false);
        }
    });

    // ------------------------------------------------------------ role modal

    function open(role) {
        editing = role ? role.role_code : null;
        Phai.form.fill(form, role);
        form.elements.role_code.readOnly = !!role;
        delBtn.hidden = !role;
        modal.show();
    }

    document.getElementById('add').addEventListener('click', () => open(null));
    editBtn.addEventListener('click', () => open(current));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const saved = editing
            ? await Phai.form.submit(form, 'PUT', '/api/roles/' + encodeURIComponent(editing))
            : await Phai.form.submit(form, 'POST', '/api/roles');
        if (!saved) return;
        modal.hide();
        Phai.toast(Phai.t('saved'));
        loadRoles(saved.role_code);
    });

    delBtn.addEventListener('click', async () => {
        if (!(await Phai.confirm(Phai.t('confirm_delete', { name: editing })))) return;
        try {
            await Phai.api.del('/api/roles/' + encodeURIComponent(editing));
        } catch (err) {
            return;
        }
        modal.hide();
        Phai.toast(Phai.t('deleted'));
        loadRoles(null);
    });

    loadRoles(null);
})();
