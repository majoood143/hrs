<?php

namespace App\Filament\Support\ListingViews;

use App\Support\ListingViews;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** One listing's views: the count, its rank and share on its board, and its app views in the last 30 days. */
class RecordViewsStats extends StatsOverviewWidget
{
    use ForListingResource;

    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        if ($this->record === null) {
            return [];
        }
        $views = (int) $this->record->getAttribute('views_count');
        $rank = ListingViews::rank($this->record);
        $app = ListingViews::appDaily($this->model(), $this->record);

        return [
            Stat::make(__('admin_widgets.listing_views.views'), number_format($views))
                ->description(__('admin_widgets.listing_views.total_hint'))
                ->descriptionIcon('heroicon-m-eye')
                ->color('primary'),
            Stat::make(__('admin_widgets.listing_views.rank'), '#'.number_format($rank['rank']))
                ->description(__('admin_widgets.listing_views.rank_hint', ['count' => number_format($rank['of'])]))
                ->descriptionIcon('heroicon-m-trophy')
                ->color($rank['rank'] <= 3 ? 'success' : 'gray'),
            Stat::make(__('admin_widgets.listing_views.share'), $rank['share'].'%')
                ->description(__('admin_widgets.listing_views.share_hint'))
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('info'),
            Stat::make(__('admin_widgets.listing_views.app_30', ['days' => ListingViews::DAYS]), number_format($app['total']))
                ->description(self::changeText($app['total'], $app['before']))
                ->descriptionIcon(self::changeIcon($app['total'], $app['before']))
                ->chart($app['views'])
                ->color('success'),
        ];
    }
}
