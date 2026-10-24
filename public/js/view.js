/*
 * Actions on a read-only view page: <button data-post="/api/x" data-body='{"a":1}' data-confirm="..." data-go="/x/{nbr}">.
 * Asks when data-confirm is set, posts, then opens data-go ({nbr} = the answer's nbr) or reloads.
 */
(function () {
    'use strict';

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-post]');
        if (!btn) return;
        e.preventDefault();
        if (btn.dataset.confirm && !(await Phai.confirm(btn.dataset.confirm))) return;
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post(btn.dataset.post, btn.dataset.body ? JSON.parse(btn.dataset.body) : {});
            Phai.toast(Phai.t('saved'));
            const go = btn.dataset.go;
            setTimeout(() => {
                if (go) location.href = Phai.url(go.replace('{nbr}', encodeURIComponent(res?.nbr ?? '')));
                else location.reload();
            }, 400);
        } catch (err) {
            if (err.fields) Phai.toast(Object.values(err.fields).join(' · '), 'danger');
            Phai.busy(btn, false);
        }
    });
})();
