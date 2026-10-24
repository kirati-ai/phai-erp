/** AI settings and usage (views/system/ai-settings.php). */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('ai-settings-config').textContent);
    const form = document.getElementById('ai-settings');
    const el = form.elements;
    const usd = (v, d = 4) => '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
    const int = (v) => Number(v || 0).toLocaleString('en-US');
    const when = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') + ' ' + String(v).slice(11, 16) : '');

    function fill(d) {
        el.enabled.checked = d.enabled;
        el.provider.value = d.provider;
        el.model.value = d.model;
        el.model.placeholder = cfg.providers[d.provider] || '';
        el.api_key.value = '';
        el.api_key.placeholder = d.key_set ? cfg.text.key_saved.replace(':hint', d.key_hint) : '';
        el.price_in.value = d.price_in;
        el.price_out.value = d.price_out;
        document.getElementById('not-writable').classList.toggle('d-none', d.writable);

        const rows = d.usage;
        const tbody = document.querySelector('#ai-usage tbody');
        tbody.innerHTML = rows.length ? rows.map((r) => `
            <tr>
                <td><span class="fw-medium">${Phai.esc(r.ai_userid ?? '')}</span> <span class="text-secondary">${Phai.esc(r.us_name ?? '')}</span></td>
                <td class="num">${int(r.month_questions)}</td>
                <td class="num">${usd(r.month_cost)}</td>
                <td class="num">${int(r.questions)}${Number(r.errors) ? ` <span class="text-danger small">(${int(r.errors)})</span>` : ''}</td>
                <td class="num">${int(r.tokens)}</td>
                <td class="num">${usd(r.cost)}</td>
                <td class="text-secondary">${Phai.esc(when(r.last_at))}</td>
            </tr>`).join('') : `<tr><td colspan="7" class="text-center text-secondary py-3">${Phai.esc(cfg.text.empty)}</td></tr>`;
        const sum = (k) => rows.reduce((s, r) => s + Number(r[k] || 0), 0);
        document.querySelector('#ai-usage tfoot').innerHTML = rows.length > 1 ? `
            <tr><td>${Phai.esc(cfg.text.total)}</td><td class="num">${int(sum('month_questions'))}</td><td class="num">${usd(sum('month_cost'))}</td>
                <td class="num">${int(sum('questions'))}</td><td class="num">${int(sum('tokens'))}</td><td class="num">${usd(sum('cost'))}</td><td></td></tr>` : '';
    }

    el.provider.addEventListener('change', () => { el.model.placeholder = cfg.providers[el.provider.value] || ''; });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('[type=submit]');
        Phai.form.errors(form, null);
        Phai.busy(btn, true);
        try {
            fill(await Phai.api.put('/api/settings/ai', {
                enabled: el.enabled.checked, provider: el.provider.value, model: el.model.value.trim(),
                api_key: el.api_key.value.trim(), price_in: el.price_in.value, price_out: el.price_out.value,
            }));
            Phai.toast(Phai.t('saved'));
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
        } finally {
            Phai.busy(btn, false);
        }
    });

    Phai.api.get('/api/settings/ai').then(fill);
})();
