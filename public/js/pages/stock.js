(function () {
    'use strict';

    const site = document.getElementById('site');
    const byPart = document.getElementById('by-part');
    const type = document.getElementById('type');
    const line = document.getElementById('pl');
    const table = document.getElementById('stock');
    const num = (v) => Number(v).toLocaleString('en-US', { maximumFractionDigits: 5 });
    const date = (v) => (v ? v.split('-').reverse().join('/') : '');

    const grid = Phai.grid({
        table,
        url: '/api/stock',
        search: '#q',
        sort: 'ld_part',
        query: () => ({ site: site.value, type: type.value, pl: line.value, by: byPart.checked ? 'part' : '' }),
        columns: [
            { key: 'ld_site' },
            { key: 'ld_loc', cls: 'detail' },
            { key: 'ld_part' },
            { key: 'pt_desc1' },
            { key: 'ld_lot', cls: 'detail' },
            { key: 'ld_expire', cls: 'detail', render: (r) => Phai.esc(date(r.ld_expire)) },
            { key: 'ld_qty_oh', cls: 'num', render: (r) => Phai.esc(num(r.ld_qty_oh)) },
            { key: 'pt_um', cls: 'text-secondary' },
        ],
        // Click a line to view it. From the Inventory menu the view page opens; from another module's
        // stock menu (?type=) it opens in a modal so the page and its menu stay where they are.
        onClick: (r) => {
            if (!r.ld_id) return;
            if (new URLSearchParams(location.search).get('type')) showLine(r.ld_id);
            else location.href = Phai.url('/stock/view') + '?' + new URLSearchParams({ id: r.ld_id });
        },
    });

    function reload() {
        // Location, lot and expiry mean nothing in totals per part.
        table.classList.toggle('phai-by-part', byPart.checked);
        grid.state.page = 1;
        grid.reload();
    }
    [site, type, line].forEach((s) => s.addEventListener('change', reload));
    byPart.addEventListener('change', reload);
    reload();

    // ------------------------------------------------------------ one line in a modal
    const modalEl = document.getElementById('line-modal');
    const types = JSON.parse(document.getElementById('stock-types').textContent);
    let filter = {};
    let history = null;
    async function showLine(id) {
        const r = await Phai.api.get(`/api/stock/view/${id}`);
        modalEl.querySelector('[data-title]').textContent = r.ld_part + ' · ' + r.pt_desc1;
        const badge = modalEl.querySelector('[data-status]');
        badge.className = `badge bg-${r.status_color}-lt`;
        badge.textContent = r.status_text;
        modalEl.querySelector('[data-fields]').innerHTML = r.fields.map(([label, value, col, numeric]) => `
            <div class="${Phai.esc(col)}">
                <label class="form-label">${Phai.esc(label)}</label>
                ${value === '' || value === null
                    ? '<div class="phai-view-value is-empty">–</div>'
                    : `<div class="phai-view-value${numeric ? ' text-end' : ''}">${Phai.esc(value)}</div>`}
            </div>`).join('');
        const first = modalEl.querySelector('[href="#line-detail"]');
        if (first) (window.bootstrap || window.tabler).Tab.getOrCreateInstance(first).show();
        filter = { part: r.ld_part, site: r.ld_site, loc: r.ld_loc, lot: r.ld_lot };
        if (modalEl.querySelector('#history')) {
            history = history || PhaiStockHistory.grid({ types, query: () => filter });
            history.state.page = 1;
            history.reload();
        }
        (window.bootstrap || window.tabler).Modal.getOrCreateInstance(modalEl).show();
    }
})();
