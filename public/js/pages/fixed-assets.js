(function () {
    'use strict';
    const cfg = JSON.parse(document.getElementById('asset-config').textContent);
    const code = cfg.asset?.fa_code;
    const path = '/api/fixed-assets' + (code ? '/' + encodeURIComponent(code) : '');
    const form = document.getElementById('asset-form');
    if (form) {
        if (cfg.asset) Phai.form.fill(form, cfg.asset);
        else form.reset();
        if (code) form.elements.fa_code.readOnly = true;
        const setCategoryDefaults = () => {
            const c = cfg.categories.find(c => c.fc_code === form.elements.fa_category.value);
            if (!c) return;
            form.elements.fa_method.value = c.fc_method;
            form.elements.fa_life_months.value = c.fc_life_months;
            form.elements.fa_rate.value = c.fc_rate;
        };
        form.elements.fa_category.addEventListener('change', setCategoryDefaults);
        // เอกสารซื้ออ้างอิง = the vendor's invoice of a receipt of an FA item (its cost is already in the asset account):
        // picking one fills vendor, date acquired, cost per unit and category from the receipt; any other reference may be typed
        const receipts = new Map();
        const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        Phai.pick.source('fa-receipts', {
            load: async (q) => (await Phai.api.get('/api/fixed-assets/receipts', { q })).map((r) => {
                if (!receipts.has(r.rc_inv_nbr) || Number(receipts.get(r.rc_inv_nbr).registered) >= Number(receipts.get(r.rc_inv_nbr).qty)) receipts.set(r.rc_inv_nbr, r);
                const done = Number(r.registered) ? ` · ${cfg.text.registered.replace(':n', Number(r.registered))}` : '';
                return { value: r.rc_inv_nbr, label: `${r.rc_nbr} · ${r.rcd_part} ${r.pt_desc1 ?? ''} · ${r.vd_name ?? r.rc_vd_code} · ${Number(r.qty)} × ${money(r.unit_cost)}${done}` };
            }),
        });
        form.elements.fa_source_ref.addEventListener('change', () => {
            const r = receipts.get(form.elements.fa_source_ref.value);
            if (!r) return;
            form.elements.fa_vendor.value = r.rc_vd_code;
            form.elements.fa_acquired.value = r.rc_date;
            form.elements.fa_cost.value = r.unit_cost;
            if (!form.elements.fa_desc.value) form.elements.fa_desc.value = r.pt_desc1 ?? '';
            if (r.category && form.elements.fa_category.value !== r.category) {
                form.elements.fa_category.value = r.category;
                setCategoryDefaults();
            }
        });
        if (!code) setCategoryDefaults();
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = form.querySelector('button[type=submit]');
            Phai.busy(button, true); Phai.form.errors(form, null);
            try {
                const a = await Phai.api(code ? 'PUT' : 'POST', path, Phai.form.read(form));
                location.href = Phai.url('/fixed-assets/' + encodeURIComponent(a.fa_code));
            } catch (error) { Phai.form.errors(form, error.fields); }
            finally { Phai.busy(button, false); }
        });
    }
    document.getElementById('delete-asset')?.addEventListener('click', async () => {
        if (!await Phai.confirm(cfg.text.confirmDelete)) return;
        try { await Phai.api('DELETE', path); location.href = Phai.url('/fixed-assets'); } catch (_) {}
    });
    document.querySelectorAll('.asset-action').forEach((f) => {
        if (f.dataset.action === 'transfer') {
            f.elements.site.value = cfg.asset.fa_site;
            f.elements.cc.value = cfg.asset.fa_cc || '';
        }
        f.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!await Phai.confirm(cfg.text.confirmAction)) return;
            const btn = f.querySelector('button');
            Phai.busy(btn, true); Phai.form.errors(f, null);
            try { await Phai.api('POST', path + '/' + f.dataset.action, Phai.form.read(f)); location.reload(); }
            catch (error) { Phai.form.errors(f, error.fields); }
            finally { Phai.busy(btn, false); }
        });
    });
})();
