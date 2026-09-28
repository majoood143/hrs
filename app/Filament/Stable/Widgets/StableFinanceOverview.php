<?php

namespace App\Filament\Stable\Widgets;

use App\Filament\Stable\Pages\Statement;
use App\Models\Stable;
use App\Services\Reports\StableStatement;
use App\Support\Money;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

/** This month's bookings money, and where the stable's balance with us stands today. */
class StableFinanceOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -4;

    public static function canView(): bool
    {
        $stable = Filament::getTenant();

        return $stable instanceof Stable && Gate::allows('update', $stable);
    }

    protected function getHeading(): ?string
    {
        return __('stable_statement.widget.heading');
    }

    protected function getStats(): array
    {
        /** @var Stable $stable */
        $stable = Filament::getTenant();
        $t = StableStatement::forPeriod($stable, 'this_month')->totals();
        $balance = StableStatement::balance($stable);
        $url = Statement::getUrl();

        return [
            Stat::make(__('stable_statement.widget.bookings'), (string) $t['bookings'])
                ->description(trans_choice('stable_bookings.riders_count', $t['riders'], ['count' => $t['riders']]))
                ->icon('heroicon-o-ticket'),
            Stat::make(__('stable_statement.widget.stable_share'), Money::formatHtml($t['stable_share']))
                ->description(__('stable_statement.widget.this_month'))
                ->icon('heroicon-o-home-modern')
                ->color('success'),
            Stat::make(__('stable_statement.widget.commission'), Money::formatHtml($t['commission'] + $t['vat_on_commission']))
                ->description($stable->formatted_commission ? __('stable_statement.widget.rate', ['rate' => $stable->formatted_commission]) : null)
                ->icon('heroicon-o-receipt-percent'),
            Stat::make(__('stable_statement.widget.balance'), Money::formatHtml(abs($balance)))
                ->description(__('stable_statement.widget.balance_'.($balance > 0 ? 'we_owe' : ($balance < 0 ? 'you_owe' : 'settled'))))
                ->descriptionIcon('heroicon-o-scale')
                ->color($balance > 0 ? 'success' : ($balance < 0 ? 'warning' : 'gray'))
                ->url($url),
        ];
    }
}
