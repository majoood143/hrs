<?php

namespace App\Filament\Stable\Resources\StableExpenses;

use App\Filament\Stable\Resources\StableExpenses\Pages\ManageStableExpenses;
use App\Filament\Support\ExpenseFields;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** What the stable spends (feed, vet, rent…), set against its share on the Statement: its own books, not ours. */
class StableExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $tenantRelationshipName = 'expenses';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?int $navigationSort = 21;

    public static function canAccess(): bool
    {
        return Expense::enabled() && parent::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.bookings');
    }

    public static function getModelLabel(): string
    {
        return __('expenses.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('expenses.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(ExpenseFields::form(forStable: true));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'horse']))
            ->defaultSort('spent_on', 'desc')
            ->columns(ExpenseFields::columns(withStable: false, withHorse: true))
            ->filters([
                SelectFilter::make('expense_category_id')
                    ->label(__('expenses.fields.category'))
                    ->options(fn () => ExpenseCategory::options(forStables: true)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateIcon('heroicon-o-receipt-refund')
            ->emptyStateHeading(__('expenses.empty_heading'))
            ->emptyStateDescription(__('expenses.stable_empty_hint'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStableExpenses::route('/'),
        ];
    }
}
