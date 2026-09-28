<?php

namespace App\Filament\Insights;

use Filament\Widgets\Widget;

/** What sold in the period, by service or package, with the money behind it. */
class InsightsTopServices extends Widget
{
    use ResolvesInsights;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    protected string $view = 'filament.insights.top-services';

    protected function getViewData(): array
    {
        $services = $this->insights()->topServices();

        return [
            'services' => $services,
            'max' => max(1, (int) $services->max('collected')),
            'owner' => $this->forOwner(),
        ];
    }
}
