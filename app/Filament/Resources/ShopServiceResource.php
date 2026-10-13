<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopServiceResource\Pages\CreateShopService;
use App\Filament\Resources\ShopServiceResource\Pages\EditShopService;
use App\Filament\Resources\ShopServiceResource\Pages\ListShopServices;
use App\Filament\Resources\ShopServiceResource\Pages\ViewShopService;
use App\Models\ShopService;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class ShopServiceResource extends Resource
{
    protected static ?string $model = ShopService::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function getModelLabel(): string
    {
        return __('admin_shop_service.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_shop_service.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_shop_service.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('en_name')
                    ->label(__('admin_shop_service.fields.en_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('ar_name')
                    ->label(__('admin_shop_service.fields.ar_name'))
                    ->required()
                    ->maxLength(255)
                    ->extraInputAttributes(['dir' => 'rtl']),
                Select::make('applies_to')
                    ->label(__('admin_shop_service.fields.applies_to'))
                    ->options(fn () => ShopService::appliesToOptions())
                    ->default('all')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('en_name')
                    ->label(__('admin_shop_service.fields.en_name'))
                    ->searchable(),
                TextColumn::make('ar_name')
                    ->label(__('admin_shop_service.fields.ar_name'))
                    ->searchable(),
                TextColumn::make('applies_to')
                    ->label(__('admin_shop_service.fields.applies_to'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ShopService::appliesToOptions()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin_shop_service.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('admin_shop_service.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('applies_to')
                    ->label(__('admin_shop_service.fields.applies_to'))
                    ->options(fn () => ShopService::appliesToOptions()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShopServices::route('/'),
            'create' => CreateShopService::route('/create'),
            'view' => ViewShopService::route('/{record}'),
            'edit' => EditShopService::route('/{record}/edit'),
        ];
    }
}
