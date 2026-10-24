(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('count-config').textContent);
    const api = '/api/stock-counts/' + encodeURIComponent(cfg.batch);
    const form = document.getElementById('count');
    const tbody = form.querySelector('tbody');
    const q = document.getElementById('q');
    const onlyDiff = document.getElementById('only-diff');
    const num = (v) => (v === null || v === '' ? '' : Number(v).toLocaleString('en-US', { maximumFractionDigits: 5 }));
    let tags = [];

    function variance(tr) {
        const input = tr.querySelector('input');
        const counted = input ? input.value.replace(/,/g, '').trim() : tr.dataset.count;
        const cell = tr.querySelector('[data-var]');
        if (counted === '' || isNaN(counted)) {
            cell.innerHTML = `<span class="text-secondary small">${Phai.esc(cfg.text.not_counted)}</span>`;
            tr.dataset.diff = '1';
            return;
        }
        const d = Number(counted) - Number(tr.dataset.sys);
        cell.innerHTML = d === 0 ? '0' : `<span class="${d < 0 ? 'text-danger' : 'text-success'}">${d > 0 ? '+' : ''}${Phai.esc(num(d))}</span>`;
        tr.dataset.diff = d === 0 ? '' : '1';
    }

    /** Void / use again, and recount for a counted tag (icons at the end of the row). */
    function actions(t) {
        if (!cfg.canEdit) return '';
        const btn = (act, icon, title, cls = '') => `<button type="button" class="btn btn-sm btn-icon btn-ghost-secondary ${cls}" data-act="${act}" title="${Phai.esc(title)}" aria-label="${Phai.esc(title)}"><i class="ti ti-${icon}"></i></button>`;
        if (t.pit_status === 'V') return `<td class="text-nowrap">${btn('restore', 'arrow-back-up', cfg.text.restore)}</td>`;
        return `<td class="text-nowrap">${t.pit_status === 'C' ? btn('recount', 'repeat', cfg.text.recount) : ''}${btn('void', 'ban', cfg.text.void_do, 'text-danger')}</td>`;
    }

    function render() {
        tbody.innerHTML = tags.map((t) => {
            const voided = t.pit_status === 'V';
            // the first count, kept when the tag was sent to be counted again
            const first = t.pit_count1 === null ? '' : `<div class="small text-secondary">${Phai.esc(cfg.text.first.replace(':qty', num(t.pit_count1)).replace(':who', t.pit_counter1 ?? ''))}</div>`;
            return `
            <tr data-tag="${t.pit_tag}" data-sys="${Number(t.pit_sys_qty)}" data-count="${t.pit_count_qty ?? ''}" ${voided ? 'data-void="1" class="text-secondary text-decoration-line-through"' : ''}>
                <td class="text-secondary">${t.pit_tag}</td>
                <td>${Phai.esc(t.pit_loc)}</td>
                <td class="fw-medium">${Phai.esc(t.pit_part)}</td>
                <td>${Phai.esc(t.pt_desc1 ?? '')}</td>
                <td>${Phai.esc(t.pit_lot)}</td>
                <td class="num">${voided ? '' : Phai.esc(num(t.pit_sys_qty))}</td>
                <td class="num">${voided ? `<span class="badge bg-secondary-lt">${Phai.esc(cfg.text.void)}</span>` : (cfg.canEdit
                    ? `<input class="form-control form-control-sm text-end" name="count_${t.pit_tag}" inputmode="decimal" value="${t.pit_count_qty === null ? '' : Number(t.pit_count_qty)}">`
                    : Phai.esc(num(t.pit_count_qty)))}${first}</td>
                <td class="num" data-var></td>
                <td class="text-secondary">${Phai.esc(t.pt_um ?? '')}</td>
                <td class="small">${Phai.esc(t.pit_counter ?? '')}</td>
                <td class="num text-secondary">${t.pit_cost === null ? '' : Phai.esc(Number(t.pit_cost).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 5 }))}</td>
                ${actions(t)}
            </tr>`;
        }).join('');
        [...tbody.rows].forEach((tr) => {
            if (tr.dataset.void) tr.querySelector('[data-var]').textContent = '';
            else variance(tr);
        });
        filter();
    }

    // void / use again / recount one tag
    tbody.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-act]');
        if (!b) return;
        const tag = b.closest('tr').dataset.tag;
        const act = b.dataset.act;
        if (act === 'void' && !(await Phai.confirm(cfg.text.void_do + ' ' + tag + '?'))) return;
        const url = `${api}/tags/${tag}/${act === 'recount' ? 'recount' : 'void'}`;
        tags = await Phai.api.post(url, act === 'recount' ? {} : { void: act === 'void' });
        render();
        if (act === 'recount') tbody.querySelector(`tr[data-tag="${tag}"] input`)?.focus();
    });

    function filter() {
        const text = q.value.trim().toLowerCase();
        for (const tr of tbody.rows) {
            const match = !text || tr.textContent.toLowerCase().includes(text);
            tr.hidden = !match || (onlyDiff.checked && !tr.dataset.diff);
        }
    }
    q.addEventListener('input', filter);
    onlyDiff.addEventListener('change', filter);

    tbody.addEventListener('input', (e) => variance(e.target.closest('tr')));

    // Enter jumps to the next count box, so counting is just type + Enter.
    tbody.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        let tr = e.target.closest('tr').nextElementSibling;
        while (tr && tr.hidden) tr = tr.nextElementSibling;
        tr?.querySelector('input')?.focus();
    });

    async function load() {
        tags = (await Phai.api.get(api)).tags;
        render();
    }

    /** Send every count box; returns false when something was wrong. */
    async function save(btn) {
        const body = { counter: document.getElementById('counter')?.value ?? '' };
        tbody.querySelectorAll('input[name^="count_"]').forEach((i) => { body[i.name] = i.value; });
        Phai.form.errors(form, null);
        Phai.busy(btn, true);
        try {
            tags = await Phai.api.put(api + '/counts', body);
            render();
            return true;
        } catch (err) {
            if (err.fields) Phai.form.errors(form, err.fields);
            return false;
        } finally {
            Phai.busy(btn, false);
        }
    }

    document.getElementById('save')?.addEventListener('click', async (e) => {
        if (await save(e.currentTarget)) Phai.toast(Phai.t('saved'));
    });

    document.getElementById('post')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        if (!(await Phai.confirm(cfg.text.post_confirm))) return;
        if (cfg.canEdit && !(await save(btn))) return;
        Phai.busy(btn, true);
        try {
            const res = await Phai.api.post(api + '/post');
            Phai.toast(cfg.text.post_done.replace(':n', res.adjusted));
            setTimeout(() => location.reload(), 800);
        } catch (err) {
            Phai.busy(btn, false);
        }
    });

    document.getElementById('delete')?.addEventListener('click', async () => {
        if (!(await Phai.confirm(Phai.t('confirm_delete', { name: cfg.batch })))) return;
        await Phai.api.del(api);
        location.href = Phai.url('/stock-counts');
    });

    // Add something that was found but is not in the list.
    const newTag = document.getElementById('new-tag');
    if (newTag) {
        const field = (n) => newTag.querySelector(`[name="${n}"]`);
        document.getElementById('add-tag').addEventListener('click', async (e) => {
            const body = { part: field('part').value, loc: field('loc').value, lot: field('lot').value, qty: field('qty').value, cost: field('cost').value,
                counter: document.getElementById('counter')?.value ?? '' };
            Phai.form.errors(form, null);
            const btn = e.currentTarget; // gone from the event after the await
            Phai.busy(btn, true);
            try {
                tags = await Phai.api.post(api + '/tags', body);
                render();
                ['part', 'lot', 'qty', 'cost'].forEach((n) => { field(n).value = ''; });
                field('part').focus();
            } catch (err) {
                if (err.fields) Phai.form.errors(form, err.fields);
            } finally {
                Phai.busy(btn, false);
            }
        });
    }

    load();
})();
