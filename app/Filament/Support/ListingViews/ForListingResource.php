<?php

namespace App\Filament\Support\ListingViews;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared by the listings' views widgets: they get the resource (`::make(['resource' => …])`) and,
 * on a record page, the record. Shown only to admins who may list that resource.
 */
trait ForListingResource
{
    /** @var class-string<resource> */
    public string $resource;

    public function mountForListingResource(): void
    {
        abort_unless($this->resource::canViewAny(), 403);
    }

    /** @return class-string<Model> */
    protected function model(): string
    {
        return $this->resource::getModel();
    }

    protected static function changeText(int $now, int $before): string
    {
        if ($before === 0) {
            return __('admin_widgets.listing_views.no_previous');
        }
        $pct = round(100 * ($now - $before) / $before, 1);

        return __('admin_widgets.listing_views.vs_previous', ['change' => ($pct > 0 ? '+' : '').$pct.'%']);
    }

    protected static function changeIcon(int $now, int $before): string
    {
        return $now >= $before ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
    }
}
