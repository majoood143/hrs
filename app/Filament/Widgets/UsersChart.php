<?php

namespace App\Filament\Widgets;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Contracts\Support\Htmlable;

class UsersChart extends ChartWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:User') ?? false;
    }

    protected int|string|array $columnSpan = 2;

    // No 5s auto-refresh; the data is cached (see CachedWidgetCounts).
    protected ?string $pollingInterval = null;

    public function getHeading(): string|Htmlable|null
    {
        return __('admin_widgets.users_chart.heading');
    }

    protected function getData(): array
    {
        // Plain arrays are cached (not TrendValue objects); the key carries the year so it rolls over.
        $data = CachedWidgetCounts::remember('UsersChart.'.now()->year, function () {
            $trend = Trend::model(User::class)
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
                    'label' => __('admin_widgets.users_chart.dataset_label'),
                    'data' => $data['data'],
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
