<?php

namespace App\Filament\Insights;

use Filament\Widgets\Widget;

/** When places sell: a weekday × start-hour grid of sold places against open places. */
class InsightsOccupancy extends Widget
{
    use ResolvesInsights;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    protected string $view = 'filament.insights.occupancy';

    protected function getViewData(): array
    {
        return ['occupancy' => $this->insights()->occupancy()];
    }
}
