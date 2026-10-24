/* Vendor evaluation (views/purchase/vendor-rating.php): on time, returned, lead time, score and grade per vendor. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('vr-config').textContent);
    const $ = (id) => document.getElementById(id);
    const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const pct = (v) => `${Number(v).toLocaleString('en-US', { maximumFractionDigits: 1 })}%`;
    const colors = { A: 'green', B: 'yellow', C: 'red' };

    async function load() {
        const d = await Phai.api.get('/api/vendor-rating', { from: $('from').value, to: $('to').value });
        $('vr').querySelector('tbody').innerHTML = d.rows.length ? d.rows.map((r) => `
            <tr>
                <td><a href="${Phai.url('/vendors?q=' + encodeURIComponent(r.vendor))}">${Phai.esc(r.vendor)}</a></td><td>${Phai.esc(r.vd_name ?? '')}</td>
                <td class="num">${money(r.value)}</td><td class="num">${Phai.esc(r.lines)}</td>
                <td class="num">${pct(r.on_time_pct)}</td><td class="num">${pct(r.returned_pct)}</td>
                <td class="num">${Phai.esc(r.lead_days ?? '')}</td><td class="num">${Phai.esc(r.score)}</td>
                <td class="text-center"><span class="badge bg-${colors[r.grade] || 'secondary'}-lt">${Phai.esc(r.grade)}</span></td>
            </tr>`).join('') : `<tr><td colspan="9" class="text-center text-secondary py-3">${Phai.esc(cfg.text.none)}</td></tr>`;
    }

    $('from').addEventListener('change', load);
    $('to').addEventListener('change', load);
    load();
})();
