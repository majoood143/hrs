<?php

namespace App\Filament\Support\ListingViews;

use App\Support\ListingViews;
use Illuminate\Database\Eloquent\Model;
use LaBoiteACode\FilamentDashboardWidgets\Data\RecentItem;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\RecentItemsWidget;

/** The five most viewed listings of a board. */
class TopViewedListings extends RecentItemsWidget
{
    use ForListingResource;

    protected static ?int $sort = 3;

    public function getHeading(): string
    {
        return __('admin_widgets.listing_views.top_heading');
    }

    public function getEmptyStateHeading(): string
    {
        return __('admin_widgets.listing_views.top_empty');
    }

    protected function getItems(): array
    {
        $top = collect(ListingViews::summary($this->model())['top']);
        $records = $this->model()::query()->whereKey($top->pluck('id'))->get()->keyBy(fn (Model $m) => $m->getKey());
        $page = $this->resource::hasPage('view') ? 'view' : 'edit';

        return $top->filter(fn (array $row) => $records->has($row['id']))
            ->values()
            ->map(fn (array $row, int $i) => RecentItem::make('#'.($i + 1).' '.ListingViews::title($records[$row['id']]))
                ->meta(trans_choice('listings.views', $row['views'], ['count' => number_format($row['views'])]))
                ->url($this->resource::getUrl($page, ['record' => $records[$row['id']]])))
            ->all();
    }
}
