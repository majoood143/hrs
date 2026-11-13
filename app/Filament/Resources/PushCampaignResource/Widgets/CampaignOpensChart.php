<?php

namespace App\Filament\Resources\PushCampaignResource\Widgets;

use App\Models\PushCampaign;
use App\Support\MobileApp\PushInsights;
use LaBoiteACode\FilamentDashboardWidgets\Data\ChartSeries;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\ComparisonChartWidget;

/** One campaign's opens per hour after it went out: when people react. */
class CampaignOpensChart extends ComparisonChartWidget
{
    public ?PushCampaign $record = null;

    public function getHeading(): string
    {
        return __('admin_widgets.push.campaign_opens_heading');
    }

    protected function getLabels(): array
    {
        return $this->record?->sent_at ? PushInsights::campaignOpens($this->record)['labels'] : [];
    }

    protected function getSeries(): array
    {
        return $this->record?->sent_at ? [
            ChartSeries::make(__('admin_widgets.push.opened'))->values(PushInsights::campaignOpens($this->record)['opened'])->type('bar')->color('success'),
        ] : [];
    }
}
