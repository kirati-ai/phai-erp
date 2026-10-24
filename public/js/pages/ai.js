/** Chat with the built-in AI (views/system/ai.php). The conversation lives in this browser tab only. */
(function () {
    'use strict';

    const cfgEl = document.getElementById('ai-config');
    if (!cfgEl) return; // AI not enabled
    const cfg = JSON.parse(cfgEl.textContent);
    const log = document.getElementById('ai-log');
    const form = document.getElementById('ai-form');
    const box = form.elements.question;
    const KEY = 'phai-ai-chat';
    let history = [];
    try { history = JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch (e) { history = []; }

    /** Small markdown: tables, lists, headings, **bold**, `code`, line breaks. Input is escaped first. */
    function md(text) {
        const inline = (s) => Phai.esc(s)
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/`([^`]+)`/g, '<code>$1</code>');
        const lines = String(text).split('\n');
        const out = [];
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            if (/^\s*\|.*\|\s*$/.test(line)) {
                const rows = [];
                while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) rows.push(lines[i++]);
                i--;
                const cells = (r) => r.trim().replace(/^\||\|$/g, '').split('|').map((c) => c.trim());
                const body = rows.filter((r) => !/^\s*\|[\s:|-]+\|\s*$/.test(r));
                const head = cells(body.shift() || '');
                const num = (c) => /^-?[\d,]+(\.\d+)?%?$/.test(c);
                out.push('<div class="table-responsive"><table class="table table-sm table-bordered w-auto mb-2"><thead><tr>'
                    + head.map((c) => `<th>${inline(c)}</th>`).join('') + '</tr></thead><tbody>'
                    + body.map((r) => '<tr>' + cells(r).map((c) => `<td${num(c) ? ' class="num"' : ''}>${inline(c)}</td>`).join('') + '</tr>').join('')
                    + '</tbody></table></div>');
            } else if (/^\s*[-*] /.test(line)) {
                const items = [];
                while (i < lines.length && /^\s*[-*] /.test(lines[i])) items.push(lines[i++].replace(/^\s*[-*] /, ''));
                i--;
                out.push('<ul class="mb-2">' + items.map((it) => `<li>${inline(it)}</li>`).join('') + '</ul>');
            } else if (/^#{1,4} /.test(line)) {
                out.push(`<div class="fw-semibold mb-1">${inline(line.replace(/^#+ /, ''))}</div>`);
            } else if (line.trim() === '') {
                out.push('<div class="mb-2"></div>');
            } else {
                out.push(`<div>${inline(line)}</div>`);
            }
        }
        return out.join('');
    }

    function bubble(role, content, sources) {
        const el = document.createElement('div');
        el.className = role === 'user' ? 'ai-q' : 'ai-a';
        el.innerHTML = role === 'user' ? Phai.esc(content).replace(/\n/g, '<br>') : md(content);
        if (sources && sources.length) {
            el.insertAdjacentHTML('beforeend', `<div class="ai-src">${Phai.esc(cfg.text.used)} ${sources.map((s) => `<code>${Phai.esc(s)}</code>`).join(' ')}</div>`);
        }
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
        return el;
    }

    function render() {
        log.innerHTML = '';
        if (!history.length) {
            log.innerHTML = `<div class="text-center text-secondary py-5"><i class="ti ti-sparkles fs-2 d-block mb-1"></i>${Phai.esc(cfg.text.empty)}</div>`;
            return;
        }
        history.forEach((m) => bubble(m.role, m.content, m.sources));
    }

    function save() {
        try { sessionStorage.setItem(KEY, JSON.stringify(history.slice(-40))); } catch (e) { /* private mode */ }
    }

    async function send() {
        const question = box.value.trim();
        if (!question || form.dataset.busy) return;
        if (!history.length) log.innerHTML = '';
        const asked = history.map(({ role, content }) => ({ role, content }));
        history.push({ role: 'user', content: question });
        bubble('user', question);
        box.value = '';
        box.style.height = '';
        const wait = bubble('assistant', '');
        wait.innerHTML = '<span class="spinner-border spinner-border-sm text-secondary"></span>';
        form.dataset.busy = '1';
        Phai.busy(form.querySelector('[type=submit]'), true);
        try {
            const res = await Phai.api.post('/api/ai/chat', { question, history: asked });
            wait.remove();
            history.push({ role: 'assistant', content: res.answer, sources: res.sources });
            bubble('assistant', res.answer, res.sources);
        } catch (err) {
            wait.remove();
            history.pop();
            box.value = question; // let the user try again
        } finally {
            delete form.dataset.busy;
            Phai.busy(form.querySelector('[type=submit]'), false);
            save();
            box.focus();
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        send();
    });
    // Enter sends, Shift+Enter is a new line; the box grows with the text
    box.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            send();
        }
    });
    box.addEventListener('input', () => {
        box.style.height = '';
        box.style.height = Math.min(box.scrollHeight, 160) + 'px';
    });
    document.getElementById('ai-new').addEventListener('click', () => {
        history = [];
        save();
        render();
        box.focus();
    });

    render();
    box.focus();
})();
