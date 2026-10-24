/* Stock card (views/inventory/stock-card.php): per item, the movements in a period with the running balance — one item or all. */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('card-config').textContent);
    const T = cfg.text;
    const $ = (id) => document.getElementById(id);
    const money = (v) => (v === null || v === undefined ? '' : Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    const qty = (v) => (v === null || v === undefined ? '' : Number(v).toLocaleString('en-US', { maximumFractionDigits: 5 }));
    const date = (v) => v.split('-').reverse().join('/');
    const body = $('card').querySelector('tbody');
    const params = () => ({ part: $('part').value.trim().toUpperCase(), type: $('type').value, site: $('site').value, from: $('from').value, to: $('to').value });

    // the item box starts with "all items" (empty value)
    Phai.pick.source('card-parts', {
        load: async (q) => [
            ...(q ? [] : [{ value: '', label: T.all }]),
            ...(await Phai.api.get('/api/stock/parts', { q })).map((r) => ({ value: r.pt_part, label: r.pt_desc1 ?? '' })),
        ],
    });

    const row = (r) => `<tr>
        <td>${Phai.esc(date(r.date))}</td><td>${Phai.esc(r.nbr ?? '')}</td>
        <td>${Phai.esc(r.type_text)} <span class="text-secondary small">${Phai.esc(r.where)}</span></td>
        <td class="num">${money(r.cost)}</td>
        <td class="num">${qty(r.in_qty)}</td><td class="num">${money(r.in_value)}</td>
        <td class="num">${qty(r.out_qty)}</td><td class="num">${money(r.out_value)}</td>
        <td class="num">${qty(r.qty)}</td><td class="num">${money(r.value)}</td></tr>`;
    const card = (c, i, from) => `<tr class="phai-card-item"><td colspan="10"><span class="me-2">${i + 1}.</span><span class="me-2">${Phai.esc(c.part.pt_part)}</span>${Phai.esc(c.part.pt_desc1 ?? '')}
            <span class="text-secondary fw-normal ms-1">(${Phai.esc(c.part.pt_um)})</span></td></tr>
        <tr class="text-secondary"><td>${Phai.esc(date(from))}</td><td colspan="7">${Phai.esc(T.opening)}</td>
            <td class="num">${qty(c.opening.qty)}</td><td class="num">${money(c.opening.value)}</td></tr>`
        + c.rows.map(row).join('')
        + `<tr class="phai-card-total"><td colspan="4">${Phai.esc(T.total)} ${Phai.esc(c.part.pt_um)}</td>
            <td class="num">${qty(c.in.qty)}</td><td class="num">${money(c.in.value)}</td>
            <td class="num">${qty(c.out.qty)}</td><td class="num">${money(c.out.value)}</td>
            <td class="num">${qty(c.closing.qty)}</td><td class="num">${money(c.closing.value)}</td></tr>`;

    async function load() {
        const p = params();
        let d;
        try {
            d = await Phai.api.get('/api/stock-card', p);
        } catch (_) {
            return;
        }
        body.innerHTML = d.cards.length
            ? d.cards.map((c, i) => card(c, i, d.from)).join('')
                + (d.cards.length > 1 ? `<tr class="phai-card-grand"><td colspan="4">${Phai.esc(T.grand)}</td>
                    <td></td><td class="num">${money(d.total.in)}</td><td></td><td class="num">${money(d.total.out)}</td>
                    <td></td><td class="num">${money(d.total.closing)}</td></tr>` : '')
            : `<tr><td colspan="10" class="text-center text-secondary py-3">${Phai.esc(T.none)}</td></tr>`;
        const print = $('card-print');
        if (print) {
            print.hidden = !d.cards.length;
            print.href = Phai.url('/stock-card/print?' + new URLSearchParams(p));
        }
    }

    // the month picker fills the dates with the whole month; dates typed in that are not a whole month show "กำหนดช่วงเอง"
    const monthEnd = (ym) => {
        const [y, m] = ym.split('-').map(Number);
        return `${ym}-${String(new Date(y, m, 0).getDate()).padStart(2, '0')}`;
    };
    const whole = () => {
        const ym = $('from').value.slice(0, 7);
        return ym && $('from').value === ym + '-01' && $('to').value === monthEnd(ym) ? ym : '';
    };
    const month = Phai.month('#card-month', {
        value: whole(),
        empty: T.range,
        change: (v) => {
            if (!v) return;
            $('from').value = v + '-01';
            $('to').value = monthEnd(v);
            load();
        },
    });

    for (const id of ['part', 'type', 'site']) $(id).addEventListener('change', load);
    for (const id of ['from', 'to']) $(id).addEventListener('change', () => { month.set(whole(), false); load(); });
    const pre = new URLSearchParams(location.search).get('part');
    if (pre) $('part').value = pre;
    load();
})();
