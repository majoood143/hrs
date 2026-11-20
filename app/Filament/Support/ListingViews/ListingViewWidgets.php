<?php

namespace App\Filament\Support\ListingViews;

use Filament\Resources\Resource;
use Filament\Widgets\WidgetConfiguration;

/** The views widgets of a listing resource's pages (registered in AdminPanelProvider::livewireComponents()). */
class ListingViewWidgets
{
    /**
     * Above a board's table.
     *
     * @param  class-string<resource>  $resource
     * @return list<WidgetConfiguration>
     */
    public static function forList(string $resource): array
    {
        return [
            ListingViewsStats::make(['resource' => $resource]),
            AppViewsChart::make(['resource' => $resource]),
            TopViewedListings::make(['resource' => $resource]),
        ];
    }

    /**
     * Below one listing (the page passes its record).
     *
     * @param  class-string<resource>  $resource
     * @return list<WidgetConfiguration>
     */
    public static function forRecord(string $resource): array
    {
        return [
            RecordViewsStats::make(['resource' => $resource]),
            AppViewsChart::make(['resource' => $resource]),
        ];
    }
}
