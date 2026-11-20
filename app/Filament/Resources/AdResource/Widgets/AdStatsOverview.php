<?php

namespace App\Filament\Resources\AdResource\Widgets;

use App\Models\Ad;
use App\Support\Ads\AdInsights;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Impressions, clicks and click-through rate over the last 30 days against the 30 before; one promotion on its edit page. */
class AdStatsOverview extends StatsOverviewWidget
{
    public ?Ad $record = null;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Ad') ?? false;
    }

    protected function getStats(): array
    {
        $id = $this->record?->getKey();
        $s = AdInsights::summary($id);
        $daily = AdInsights::daily($id);
        $rate = AdInsights::rate($s['clicks'], $s['impressions']);
        $rateBefore = AdInsights::rate($s['clicks_before'], $s['impressions_before']);
        $change = fn (?float $pct) => $pct === null
            ? __('admin_widgets.ads.no_previous')
            : __('admin_widgets.ads.vs_previous', ['change' => ($pct > 0 ? '+' : '').$pct.'%']);
        $icon = fn (?float $pct) => ($pct ?? 0) >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $impressionsChange = AdInsights::change($s['impressions'], $s['impressions_before']);
        $clicksChange = AdInsights::change($s['clicks'], $s['clicks_before']);

        return [
            Stat::make(__('admin_widgets.ads.impressions'), number_format($s['impressions']))
                ->description($change($impressionsChange))
                ->descriptionIcon($icon($impressionsChange))
                ->chart($daily['impressions'])
                ->color('info'),
            Stat::make(__('admin_widgets.ads.clicks'), number_format($s['clicks']))
                ->description($change($clicksChange))
                ->descriptionIcon($icon($clicksChange))
                ->chart($daily['clicks'])
                ->color('success'),
            Stat::make(__('admin_widgets.ads.ctr'), $rate.'%')
                ->description($s['impressions_before'] > 0
                    ? __('admin_widgets.ads.rate_before', ['rate' => $rateBefore.'%'])
                    : __('admin_widgets.ads.no_previous'))
                ->descriptionIcon('heroicon-m-cursor-arrow-rays')
                ->color($rate >= 2 ? 'success' : ($rate >= 0.5 ? 'warning' : 'gray')),
            Stat::make(__('admin_widgets.ads.running'), number_format($s['running']))
                ->description($this->record
                    ? __('ads.statuses.'.$this->record->status)
                    : __('admin_widgets.ads.running_hint'))
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('primary'),
        ];
    }
}
