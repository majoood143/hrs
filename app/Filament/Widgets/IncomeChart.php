<?php

namespace App\Filament\Widgets;

use App\Services\Reports\IncomeStatement;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/** Day by day: what is due to us against what stays with the client. */
class IncomeChart extends ChartWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    // See IncomeOverview::$pollingInterval: same reasoning, this chart re-sums a whole date range
    // (up to 90 days) in PHP on every render and does not need a 5s auto-refresh.
    protected ?string $pollingInterval = null;

    public ?string $filter = '30';

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:ServiceOrder') ?? false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('admin_income_report.widgets.chart_heading');
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => __('admin_income_report.widgets.last_days', ['days' => 7]),
            '30' => __('admin_income_report.widgets.last_days', ['days' => 30]),
            '90' => __('admin_income_report.widgets.last_days', ['days' => 90]),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = in_array($this->filter, ['7', '30', '90'], true) ? (int) $this->filter : 30;
        $byDay = (new IncomeStatement(CarbonImmutable::now()->subDays($days - 1)->startOfDay(), CarbonImmutable::now()->endOfDay()))->byDay();

        return [
            'datasets' => [
                [
                    'label' => __('admin_income_report.widgets.due_to_us'),
                    'data' => $byDay->pluck('due_to_us')->map(fn (int $baisa) => $baisa / 1000)->values()->all(),
                    'backgroundColor' => '#16a34a',
                ],
                [
                    'label' => __('admin_income_report.widgets.client_keeps'),
                    'data' => $byDay->pluck('client_keeps')->map(fn (int $baisa) => $baisa / 1000)->values()->all(),
                    'backgroundColor' => '#94a3b8',
                ],
            ],
            'labels' => $byDay->keys()->map(fn (string $date) => CarbonImmutable::parse($date)->format('M j'))->values()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true]]];
    }
}
