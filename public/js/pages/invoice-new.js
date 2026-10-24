(function () {
    'use strict';

    const text = JSON.parse(document.getElementById('invoice-new-text').textContent);
    const tbody = document.querySelector('#to-invoice tbody');
    const q = document.getElementById('q');
    const date = (v) => (v ? v.split('-').reverse().join('/') : '');

    async function load() {
        const rows = await Phai.api.get('/api/invoices/shipments', { q: q.value.trim() });
        tbody.innerHTML = rows.length ? rows.map((r) => `
            <tr>
                <td class="fw-medium">${Phai.esc(r.sh_nbr)}</td>
                <td>${Phai.esc(date(r.sh_date))}</td>
                <td>${Phai.esc(r.sh_so_nbr)}</td>
                <td>${Phai.esc(r.sh_cm_code)}</td>
                <td>${Phai.esc(r.cm_name ?? '')}</td>
                <td><a class="btn btn-sm btn-primary" href="${Phai.esc(Phai.url('/invoices/new?sh=' + encodeURIComponent(r.sh_nbr)))}"><i class="ti ti-file-invoice me-1"></i>${Phai.esc(text.make)}</a></td>
            </tr>`).join('')
            : `<tr><td colspan="6" class="text-center text-secondary py-3">${Phai.esc(text.empty)}</td></tr>`;
    }


    let timer;
    q.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(load, 250);
    });
    load();
})();
