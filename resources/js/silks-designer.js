/**
 * Racing silks designer (cms/blocks/silks_designer.blade.php): repaints the drawing in place as
 * the visitor picks patterns and colours, instead of submitting the form.
 *
 * Progressive enhancement: the controls are a GET form whose query string is the design, so
 * without this script "Update preview" redraws it on the server and "Download SVG" is a plain
 * link. Here the URL is kept in step (it is the share link), and the PNG and PDF downloads,
 * copy link, surprise and reset buttons are switched on. The PNG is drawn from the same SVG the
 * server serves for downloads, so the files always match; the PDF is laid out by the server
 * around that same drawing.
 */
const AREAS = ['body', 'sleeves', 'cap'];
const PLAIN = 'plain';
const PNG_WIDTH = 1200;

export function silksDesigner() {
    document.querySelectorAll('[data-silks-designer]').forEach(setup);
}

function setup(form) {
    const catalog = JSON.parse(form.querySelector('[data-silks-catalog]').textContent);
    const colors = Object.fromEntries(catalog.colors.map((color) => [color.key, color]));
    const patterns = Object.fromEntries(AREAS.map((area) => [
        area,
        Object.fromEntries((catalog.patterns[area] || []).map((pattern) => [pattern.key, pattern])),
    ]));
    const figure = form.querySelector('[data-silks-figure]');
    const svgLink = form.querySelector('[data-silks-svg]');
    const imageUrl = form.dataset.imageUrl;

    form.querySelectorAll('[data-silks-js]').forEach((el) => { el.hidden = false; });
    form.querySelectorAll('[data-silks-nojs]').forEach((el) => el.remove());

    const read = () => {
        const data = new FormData(form);

        return Object.fromEntries(AREAS.map((area) => [area, {
            pattern: data.get(area),
            base: data.get(`${area}1`),
            accent: data.get(`${area}2`),
        }]));
    };

    const designQuery = (design) => {
        const params = new URLSearchParams();
        AREAS.forEach((area) => {
            params.set(area, design[area].pattern);
            params.set(`${area}1`, design[area].base);
            params.set(`${area}2`, design[area].accent);
        });

        return params;
    };

    // the designer page without its query: the server adds the design and language to make the
    // link (and QR code) printed on the downloads
    const pageUrl = () => `${window.location.origin}${window.location.pathname}`;

    const patternName = (area, key) => (key === PLAIN ? catalog.plain : patterns[area][key]?.name ?? '');

    // mirrors SilksDesign::describe()
    const describe = (design) => {
        const lower = (text) => text.toLocaleLowerCase(document.documentElement.lang || undefined);
        const text = AREAS.map((area) => {
            const { pattern, base, accent } = design[area];
            const template = catalog.describe[pattern === PLAIN ? area : `${area}_pattern`];

            return template
                .replace(':pattern', pattern === PLAIN ? '' : lower(patternName(area, pattern)))
                .replace(':colour', lower(colors[accent]?.name ?? ''))
                .replace(':base', lower(colors[base]?.name ?? ''));
        }).join(catalog.describe.separator);

        return text.charAt(0).toLocaleUpperCase() + text.slice(1);
    };

    const setPattern = (group, svg) => {
        const doc = new DOMParser().parseFromString(`<svg xmlns="http://www.w3.org/2000/svg">${svg}</svg>`, 'image/svg+xml');
        const nodes = doc.querySelector('parsererror') ? [] : [...doc.documentElement.childNodes];
        group.replaceChildren(...nodes.map((node) => document.importNode(node, true)));
    };

    const paint = () => {
        const design = read();

        AREAS.forEach((area) => {
            const { pattern, base, accent } = design[area];
            const baseHex = colors[base]?.hex;
            const accentHex = colors[accent]?.hex;

            figure.querySelectorAll(`[data-silks-base="${area}"]`).forEach((el) => el.setAttribute('fill', baseHex));
            figure.querySelectorAll(`[data-silks-pattern="${area}"]`).forEach((group) => {
                group.setAttribute('color', accentHex);
                if (group.dataset.silksKey !== pattern) {
                    setPattern(group, patterns[area][pattern]?.svg ?? '');
                    group.dataset.silksKey = pattern;
                }
            });

            form.style.setProperty(`--silks-${area}-base`, baseHex);
            form.style.setProperty(`--silks-${area}-accent`, accentHex);

            const accentRow = form.querySelector(`[data-silks-accent-row="${area}"]`);
            if (accentRow) accentRow.hidden = pattern === PLAIN;

            form.querySelectorAll(`[data-silks-name="${area}1"]`).forEach((el) => { el.textContent = colors[base]?.name ?? ''; });
            form.querySelectorAll(`[data-silks-name="${area}2"]`).forEach((el) => { el.textContent = colors[accent]?.name ?? ''; });
        });

        const description = describe(design);
        const label = form.dataset.label.replace(':description', description);
        form.querySelector('[data-silks-description]').textContent = description;
        figure.setAttribute('aria-label', label);
        const title = figure.querySelector('title');
        if (title) title.textContent = label;

        // the page URL keeps its other parameters (lang, …) and becomes the share link
        const query = designQuery(design);
        const pageParams = new URLSearchParams(window.location.search);
        query.forEach((value, key) => pageParams.set(key, value));
        pageParams.delete('silks'); // the short form a QR code opened with; the full one replaces it
        window.history.replaceState(null, '', `${window.location.pathname}?${pageParams}${window.location.hash}`);

        query.set('page', pageUrl());
        query.set('download', '1');
        svgLink.href = `${imageUrl}?${query}`;
    };

    const choose = (design) => {
        AREAS.forEach((area) => {
            [[area, design[area].pattern], [`${area}1`, design[area].base], [`${area}2`, design[area].accent]].forEach(([name, value]) => {
                const input = form.querySelector(`input[name="${name}"][value="${CSS.escape(value)}"]`);
                if (input) input.checked = true;
            });
        });
        paint();
    };

    const pickOne = (list) => list[Math.floor(Math.random() * list.length)];

    const surprise = () => {
        const keys = Object.keys(colors);
        choose(Object.fromEntries(AREAS.map((area) => {
            const options = Object.keys(patterns[area]);
            // plain comes up now and then, as it does on real silks
            const pattern = Math.random() < 0.2 || !options.length ? PLAIN : pickOne(options);
            const base = pickOne(keys);

            return [area, { pattern, base, accent: pickOne(keys.filter((key) => key !== base)) }];
        })));
    };

    const reset = () => {
        const params = JSON.parse(form.dataset.default);
        choose(Object.fromEntries(AREAS.map((area) => [area, {
            pattern: params[area],
            base: params[`${area}1`],
            accent: params[`${area}2`],
        }])));
    };

    // the server's SVG drawn onto a canvas: the PNG download, and the picture inside the PDF
    // (mPDF cannot clip SVG shapes, so the PDF gets the browser's rendering)
    const renderPng = async (design, bare) => {
        const query = designQuery(design);
        query.set(bare ? 'bare' : 'page', bare ? '1' : pageUrl());
        const response = await fetch(`${imageUrl}?${query}`, { credentials: 'same-origin' });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const url = URL.createObjectURL(new Blob([await response.text()], { type: 'image/svg+xml' }));
        try {
            const image = new Image();
            await new Promise((resolve, reject) => {
                image.onload = resolve;
                image.onerror = reject;
                image.src = url;
            });

            const canvas = document.createElement('canvas');
            canvas.width = PNG_WIDTH;
            canvas.height = Math.round(PNG_WIDTH * (image.naturalHeight / image.naturalWidth));
            const context = canvas.getContext('2d');
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0, canvas.width, canvas.height);

            return canvas;
        } finally {
            URL.revokeObjectURL(url);
        }
    };

    const busy = async (button, work) => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        try {
            await work();
        } catch (error) {
            window.alert(form.dataset.failed);
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    };

    const downloadPng = (button) => busy(button, async () => {
        const canvas = await renderPng(read(), false);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        if (!blob) throw new Error('empty canvas');
        save(blob, 'racing-silks.png');
    });

    const downloadPdf = (button) => busy(button, async () => {
        const design = read();
        const canvas = await renderPng(design, true);

        const body = new FormData();
        designQuery(design).forEach((value, key) => body.set(key, value));
        body.set('image', canvas.toDataURL('image/png'));
        body.set('page', pageUrl());

        const response = await fetch(form.dataset.pdfUrl, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': form.dataset.csrf, Accept: 'application/pdf' },
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        save(await response.blob(), 'racing-silks.pdf');
    });

    const copyLink = async (button) => {
        const label = button.querySelector('[data-silks-copy-label]');
        const original = label.textContent;
        try {
            await navigator.clipboard.writeText(window.location.href);
            label.textContent = form.dataset.copied;
            setTimeout(() => { label.textContent = original; }, 2000);
        } catch (error) {
            window.prompt(original, window.location.href);
        }
    };

    // tabs: one area at a time (without the script all three are listed one under the other)
    const tabs = [...form.querySelectorAll('[data-silks-tab]')];
    const showArea = (area, focus = false) => {
        tabs.forEach((tab) => {
            const selected = tab.dataset.silksTab === area;
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            tab.tabIndex = selected ? 0 : -1;
            if (selected && focus) tab.focus();
        });
        form.querySelectorAll('[data-silks-panel]').forEach((panel) => { panel.hidden = panel.dataset.silksPanel !== area; });
    };
    if (tabs.length) {
        form.querySelectorAll('[data-silks-panel-title]').forEach((el) => el.classList.add('sr-only'));
        showArea(tabs[0].dataset.silksTab);
        tabs.forEach((tab, i) => {
            tab.addEventListener('click', () => showArea(tab.dataset.silksTab));
            tab.addEventListener('keydown', (event) => {
                const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
                if (!step) return;
                event.preventDefault();
                // arrows follow the reading direction
                const rtl = getComputedStyle(form).direction === 'rtl';
                const next = tabs[(i + (rtl ? -step : step) + tabs.length) % tabs.length];
                showArea(next.dataset.silksTab, true);
            });
        });
    }

    form.addEventListener('change', paint);
    form.addEventListener('submit', (event) => event.preventDefault());
    form.querySelector('[data-silks-random]')?.addEventListener('click', surprise);
    form.querySelector('[data-silks-reset]')?.addEventListener('click', reset);
    form.querySelector('[data-silks-png]')?.addEventListener('click', (event) => downloadPng(event.currentTarget));
    form.querySelector('[data-silks-pdf]')?.addEventListener('click', (event) => downloadPdf(event.currentTarget));
    form.querySelector('[data-silks-copy]')?.addEventListener('click', (event) => copyLink(event.currentTarget));
}

function save(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
