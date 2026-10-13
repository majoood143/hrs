<?php

namespace App\Filament\Support;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

/**
 * The admin form and table of a typed directory place (clinics, centers) and of its service list,
 * in the admin's language. `$section` is the place's table and public translation file
 * (`clinics`, `centers`): its `types.*` and `days.*` strings label the options.
 */
final class DirectoryPlaceSchema
{
    /** `ar_name` in the Arabic admin, else `en_name`. */
    public static function nameColumn(): string
    {
        return app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';
    }

    /**
     * @param  class-string<Model>  $model  with a `TYPES` constant
     * @return array<string, string>
     */
    public static function typeOptions(string $model, string $section): array
    {
        return collect($model::TYPES)->mapWithKeys(fn ($label, string $type) => [$type => __("{$section}.types.{$type}")])->all();
    }

    /**
     * A service's `applies_to` choices: the place types, plus "all"/"both".
     *
     * @param  class-string<Model>  $serviceModel  with an `APPLIES_TO` constant
     * @return array<string, string>
     */
    public static function appliesToOptions(string $serviceModel, string $section): array
    {
        return collect($serviceModel::APPLIES_TO)
            ->mapWithKeys(fn ($label, string $key) => [$key => in_array($key, ['all', 'both'], true)
                ? __("admin_directory.applies_to.{$key}")
                : __("{$section}.types.{$key}")])
            ->all();
    }

