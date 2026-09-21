/**
 * Races widget: picking a date or a month swaps the widget in place instead of reloading the page.
 *
 * Progressive enhancement: every control is a real link (`?race_month=…&race_date=…`), so without this
 * script, or if a request fails, the browser simply follows the link. The fragment comes from
 * /racing/widget (the widget alone, ~10 KB), and is cached for a minute so flipping back and forth is instant.
 */
export function raceWidget() {
    const root = document.querySelector('[data-race-widget]');
    if (!root || !('fetch' in window) || !('DOMParser' in window)) return;

    const endpoint = root.dataset.endpoint;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const sideBySide = window.matchMedia('(min-width: 1024px)');

    const TTL = 60_000;
    const MAX_CACHED = 24;
    const cache = new Map();
    let latest = 0;

    const fragmentUrl = (link) => {
        const url = new URL(link.href, window.location.href);
        const params = new URLSearchParams(url.search);
        params.set('from', url.pathname);

        return `${endpoint}?${params}`;
    };

    const load = (url) => {
        const hit = cache.get(url);
        if (hit && Date.now() - hit.at < TTL) return hit.html;

        const html = fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then((response) => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            return response.text();
        });

        cache.set(url, { at: Date.now(), html });
        html.catch(() => cache.delete(url));
        if (cache.size > MAX_CACHED) cache.delete(cache.keys().next().value);

        return html;
    };

    // The swapped-out link is gone, so put keyboard / screen-reader focus somewhere sensible.
    const settle = (kind) => {
        const panel = root.querySelector('[data-rw-panel]');
        let target;

        if (kind === 'day') {
            target = panel?.querySelector('[data-rw-focus]');

            // on a phone the day sits below the calendar, so bring it into view
            if (panel && !sideBySide.matches) {
                panel.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
            }
        } else {
            target = root.querySelector(`[data-rw="${kind}"]`) ?? root.querySelector('[data-rw-month]');
        }

        target?.focus({ preventScroll: true });
    };

    root.addEventListener('click', async (event) => {
        const link = event.target.closest('a[data-rw]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        event.preventDefault();

        const ticket = ++latest;
        root.setAttribute('aria-busy', 'true');

        try {
            const doc = new DOMParser().parseFromString(await load(fragmentUrl(link)), 'text/html');
            const next = doc.querySelector('[data-race-widget]');

            if (!next) throw new Error('No widget in the response');
            if (ticket !== latest) return; // a newer tap won

            root.innerHTML = next.innerHTML;
            window.history.replaceState(window.history.state, '', link.href);
            settle(link.dataset.rw);
        } catch {
            if (ticket === latest) window.location.assign(link.href);
        } finally {
            if (ticket === latest) root.removeAttribute('aria-busy');
        }
    });
}
