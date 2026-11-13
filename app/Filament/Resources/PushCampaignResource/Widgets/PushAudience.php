<?php

namespace App\Filament\Resources\PushCampaignResource\Widgets;

use App\Support\MobileApp\PushInsights;
use LaBoiteACode\FilamentDashboardWidgets\Data\BreakdownItem;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\BreakdownWidget;

/** Who the phones that take notifications are: signed-in customers, stable owners, guests, and the platforms. */
class PushAudience extends BreakdownWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:PushCampaign') ?? false;
    }

    public function getHeading(): string
    {
        return __('admin_widgets.push.audience_heading');
    }

    protected function getItems(): array
    {
        $a = PushInsights::audience();
        $items = [
            BreakdownItem::make(__('admin_mobile_app.campaigns.audiences.customers'), $a['customers'])->color('primary')->icon('heroicon-o-user'),
            BreakdownItem::make(__('admin_mobile_app.campaigns.audiences.owners'), $a['owners'])->color('warning')->icon('heroicon-o-home-modern'),
            BreakdownItem::make(__('admin_mobile_app.campaigns.audiences.guests'), $a['guests'])->color('gray')->icon('heroicon-o-user-circle'),
        ];

        foreach ($a['platforms'] as $platform => $count) {
            $items[] = BreakdownItem::make(__('admin_widgets.push.platform', ['platform' => $platform === 'ios' ? 'iOS' : ucfirst((string) $platform)]), $count)
                ->color('info')->icon('heroicon-o-device-phone-mobile');
        }

        return $items;
    }

    /** The platforms split the same phones again, so shares are of all phones, not of the rows. */
    protected function getTotal(): ?float
    {
        $a = PushInsights::audience();

        return (float) ($a['customers'] + $a['owners'] + $a['guests']);
    }
}
