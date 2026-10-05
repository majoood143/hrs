/**
 * Form result charts (admin "Insights" page and the public "Form results chart" CMS block).
 *
 * Each chart is a `[data-form-chart]` element holding a <canvas> and a JSON spec built by
 * App\Services\Forms\FormChart. Charts are drawn when they appear in the page (also after a Livewire
 * update), destroyed when they leave it, and recoloured when the admin switches light/dark.
 * The world map (chartjs-chart-geo + the country shapes) is a separate chunk, loaded only when a page
 * has a map. Every chart also has a table twin in the markup, so nothing depends on this script.
 */
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, DoughnutController, ArcElement, Tooltip, Legend);

// The validated reference palette (categorical slots 1-6, chart ink), each stepped for light and dark.
const PALETTE = {
    light: {
        series: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300'],
        text: '#0b0b0b',
        secondary: '#52514e',
        muted: '#898781',
        grid: '#e1e0d9',
        surface: '#ffffff',
        empty: '#e6e5df',
        // sequential blue, low -> high (the map)
        ramp: ['#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#184f95', '#0d366b'],
    },
    dark: {
        series: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300'],
        text: '#ffffff',
        secondary: '#c3c2b7',
        muted: '#898781',
        grid: '#2c2c2a',
        surface: '#18181b',
        empty: '#383835',
        ramp: ['#184f95', '#1c5cab', '#256abf', '#2a78d6', '#3987e5', '#5598e7', '#86b6ef'],
    },
};

export const isDark = () => document.documentElement.classList.contains('dark');
export const palette = () => (isDark() ? PALETTE.dark : PALETTE.light);

const charts = new Set();

/** A long answer split into lines of ~26 characters, three at most, so the bar labels stay readable. */
function wrap(text, max = 26) {
    const words = String(text).split(/\s+/);
    const lines = [];
    let line = '';

    for (const word of words) {
        if (line && (line + ' ' + word).length > max) {
            lines.push(line);
            line = word;
        } else {
            line = line ? line + ' ' + word : word;
        }
    }

    if (line) {
        lines.push(line);
    }

    return lines.length > 3 ? [...lines.slice(0, 2), lines.slice(2).join(' ').slice(0, max - 1) + '…'] : lines;
}

export function formatters(spec) {
    const number = new Intl.NumberFormat(spec.locale === 'ar' ? 'ar-u-nu-latn' : 'en', { maximumFractionDigits: 1 });
    const percent = (v) => `${number.format(v)}%`;
    const value = (i) => (spec.format === 'percent' ? spec.shares[i] : spec.counts[i]);

    return {
        number,
        percent,
        value,
        // what a bar end / tooltip says: "12 · 40%" or "40%"
        text: (i) => (spec.format === 'percent' ? percent(spec.shares[i]) : `${number.format(spec.counts[i])} · ${percent(spec.shares[i])}`),
        tick: (v) => (spec.format === 'percent' ? percent(v) : number.format(v)),
    };
}

/** Numbers at the end of each bar (only when the chart asks for them: few bars). */
const valueLabels = {
    id: 'formChartValues',
    afterDatasetsDraw(chart, _args, options) {
        if (!options?.enabled) {
            return;
        }

        const { ctx } = chart;
        const horizontal = chart.options.indexAxis === 'y';

        ctx.save();
        ctx.fillStyle = palette().secondary;
        ctx.font = `500 12px ${Chart.defaults.font.family}`;

        chart.getDatasetMeta(0).data.forEach((bar, i) => {
            const text = options.text(i);

            if (horizontal) {
                ctx.textAlign = options.rtl ? 'right' : 'left';
                ctx.textBaseline = 'middle';
                ctx.fillText(text, bar.x + (options.rtl ? -6 : 6), bar.y);
            } else {
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                ctx.fillText(text, bar.x, bar.y - 4);
            }
        });

        ctx.restore();
    },
};

/** The number answered in the middle of a donut. */
const donutCentre = {
    id: 'formChartCentre',
    afterDatasetsDraw(chart, _args, options) {
        if (!options?.text) {
            return;
        }

        const meta = chart.getDatasetMeta(0);
        const arc = meta.data[0];

        if (!arc) {
            return;
        }

        const { ctx } = chart;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = palette().text;
        ctx.font = `600 22px ${Chart.defaults.font.family}`;
        ctx.fillText(options.text, arc.x, arc.y - 8);
        ctx.fillStyle = palette().muted;
        ctx.font = `400 12px ${Chart.defaults.font.family}`;
        ctx.fillText(options.caption, arc.x, arc.y + 14);
        ctx.restore();
    },
};

function tooltip(spec, f) {
    return {
        rtl: spec.dir === 'rtl',
        textDirection: spec.dir,
        displayColors: spec.type === 'donut',
        callbacks: {
            title: (items) => spec.labels[items[0].dataIndex],
            label: (item) => ` ${f.text(item.dataIndex)}`,
        },
    };
}

