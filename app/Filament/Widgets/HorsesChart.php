<?php

namespace App\Filament\Widgets;

use App\Models\Horse;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class HorsesChart extends ChartWidget
{
    public function getHeading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return __('admin_widgets.horses_chart.heading');
    }
    protected int | string | array $columnSpan = 2;

    protected static ?int $sort = 1;
    protected static bool $isLazy = false; // Load the widget only when it is visible on the
    protected string $color = 'success'; // Primary, secondary, tertiary, success, warning, danger, in
    // danger, info, gray, dark, black, white

    protected function getData(): array
    {
        $data = Trend::model(Horse::class)
            ->between(
                start: now()->startOfYear(),
                end: now()->endOfYear(),
            )
            ->perMonth()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => __('admin_widgets.horses_chart.dataset_label'),
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn(TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
