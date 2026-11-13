<?php

namespace App\Filament\Resources\PushCampaignResource\Widgets;

use App\Support\MobileApp\PushInsights;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Reach, sends, opens and open rate over the last 30 days, against the 30 before. */
class PushStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:PushCampaign') ?? false;
    }

    protected function getStats(): array
    {
        $s = PushInsights::summary();
        $daily = PushInsights::daily();
        $rate = PushInsights::rate($s['opened'], $s['sent']);
        $rateBefore = PushInsights::rate($s['opened_before'], $s['sent_before']);
        $change = fn (?float $pct) => $pct === null
            ? __('admin_widgets.push.no_previous')
            : __('admin_widgets.push.vs_previous', ['change' => ($pct > 0 ? '+' : '').$pct.'%']);
        $icon = fn (?float $pct) => ($pct ?? 0) >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $sentChange = PushInsights::change($s['sent'], $s['sent_before']);
        $openedChange = PushInsights::change($s['opened'], $s['opened_before']);

        return [
            Stat::make(__('admin_widgets.push.reachable'), number_format($s['reachable']))
                ->description(__('admin_widgets.push.active_7', ['count' => number_format($s['active'])]))
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('primary'),
            Stat::make(__('admin_widgets.push.delivered'), number_format($s['sent']))
                ->description($change($sentChange))
                ->descriptionIcon($icon($sentChange))
                ->chart($daily['sent'])
                ->color('info'),
            Stat::make(__('admin_widgets.push.opened'), number_format($s['opened']))
                ->description($change($openedChange))
                ->descriptionIcon($icon($openedChange))
                ->chart($daily['opened'])
                ->color('success'),
            Stat::make(__('admin_widgets.push.open_rate'), $rate.'%')
                ->description($s['sent_before'] > 0
                    ? __('admin_widgets.push.rate_before', ['rate' => $rateBefore.'%'])
                    : __('admin_widgets.push.no_previous'))
                ->descriptionIcon('heroicon-m-cursor-arrow-rays')
                ->color($rate >= 10 ? 'success' : ($rate >= 3 ? 'warning' : 'gray')),
        ];
    }
}
