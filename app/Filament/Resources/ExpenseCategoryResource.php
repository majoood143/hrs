<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseCategoryResource\Pages\ManageExpenseCategories;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** The one list of expense kinds, for us, the stables or both. One in use can only be switched off. */
class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        return Expense::enabled() && parent::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin_navigation.payments');
    }

    public static function getModelLabel(): string
    {
        return __('expenses.categories.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('expenses.categories.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('en_name')->label(__('expenses.categories.en_name'))->required()->maxLength(255),
            TextInput::make('ar_name')->label(__('expenses.categories.ar_name'))->required()->maxLength(255),
            Select::make('scope')
                ->label(__('expenses.categories.scope'))
                ->options(collect(ExpenseCategory::SCOPES)->mapWithKeys(fn (string $s) => [$s => __('expenses.categories.scopes.'.$s)])->all())
                ->default('both')
                ->selectablePlaceholder(false)
                ->required(),
            TextInput::make('sort')->label(__('expenses.categories.sort'))->numeric()->minValue(0)->default(0),
            Toggle::make('is_active')->label(__('expenses.categories.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('en_name')->label(__('expenses.categories.en_name'))->searchable(),
                TextColumn::make('ar_name')->label(__('expenses.categories.ar_name'))->searchable(),
                TextColumn::make('scope')->label(__('expenses.categories.scope'))->badge()->formatStateUsing(fn (string $state) => __('expenses.categories.scopes.'.$state)),
                TextColumn::make('expenses_count')->label(__('expenses.plural'))->counts('expenses'),
                IconColumn::make('is_active')->label(__('expenses.categories.is_active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (ExpenseCategory $record) => ! $record->expenses()->withTrashed()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenseCategories::route('/'),
        ];
    }
}
