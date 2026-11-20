<?php

namespace App\Filament\Resources\MobileAnnouncementResource\Widgets;

use App\Models\MobileAnnouncement;
use App\Support\MobileApp\AnnouncementInsights;
use LaBoiteACode\FilamentDashboardWidgets\Data\ChartSeries;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\ComparisonChartWidget;

/** Popups shown and button taps per day; one announcement on its edit page. */
class AnnouncementActivityChart extends ComparisonChartWidget
{
    public ?MobileAnnouncement $record = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:MobileAnnouncement') ?? false;
    }

    public function getHeading(): string
    {
        return __('admin_widgets.announcements.activity_heading', ['days' => AnnouncementInsights::DAYS]);
    }

    protected function getLabels(): array
    {
        return AnnouncementInsights::daily($this->record?->getKey())['labels'];
    }

    protected function getSeries(): array
    {
        $daily = AnnouncementInsights::daily($this->record?->getKey());

        return [
            ChartSeries::make(__('admin_widgets.announcements.shown'))->values($daily['shown'])->color('info')->filled(),
            ChartSeries::make(__('admin_widgets.announcements.clicked'))->values($daily['clicked'])->color('success'),
        ];
    }
}
