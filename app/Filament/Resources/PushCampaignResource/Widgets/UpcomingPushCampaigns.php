<?php

namespace App\Filament\Resources\PushCampaignResource\Widgets;

use App\Filament\Resources\PushCampaignResource;
use App\Models\PushCampaign;
use LaBoiteACode\FilamentDashboardWidgets\Data\RecentItem;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\RecentItemsWidget;

/** The next campaigns due to go out (not cached: it is the queue the admin is about to change). */
class UpcomingPushCampaigns extends RecentItemsWidget
{
    protected static ?int $sort = 6;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:PushCampaign') ?? false;
    }

    public function getHeading(): string
    {
        return __('admin_widgets.push.upcoming_heading');
    }

    public function getEmptyStateHeading(): string
    {
        return __('admin_widgets.push.upcoming_empty');
    }

    protected function getItems(): array
    {
        return PushCampaign::query()
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get()
            ->map(fn (PushCampaign $c) => RecentItem::make($c->localized('title') ?? '#'.$c->getKey(), __('admin_mobile_app.campaigns.audiences.'.$c->audience).' · '.__('admin_mobile_app.campaigns.topics.'.$c->topic))
                ->meta($c->scheduled_at?->timezone(config('stable_bookings.timezone', 'Asia/Muscat'))->translatedFormat('j M, H:i'))
                ->badge($c->source ? __('admin_mobile_app.campaigns.fields.automatic') : null)
                ->badgeColor('gray')
                ->url(PushCampaignResource::getUrl('edit', ['record' => $c])))
            ->all();
    }
}
