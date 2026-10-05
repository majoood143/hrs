/**
 * The nationality map of the form charts: a choropleth (one blue ramp, darker = more answers, no answers
 * = neutral gray), zoomed to the countries that answered. Loaded on demand by form-charts.js, so the
 * country shapes (~750 kB, the 1:50m set, which still has Bahrain and the other small Gulf states) are
 * only downloaded by a page that shows a map.
 */
import { Chart } from 'chart.js';
import { ChoroplethController, ColorScale, GeoFeature, ProjectionScale, topojson } from 'chartjs-chart-geo';
import { geoBounds } from 'd3-geo';
import codes from 'i18n-iso-countries/codes.json';
import { palette } from './form-charts.js';

Chart.register(ChoroplethController, GeoFeature, ColorScale, ProjectionScale);

// ISO alpha-2 (what our countries store) -> ISO numeric (what the map's shapes are keyed by)
const NUMERIC = new Map(codes.map(([alpha2, , numeric]) => [alpha2, numeric]));
const ANTARCTICA = '010';

let shapes;

async function countries() {
    if (!shapes) {
        const world = (await import('world-atlas/countries-50m.json')).default;
        shapes = topojson.feature(world, world.objects.countries).features.filter((f) => f.id !== ANTARCTICA);
    }

    return shapes;
}

/** A box around the answered countries, at least 40° by 24°, so one small country is not a full-screen blob. */
function frame(features) {
    if (features.length === 0) {
        return { type: 'Sphere' };
    }

    let [[west, south], [east, north]] = geoBounds({ type: 'FeatureCollection', features });

    if (east < west) {
        // crosses the antimeridian: show the world
        return { type: 'Sphere' };
    }

    const widen = (low, high, min) => {
        const extra = Math.max(0, min - (high - low)) / 2;
        return [low - extra, high + extra];
    };
    [west, east] = widen(west, east, 40);
    [south, north] = widen(south, north, 24);
    south = Math.max(south, -60);
    north = Math.min(north, 80);

    return { type: 'MultiPoint', coordinates: [[west, south], [east, north], [west, north], [east, south]] };
}

export async function mapConfig(spec, f, { tooltip }) {
    const features = await countries();
    const rows = new Map();

    spec.codes.forEach((code, i) => {
        const numeric = code ? NUMERIC.get(code.toUpperCase()) : null;

        if (numeric) {
            rows.set(numeric, i);
        }
    });

    const answered = features.filter((feature) => rows.has(feature.id));

    return {
        type: 'choropleth',
        data: {
            labels: features.map((feature) => (rows.has(feature.id) ? spec.labels[rows.get(feature.id)] : feature.properties.name)),
            datasets: [
                {
                    label: spec.series,
                    outline: frame(answered),
                    showOutline: false,
                    showGraticule: false,
                    clipMap: false,
                    borderColor: () => palette().surface,
                    borderWidth: 0.6,
                    hoverBorderColor: () => palette().text,
                    hoverBorderWidth: 1.2,
                    data: features.map((feature) => ({
                        feature,
                        // no answers is "missing" (gray), never the lightest blue
                        value: rows.has(feature.id) ? f.value(rows.get(feature.id)) : null,
                        row: rows.has(feature.id) ? rows.get(feature.id) : null,
                    })),
                },
            ],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltip,
                    displayColors: false,
                    filter: (item) => item.raw.row !== null,
                    callbacks: {
                        title: (items) => spec.labels[items[0].raw.row],
                        label: (item) => ` ${f.text(item.raw.row)}`,
                    },
                },
            },
            scales: {
                projection: { axis: 'x', projection: 'mercator', padding: 8 },
                color: {
                    axis: 'x',
                    beginAtZero: true,
                    missing: palette().empty,
                    interpolate: (v) => {
                        const ramp = palette().ramp;
                        return ramp[Math.min(ramp.length - 1, Math.max(0, Math.round(v * (ramp.length - 1))))];
                    },
                    legend: { position: spec.dir === 'rtl' ? 'bottom-left' : 'bottom-right', align: 'top', length: 160, width: 10, margin: { left: 12, right: 12, top: 8, bottom: 12 } },
                    ticks: { color: () => palette().muted, precision: 0, callback: (v) => f.tick(v), maxTicksLimit: 4 },
                    border: { display: false },
                    grid: { display: false },
                },
            },
        },
    };
}
