<?php

namespace App\Filament\Insights;

use App\Filament\Pages\StableStatementReport;
use Filament\Widgets\Widget;

/** The admins' ranking of stables for the period: sales, their share, our earnings. */
class InsightsTopStables extends Widget
{
    use ResolvesInsights;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.insights.top-stables';

    public static function canView(): bool
    {
        return true;
    }

    protected function getViewData(): array
    {
        $rows = $this->scopeStable() ? collect() : $this->insights()->byStable();

        return [
            'rows' => $rows,
            'max' => max(1, (int) $rows->max('collected')),
            'statementUrl' => fn (int $id) => StableStatementReport::getUrl(['stable' => $id]),
        ];
    }
}
