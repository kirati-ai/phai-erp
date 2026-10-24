/*
 * The stock history grid, shared by the stock history page and the stock line view so every history
 * looks the same: transaction number, date and time first, then what, which document, item and place, before → change → after, value, why, who.
 * Table markup: views/inventory/history-table.php (same column order).
 *
 *   PhaiStockHistory.grid({ types, query: () => ({...}), search: '#q' })
 */
window.PhaiStockHistory = (function () {
    'use strict';

    const num = (v) => Number(v).toLocaleString('en-US', { maximumFractionDigits: 5 });
    const money = (v) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const date = (v) => (v ? v.split('-').reverse().join('/') : '');

    function grid({ types, query, search }) {
        return Phai.grid({
            table: '#history',
            url: '/api/stock/history',
            search,
            sort: 'tr_trnbr',
            dir: 'desc',
            query,
            columns: [
                { key: 'tr_trnbr', cls: 'text-nowrap' },
                { key: 'tr_date', cls: 'text-nowrap', render: (r) => Phai.esc(date(r.tr_date)) },
                { key: 'tr_time', cls: 'text-nowrap text-secondary' },
                { key: 'tr_type', render: (r) => Phai.esc(types[r.tr_type] || r.tr_type) },
                { key: 'tr_nbr', cls: 'text-nowrap' },
                { key: 'tr_part' },
                { key: 'pt_desc1' },
                { key: 'tr_loc', render: (r) => Phai.esc(r.tr_site + ' / ' + r.tr_loc) },
                { key: 'tr_lot' },
                { key: 'tr_begin_qty', cls: 'num text-secondary', render: (r) => Phai.esc(num(r.tr_begin_qty)) },
                {
                    key: 'tr_chg_qty',
                    cls: 'num',
                    render: (r) => `<span class="${Number(r.tr_chg_qty) < 0 ? 'text-danger' : 'text-success'}">${Number(r.tr_chg_qty) > 0 ? '+' : ''}${Phai.esc(num(r.tr_chg_qty))}</span>`,
                },
                { key: 'tr_loc_qty', cls: 'num', render: (r) => Phai.esc(num(r.tr_loc_qty)) },
                { key: 'tr_um', cls: 'text-secondary' },
                { key: 'movement_value', cls: 'num', render: (r) => Phai.esc(money(r.tr_chg_qty * r.tr_cost)) },
                { key: 'tr_rsn_code' },
                { key: 'tr_userid', cls: 'text-secondary' },
                { key: 'tr_line', hidden: true },
                { key: 'tr_price', hidden: true, cls: 'num', render: (r) => Phai.esc(num(r.tr_price)) },
                { key: 'tr_cost', hidden: true, cls: 'num', render: (r) => Phai.esc(num(r.tr_cost)) },
                { key: 'tr_rmks', hidden: true },
            ],
        });
    }

    return { grid };
})();
