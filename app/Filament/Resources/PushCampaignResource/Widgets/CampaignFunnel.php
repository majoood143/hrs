<?php

namespace App\Filament\Resources\PushCampaignResource\Widgets;

use App\Models\PushCampaign;
use LaBoiteACode\FilamentDashboardWidgets\Data\FunnelStage;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\FunnelWidget;

/** One campaign: phones targeted → taken by Expo → opened (the report page). */
class CampaignFunnel extends FunnelWidget
{
    public ?PushCampaign $record = null;

    public function getHeading(): string
    {
        return __('admin_widgets.push.campaign_funnel_heading');
    }

    protected function getStages(): array
    {
        return [
            FunnelStage::make(__('admin_widgets.push.targeted'), (int) $this->record?->recipients),
            FunnelStage::make(__('admin_widgets.push.delivered'), (int) $this->record?->accepted)->color('info'),
            FunnelStage::make(__('admin_widgets.push.opened'), (int) $this->record?->opened)->color('success'),
        ];
    }
}
