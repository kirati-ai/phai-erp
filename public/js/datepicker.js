/** Local calendar in the screen's language (Thai: Buddhist years); underlying date values remain YYYY-MM-DD. */
(function () {
    'use strict';
    const L = window.PHAI_LANG || {}; // the screen's language (lang/<locale>/js.php): month names, the year (พ.ศ. = +543 in Thai)
    const months = (L.months || 'January,February,March,April,May,June,July,August,September,October,November,December').split(',');
    const weekdays = (L.cal_weekdays || 'Su,Mo,Tu,We,Th,Fr,Sa').split(',');
    const yearOffset = Number(L.year_offset || 0);
    const popup = document.createElement('div');
    popup.className = 'phai-calendar';
    popup.hidden = true;
    popup.setAttribute('role', 'dialog');
    popup.setAttribute('aria-label', L.cal_pick || 'Pick a date');
    document.body.append(popup);
    let input = null, year, month;
    const iso = (d) => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    const allowed = (value) => (!input.min || value >= input.min) && (!input.max || value <= input.max);
    function close() { popup.hidden = true; input = null; }
    function select(value) {
        if (!input || input.disabled || input.readOnly || (value && !allowed(value))) return;
        const target = input;
        target.value = value;
        target.dispatchEvent(new Event('input', {bubbles:true}));
        target.dispatchEvent(new Event('change', {bubbles:true}));
        close();
        target.focus();
    }
    function render() {
        popup.innerHTML = `<div class="d-flex align-items-center gap-1 mb-2"><button type="button" class="btn btn-sm btn-icon" data-move="-1" aria-label="${L.cal_prev || ''}">‹</button><select class="form-select form-select-sm" data-month aria-label="${L.cal_month || ''}">${months.map((m,i)=>`<option value="${i}" ${i===month?'selected':''}>${m}</option>`).join('')}</select><input type="number" class="form-control form-control-sm" data-year aria-label="${L.cal_year || ''}" value="${year+yearOffset}" min="${1+yearOffset}" max="${9999+yearOffset}"><button type="button" class="btn btn-sm btn-icon" data-move="1" aria-label="${L.cal_next || ''}">›</button></div><div class="phai-calendar-days">${weekdays.map(d=>`<span class="text-secondary">${d}</span>`).join('')}</div>`;
        const days = popup.querySelector('.phai-calendar-days');
        const start = new Date(year,month,1).getDay();
        for (let i=0;i<start;i++) days.append(document.createElement('span'));
        for (let n=1;n<=new Date(year,month+1,0).getDate();n++) {
            const value=iso(new Date(year,month,n));
            const b=document.createElement('button');
            b.type='button'; b.textContent=n; b.dataset.date=value;
            b.className='btn btn-sm'+(value===input.value?' btn-primary':'')+(value===iso(new Date())?' phai-today':'');
            b.disabled=!allowed(value); days.append(b);
        }
        const today=iso(new Date());
        popup.insertAdjacentHTML('beforeend',`<div class="d-flex justify-content-between mt-2"><button type="button" class="btn btn-sm" data-clear><i class="ti ti-eraser me-1"></i>${L.cal_clear || 'Clear'}</button><button type="button" class="btn btn-sm" data-date="${today}" ${allowed(today)?'':'disabled'}><i class="ti ti-calendar-event me-1"></i>${L.cal_today || 'Today'}</button></div>`);
    }
    document.addEventListener('click', (e) => {
        const target=e.target.closest('input[type="date"]');
        if (target && !target.disabled && !target.readOnly) {
            e.preventDefault(); input=target;
            (target.closest('.modal') || document.body).append(popup);
            const d=target.value?new Date(target.value+'T00:00:00'):new Date();
            year=d.getFullYear(); month=d.getMonth(); render(); popup.hidden=false;
            const r=target.getBoundingClientRect();
            popup.style.left=Math.max(8,Math.min(r.left,innerWidth-popup.offsetWidth-8))+'px';
            popup.style.top=Math.max(8,r.bottom+popup.offsetHeight>innerHeight?r.top-popup.offsetHeight:r.bottom+4)+'px';
        } else if (!popup.contains(e.target)) close();
    });
    popup.addEventListener('click',(e)=>{
        const b=e.target.closest('button'); if (!b || b.disabled) return;
        if (b.dataset.date) select(b.dataset.date);
        else if (b.hasAttribute('data-clear')) select('');
        else if (b.dataset.move) { const d=new Date(year,month+Number(b.dataset.move),1); year=d.getFullYear();month=d.getMonth();render(); }
    });
    popup.addEventListener('change',(e)=>{
        if (e.target.hasAttribute('data-month')) month=Number(e.target.value);
        else if (e.target.hasAttribute('data-year')) { const y=Number(e.target.value)-yearOffset; if (y<1 || y>9999) return; year=y; }
        render();
    });
    document.addEventListener('keydown',(e)=>{
        if (e.key==='Escape' && !popup.hidden) { e.preventDefault(); e.stopPropagation(); const target=input;close();target?.focus(); }
        else if (e.key==='ArrowDown' && e.target.matches('input[type="date"]') && !e.target.disabled && !e.target.readOnly) { e.preventDefault();e.target.click();popup.querySelector('select').focus(); }
    },true);
    document.addEventListener('scroll', (e)=>{if(!popup.contains(e.target))close();},true);
    window.addEventListener('resize',close);

    // an empty date shows its yyyy-mm-dd faint, like a placeholder; also when a page sets .value in code
    const mark = (el) => el.classList.toggle('phai-date-empty', el.value === '');
    const prop = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    Object.defineProperty(HTMLInputElement.prototype, 'value', {
        get() { return prop.get.call(this); },
        set(v) { prop.set.call(this, v); if (this.type === 'date') mark(this); },
        configurable: true,
    });
    for (const type of ['input', 'change']) {
        document.addEventListener(type, (e) => { if (e.target.type === 'date') mark(e.target); }, true);
    }
    const markNew = () => document.querySelectorAll('input[type="date"]:not([data-date-mark])').forEach((el) => { el.dataset.dateMark = ''; mark(el); });
    new MutationObserver(markNew).observe(document.documentElement, { childList: true, subtree: true });
    markNew();
})();
