<?php

namespace App\Filament\Resources\PushCampaignResource\Widgets;

use App\Support\MobileApp\PushInsights;
use LaBoiteACode\FilamentDashboardWidgets\Data\FunnelStage;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\FunnelWidget;

/** Campaigns sent in the last 30 days: phones targeted → taken by Expo → opened. */
class PushFunnel extends FunnelWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:PushCampaign') ?? false;
    }

    public function getHeading(): string
    {
        return __('admin_widgets.push.funnel_heading', ['days' => PushInsights::DAYS]);
    }

    protected function getStages(): array
    {
        $s = PushInsights::summary();

        return [
            FunnelStage::make(__('admin_widgets.push.targeted'), $s['targeted']),
            FunnelStage::make(__('admin_widgets.push.delivered'), $s['accepted'])->color('info'),
            FunnelStage::make(__('admin_widgets.push.opened'), $s['campaign_opens'])->color('success'),
        ];
    }
}
