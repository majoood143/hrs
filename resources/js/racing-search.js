/**
 * Racing search: the source is slow, so while a search (or another page of results) loads we show
 * a busy button, an animated progress bar, rotating status lines and a skeleton of the results table.
 * The request itself stays a normal page load; without JS the form works exactly the same.
 */
const STEP_MS = 2500;

export function racingSearch() {
    const forms = document.querySelectorAll('form[data-racing-search]');
    if (!forms.length) return;

    const active = [];

    const start = (form, scroll = false) => {
        const loader = form.parentElement.querySelector(':scope > [data-racing-loading]');
        const button = form.querySelector('[data-racing-search-button]');
        const label = button?.querySelector('[data-racing-search-label]');
        const results = document.querySelector('[data-racing-results]');

        if (button) {
            button.setAttribute('aria-busy', 'true');
            button.querySelector('[data-racing-search-idle]')?.classList.add('hidden');
            button.querySelector('[data-racing-search-busy]')?.classList.remove('hidden');
            if (label) {
                label.dataset.idleLabel ??= label.textContent;
                label.textContent = label.dataset.busyLabel;
            }
        }

        if (!loader) return;

        results?.setAttribute('hidden', '');
        loader.removeAttribute('hidden');

        if (scroll) {
            const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            loader.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'center' });
        }

        let steps = [];
        try {
            steps = JSON.parse(loader.dataset.steps || '[]');
        } catch {
            // keep the first line only
        }

        const stepEl = loader.querySelector('[data-racing-loading-step]');
        let index = 0;
        if (stepEl && steps.length) stepEl.textContent = steps[0];

        const timer = window.setInterval(() => {
            if (index >= steps.length - 1) return window.clearInterval(timer);
            stepEl.textContent = steps[++index];
        }, STEP_MS);

        active.push({ form, loader, results, timer });
    };

    // Coming back with the Back button restores the page from cache in its loading state: undo it.
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;

        active.splice(0).forEach(({ form, loader, results, timer }) => {
            window.clearInterval(timer);
            loader?.setAttribute('hidden', '');
            results?.removeAttribute('hidden');

            const button = form.querySelector('[data-racing-search-button]');
            const label = button?.querySelector('[data-racing-search-label]');
            button?.removeAttribute('aria-busy');
            button?.querySelector('[data-racing-search-idle]')?.classList.remove('hidden');
            button?.querySelector('[data-racing-search-busy]')?.classList.add('hidden');
            if (label?.dataset.idleLabel) label.textContent = label.dataset.idleLabel;
        });
    });

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;

            const button = form.querySelector('[data-racing-search-button]');
            if (button?.getAttribute('aria-busy') === 'true') {
                event.preventDefault(); // a double click must not fire a second slow search
                return;
            }

            start(form);
        });
    });

    // "View all" and pagination links reload the same search page: show the same loading state.
    const results = document.querySelector('[data-racing-results]');
    const searchPath = new URL(forms[0].action, window.location.href).pathname;

    results?.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target && link.target !== '_self') return;

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || url.pathname !== searchPath) return;

        start(forms[0], true);
    });
}
