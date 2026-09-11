<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\RegionResource\RelationManagers\CityRelationManager;
use App\Filament\Resources\RegionResource\Pages\ListRegions;
use App\Filament\Resources\RegionResource\Pages\CreateRegion;
use App\Filament\Resources\RegionResource\Pages\ViewRegion;
use App\Filament\Resources\RegionResource\Pages\EditRegion;
use App\Filament\Resources\RegionResource\Pages;
use App\Filament\Resources\RegionResource\RelationManagers;
use App\Models\Region;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return __('admin_navigation.countries');
    }

    public static function getModelLabel(): string
    {
        return __('admin_region.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_region.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_region.navigation.plural');
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
                Select::make('country_id')
                    ->relationship(name:'country',titleAttribute:'en_name')
                    ->searchable()
                    ->preload()
                    ->required(),
                    
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('en_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ar_name')
                    ->searchable()
                    ->sortable(),
                     TextColumn::make('country.en_name')
                     ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('city_count')
                ->counts('city')
                ->badge(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
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
            CityRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegions::route('/'),
            'create' => CreateRegion::route('/create'),
            'view' => ViewRegion::route('/{record}'),
            'edit' => EditRegion::route('/{record}/edit'),
        ];
    }
}
