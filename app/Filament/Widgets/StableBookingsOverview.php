<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\StableInsights;
use App\Filament\Resources\StableResource;
use App\Models\Stable;
use App\Models\StablePaymentAccount;
use App\Services\Reports\StableInsights as Insights;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** On the admin dashboard: this month's stable bookings, what they earned us, and what waits for an admin. */
class StableBookingsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Stable') ?? false;
    }

    protected function getHeading(): ?string
    {
        return __('stable_insights.dashboard.heading');
    }

    protected function getStats(): array
    {
        $insights = Insights::forPeriod(null, 'this_month');
        $m = $insights->money();
        $p = $insights->previous()->money();
        $delta = Insights::delta($m['our_share'], $p['our_share']);
        $pendingStables = Stable::query()->pendingApproval()->count();
        $pendingKeys = StablePaymentAccount::query()->where('status', StablePaymentAccount::PENDING)->count();
        $insightsUrl = StableInsights::getUrl();

        return [
            Stat::make(__('stable_insights.kpi.bookings'), (string) $m['bookings'])
                ->description(__('stable_insights.dashboard.sales', ['amount' => Money::format($m['collected'])]))
                ->icon('heroicon-o-ticket')
                ->url($insightsUrl),
            Stat::make(__('stable_insights.kpi.our_share'), Money::formatHtml($m['our_share']))
                ->description($delta === null ? __('stable_insights.kpi.no_compare') : __('stable_insights.kpi.vs_previous', ['delta' => ($delta > 0 ? '+' : '').$delta.'%']))
                ->descriptionIcon($delta === null ? null : ($delta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'))
                ->color($delta === null ? 'gray' : ($delta >= 0 ? 'success' : 'danger'))
                ->icon('heroicon-o-building-library')
                ->url($insightsUrl),
            Stat::make(__('stable_insights.dashboard.waiting'), (string) ($pendingStables + $pendingKeys))
                ->description(__('stable_insights.dashboard.waiting_hint', ['stables' => $pendingStables, 'keys' => $pendingKeys]))
                ->color($pendingStables + $pendingKeys > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-clock')
                ->url(StableResource::getUrl('index', ['tableFilters' => ['approval_status' => ['value' => 'pending']]])),
        ];
    }
}
