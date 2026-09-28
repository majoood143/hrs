<?php

namespace App\Filament\Insights;

use App\Services\Reports\StableInsights;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The period's headline numbers, each against the period just before. */
class InsightsKpis extends StatsOverviewWidget
{
    use ResolvesInsights;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getStats(): array
    {
        $now = $this->insights();
        $before = $now->previous();
        $m = $now->money();
        $p = $before->money();
        $occupancy = $now->occupancyRate();
        $customers = $now->customers();
        $attendance = $now->attendance();
        $rating = $now->rating();

        $share = $this->forOwner()
            ? $this->money(__('stable_insights.kpi.stable_share'), $m['stable_share'], $p['stable_share'], 'heroicon-o-home-modern')
            : $this->money(__('stable_insights.kpi.our_share'), $m['our_share'], $p['our_share'], 'heroicon-o-building-library');

        return [
            $this->money(__('stable_insights.kpi.sales'), $m['collected'], $p['collected'], 'heroicon-o-banknotes'),
            $share,
            $this->money(__('stable_insights.kpi.commission'), $m['commission'], $p['commission'], 'heroicon-o-receipt-percent'),
            $this->count(__('stable_insights.kpi.bookings'), $m['bookings'], $p['bookings'], 'heroicon-o-ticket')
                ->description(trans_choice('stable_bookings.riders_count', $m['riders'], ['count' => $m['riders']]).$this->deltaText(StableInsights::delta($m['bookings'], $p['bookings']))),
            Stat::make(__('stable_insights.kpi.occupancy'), $occupancy === null ? '—' : $occupancy.'%')
                ->description($this->deltaText(StableInsights::delta($occupancy, $before->occupancyRate()), prefix: false) ?: __('stable_insights.kpi.occupancy_hint'))
                ->icon('heroicon-o-chart-pie'),
            Stat::make(__('stable_insights.kpi.customers'), (string) ($customers['new'] + $customers['returning']))
                ->description(__('stable_insights.kpi.customers_hint', $customers))
                ->icon('heroicon-o-user-group'),
            Stat::make(__('stable_insights.kpi.cancellations'), $attendance['cancel_rate'] === null ? '—' : $attendance['cancel_rate'].'%')
                ->description(__('stable_insights.kpi.no_show', ['rate' => $attendance['no_show_rate'] === null ? '—' : $attendance['no_show_rate'].'%']))
                ->icon('heroicon-o-x-circle'),
            Stat::make(__('stable_insights.kpi.rating'), $rating['average'] === null ? '—' : number_format($rating['average'], 1).' ★')
                ->description(trans_choice('stable_reviews.count', $rating['count'], ['count' => $rating['count']]))
                ->icon('heroicon-o-star'),
        ];
    }

    private function money(string $label, int $now, int $before, string $icon): Stat
    {
        $delta = StableInsights::delta($now, $before);

        return Stat::make($label, Money::formatHtml($now))
            ->description($this->deltaText($delta, prefix: false) ?: __('stable_insights.kpi.no_compare'))
            ->descriptionIcon($delta === null ? null : ($delta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'))
            ->color($delta === null ? 'gray' : ($delta >= 0 ? 'success' : 'danger'))
            ->icon($icon);
    }

    private function count(string $label, int $now, int $before, string $icon): Stat
    {
        return Stat::make($label, (string) $now)->icon($icon);
    }

    private function deltaText(?int $delta, bool $prefix = true): string
    {
        if ($delta === null) {
            return '';
        }

        $text = __('stable_insights.kpi.vs_previous', ['delta' => ($delta > 0 ? '+' : '').$delta.'%']);

        return $prefix ? ' · '.$text : $text;
    }
}
