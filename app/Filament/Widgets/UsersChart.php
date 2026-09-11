<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class UsersChart extends ChartWidget
{
    protected int | string | array $columnSpan = 2;

    public function getHeading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return __('admin_widgets.users_chart.heading');
    }

    protected function getData(): array
    {
        $data = Trend::model(User::class)
            ->between(
            start: now()->startOfYear(),
            end: now()->endOfYear(),
            )
            ->perMonth()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => __('admin_widgets.users_chart.dataset_label'),
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn(TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
