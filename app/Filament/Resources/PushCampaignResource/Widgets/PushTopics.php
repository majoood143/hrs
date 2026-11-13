<?php

namespace App\Filament\Resources\PushCampaignResource\Widgets;

use App\Support\MobileApp\PushInsights;
use LaBoiteACode\FilamentDashboardWidgets\Data\BreakdownItem;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\BreakdownWidget;

/** How many phones still take each topic (the app's Notifications screen lets them turn topics off). */
class PushTopics extends BreakdownWidget
{
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:PushCampaign') ?? false;
    }

    public function getHeading(): string
    {
        return __('admin_widgets.push.topics_heading');
    }

    public function getHeadingDescription(): string
    {
        return __('admin_widgets.push.topics_description');
    }

    protected function getItems(): array
    {
        return collect(PushInsights::audience()['topics'])
            ->map(fn (int $count, string $topic) => BreakdownItem::make(__('admin_mobile_app.campaigns.topics.'.$topic), $count)->color('primary'))
            ->values()
            ->all();
    }

    protected function getTotal(): ?float
    {
        return (float) PushInsights::summary()['reachable'];
    }

    protected function shouldSortByValue(): bool
    {
        return true;
    }
}
