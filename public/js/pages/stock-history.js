(function () {
    'use strict';

    const typeText = JSON.parse(document.getElementById('type-text').textContent);
    const f = { from: 'from', to: 'to', site: 'site', type: 'type' };
    for (const k in f) f[k] = document.getElementById(f[k]);
    const partBadge = document.getElementById('part-filter');

    // Opened from the balance page: ?part=X&site=Y
    const params = new URLSearchParams(location.search);
    let part = params.get('part') || '';
    if (params.get('site')) f.site.value = params.get('site');

    function showPart() {
        partBadge.classList.toggle('d-none', !part);
        partBadge.innerHTML = part ? `${Phai.esc(part)} <a href="#" class="ms-1 text-reset" data-clear><i class="ti ti-x"></i></a>` : '';
    }
    partBadge.addEventListener('click', (e) => {
        if (!e.target.closest('[data-clear]')) return;
        e.preventDefault();
        part = '';
        showPart();
        reload();
    });

    const grid = PhaiStockHistory.grid({
        types: typeText,
        search: '#q',
        query: () => ({ part, from: f.from.value, to: f.to.value, site: f.site.value, type: f.type.value }),
    });

    function reload() {
        grid.state.page = 1;
        grid.reload();
    }
    Object.values(f).forEach((x) => x.addEventListener('change', reload));
    showPart();
    reload();
})();
