/* Receipts with their landed costs; a row opens its view (views/purchase/landed-view.php). */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('lc-config').textContent);
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const date = (v) => (v ? v.split('-').reverse().join('/') : '');

    Phai.grid({
        table: '#receipts',
        url: '/api/landed-costs',
        search: '#q',
        sort: 'rc_nbr',
        dir: 'desc',
        columns: [
            { key: 'rc_nbr', cls: 'fw-medium' },
            { key: 'rc_date', render: (r) => Phai.esc(date(r.rc_date)) },
            { key: 'rc_po_nbr' },
            { key: 'vd_name' },
            { key: 'rc_curr' },
            { key: 'rc_net_amt', cls: 'num', render: (r) => Phai.esc(money(r.rc_net_amt)) },
            { key: 'lc_total', cls: 'num', render: (r) => (Number(r.lc_total) ? Phai.esc(money(r.lc_total)) : '') },
            { key: 'lc_allocated', render: (r) => (Number(r.lc_allocated) === 1 ? `<span class="badge bg-green-lt">${Phai.esc(cfg.text.allocated)}</span>` : '') },
        ],
        onClick: (r) => { location.href = Phai.url('/landed-costs/' + encodeURIComponent(r.rc_nbr)); },
    }).reload();
})();
