<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages\ManageExpenses;
use App\Filament\Support\ExpenseFields;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Stable;
use App\Services\Reports\ClosedBooks;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Our own running costs (part of the Income report's "net after expenses"), plus, read-only, what
 * the stables record for themselves. Only while General Settings → Modules → Expenses is on.
 */
class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        // checked here too: super admins skip the policy
        return Expense::enabled() && parent::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin_navigation.payments');
    }

    public static function getModelLabel(): string
    {
        return __('expenses.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('expenses.plural');
    }

    /** Ours, unlocked: a stable's are its own books, a closed month's are settled. */
    public static function changeable(Expense $record): bool
    {
        return $record->stable_id === null && ! ClosedBooks::isClosed($record->spent_on?->toDateString());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(ExpenseFields::form(forStable: false));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'stable']))
            ->defaultSort('spent_on', 'desc')
            ->columns(ExpenseFields::columns(withStable: true, withHorse: false))
            ->filters([
                SelectFilter::make('whose')
                    ->label(__('expenses.filters.whose'))
                    ->options(['ours' => __('expenses.ours'), 'stables' => __('expenses.filters.stables')])
                    ->default('ours')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'ours' => $query->whereNull('stable_id'),
                        'stables' => $query->whereNotNull('stable_id'),
                        default => $query,
                    }),
                SelectFilter::make('stable_id')
                    ->label(__('expenses.fields.stable'))
                    ->options(fn () => Stable::query()->whereHas('expenses')->get()->mapWithKeys(fn (Stable $s) => [$s->getKey() => $s->name])->all())
                    ->searchable(),
                SelectFilter::make('expense_category_id')
                    ->label(__('expenses.fields.category'))
                    ->options(fn () => ExpenseCategory::query()->orderBy('sort')->get()->mapWithKeys(fn (ExpenseCategory $c) => [$c->getKey() => $c->name])->all()),
            ])
            ->recordActions([
                ViewAction::make()->visible(fn (Expense $record) => ! static::changeable($record)),
                EditAction::make()->visible(fn (Expense $record) => static::changeable($record)),
                DeleteAction::make()->visible(fn (Expense $record) => static::changeable($record)),
            ])
            ->emptyStateIcon('heroicon-o-receipt-refund')
            ->emptyStateHeading(__('expenses.empty_heading'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenses::route('/'),
        ];
    }
}
