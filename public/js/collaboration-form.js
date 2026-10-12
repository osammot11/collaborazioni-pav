(() => {
    let busy = false;
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('[data-collaboration-form]')) return;
        event.preventDefault();
        if (busy) return;
        busy = true;
        const body = new FormData(form);
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        form.setAttribute('aria-busy', 'true');
        form.querySelector('[data-save-feedback]')?.remove();
        const show = (message, error = false) => {
            const node = document.createElement('div');
            node.dataset.saveFeedback = '';
            node.className = `flash ${error ? 'flash-error' : 'flash-success'}`;
            node.setAttribute('role', error ? 'alert' : 'status');
            node.textContent = message;
            (document.querySelector('[data-collaboration-form]') || form).append(node);
        };
        try {
            const response = await fetch(form.action, { method: 'POST', body, credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (response.redirected || response.status === 401 || response.status === 419) {
                throw new Error('Sessione scaduta. Accedi nuovamente prima di salvare.');
            }
            const data = await response.json();
            if (!response.ok) {
                show(data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Salvataggio non riuscito.'), true);
                if (data.errors) form.elements.namedItem(Object.keys(data.errors)[0])?.focus();
                return;
            }
            const parsed = new DOMParser().parseFromString(data.html, 'text/html');
            const main = parsed.querySelector('main');
            if (!main) throw new Error('Risposta non valida. Verifica i dati prima di riprovare.');
            const y = window.scrollY;
            document.querySelector('main').innerHTML = main.innerHTML;
            document.title = parsed.title;
            history.replaceState({}, '', data.url);
            window.scrollTo({ top: y, behavior: 'instant' });
            show(data.message);
        } catch (error) {
            show(error.message || 'Connessione interrotta. Verifica i dati prima di riprovare.', true);
        } finally {
            busy = false;
            button.disabled = false;
            form.removeAttribute('aria-busy');
        }
    });
})();
