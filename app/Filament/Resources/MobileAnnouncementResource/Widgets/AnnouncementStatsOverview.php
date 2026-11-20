<?php

namespace App\Filament\Resources\MobileAnnouncementResource\Widgets;

use App\Models\MobileAnnouncement;
use App\Support\MobileApp\AnnouncementInsights;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Popups shown, people reached, button taps and tap rate over the last 30 days; one announcement on its edit page. */
class AnnouncementStatsOverview extends StatsOverviewWidget
{
    public ?MobileAnnouncement $record = null;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:MobileAnnouncement') ?? false;
    }

    protected function getStats(): array
    {
        $id = $this->record?->getKey();
        $s = AnnouncementInsights::summary($id);
        $daily = AnnouncementInsights::daily($id);
        $rate = AnnouncementInsights::rate($s['clicked'], $s['shown']);
        $change = fn (?float $pct) => $pct === null
            ? __('admin_widgets.announcements.no_previous')
            : __('admin_widgets.announcements.vs_previous', ['change' => ($pct > 0 ? '+' : '').$pct.'%']);
        $icon = fn (?float $pct) => ($pct ?? 0) >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $shownChange = AnnouncementInsights::change($s['shown'], $s['shown_before']);
        $clickedChange = AnnouncementInsights::change($s['clicked'], $s['clicked_before']);

        return [
            Stat::make(__('admin_widgets.announcements.shown'), number_format($s['shown']))
                ->description($change($shownChange))
                ->descriptionIcon($icon($shownChange))
                ->chart($daily['shown'])
                ->color('info'),
            Stat::make(__('admin_widgets.announcements.reach'), number_format($s['reach']))
                ->description(__('admin_widgets.announcements.reach_hint'))
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('primary'),
            Stat::make(__('admin_widgets.announcements.clicked'), number_format($s['clicked']))
                ->description($change($clickedChange))
                ->descriptionIcon($icon($clickedChange))
                ->chart($daily['clicked'])
                ->color('success'),
            Stat::make(__('admin_widgets.announcements.ctr'), $rate.'%')
                ->description($this->record
                    ? __('admin_mobile_app.status.'.$this->record->status())
                    : __('admin_widgets.announcements.live', ['count' => number_format($s['live'])]))
                ->descriptionIcon('heroicon-m-cursor-arrow-rays')
                ->color($rate >= 10 ? 'success' : ($rate >= 3 ? 'warning' : 'gray')),
        ];
    }
}
