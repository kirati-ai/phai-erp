/** e-Tax log page: the month picker beside "XML of the month" points the link at /etax/xml/<yyyy-mm>. */
(function () {
    'use strict';

    const month = document.getElementById('xml-month');
    const link = document.getElementById('xml-zip');
    if (!month || !link) return;
    const point = () => { link.href = Phai.url('/etax/xml/' + encodeURIComponent(month.value)); };
    month.addEventListener('change', point);
    point();
})();
