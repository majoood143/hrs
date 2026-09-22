<?php

namespace App\Filament\Widgets;

use App\Services\Reports\IncomeStatement;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

/** This month's income at a glance, with the last two weeks as a sparkline. */
class IncomeOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    // Filament's CanPoll trait defaults every widget to a 5s auto-refresh. getStats() below reads
    // every paid/refunded order of the month (and a 14-day range) and sums them in PHP; that is
    // fine on page load but not worth re-running every 5 seconds for every open dashboard tab.
    // An admin can refresh the page for up-to-the-minute figures.
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:ServiceOrder') ?? false;
    }

    protected function getHeading(): ?string
    {
        return __('admin_income_report.widgets.heading');
    }

    protected function getStats(): array
    {
        $month = IncomeStatement::forPeriod('this_month');
        $today = IncomeStatement::forPeriod('today')->totals();
        $totals = $month->totals();

        $recent = new IncomeStatement(CarbonImmutable::now()->subDays(13)->startOfDay(), CarbonImmutable::now()->endOfDay());
        $days = $recent->byDay();

        return [
            Stat::make(__('admin_income_report.widgets.collected'), Money::formatHtml($totals['collected']))
                ->description(new HtmlString(__('admin_income_report.widgets.orders', ['count' => $totals['orders'], 'today' => Money::formatHtml($today['collected'])])))
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($days->pluck('collected')->map(fn (int $baisa) => $baisa / 1000)->values()->all())
                ->color('primary'),

            Stat::make(__('admin_income_report.widgets.due_to_us'), Money::formatHtml($totals['due_to_us']))
                ->description(new HtmlString(__('admin_income_report.widgets.due_hint', ['fee' => Money::formatHtml($totals['fee'] + $totals['vat_on_fee']), 'commission' => Money::formatHtml(IncomeStatement::commissionWithVat($totals))])))
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->chart($days->pluck('due_to_us')->map(fn (int $baisa) => $baisa / 1000)->values()->all())
                ->color('success'),

            Stat::make(__('admin_income_report.widgets.client_keeps'), Money::formatHtml($totals['client_keeps']))
                ->description(__('admin_income_report.widgets.client_hint'))
                ->descriptionIcon('heroicon-m-building-library')
                ->color('gray'),

            Stat::make(__('admin_income_report.widgets.refunded'), Money::formatHtml($totals['refunded']))
                ->description(__('admin_income_report.widgets.refunded_hint'))
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color($totals['refunded'] > 0 ? 'warning' : 'gray'),
        ];
    }
}
