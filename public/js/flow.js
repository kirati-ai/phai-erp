/**
 * The story cards on a module dashboard (views/partials/flow.php): under each card, small, one per line, the numbers of
 * the first 3 documents waiting for that screen (red when late), then +n for the rest. Nothing when none wait.
 */
(function () {
    'use strict';

    const SHOW = 3;

    async function load(box) {
        const q = JSON.parse(box.dataset.flow);
        let rows;
        try {
            rows = await Phai.api.get(q.api, q.params || {});
        } catch (_) {
            return; // a queue that cannot load shows nothing; the card still tells what the screen is
        }
        rows = Array.isArray(rows) ? rows : (rows.rows || []);
        box.innerHTML = rows.slice(0, SHOW)
            .map((r) => `<div${q.urgent && r[q.urgent] ? ' class="text-danger"' : ''}>${Phai.esc(r[q.key] ?? '')}</div>`)
            .join('') + (rows.length > SHOW ? `<div>+${rows.length - SHOW}</div>` : '');
    }

    document.querySelectorAll('[data-flow]').forEach(load);
})();
