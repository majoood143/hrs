<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Models\Customer;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The people who have signed in with their phone. Read-only: an admin does not edit a customer's identity. */
class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 15;

    public static function getNavigationGroup(): ?string
    {
        return __('admin_navigation.payments');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_customer.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_customer.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_customer.navigation.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phone')
                    ->label(__('admin_customer.columns.phone'))
                    ->formatStateUsing(fn (string $state) => '+'.$state)
                    ->searchable()
                    ->copyable()
                    ->weight('medium'),
                TextColumn::make('name')
                    ->label(__('admin_customer.columns.name'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('admin_customer.columns.email'))
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('orders_count')
                    ->label(__('admin_customer.columns.orders'))
                    ->counts('orders')
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label(__('admin_customer.columns.last_login'))
                    ->dateTime('M d, Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin_customer.columns.created_at'))
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_login_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
        ];
    }
}
