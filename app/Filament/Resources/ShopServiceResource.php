<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ShopServiceResource\Pages\ListShopServices;
use App\Filament\Resources\ShopServiceResource\Pages\CreateShopService;
use App\Filament\Resources\ShopServiceResource\Pages\ViewShopService;
use App\Filament\Resources\ShopServiceResource\Pages\EditShopService;
use App\Filament\Resources\ShopServiceResource\Pages;
use App\Models\ShopService;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class ShopServiceResource extends Resource
{
    protected static ?string $model = ShopService::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';

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
                    ->required()
                    ->maxLength(255),
                TextInput::make('ar_name')
                    ->required()
                    ->maxLength(255),
                Select::make('applies_to')
                    ->label('Applies To')
                    ->options(ShopService::APPLIES_TO)
                    ->default('all')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('en_name')
                    ->searchable(),
                TextColumn::make('ar_name')
                    ->searchable(),
                TextColumn::make('applies_to')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ShopService::APPLIES_TO[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ])
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
