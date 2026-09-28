<?php

namespace App\Filament\Stable\Pages;

use App\Filament\Insights\InsightsFilters;
use App\Filament\Insights\InsightsKpis;
use App\Filament\Insights\InsightsOccupancy;
use App\Filament\Insights\InsightsSalesChart;
use App\Filament\Insights\InsightsTopServices;
use App\Models\Stable;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/** How the stable is doing: sales and commission over time, when it fills up, what sells, who comes back. */
class Insights extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'insights';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?int $navigationSort = 25;

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.bookings');
    }

    public static function getNavigationLabel(): string
    {
        return __('stable_insights.title');
    }

    public function getTitle(): string
    {
        return __('stable_insights.title');
    }

    public static function canAccess(): bool
    {
        $stable = Filament::getTenant();

        return $stable instanceof Stable && Gate::allows('update', $stable);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components(InsightsFilters::period());
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 2];
    }

    public function getWidgets(): array
    {
        return [InsightsKpis::class, InsightsSalesChart::class, InsightsOccupancy::class, InsightsTopServices::class];
    }
}
