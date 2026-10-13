<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CountryResource\Pages\CreateCountry;
use App\Filament\Resources\CountryResource\Pages\EditCountry;
use App\Filament\Resources\CountryResource\Pages\ListCountries;
use App\Filament\Resources\CountryResource\Pages\ViewCountry;
use App\Filament\Resources\CountryResource\RelationManagers\RegionRelationManager;
use App\Filament\Support\InUseDeleteActions;
use App\Models\Country;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-asia-australia';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.countries');
    }

    public static function getModelLabel(): string
    {
        return __('admin_country.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_country.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_country.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        $upper = fn (?string $state) => $state ? strtoupper($state) : $state;

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
                TextInput::make('country_code')
                    ->label(__('admin_country.fields.country_code'))
                    ->helperText(__('admin_country.fields.country_code_helper'))
                    ->live(onBlur: true)
                    ->length(2)
                    ->alpha()
                    ->unique(ignoreRecord: true)
                    ->formatStateUsing($upper)
                    ->dehydrateStateUsing($upper),
                TextInput::make('phone_code')
                    ->label(__('admin_country.fields.phone_code'))
                    ->helperText(__('admin_country.fields.phone_code_helper'))
                    ->prefix('+')
                    ->maxLength(5)
                    ->regex('/^\+?\d{1,5}$/'),
                Placeholder::make('flag_preview')
                    ->label(__('admin_country.fields.flag'))
                    ->content(function (Get $get) {
                        $code = strtolower((string) $get('country_code'));

                        // Only two letters ever reach the URL (the field is not saved yet when previewed).
                        if (! preg_match('/^[a-z]{2}$/', $code)) {
                            return '—';
                        }

                        return new HtmlString(
                            '<img src="'.e(asset("flags/{$code}.svg")).'" alt="" style="width:32px;height:24px;object-fit:cover;border:1px solid #e5e7eb;border-radius:2px" onerror="this.style.display=\'none\'">'
                        );
                    }),
                TextInput::make('currency_code')
                    ->label(__('admin_country.fields.currency_code'))
                    ->helperText(__('admin_country.fields.currency_code_helper'))
                    ->length(3)
                    ->alpha()
                    ->formatStateUsing($upper)
                    ->dehydrateStateUsing($upper),
                TextInput::make('nationality_en')
                    ->label(__('admin_country.fields.nationality_en'))
                    ->maxLength(255),
                TextInput::make('nationality_ar')
                    ->label(__('admin_country.fields.nationality_ar'))
                    ->maxLength(255)
                    ->extraInputAttributes(['dir' => 'rtl']),
                TextInput::make('order')
                    ->label(__('admin_country.fields.order'))
                    ->integer()
                    ->default(0),
                Toggle::make('is_public')
                    ->label(__('admin_country.fields.is_public'))
                    ->helperText(__('admin_country.fields.is_public_helper'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('flag_url')
                    ->label(__('admin_country.fields.flag'))
                    ->width(32)
                    ->height(24),
                TextColumn::make('en_name')
                    ->label(__('admin_locations.fields.en_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ar_name')
                    ->label(__('admin_locations.fields.ar_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country_code')
                    ->label(__('admin_country.fields.country_code'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone_code_formatted')
                    ->label(__('admin_country.fields.phone_code')),
                TextColumn::make('currency_code')
                    ->label(__('admin_country.fields.currency_code'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nationality_en')
                    ->label(__('admin_country.fields.nationality_en'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nationality_ar')
                    ->label(__('admin_country.fields.nationality_ar'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('order')
                    ->label(__('admin_country.fields.order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('region_count')
                    ->label(__('admin_locations.fields.regions'))
                    ->counts('region')
                    ->badge()
                    ->sortable(),
                ToggleColumn::make('is_public')
                    ->label(__('admin_country.fields.public')),
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
            ->defaultSort('order')
            ->filters([
                TernaryFilter::make('is_public')
                    ->label(__('admin_country.fields.is_public')),
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
            RegionRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCountries::route('/'),
            'create' => CreateCountry::route('/create'),
            'view' => ViewCountry::route('/{record}'),
            'edit' => EditCountry::route('/{record}/edit'),
        ];
    }
}
