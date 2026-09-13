<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CityResource\Pages\ListCities;
use App\Filament\Resources\CityResource\Pages\CreateCity;
use App\Filament\Resources\CityResource\Pages\ViewCity;
use App\Filament\Resources\CityResource\Pages\EditCity;
use App\Filament\Resources\CityResource\Pages;
use App\Filament\Resources\CityResource\RelationManagers;
use App\Filament\Resources\CityResource\RelationManagers\HorseRelationManager;
use App\Models\City;
use App\Models\Country;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Carbon\Carbon;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return __('admin_navigation.countries');
    }

    public static function getModelLabel(): string
    {
        return __('admin_city.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_city.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_city.navigation.plural');
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
                    ->options(fn () => Country::pluck('en_name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateHydrated(function (Select $component, $state, $record) {
                        if ($record) {
                            $component->state($record->region?->country_id);
                        }
                    })
                    ->afterStateUpdated(fn (Set $set) => $set('region_id', null))
                    ->dehydrated(false)
                    ->required(),
                Select::make('region_id')
                    ->relationship(
                        name: 'region',
                        titleAttribute: 'en_name',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query->when(
                            $get('country_id'),
                            fn (Builder $query, $countryId) => $query->where('country_id', $countryId),
                        ),
                    )
                    ->searchable()
                    ->preload()
                    ->live()
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
                TextColumn::make('region.en_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('region.country.en_name')
                    ->label('Country')
                    ->searchable()
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
                SelectFilter::make('region_id')
                    ->relationship('region', 'en_name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->options(fn () => Country::pluck('en_name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $countryId) => $query->whereHas(
                            'region',
                            fn (Builder $query) => $query->where('country_id', $countryId),
                        ),
                    )),
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
            HorseRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCities::route('/'),
            'create' => CreateCity::route('/create'),
            'view' => ViewCity::route('/{record}'),
            'edit' => EditCity::route('/{record}/edit'),
        ];
    }
}
