<?php

namespace App\Filament\Widgets;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\Horse;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Contracts\Support\Htmlable;

class HorsesChart extends ChartWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Horse') ?? false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('admin_widgets.horses_chart.heading');
    }

    protected int|string|array $columnSpan = 2;

    protected static ?int $sort = 1;

    // No 5s auto-refresh; the data is cached (see CachedWidgetCounts). Lazy (Filament's default),
    // so the dashboard renders before this chart's query runs.
    protected ?string $pollingInterval = null;

    protected string $color = 'success'; // Primary, secondary, tertiary, success, warning, danger, in
    // danger, info, gray, dark, black, white

    protected function getData(): array
    {
        // Plain arrays are cached (not TrendValue objects); the key carries the year so it rolls over.
        $data = CachedWidgetCounts::remember('HorsesChart.'.now()->year, function () {
            $trend = Trend::model(Horse::class)
                ->between(
                    start: now()->startOfYear(),
                    end: now()->endOfYear(),
                )
                ->perMonth()
                ->count();

            return [
                'data' => $trend->map(fn (TrendValue $value) => $value->aggregate)->all(),
                'labels' => $trend->map(fn (TrendValue $value) => $value->date)->all(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => __('admin_widgets.horses_chart.dataset_label'),
                    'data' => $data['data'],
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
