<?php

namespace App\Filament\Widgets;

use App\Models\Horse;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class HorsesChart extends ChartWidget
{
    protected static ?string $heading = 'Horses Chart';

    protected static ?int $sort = 1;
    protected static bool $isLazy = false; // Load the widget only when it is visible on the
    protected static string $color = 'success'; // Primary, secondary, tertiary, success, warning, danger, in
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
                    'label' => 'Horses Registered',
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
