<?php

namespace App\Filament\Support\ListingViews;

use App\Support\ListingViews;
use Illuminate\Database\Eloquent\Model;
use LaBoiteACode\FilamentDashboardWidgets\Data\Trend;
use LaBoiteACode\FilamentDashboardWidgets\Data\TrendPoint;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\TrendWidget;

/** App views per day over the last 30 days, for a board or (on a record page) one listing. */
class AppViewsChart extends TrendWidget
{
    use ForListingResource;

    public ?Model $record = null;

    protected static ?int $sort = 2;

    public function getColumnSpan(): int|string|array
    {
        return $this->record === null ? 2 : 'full'; // beside the top 5 on a board, alone under a listing
    }

    protected function getTrend(): Trend
    {
        $app = ListingViews::appDaily($this->model(), $this->record);
        $change = $app['before'] > 0 ? round(100 * ($app['total'] - $app['before']) / $app['before'], 1) : null;

        return Trend::make(__('admin_widgets.listing_views.chart_heading', ['days' => ListingViews::DAYS]))
            ->value(number_format($app['total']))
            ->comparison($change)
            ->points(array_map(fn (string $label, int $n) => TrendPoint::make($label, $n), $app['labels'], $app['views']))
            ->type('area')
            ->color('primary');
    }
}