    /** @param  class-string<Model>  $model */
    public static function form(Schema $schema, string $model, string $section, string $defaultType): Schema
    {
        $label = fn (string $key) => __("admin_directory.fields.{$key}");

        return $schema->components([
            Tabs::make('Place')
                ->tabs([
                    Tab::make(__('admin_directory.tabs.info'))
                        ->schema([
                            TextInput::make('en_name')
                                ->label($label('en_name'))
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                // The slug follows the English name only while creating: renaming keeps the page's links.
                                ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                                    if ($operation === 'create') {
                                        $set('slug', Str::slug((string) $state));
                                    }
                                }),
                            TextInput::make('ar_name')
                                ->label($label('ar_name'))
                                ->required()
                                ->maxLength(255)
                                ->extraInputAttributes(['dir' => 'rtl']),
                            TextInput::make('slug')
                                ->label($label('slug'))
                                ->helperText($label('slug_helper'))
                                ->required()
                                ->alphaDash()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            Select::make('type')
                                ->label($label('type'))
                                ->options(fn () => self::typeOptions($model, $section))
                                ->default($defaultType)
                                ->required()
                                ->live(),
                            Toggle::make('is_active')
                                ->label($label('is_active'))
                                ->default(true),
                        ])->icon('heroicon-o-information-circle')
                        ->columns(2),
                    Tab::make(__('admin_directory.tabs.location'))
                        ->schema([
                            Select::make('country_id')
                                ->label($label('country'))
                                ->options(fn () => Country::query()->orderBy(self::nameColumn())->pluck(self::nameColumn(), 'id'))
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('region_id', null);
                                    $set('city_id', null);
                                })
                                ->required(),
                            Select::make('region_id')
                                ->label($label('region'))
                                ->options(fn (Get $get): Collection => Region::query()
                                    ->where('country_id', $get('country_id'))
                                    ->orderBy(self::nameColumn())
                                    ->pluck(self::nameColumn(), 'id'))
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                                ->required(),
                            Select::make('city_id')
                                ->label($label('city'))
                                ->options(fn (Get $get): Collection => City::query()
                                    ->where('region_id', $get('region_id'))
                                    ->orderBy(self::nameColumn())
                                    ->pluck(self::nameColumn(), 'id'))
                                ->searchable()
                                ->required(),
                            TextInput::make('address')
                                ->label($label('address'))
                                ->maxLength(255)
                                ->columnSpanFull(),
                            TextInput::make('map_link')
                                ->label($label('map_link'))
                                ->url()
                                ->rule('url:http,https')
                                ->maxLength(255)
                                ->columnSpanFull(),
                            TextInput::make('phone')
                                ->label($label('phone'))
                                ->tel()
                                ->maxLength(32),
                            TextInput::make('website_url')
                                ->label($label('website_url'))
                                ->url()
                                ->rule('url:http,https')
                                ->maxLength(255),
                            TextInput::make('instagram_url')
                                ->label($label('instagram_url'))
                                ->url()
                                ->rule('url:http,https')
                                ->maxLength(255),
                        ])->icon('heroicon-o-map-pin')
                        ->columns(3),
                    Tab::make(__('admin_directory.tabs.description'))
                        ->schema(PlaceFormFields::descriptions($label('en_description'), $label('ar_description')))
                        ->icon('heroicon-o-document-text'),
                    Tab::make(__('admin_directory.tabs.services'))
                        ->schema([
                            Select::make('services')
                                ->label($label('services'))
                                ->relationship(
                                    name: 'services',
                                    titleAttribute: self::nameColumn(),
                                    modifyQueryUsing: fn (Builder $query, Get $get) => $query->forType($get('type')),
                                )
                                ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->getAttribute('name'))
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])->icon('heroicon-o-clipboard-document-check'),
                    Tab::make(__('admin_directory.tabs.hours'))
                        ->schema(collect($model::DAYS)->map(
                            fn (string $day) => Fieldset::make(__("{$section}.days.{$day}"))
                                ->schema([
                                    Toggle::make("opening_hours.{$day}.closed")
                                        ->label($label('closed'))
                                        ->live(),
                                    TimePicker::make("opening_hours.{$day}.opens_at")
                                        ->label($label('opens_at'))
                                        ->seconds(false)
                                        ->hidden(fn (Get $get) => (bool) $get("opening_hours.{$day}.closed")),
                                    TimePicker::make("opening_hours.{$day}.closes_at")
                                        ->label($label('closes_at'))
                                        ->seconds(false)
                                        ->hidden(fn (Get $get) => (bool) $get("opening_hours.{$day}.closed")),
                                ])
                                ->columns(3)
                        )->all())
                        ->icon('heroicon-o-clock'),
                    Tab::make(__('admin_directory.tabs.photos'))
                        ->schema([
                            PlaceFormFields::coverPhoto("{$section}/covers", $label('cover_photo')),
                            PlaceFormFields::gallery("{$section}/gallery", $label('gallery')),
                        ])->icon('heroicon-o-photo')
                        ->columns(1),
                ])->columnSpanFull(),
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  string  $route  the public page's route name (`clinics.show`)
     */
    public static function table(Table $table, string $model, string $section, string $route): Table
    {
        $name = self::nameColumn();
        $label = fn (string $key) => __("admin_directory.fields.{$key}");

        return $table
            // The city/services columns are eager loaded by Filament; the country under the city is not.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('country'))
            ->columns([
                ImageColumn::make('cover_photo')
                    ->label($label('cover_photo'))
                    ->disk('public')
                    ->square(),
                TextColumn::make($name)
                    ->label($label('name'))
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable(),
                TextColumn::make('type')
                    ->label($label('type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::typeOptions($model, $section)[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('city.'.$name)
                    ->label($label('city'))
                    ->description(fn (Model $record): string => (string) $record->getRelation('country')?->getAttribute($name))
                    ->searchable(['en_name', 'ar_name']),
                TextColumn::make('services.'.$name)
                    ->label($label('services'))
                    ->badge()
                    ->separator(','),
                IconColumn::make('is_active')
                    ->label($label('is_active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label($label('created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort($name)
            ->filters([
                SelectFilter::make('type')
                    ->label($label('type'))
                    ->options(fn () => self::typeOptions($model, $section)),
                SelectFilter::make('country_id')
                    ->label($label('country'))
                    ->relationship('country', $name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('city_id')
                    ->label($label('city'))
                    ->relationship('city', $name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('services')
                    ->label($label('services'))
                    ->relationship('services', $name)
                    ->multiple()
                    ->preload(),
                SelectFilter::make('is_active')
                    ->label($label('is_active'))
                    ->options([1 => __('admin_directory.options.active'), 0 => __('admin_directory.options.inactive')]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    ...WebsiteLinkActions::make(
                        url: fn (Model $record): string => route($route, $record->getAttribute('slug')),
                        isPublic: fn (Model $record): bool => $record->getAttribute('is_active') && filled($record->getAttribute('slug')),
                    ),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    /** @param  class-string<Model>  $serviceModel */
    public static function serviceForm(Schema $schema, string $serviceModel, string $section, string $defaultAppliesTo): Schema
    {
        return $schema->components([
            TextInput::make('en_name')
                ->label(__('admin_directory.fields.en_name'))
                ->required()
                ->maxLength(255),
            TextInput::make('ar_name')
                ->label(__('admin_directory.fields.ar_name'))
                ->required()
                ->maxLength(255)
                ->extraInputAttributes(['dir' => 'rtl']),
            Select::make('applies_to')
                ->label(__('admin_directory.fields.applies_to'))
                ->options(fn () => self::appliesToOptions($serviceModel, $section))
                ->default($defaultAppliesTo)
                ->required(),
        ]);
    }

    /** @param  class-string<Model>  $serviceModel */
    public static function serviceTable(Table $table, string $serviceModel, string $section): Table
    {
        $label = fn (string $key) => __("admin_directory.fields.{$key}");

        return $table
            ->columns([
                TextColumn::make('en_name')
                    ->label($label('en_name'))
                    ->searchable(),
                TextColumn::make('ar_name')
                    ->label($label('ar_name'))
                    ->searchable(),
                TextColumn::make('applies_to')
                    ->label($label('applies_to'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::appliesToOptions($serviceModel, $section)[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label($label('created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label($label('updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('applies_to')
                    ->label($label('applies_to'))
                    ->options(fn () => self::appliesToOptions($serviceModel, $section)),
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
}
