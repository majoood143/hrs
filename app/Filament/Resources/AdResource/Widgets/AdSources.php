<?php

namespace App\Filament\Resources\AdResource\Widgets;

use App\Models\Ad;
use App\Support\Ads\AdInsights;
use LaBoiteACode\FilamentDashboardWidgets\Data\BreakdownItem;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\BreakdownWidget;

/** Where the impressions come from (website or app), with each side's clicks and click-through rate. */
class AdSources extends BreakdownWidget
{
    public ?Ad $record = null;

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Ad') ?? false;
    }

    public function getHeading(): string
    {
        return __('admin_widgets.ads.sources_heading', ['days' => AdInsights::DAYS]);
    }

    protected function getItems(): array
    {
        $icons = ['web' => 'heroicon-o-globe-alt', 'app' => 'heroicon-o-device-phone-mobile'];
        $colors = ['web' => 'primary', 'app' => 'info'];

        return collect(AdInsights::sources($this->record?->getKey()))->map(fn (array $s, string $source) => BreakdownItem::make(__("admin_widgets.ads.source.{$source}"), $s['impressions'])
            ->description(__('admin_widgets.ads.clicks_ctr', ['clicks' => number_format($s['clicks']), 'rate' => AdInsights::rate($s['clicks'], $s['impressions']).'%']))
            ->color($colors[$source])->icon($icons[$source]))->values()->all();
    }
}
