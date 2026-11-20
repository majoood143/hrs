<?php

namespace App\Filament\Resources\AdResource\Widgets;

use App\Filament\Resources\AdResource;
use App\Support\Ads\AdInsights;
use LaBoiteACode\FilamentDashboardWidgets\Data\BreakdownItem;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\BreakdownWidget;

/** The promotions with the most clicks over the last 30 days, with their impressions and click-through rate. */
class TopAds extends BreakdownWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Ad') ?? false;
    }

    public function getHeading(): string
    {
        return __('admin_widgets.ads.top_heading', ['days' => AdInsights::DAYS]);
    }

    protected function getItems(): array
    {
        return array_map(fn (array $ad) => BreakdownItem::make($ad['name'], $ad['clicks'])
            ->description(__('admin_widgets.ads.impressions_ctr', ['impressions' => number_format($ad['impressions']), 'rate' => AdInsights::rate($ad['clicks'], $ad['impressions']).'%']))
            ->color('success')->icon('heroicon-o-cursor-arrow-rays')
            ->url(AdResource::getUrl('edit', ['record' => $ad['id']])), AdInsights::top());
    }
}
