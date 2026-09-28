<?php

namespace App\Filament\Stable\Resources\StablePackagePurchases;

use App\Filament\Stable\Resources\StablePackagePurchases\Pages\ListStablePackagePurchases;
use App\Models\StablePackagePurchase;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Who bought a package, and how many of its sessions are left. */
class StablePackagePurchaseResource extends Resource
{
    protected static ?string $model = StablePackagePurchase::class;

    protected static ?string $tenantRelationshipName = 'packagePurchases';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 15;

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.bookings');
    }

    public static function getModelLabel(): string
    {
        return __('stable_packages.purchases.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stable_packages.purchases.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [StablePackagePurchase::ACTIVE, StablePackagePurchase::PENDING])->with(['package', 'order', 'offering']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('reference')->label(__('stable_packages.fields.reference'))->searchable()->fontFamily('mono'),
                TextColumn::make('name')
                    ->label(__('stable_packages.fields.package'))
                    ->formatStateUsing(fn (StablePackagePurchase $record) => $record->package?->name ?? $record->name)
                    ->description(fn (StablePackagePurchase $record) => $record->offering?->name),
                TextColumn::make('order.customer_name')
                    ->label(__('stable_bookings.fields.customer'))
                    ->description(fn (StablePackagePurchase $record) => $record->order?->customer_phone),
                TextColumn::make('left')
                    ->label(__('stable_packages.purchases.left'))
                    ->state(fn (StablePackagePurchase $record) => $record->sessionsLeft().' / '.$record->sessions)
                    ->icon('heroicon-o-ticket'),
                TextColumn::make('expires_at')->label(__('stable_packages.fields.expires'))->date()->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('stable_panel.bookings.status'))
                    ->state(fn (StablePackagePurchase $record) => $record->statusKey())
                    ->formatStateUsing(fn (string $state) => __('stable_packages.status.'.$state))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('stable_package_id')->label(__('stable_packages.fields.package'))->relationship('package', 'en_name'),
            ])
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->emptyStateHeading(__('stable_packages.purchases.empty'));
    }

    public static function getPages(): array
    {
        return ['index' => ListStablePackagePurchases::route('/')];
    }
}
