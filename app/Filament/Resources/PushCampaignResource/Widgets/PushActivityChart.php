<?php

namespace App\Filament\Resources\PushCampaignResource\Widgets;

use App\Support\MobileApp\PushInsights;
use LaBoiteACode\FilamentDashboardWidgets\Data\ChartSeries;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\ComparisonChartWidget;

/** Delivered and opened notifications per day (campaigns and automatic ones). */
class PushActivityChart extends ComparisonChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:PushCampaign') ?? false;
    }

    public function getHeading(): string
    {
        return __('admin_widgets.push.activity_heading', ['days' => PushInsights::DAYS]);
    }

    protected function getLabels(): array
    {
        return PushInsights::daily()['labels'];
    }

    protected function getSeries(): array
    {
        $daily = PushInsights::daily();

        return [
            ChartSeries::make(__('admin_widgets.push.delivered'))->values($daily['sent'])->color('info')->filled(),
            ChartSeries::make(__('admin_widgets.push.opened'))->values($daily['opened'])->color('success'),
        ];
    }
}
