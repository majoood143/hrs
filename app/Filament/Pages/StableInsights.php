<?php

namespace App\Filament\Pages;

use App\Filament\Insights\InsightsFilters;
use App\Filament\Insights\InsightsKpis;
use App\Filament\Insights\InsightsOccupancy;
use App\Filament\Insights\InsightsSalesChart;
use App\Filament\Insights\InsightsTopServices;
use App\Filament\Insights\InsightsTopStables;
use App\Models\Stable;
use App\Services\Reports\StableInsights as Insights;
use App\Support\Money;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Response;

/**
 * Stable bookings across the site: sales, the stables' share and our earnings over time, the
 * ranking of stables, what sells and when. Filter to one stable to see what its owner sees.
 */
class StableInsights extends BaseDashboard
{
    use HasFiltersForm;
    use HasPageShield;

    protected static string $routePath = 'stable-insights';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?int $navigationSort = 7;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.payments');
    }

    public static function getNavigationLabel(): string
    {
        return __('stable_insights.admin_title');
    }

    public function getTitle(): string
    {
        return __('stable_insights.admin_title');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('stable_id')
                ->label(__('stable_statement.fields.stable'))
                ->placeholder(__('stable_insights.all_stables'))
                ->options(fn () => Stable::query()->orderBy('en_name')->pluck('en_name', 'id'))
                ->searchable(),
            ...InsightsFilters::period(),
        ]);
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 2];
    }

    public function getWidgets(): array
    {
        return [InsightsKpis::class, InsightsSalesChart::class, InsightsTopStables::class, InsightsOccupancy::class, InsightsTopServices::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rankingCsv')
                ->label(__('stable_insights.export'))
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(function () {
                    $insights = Insights::forPeriod(null, (string) ($this->filters['period'] ?? 'this_month'), $this->filters['date_from'] ?? null, $this->filters['date_to'] ?? null);

                    return Response::streamDownload(function () use ($insights) {
                        $out = fopen('php://output', 'w');
                        fwrite($out, "\xEF\xBB\xBF");
                        fputcsv($out, [__('stable_insights.admin_title'), $insights->from->toDateString().' → '.$insights->to->toDateString()]);
                        fputcsv($out, [__('stable_statement.fields.stable'), __('stable_insights.services.sold'), __('stable_insights.kpi.sales'), __('stable_insights.chart.stables_share'), __('stable_insights.kpi.commission'), __('stable_insights.kpi.our_share')]);

                        foreach ($insights->byStable() as $row) {
                            fputcsv($out, [$row['stable']?->en_name ?? '#'.$row['stable_id'], $row['count'], Money::plain($row['collected']), Money::plain($row['stable_share']), Money::plain($row['commission']), Money::plain($row['our_share'])]);
                        }
                    }, 'stable-insights-'.$insights->from->toDateString().'_'.$insights->to->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}
