<?php

namespace App\Filament\Support;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Services\Reports\ClosedBooks;
use App\Support\Money;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

/** The expense form and columns, shared by admin → Payments → Expenses and the stable panel's Expenses. */
final class ExpenseFields
{
    /** @return list<Component> */
    public static function form(bool $forStable): array
    {
        return array_values(array_filter([
            Select::make('expense_category_id')
                ->label(__('expenses.fields.category'))
                ->options(fn () => ExpenseCategory::options($forStable))
                ->searchable()
                ->required(),
            DatePicker::make('spent_on')
                ->label(__('expenses.fields.spent_on'))
                ->default(fn () => now(config('stable_bookings.timezone'))->toDateString())
                ->maxDate(fn () => now(config('stable_bookings.timezone'))->toDateString())
                // ours are in our books: none in a closed month
                ->minDate($forStable ? null : fn () => ClosedBooks::firstOpenDay())
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    if (ClosedBooks::isClosed(substr((string) $value, 0, 10))) {
                        $fail(__('expenses.closed_month'));
                    }
                }, ! $forStable)
                ->required(),
            TextInput::make('amount')
                ->label(__('expenses.fields.amount'))
                ->numeric()
                ->step(0.001)
                ->minValue(0.001)
                ->maxValue(9999999.999)
                ->suffix(fn () => SiteSetting::currency()['code'])
                ->required(),
            $forStable ? Select::make('stable_horse_id')
                ->label(__('expenses.fields.horse'))
                ->helperText(__('expenses.horse_hint'))
                ->options(fn () => Filament::getTenant() instanceof Stable ? Filament::getTenant()->horses()->orderBy('name')->pluck('name', 'id')->all() : [])
                ->searchable() : null,
            TextInput::make('vendor')
                ->label(__('expenses.fields.vendor'))
                ->maxLength(255),
            Textarea::make('note')
                ->label(__('expenses.fields.note'))
                ->rows(2)
                ->maxLength(2000)
                ->columnSpanFull(),
            FileUpload::make('receipt')
                ->label(__('expenses.fields.receipt'))
                ->disk(Expense::DISK)
                ->visibility('private')
                ->directory('expenses/receipts')
                ->acceptedFileTypes(Expense::RECEIPT_TYPES)
                ->maxSize(Expense::RECEIPT_MAX_KB)
                ->downloadable()
                ->openable()
                ->columnSpanFull(),
        ]));
    }

    /** @return list<Column> */
    public static function columns(bool $withStable, bool $withHorse): array
    {
        return array_values(array_filter([
            TextColumn::make('spent_on')
                ->label(__('expenses.fields.spent_on'))
                ->date('Y-m-d')
                ->icon('heroicon-o-calendar')
                ->sortable(),
            TextColumn::make('category.en_name')
                ->label(__('expenses.fields.category'))
                ->formatStateUsing(fn (Expense $record) => $record->category?->name)
                ->badge(),
            $withStable ? TextColumn::make('stable.en_name')
                ->label(__('expenses.fields.stable'))
                ->formatStateUsing(fn (Expense $record) => $record->stable?->name)
                ->placeholder(__('expenses.ours'))
                ->searchable() : null,
            $withHorse ? TextColumn::make('horse.name')
                ->label(__('expenses.fields.horse'))
                ->placeholder('—') : null,
            TextColumn::make('vendor')
                ->label(__('expenses.fields.vendor'))
                ->placeholder('—')
                ->searchable(),
            TextColumn::make('amount')
                ->label(__('expenses.fields.amount'))
                ->formatStateUsing(fn (Expense $record) => Money::formatHtml($record->baisa()))
                ->alignEnd()
                ->sortable(),
            IconColumn::make('receipt')
                ->label(__('expenses.fields.receipt'))
                ->icon(fn (?string $state) => $state ? 'heroicon-o-paper-clip' : null)
                ->url(fn (Expense $record) => $record->receiptUrl(), shouldOpenInNewTab: true),
        ]));
    }
}
