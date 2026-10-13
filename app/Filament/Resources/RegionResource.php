<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegionResource\Pages\CreateRegion;
use App\Filament\Resources\RegionResource\Pages\EditRegion;
use App\Filament\Resources\RegionResource\Pages\ListRegions;
use App\Filament\Resources\RegionResource\Pages\ViewRegion;
use App\Filament\Resources\RegionResource\RelationManagers\CityRelationManager;
use App\Filament\Support\InUseDeleteActions;
use App\Models\Country;
use App\Models\Region;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
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

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    public static function getNavigationGroup(): string|\UnitEnum|null
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
        return (string) static::$model::count();
    }

    /** `ar_name` in the Arabic admin, else `en_name`. */
    private static function nameColumn(): string
    {
        return app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('en_name')
                    ->label(__('admin_locations.fields.en_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('ar_name')
                    ->label(__('admin_locations.fields.ar_name'))
                    ->required()
                    ->maxLength(255)
                    ->extraInputAttributes(['dir' => 'rtl']),
                Select::make('country_id')
                    ->label(__('admin_locations.fields.country'))
                    ->options(fn () => Country::query()->orderBy(static::nameColumn())->pluck(static::nameColumn(), 'id'))
                    ->searchable()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $name = static::nameColumn();

        return $table
            ->columns([
                TextColumn::make('en_name')
                    ->label(__('admin_locations.fields.en_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ar_name')
                    ->label(__('admin_locations.fields.ar_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country.'.$name)
                    ->label(__('admin_locations.fields.country'))
                    ->sortable()
                    ->searchable(['en_name', 'ar_name']),
                TextColumn::make('city_count')
                    ->label(__('admin_locations.fields.cities'))
                    ->counts('city')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin_locations.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('admin_locations.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort($name)
            ->filters([
                SelectFilter::make('country_id')
                    ->label(__('admin_locations.fields.country'))
                    ->relationship('country', $name)
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    InUseDeleteActions::delete('admin_locations'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    InUseDeleteActions::bulk('admin_locations'),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
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
