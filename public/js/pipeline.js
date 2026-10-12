(() => {
    const root = document.getElementById('pipeline-content');
    if (!root) return;
    let observer;
    let busy = false;

    const setupScroll = () => {
        observer?.disconnect();
        const board = root.querySelector('#pipeline-board');
        const top = root.querySelector('[data-pipeline-scroll-top]');
        const spacer = top?.querySelector('[data-pipeline-scroll-spacer]');
        if (!board || !top || !spacer) return;
        const sync = (source, target) => {
            if (Math.abs(target.scrollLeft - source.scrollLeft) > 1) target.scrollLeft = source.scrollLeft;
        };
        const resize = () => {
            spacer.style.width = `${board.scrollWidth}px`;
            top.hidden = board.scrollWidth <= board.clientWidth + 1;
            top.scrollLeft = board.scrollLeft;
        };
        top.addEventListener('scroll', () => sync(top, board), { passive: true });
        board.addEventListener('scroll', () => sync(board, top), { passive: true });
        if ('ResizeObserver' in window) {
            observer = new ResizeObserver(resize);
            observer.observe(board);
            board.querySelectorAll('.pipeline-column').forEach(column => observer.observe(column));
        }
        board.classList.add('pipeline-scroll-enhanced');
        resize();
    };

    const feedback = (message, error = false, form = null) => {
        const node = form ? document.createElement('div') : root.querySelector('[data-pipeline-feedback]');
        if (!node) return;
        node.hidden = false;
        node.className = `flash ${error ? 'flash-error' : 'flash-success'}`;
        node.setAttribute('role', error ? 'alert' : 'status');
        node.textContent = message;
        if (form) {
            node.dataset.pipelineError = '';
            form.querySelector('[data-pipeline-error]')?.remove();
            form.append(node);
        }
    };

    const replaceContent = (html, submittedForm) => {
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const next = parsed.querySelector('#pipeline-content');
        if (!next) throw new Error('Risposta non valida. Nessun dato della pagina è stato sostituito.');
        const scroll = root.querySelector('#pipeline-board')?.scrollLeft ?? 0;
        const y = window.scrollY;
        // Preserve unsaved edits in any other expanded card.
        const drafts = [...root.querySelectorAll('details[open] [data-pipeline-update]')]
            .filter(form => form !== submittedForm)
            .map(form => ({ id: form.closest('[data-opportunity-id]').dataset.opportunityId, fields: [...new FormData(form)] }));
        root.innerHTML = next.innerHTML;
        for (const draft of drafts) {
            const card = root.querySelector(`[data-opportunity-id="${draft.id}"]`);
            if (!card) continue;
            card.querySelector('details').open = true;
            const form = card.querySelector('form');
            for (const [name, value] of draft.fields) {
                if (name === '_token') continue;
                const input = form.elements.namedItem(name);
                if (input) input.value = value;
            }
        }
        setupScroll();
        const board = root.querySelector('#pipeline-board');
        const top = root.querySelector('[data-pipeline-scroll-top]');
        if (board) board.scrollLeft = scroll;
        if (top) top.scrollLeft = scroll;
        window.scrollTo({ top: y, behavior: 'instant' });
    };

    const request = async (url, options = {}, form = null, navigate = false) => {
        if (busy) return;
        busy = true;
        root.setAttribute('aria-busy', 'true');
        const buttons = [...root.querySelectorAll('button')];
        buttons.forEach(button => button.disabled = true);
        form?.querySelector('[data-pipeline-error]')?.remove();
        form?.querySelectorAll('[aria-invalid]').forEach(input => input.removeAttribute('aria-invalid'));
        try {
            const response = await fetch(url, {
                credentials: 'same-origin', ...options,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.redirected || response.status === 401 || response.status === 419) {
                throw new Error('Sessione scaduta. Accedi nuovamente prima di salvare.');
            }
            const data = await response.json();
            if (!response.ok) {
                if (data.errors && form) {
                    const fields = Object.keys(data.errors);
                    fields.forEach(name => form.elements.namedItem(name)?.setAttribute('aria-invalid', 'true'));
                    feedback(Object.values(data.errors).flat().join(' '), true, form);
                    form.elements.namedItem(fields[0])?.focus();
                    return;
                }
                throw new Error(data.message || 'Aggiornamento non riuscito. Riprova.');
            }
            replaceContent(data.html, form);
            if (navigate) history.pushState({}, '', url);
            if (data.message) feedback(data.message);
        } catch (error) {
            feedback(error.message || 'Connessione interrotta. Verifica i dati prima di riprovare.', true, form);
        } finally {
            busy = false;
            root.removeAttribute('aria-busy');
            buttons.forEach(button => button.disabled = false);
        }
    };

    root.addEventListener('submit', event => {
        const form = event.target;
        if (form.matches('[data-pipeline-update]')) {
            event.preventDefault();
            const url = new URL(form.action);
            url.search = location.search;
            request(url.href, { method: 'POST', body: new FormData(form) }, form);
        } else if (form.matches('[data-pipeline-filters]')) {
            event.preventDefault();
            const url = new URL(form.action);
            url.search = new URLSearchParams(new FormData(form)).toString();
            request(url.href, {}, null, true);
        }
    });
    root.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        const url = new URL(link.href);
        if (url.origin === location.origin && url.pathname === location.pathname) {
            event.preventDefault();
            request(url.href, {}, null, true);
        }
    });
    window.addEventListener('popstate', () => request(location.href));
    setupScroll();
})();
