<?php

namespace App\Filament\Resources\MobileAnnouncementResource\Widgets;

use App\Models\MobileAnnouncement;
use App\Support\MobileApp\AnnouncementInsights;
use LaBoiteACode\FilamentDashboardWidgets\Data\BreakdownItem;
use LaBoiteACode\FilamentDashboardWidgets\Widgets\BreakdownWidget;

/** What people did with the popups they saw: tapped the button, closed it, or neither (left the app, or no close button). */
class AnnouncementOutcomes extends BreakdownWidget
{
    public ?MobileAnnouncement $record = null;

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:MobileAnnouncement') ?? false;
    }

    public function getHeading(): string
    {
        return __('admin_widgets.announcements.outcomes_heading', ['days' => AnnouncementInsights::DAYS]);
    }

    protected function getItems(): array
    {
        $s = AnnouncementInsights::summary($this->record?->getKey());

        return [
            BreakdownItem::make(__('admin_widgets.announcements.clicked'), $s['clicked'])->color('success')->icon('heroicon-o-cursor-arrow-rays'),
            BreakdownItem::make(__('admin_widgets.announcements.dismissed'), $s['dismissed'])->color('gray')->icon('heroicon-o-x-mark'),
            BreakdownItem::make(__('admin_widgets.announcements.no_action'), max(0, $s['shown'] - $s['clicked'] - $s['dismissed']))->color('warning')->icon('heroicon-o-eye'),
        ];
    }
}
