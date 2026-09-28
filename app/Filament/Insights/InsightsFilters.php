<?php

namespace App\Filament\Insights;

use App\Services\Reports\IncomeStatement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;

/** The period filter both Insights pages share. */
final class InsightsFilters
{
    /** @return array<int, mixed> */
    public static function period(): array
    {
        return [
            Select::make('period')
                ->label(__('admin_income_report.filters.period'))
                ->options(collect(IncomeStatement::PERIODS)->mapWithKeys(fn (string $p) => [$p => __('admin_income_report.periods.'.$p)])->all())
                ->default('this_month')
                ->selectablePlaceholder(false)
                ->native(false),
            DatePicker::make('date_from')
                ->label(__('admin_income_report.filters.date_from'))
                ->visible(fn (Get $get) => $get('period') === 'custom'),
            DatePicker::make('date_to')
                ->label(__('admin_income_report.filters.date_to'))
                ->visible(fn (Get $get) => $get('period') === 'custom'),
        ];
    }
}
