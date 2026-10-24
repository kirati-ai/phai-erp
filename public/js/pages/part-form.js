/* Item form: pt_ad_code picks a vendor for a raw material (RM line), a customer for anything else. */
(function () {
    'use strict';

    const line = document.getElementById('f-pt_prod_line');
    const ad = document.getElementById('f-pt_ad_code');
    if (!line || !ad) return;
    let types = {};

    function sync() {
        // nothing to pick from until the product line says vendor or customer
        ad.disabled = !line.value || !(line.value in types);
        const pick = types[line.value] === 'RM' ? 'vendors' : 'customers';
        if (ad.dataset.pick === pick) return;
        ad.dataset.pick = pick;
        ad.value = ad.value; // shows the code with its name from the new list
    }
    Phai.api.get('/api/parts/line-types').then((t) => { types = t || {}; sync(); });
    line.addEventListener('change', sync);
    document.addEventListener('shown.bs.modal', sync); // a record was filled in by code
})();
