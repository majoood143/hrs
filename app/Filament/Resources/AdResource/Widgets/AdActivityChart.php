<?php

namespace App\Filament\Resources\AdResource\Widgets;

use App\Models\Ad;
use App\Support\Ads\AdInsights;
use LaBoiteACode\FilamentDashboardWidgets\Data\ChartSeries;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\ComparisonChartWidget;

/** Impressions and clicks per day; one promotion on its edit page. */
class AdActivityChart extends ComparisonChartWidget
{
    public ?Ad $record = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Ad') ?? false;
    }

    public function getHeading(): string
    {
        return __('admin_widgets.ads.activity_heading', ['days' => AdInsights::DAYS]);
    }

    protected function getLabels(): array
    {
        return AdInsights::daily($this->record?->getKey())['labels'];
    }

    protected function getSeries(): array
    {
        $daily = AdInsights::daily($this->record?->getKey());

        return [
            ChartSeries::make(__('admin_widgets.ads.impressions'))->values($daily['impressions'])->color('info')->filled(),
            ChartSeries::make(__('admin_widgets.ads.clicks'))->values($daily['clicks'])->color('success'),
        ];
    }
}
