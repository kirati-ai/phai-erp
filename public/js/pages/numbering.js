(function () {
    'use strict';

    const form = document.getElementById('numbering');
    const shapes = JSON.parse(document.getElementById('numbering-shapes').textContent);

    /** Show what the next number will look like, e.g. PO2026100001. */
    function example(tr) {
        const doc = tr.dataset.doc;
        const prefix = form.elements['prefix_' + doc].value.trim().toUpperCase();
        const shape = shapes[form.elements['format_' + doc].value];
        const next = form.elements['next_' + doc].value.trim();
        tr.querySelector('[data-example]').textContent = shape && /^\d+$/.test(next)
            ? prefix + shape.date + next.padStart(shape.digits, '0') : '';
    }

    form.querySelectorAll('tr[data-doc]').forEach(example);
    form.addEventListener('input', (e) => {
        const tr = e.target.closest('tr[data-doc]');
        if (tr) example(tr);
    });
    form.addEventListener('change', (e) => {
        const tr = e.target.closest('tr[data-doc]');
        if (tr) example(tr);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (await Phai.form.submit(form, 'PUT', '/api/settings/numbering')) Phai.toast(Phai.t('saved'));
    });
})();
