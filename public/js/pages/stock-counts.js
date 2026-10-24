(function () {
    'use strict';

    const status = JSON.parse(document.getElementById('status-text').textContent);
    const date = (v) => (v ? v.split('-').reverse().join('/') : '');
    const open = (batch) => { location.href = Phai.url('/stock-counts/' + encodeURIComponent(batch)); };

    Phai.grid({
        table: '#counts',
        url: '/api/stock-counts',
        sort: 'pi_batch',
        dir: 'desc',
        files: { doc: 'PI', key: 'pi_batch' },
        columns: [
            { key: 'pi_batch' },
            { key: 'pi_date', render: (r) => Phai.esc(date(r.pi_date)) },
            { key: 'pi_site' },
            { key: 'pi_desc' },
            { key: 'pi_locs', render: (r) => Phai.esc(r.pi_locs ? r.pi_locs.split(',').join(', ') : status.all) },
            { key: 'counted', cls: 'num', render: (r) => Phai.esc(`${r.counted} / ${r.tags}`) },
            {
                key: 'pi_status',
                render: (r) => `<span class="badge ${r.pi_status === 'P' ? 'bg-green-lt' : 'bg-yellow-lt'}">${Phai.esc(status[r.pi_status] || r.pi_status)}</span>`,
            },
        ],
        onClick: (r) => open(r.pi_batch),
    }).reload();

    const modalEl = document.getElementById('new-count');
    if (!modalEl) return;
    const modal = (window.bootstrap || window.tabler).Modal.getOrCreateInstance(modalEl);
    const form = modalEl.querySelector('form');

    // what to count: the whole warehouse, or the locations ticked (a cycle count)
    const box = document.getElementById('scope-locs');
    const scope = () => form.querySelector('[name="scope"]:checked').value;
    async function loadLocs() {
        const rows = await Phai.api.get('/api/stock/locations', { site: form.elements.site.value });
        box.innerHTML = rows.map((l) => `<label class="form-check mb-0"><input class="form-check-input" type="checkbox" value="${Phai.esc(l.loc_loc)}" data-loc>
            <span class="form-check-label">${Phai.esc(l.loc_loc)}${l.loc_desc ? ' · ' + Phai.esc(l.loc_desc) : ''}</span></label>`).join('');
    }
    form.addEventListener('change', (e) => {
        if (e.target.name === 'scope') box.hidden = scope() !== 'locs';
        if (e.target.name === 'site') loadLocs();
    });

    document.getElementById('add').addEventListener('click', () => { loadLocs(); modal.show(); });
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = Phai.form.read(form);
        body.locs = scope() === 'locs' ? [...box.querySelectorAll('[data-loc]:checked')].map((c) => c.value) : [];
        Phai.form.errors(form, null);
        if (scope() === 'locs' && !body.locs.length) {
            Phai.form.errors(form, { locs: status.pick });
            return;
        }
        const btn = form.querySelector('[type="submit"]');
        Phai.busy(btn, true);
        try {
            open((await Phai.api.post('/api/stock-counts', body)).pi_batch);
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
        } finally {
            Phai.busy(btn, false);
        }
    });
})();
