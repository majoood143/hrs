<?php

namespace App\Filament\Resources\MobileAnnouncementResource\Widgets;

use App\Filament\Resources\MobileAnnouncementResource;
use App\Models\MobileAnnouncement;
use App\Support\MobileApp\AnnouncementInsights;
use LaBoiteACode\FilamentDashboardWidgets\Data\RecentItem;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\RecentItemsWidget;

/** The announcements with the best tap rate (all time, shown at least once). */
class TopAnnouncements extends RecentItemsWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:MobileAnnouncement') ?? false;
    }

    public function getHeading(): string
    {
        return __('admin_widgets.announcements.top_heading');
    }

    public function getEmptyStateHeading(): string
    {
        return __('admin_widgets.announcements.top_empty');
    }

    protected function getItems(): array
    {
        $totals = collect(AnnouncementInsights::totals())->filter(fn (array $t) => $t['shown'] > 0);
        $records = MobileAnnouncement::query()->whereKey($totals->keys())->get()->keyBy('id');

        return $totals->filter(fn (array $t, int $id) => $records->has($id))
            ->sortByDesc(fn (array $t) => [AnnouncementInsights::rate($t['clicked'], $t['shown']), $t['shown']])
            ->take(5)
            ->map(fn (array $t, int $id) => RecentItem::make($records[$id]->localized('title') ?? '#'.$id, __('admin_widgets.announcements.top_line', [
                'shown' => number_format($t['shown']), 'clicked' => number_format($t['clicked']),
            ]))
                ->meta(AnnouncementInsights::rate($t['clicked'], $t['shown']).'%')
                ->badge(__('admin_mobile_app.status.'.$records[$id]->status()))
                ->badgeColor($records[$id]->status() === 'live' ? 'success' : 'gray')
                ->url(MobileAnnouncementResource::getUrl('edit', ['record' => $records[$id]])))
            ->values()
            ->all();
    }
}
