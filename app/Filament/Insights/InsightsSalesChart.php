<?php

namespace App\Filament\Insights;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The money of the period by day/week/month as stacked bars: the stable's share with the
 * commission on top (owner), or the stables' share with our earnings on top (admin). One axis,
 * one unit; colours are the validated categorical slots 1 and 2, stepped for light and dark.
 */
class InsightsSalesChart extends ChartWidget
{
    use ResolvesInsights;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    public function getHeading(): string|Htmlable|null
    {
        return __('stable_insights.chart.heading_'.$this->insights()->bucket());
    }

    public function getDescription(): string|Htmlable|null
    {
        return __($this->forOwner() ? 'stable_insights.chart.description_owner' : 'stable_insights.chart.description_admin');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $series = $this->insights()->series();
        $owner = $this->forOwner();

        return [
            'datasets' => [
                [
                    'label' => $owner ? __('stable_insights.chart.stable_share') : __('stable_insights.chart.stables_share'),
                    'data' => $series->pluck('stable_share')->map(fn (int $b) => round($b / 1000, 3))->values()->all(),
                ],
                [
                    'label' => $owner ? __('stable_insights.chart.commission') : __('stable_insights.chart.our_share'),
                    'data' => $series->pluck($owner ? 'commission' : 'our_share')->map(fn (int $b) => round($b / 1000, 3))->values()->all(),
                ],
            ],
            'labels' => $series->pluck('label')->values()->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            datasets: {
                bar: {
                    maxBarThickness: 28,
                    backgroundColor: (ctx) => {
                        const dark = document.documentElement.classList.contains('dark');
                        return [dark ? '#3987e5' : '#2a78d6', dark ? '#d95926' : '#eb6834'][ctx.datasetIndex];
                    },
                    // a 2px surface gap between the two stacked segments
                    borderColor: () => document.documentElement.classList.contains('dark') ? '#18181b' : '#ffffff',
                    borderWidth: (ctx) => ctx.datasetIndex === 1 ? { bottom: 2, top: 0, left: 0, right: 0 } : 0,
                    borderSkipped: false,
                    borderRadius: (ctx) => ctx.datasetIndex === 1 ? { topLeft: 4, topRight: 4 } : 0,
                },
            },
            scales: {
                x: { stacked: true, grid: { display: false }, ticks: { color: '#898781' } },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: () => document.documentElement.classList.contains('dark') ? '#2c2c2a' : '#e1e0d9' },
                    ticks: { color: '#898781', callback: (value) => Number(value).toLocaleString(undefined, { maximumFractionDigits: 0 }) },
                },
            },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 10 } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ${Number(ctx.parsed.y).toFixed(3)}`,
                        footer: (items) => `= ${items.reduce((sum, item) => sum + Number(item.parsed.y), 0).toFixed(3)}`,
                    },
                },
            },
        }
        JS);
    }
}
