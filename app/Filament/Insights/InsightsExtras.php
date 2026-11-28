<?php

namespace App\Filament\Insights;

use App\Models\StableExtra;
use Filament\Widgets\Widget;

/** What customers add to their sessions: extras revenue, how many bookings take one, the best sellers. */
class InsightsExtras extends Widget
{
    use ResolvesInsights;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    protected string $view = 'filament.insights.extras';

    public static function canView(): bool
    {
        return StableExtra::enabled();
    }

    protected function getViewData(): array
    {
        $extras = $this->insights()->extras();

        return [
            'extras' => $extras,
            'max' => max(1, (int) $extras['top']->max('amount')),
        ];
    }
}