function barConfig(spec, f) {
    const rtl = spec.dir === 'rtl';
    const horizontal = spec.type === 'bar';
    const valueAxis = {
        beginAtZero: true,
        border: { display: false },
        grid: { color: () => palette().grid },
        ticks: { color: () => palette().muted, precision: 0, callback: (v) => f.tick(v) },
        ...(spec.format === 'percent' && horizontal ? { suggestedMax: 100 } : {}),
    };
    const categoryAxis = {
        grid: { display: false },
        border: { color: () => palette().grid },
        ticks: { color: () => palette().secondary, autoSkip: !horizontal, maxRotation: 0 },
    };

    return {
        type: 'bar',
        data: {
            labels: horizontal ? spec.labels.map((label) => wrap(label)) : spec.labels,
            datasets: [
                {
                    label: spec.series,
                    data: spec.labels.map((_, i) => f.value(i)),
                    backgroundColor: () => palette().series[0],
                    hoverBackgroundColor: () => palette().series[0],
                    borderRadius: 4,
                    borderSkipped: 'start',
                    maxBarThickness: horizontal ? 22 : 28,
                },
            ],
        },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false, axis: horizontal ? 'y' : 'x' },
            layout: { padding: horizontal ? { [rtl ? 'left' : 'right']: spec.format === 'percent' ? 48 : 76 } : { top: 20 } },
            scales: horizontal
                ? { x: { ...valueAxis, reverse: rtl }, y: { ...categoryAxis, position: rtl ? 'right' : 'left' } }
                : { x: { ...categoryAxis, reverse: rtl }, y: { ...valueAxis, position: rtl ? 'right' : 'left' } },
            plugins: {
                legend: { display: false },
                tooltip: tooltip(spec, f),
                formChartValues: { enabled: spec.valueLabels, rtl, text: (i) => (horizontal ? f.text(i) : f.tick(f.value(i))) },
            },
        },
        plugins: [valueLabels],
    };
}

function donutConfig(spec, f) {
    const rtl = spec.dir === 'rtl';

    return {
        type: 'doughnut',
        data: {
            labels: spec.labels,
            datasets: [
                {
                    label: spec.series,
                    data: spec.labels.map((_, i) => f.value(i)),
                    backgroundColor: (ctx) => palette().series[spec.slots[ctx.dataIndex] % palette().series.length],
                    borderColor: () => palette().surface,
                    borderWidth: 2,
                    hoverOffset: 4,
                },
            ],
        },
        options: {
            maintainAspectRatio: false,
            cutout: '64%',
            plugins: {
                legend: {
                    position: 'bottom',
                    rtl,
                    textDirection: spec.dir,
                    labels: {
                        color: () => palette().secondary,
                        usePointStyle: true,
                        pointStyle: 'rectRounded',
                        boxWidth: 10,
                        padding: 14,
                        // identity is never colour alone: the legend names each answer with its numbers
                        generateLabels: (chart) =>
                            Chart.overrides.doughnut.plugins.legend.labels.generateLabels(chart).map((item) => ({
                                ...item,
                                text: `${spec.labels[item.index]} (${f.text(item.index)})`,
                                fontColor: palette().secondary,
                            })),
                    },
                },
                tooltip: tooltip(spec, f),
                formChartCentre: { text: f.number.format(spec.answered), caption: spec.series },
            },
        },
        plugins: [donutCentre],
    };
}

async function render(el) {
    el.dataset.ready = '1';

    const source = el.querySelector('script[type="application/json"]');
    const canvas = el.querySelector('canvas');

    if (!source || !canvas) {
        return;
    }

    const spec = JSON.parse(source.textContent);
    const f = formatters(spec);
    let config;

    if (spec.type === 'map') {
        const { mapConfig } = await import('./form-charts-map.js');
        config = await mapConfig(spec, f, { tooltip: tooltip(spec, f) });
    } else {
        config = spec.type === 'donut' ? donutConfig(spec, f) : barConfig(spec, f);
    }

    if (!canvas.isConnected) {
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        config.options.animation = false;
    }

    charts.add(new Chart(canvas, config));
    el.classList.add('is-drawn');
}

function scan() {
    document.querySelectorAll('[data-form-chart]:not([data-ready])').forEach((el) => {
        render(el).catch((error) => console.error('form chart', error));
    });

    for (const chart of charts) {
        if (!chart.canvas?.isConnected) {
            chart.destroy();
            charts.delete(chart);
        }
    }
}

function boot() {
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    scan();

    let queued = false;
    new MutationObserver(() => {
        if (!queued) {
            queued = true;
            requestAnimationFrame(() => {
                queued = false;
                scan();
            });
        }
    }).observe(document.body, { childList: true, subtree: true });

    // the admin's light/dark switch: the scriptable colours re-read the palette
    new MutationObserver(() =>
        charts.forEach((chart) => {
            if (chart.options.scales?.color) {
                chart.options.scales.color.missing = palette().empty;
            }
            chart.update('none');
        }),
    ).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
