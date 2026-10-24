(function () {
    'use strict';
    const cfg = JSON.parse(document.getElementById('depr-config').textContent);
    const month = document.getElementById('depr-month');
    // opened from the work queue on the asset list: ?month=yyyy-mm
    const want = new URLSearchParams(location.search).get('month');
    if (want && /^\d{4}-\d{2}$/.test(want)) month.value = want;
    const table = document.querySelector('#depr-table tbody');
    const print = document.getElementById('depr-print');
    const syncPrint = () => {if (print) print.href = Phai.url('/fixed-assets/depreciation/print?pdf=1&month=' + encodeURIComponent(month.value));};
    syncPrint();
    const post = document.getElementById('post');
    const total = document.getElementById('depr-total');
    let rows = [], previewMonth = '';
    const money = n => Number(n).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    function selection() {
        const selected = new Set([...table.querySelectorAll('input:checked')].map(e => e.value));
        if (post) post.disabled = !selected.size || month.value !== previewMonth;
        total.textContent = cfg.text.total.replace(':n', selected.size).replace(':amount', money(rows.filter(r => selected.has(r.fa_code)).reduce((sum,r) => sum + r.depreciation, 0)));
        return [...selected];
    }
    async function preview() {
        if (post) post.disabled = true;
        try {
            const data = await Phai.api('GET', '/api/fixed-assets/depreciation-preview', {month: month.value});
            rows = data.rows; previewMonth = data.month;
            table.innerHTML = rows.map(r => `<tr><td><input type="checkbox" value="${Phai.esc(r.fa_code)}" ${r.eligible ? 'checked' : 'disabled'} aria-label="${Phai.esc(cfg.text.select.replace(':code', r.fa_code))}"></td><td><a href="${Phai.url('/fixed-assets/' + encodeURIComponent(r.fa_code))}">${Phai.esc(r.fa_code)}</a></td><td>${Phai.esc(r.fa_desc)}</td><td>${Phai.esc(r.fa_method)}</td><td class="text-end">${money(r.depreciation)}</td><td>${Phai.esc(r.reason)}</td></tr>`).join('') || `<tr><td colspan="6" class="text-center text-secondary">${Phai.esc(cfg.text.none)}</td></tr>`;
            document.getElementById('select-all').checked = true;
            selection();
        } catch (_) {}
    }
    table.addEventListener('change', selection);
    month.addEventListener('input', () => {syncPrint();if (post) post.disabled = true;});
    document.getElementById('select-all').addEventListener('change', e => {table.querySelectorAll('input:not(:disabled)').forEach(c => c.checked = e.target.checked); selection();});
    document.getElementById('preview').addEventListener('click', preview);
    post?.addEventListener('click', async () => {
        if (month.value !== previewMonth) return;
        const codes = selection();
        if (!codes.length || !await Phai.confirm(cfg.text.confirm.replace(':month', previewMonth).replace(':n', codes.length))) return;
        Phai.busy(post, true);
        try { await Phai.api('POST', '/api/fixed-assets/depreciation-batch', {month: previewMonth, codes}); await preview(); }
        catch (_) {} finally { Phai.busy(post, false); selection(); }
    });
    preview();
})();
