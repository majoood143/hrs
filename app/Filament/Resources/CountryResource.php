<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CountryResource\Pages\ListCountries;
use App\Filament\Resources\CountryResource\Pages\CreateCountry;
use App\Filament\Resources\CountryResource\Pages\ViewCountry;
use App\Filament\Resources\CountryResource\Pages\EditCountry;
use App\Filament\Resources\CountryResource\Pages;
use App\Filament\Resources\CountryResource\RelationManagers;
use App\Filament\Resources\CountryResource\RelationManagers\RegionRelationManager;
use App\Models\Country;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-globe-asia-australia';

    public static function getNavigationGroup(): string | \UnitEnum | null
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
                TextInput::make('country_code')
                    ->label(__('admin_country.fields.country_code'))
                    ->helperText('2-letter ISO code, e.g. OM')
                    ->live(onBlur: true)
                    ->length(2)
                    ->alpha()
                    ->formatStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state)
                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state),
                TextInput::make('phone_code')
                    ->label(__('admin_country.fields.phone_code'))
                    ->helperText('Digits only, e.g. 968')
                    ->prefix('+')
                    ->maxLength(5)
                    ->numeric(),
                Placeholder::make('flag_preview')
                    ->label(__('admin_country.fields.flag'))
                    ->content(function (Get $get) {
                        $code = strtolower((string) $get('country_code'));

                        if ($code === '' || strlen($code) !== 2) {
                            return '—';
                        }

                        return new \Illuminate\Support\HtmlString(
                            '<img src="' . asset("flags/{$code}.svg") . '" style="width:32px;height:24px;object-fit:cover;border:1px solid #e5e7eb;border-radius:2px" onerror="this.style.display=\'none\'">'
                        );
                    }),
                TextInput::make('currency_code')
                    ->label(__('admin_country.fields.currency_code'))
                    ->helperText('3-letter ISO 4217 code, e.g. OMR')
                    ->length(3)
                    ->alpha()
                    ->formatStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state)
                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state),
                TextInput::make('nationality_en')
                    ->label(__('admin_country.fields.nationality_en'))
                    ->maxLength(255),
                TextInput::make('nationality_ar')
                    ->label(__('admin_country.fields.nationality_ar'))
                    ->maxLength(255)
                    ->extraInputAttributes(['dir' => 'rtl']),
                TextInput::make('order')
                    ->label(__('admin_country.fields.order'))
                    ->numeric()
                    ->default(0),
                Toggle::make('is_public')
                    ->label('Visible in public transfer form')
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ar_name')
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
                    ->counts('region')
                    ->badge(),
                ToggleColumn::make('is_public')
                    ->label('Public'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order')
            ->filters([
                TernaryFilter::make('is_public')
                    ->label('Public visibility'),
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
