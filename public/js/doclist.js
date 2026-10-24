/** Document list (views/doc/list.php): search, status filter, click a row to open it. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('doclist-config').textContent);
    const status = document.getElementById('status');
    // a menu link may open the list already filtered, e.g. /journals?status=AR
    const preset = new URLSearchParams(location.search).get('status');
    if (status && preset && [...status.options].some((o) => o.value === preset)) status.value = preset;
    const key = cfg.key || cfg.columns[0].key;
    // one month at a time (cfg.month = the date column): this month first, ‹ › step a month, "every month" shows all
    const monthBox = cfg.month ? Phai.month('#month-picker', {
        value: new URLSearchParams(location.search).get('month'),
        change: () => { grid.state.page = 1; grid.reload(); },
    }) : null;
    const statusKey = cfg.columns.find((c) => c.type === 'status')?.key;
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const date = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '');

    function cell(c, r) {
        const v = r[c.key];
        switch (c.type) {
            case 'date': return Phai.esc(date(v));
            case 'money': return Phai.esc(money(v)) + (c.suffix ? ` <span class="text-secondary small">${Phai.esc(r[c.suffix] ?? '')}</span>` : '');
            case 'status': {
                const s = cfg.status[v] || { text: v, color: 'secondary' };
                return `<span class="badge bg-${s.color}-lt">${Phai.esc(s.text)}</span>`;
            }
            default: return Phai.esc(v ?? '');
        }
    }

    const grid = Phai.grid({
        table: '#docs',
        url: cfg.api,
        search: '#q',
        sort: cfg.sort || key,
        files: cfg.files ? { doc: cfg.files, key } : null,
        dir: 'desc',
        query: () => ({ status: status ? status.value : '', ...(monthBox && monthBox.value ? { month: monthBox.value, month_key: cfg.month } : {}) }),
        columns: cfg.columns.map((c) => ({
            key: c.key,
            cls: c.type === 'money' ? 'num text-nowrap' : (c.strong ? 'text-nowrap' : ''),
            render: (r) => cell(c, r),
        })),
        // a document that is only its printed form (cfg.view === false, e.g. 50 ทวิ) opens printed
        onClick: cfg.view === false
            ? (r) => window.open(Phai.url(cfg.print.replace('{key}', encodeURIComponent(r[key]))), '_blank', 'noopener')
            : (r) => { location.href = Phai.url(cfg.page + '/' + encodeURIComponent(r[key])); },
        actions: {
            view: cfg.view === false ? null : (r) => { location.href = Phai.url(cfg.page + '/' + encodeURIComponent(r[key])); },
            print: cfg.print ? (r) => cfg.print.replace('{key}', encodeURIComponent(r[key])) : null,
            // edit opens the document ready to edit; undo cancels it (a journal is reversed)
            edit: cfg.editWhen ? (r) => { location.href = Phai.url(cfg.page + '/' + encodeURIComponent(r[key]) + '?edit=1'); } : null, // opens it ready to edit
            canEdit: (r) => !!(cfg.editWhen && cfg.editWhen.includes(r[statusKey])),
            undo: cfg.undoWhen ? async (r) => {
                if (!(await Phai.confirm(cfg.undoConfirm))) return;
                try {
                    await Phai.api.post(cfg.api + '/' + encodeURIComponent(r[key]) + cfg.undoApi);
                } catch (err) {
                    return;
                }
                Phai.toast(Phai.t('saved'));
                grid.reload();
            } : null,
            canUndo: (r) => !!(cfg.undoWhen && cfg.undoWhen.includes(r[statusKey])),
        },
    });

    status?.addEventListener('change', () => {
        grid.state.page = 1;
        grid.reload();
    });
    grid.reload();
})();
