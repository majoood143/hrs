<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages\CreateCity;
use App\Filament\Resources\CityResource\Pages\EditCity;
use App\Filament\Resources\CityResource\Pages\ListCities;
use App\Filament\Resources\CityResource\Pages\ViewCity;
use App\Filament\Resources\CityResource\RelationManagers\HorseRelationManager;
use App\Filament\Support\InUseDeleteActions;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    public static function getNavigationGroup(): string|\UnitEnum|null
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
                // Only narrows the regions offered: a city stores its region, not its country.
                Select::make('country_id')
                    ->label(__('admin_locations.fields.country'))
                    ->options(fn () => Country::query()->orderBy(static::nameColumn())->pluck(static::nameColumn(), 'id'))
                    ->searchable()
                    ->live()
                    ->afterStateHydrated(function (Select $component, ?City $record): void {
                        if ($record) {
                            $component->state($record->region?->country_id);
                        }
                    })
                    ->afterStateUpdated(fn (Set $set) => $set('region_id', null))
                    ->dehydrated(false)
                    ->required(),
                Select::make('region_id')
                    ->label(__('admin_locations.fields.region'))
                    ->options(fn (Get $get) => Region::query()
                        ->where('country_id', $get('country_id'))
                        ->orderBy(static::nameColumn())
                        ->pluck(static::nameColumn(), 'id'))
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
                TextColumn::make('region.'.$name)
                    ->label(__('admin_locations.fields.region'))
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable(),
                TextColumn::make('region.country.'.$name)
                    ->label(__('admin_locations.fields.country'))
                    ->searchable(['en_name', 'ar_name'])
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
                    ->options(fn () => Country::query()->orderBy($name)->pluck($name, 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $countryId) => $query->whereHas(
                            'region',
                            fn (Builder $query) => $query->where('country_id', $countryId),
                        ),
                    )),
                SelectFilter::make('region_id')
                    ->label(__('admin_locations.fields.region'))
                    ->relationship('region', $name)
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
