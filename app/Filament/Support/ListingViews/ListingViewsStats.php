<?php

namespace App\Filament\Support\ListingViews;

use App\Support\ListingViews;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** A board's views: all time, per listing, never seen, and app views in the last 30 days. */
class ListingViewsStats extends StatsOverviewWidget
{
    use ForListingResource;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $s = ListingViews::summary($this->model());
        $app = ListingViews::appDaily($this->model());

        return [
            Stat::make(__('admin_widgets.listing_views.total'), number_format($s['total']))
                ->description(__('admin_widgets.listing_views.total_hint'))
                ->descriptionIcon('heroicon-m-eye')
                ->color('primary'),
            Stat::make(__('admin_widgets.listing_views.average'), number_format($s['listings'] > 0 ? $s['total'] / $s['listings'] : 0, 1))
                ->description(__('admin_widgets.listing_views.listings', ['count' => number_format($s['listings'])]))
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('info'),
            Stat::make(__('admin_widgets.listing_views.unseen'), number_format($s['unseen']))
                ->description(__('admin_widgets.listing_views.unseen_hint'))
                ->descriptionIcon('heroicon-m-eye-slash')
                ->color($s['unseen'] > 0 ? 'warning' : 'success'),
            Stat::make(__('admin_widgets.listing_views.app_30', ['days' => ListingViews::DAYS]), number_format($app['total']))
                ->description(self::changeText($app['total'], $app['before']))
                ->descriptionIcon(self::changeIcon($app['total'], $app['before']))
                ->chart($app['views'])
                ->color('success'),
        ];
    }
}
