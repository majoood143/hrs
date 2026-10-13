<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopResource\Pages\CreateShop;
use App\Filament\Resources\ShopResource\Pages\EditShop;
use App\Filament\Resources\ShopResource\Pages\ListShops;
use App\Filament\Resources\ShopResource\Pages\ViewShop;
use App\Filament\Support\PlaceFormFields;
use App\Filament\Support\WebsiteLinkActions;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Shop;
use App\Models\ShopService;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.directory');
    }

    public static function getModelLabel(): string
    {
        return __('admin_shop.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_shop.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_shop.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    /** `ar_name` in the Arabic admin, else `en_name` (countries, regions, cities, categories, shops). */
    private static function nameColumn(): string
    {
        return app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Shop')
                    ->tabs([
                        Tab::make(__('admin_shop.tabs.info'))
                            ->schema([
                                TextInput::make('en_name')
                                    ->label(__('admin_shop.fields.en_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    // The slug follows the English name only while creating: renaming a live shop keeps its links.
                                    ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                                        if ($operation === 'create') {
                                            $set('slug', Str::slug((string) $state));
                                        }
                                    }),
                                TextInput::make('ar_name')
                                    ->label(__('admin_shop.fields.ar_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                                TextInput::make('slug')
                                    ->label(__('admin_shop.fields.slug'))
                                    ->helperText(__('admin_shop.fields.slug_helper'))
                                    ->required()
                                    ->alphaDash()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                Select::make('type')
                                    ->label(__('admin_shop.fields.type'))
                                    ->options(fn () => Shop::typeOptions())
                                    ->default('tack')
                                    ->required()
                                    ->live(),
                                Toggle::make('is_active')
                                    ->label(__('admin_shop.fields.is_active'))
                                    ->default(true),
                                Toggle::make('is_online')
                                    ->label(__('admin_shop.fields.is_online'))
                                    ->helperText(__('admin_shop.fields.is_online_helper'))
                                    ->live()
                                    ->default(false),
                            ])->icon('heroicon-o-information-circle')
                            ->columns(2),
                        Tab::make(__('admin_shop.tabs.location'))
                            ->schema([
                                Select::make('country_id')
                                    ->label(__('admin_shop.fields.country'))
                                    ->options(fn () => Country::query()->orderBy(static::nameColumn())->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('region_id', null);
                                        $set('city_id', null);
                                    })
                                    ->required(),
                                Select::make('region_id')
                                    ->label(__('admin_shop.fields.region'))
                                    ->options(fn (Get $get): Collection => Region::query()
                                        ->where('country_id', $get('country_id'))
                                        ->orderBy(static::nameColumn())
                                        ->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                                    ->required(),
                                Select::make('city_id')
                                    ->label(__('admin_shop.fields.city'))
                                    ->options(fn (Get $get): Collection => City::query()
                                        ->where('region_id', $get('region_id'))
                                        ->orderBy(static::nameColumn())
                                        ->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->required(),
                                TextInput::make('address')
                                    ->label(__('admin_shop.fields.address'))
                                    ->maxLength(255)
                                    ->hidden(fn (Get $get) => $get('is_online'))
                                    ->columnSpanFull(),
                                TextInput::make('map_link')
                                    ->label(__('admin_shop.fields.map_link'))
                                    ->url()
                                    ->rule('url:http,https')
                                    ->maxLength(255)
                                    ->hidden(fn (Get $get) => $get('is_online'))
                                    ->columnSpanFull(),
                                Select::make('delivery_scope')
                                    ->label(__('admin_shop.fields.delivery_scope'))
                                    ->options(fn () => Shop::deliveryScopeOptions())
                                    ->visible(fn (Get $get) => $get('is_online'))
                                    ->required(fn (Get $get) => $get('is_online'))
                                    ->columnSpanFull(),
                                TextInput::make('phone')
                                    ->label(__('admin_shop.fields.phone'))
                                    ->tel()
                                    ->maxLength(32),
                                TextInput::make('website_url')
                                    ->label(__('admin_shop.fields.website_url'))
                                    ->url()
                                    ->rule('url:http,https')
                                    ->maxLength(255),
                                TextInput::make('instagram_url')
                                    ->label(__('admin_shop.fields.instagram_url'))
                                    ->url()
                                    ->rule('url:http,https')
                                    ->maxLength(255),
                            ])->icon('heroicon-o-map-pin')
                            ->columns(3),
                        Tab::make(__('admin_shop.tabs.description'))
                            ->schema(PlaceFormFields::descriptions(__('admin_shop.fields.en_description'), __('admin_shop.fields.ar_description')))
                            ->icon('heroicon-o-document-text'),
                        Tab::make(__('admin_shop.tabs.services'))
                            ->schema([
                                Select::make('services')
                                    ->label(__('admin_shop.fields.services'))
                                    ->relationship(
                                        name: 'services',
                                        titleAttribute: static::nameColumn(),
                                        modifyQueryUsing: fn (Builder $query, Get $get) => $query->forType($get('type')),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (ShopService $record) => $record->name)
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])->icon('heroicon-o-clipboard-document-check'),
                        Tab::make(__('admin_shop.tabs.payment_options'))
                            ->schema([
                                CheckboxList::make('payment_options')
                                    ->label(__('admin_shop.fields.payment_options'))
                                    ->options(fn () => Shop::paymentOptionOptions())
                                    ->columns(2),
                            ])->icon('heroicon-o-credit-card'),
                        Tab::make(__('admin_shop.tabs.hours'))
                            ->schema(collect(Shop::DAYS)->map(
                                fn (string $day) => Fieldset::make(__('shops.days.'.$day))
                                    ->schema([
                                        Toggle::make("opening_hours.{$day}.closed")
                                            ->label(__('admin_shop.fields.closed'))
                                            ->live(),
                                        TimePicker::make("opening_hours.{$day}.opens_at")
                                            ->label(__('admin_shop.fields.opens_at'))
                                            ->seconds(false)
                                            ->hidden(fn (Get $get) => (bool) $get("opening_hours.{$day}.closed")),
                                        TimePicker::make("opening_hours.{$day}.closes_at")
                                            ->label(__('admin_shop.fields.closes_at'))
                                            ->seconds(false)
                                            ->hidden(fn (Get $get) => (bool) $get("opening_hours.{$day}.closed")),
                                    ])
                                    ->columns(3)
                            )->all())
                            ->icon('heroicon-o-clock'),
                        Tab::make(__('admin_shop.tabs.photos'))
                            ->schema([
                                PlaceFormFields::coverPhoto('shops/covers', __('admin_shop.fields.cover_photo')),
                                PlaceFormFields::gallery('shops/gallery', __('admin_shop.fields.gallery')),
                            ])->icon('heroicon-o-photo')
                            ->columns(1),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $name = static::nameColumn();

        return $table
            // The city/services columns are eager loaded by Filament; the country under the city is not.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('country'))
            ->columns([
                ImageColumn::make('cover_photo')
                    ->label(__('admin_shop.fields.cover_photo'))
                    ->disk('public')
                    ->square(),
                TextColumn::make($name)
                    ->label(__('admin_shop.fields.name'))
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('admin_shop.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Shop::typeOptions()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('city.'.$name)
                    ->label(__('admin_shop.fields.city'))
                    ->description(fn (Shop $record): string => (string) $record->country?->{$name})
                    ->searchable(['en_name', 'ar_name']),
                TextColumn::make('services.'.$name)
                    ->label(__('admin_shop.fields.services'))
                    ->badge()
                    ->separator(','),
                IconColumn::make('is_online')
                    ->label(__('admin_shop.fields.online'))
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('admin_shop.fields.is_active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin_shop.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort($name)
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin_shop.fields.type'))
                    ->options(fn () => Shop::typeOptions()),
                SelectFilter::make('country_id')
                    ->label(__('admin_shop.fields.country'))
                    ->relationship('country', $name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('city_id')
                    ->label(__('admin_shop.fields.city'))
                    ->relationship('city', $name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('services')
                    ->label(__('admin_shop.fields.services'))
                    ->relationship('services', $name)
                    ->multiple()
                    ->preload(),
                SelectFilter::make('is_active')
                    ->label(__('admin_shop.fields.is_active'))
                    ->options([1 => __('admin_shop.options.active'), 0 => __('admin_shop.options.inactive')]),
                SelectFilter::make('is_online')
                    ->label(__('admin_shop.fields.mode'))
                    ->options([1 => __('admin_shop.options.online'), 0 => __('admin_shop.options.physical')]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    ...WebsiteLinkActions::make(
                        url: fn (Shop $record): string => route('shops.show', $record->slug),
                        isPublic: fn (Shop $record): bool => $record->is_active && filled($record->slug),
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

    public static function getPages(): array
    {
        return [
            'index' => ListShops::route('/'),
            'create' => CreateShop::route('/create'),
            'view' => ViewShop::route('/{record}'),
            'edit' => EditShop::route('/{record}/edit'),
        ];
    }
}
