<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HorseSalePostResource\Pages\CreateHorseSalePost;
use App\Filament\Resources\HorseSalePostResource\Pages\EditHorseSalePost;
use App\Filament\Resources\HorseSalePostResource\Pages\ListHorseSalePosts;
use App\Filament\Resources\HorseSalePostResource\Pages\ViewHorseSalePost;
use App\Filament\Support\PendingPosts;
use App\Filament\Support\PlaceFormFields;
use App\Filament\Support\WebsiteLinkActions;
use App\Models\City;
use App\Models\Country;
use App\Models\HorseSalePost;
use App\Models\Region;
use App\Models\SiteSetting;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class HorseSalePostResource extends Resource
{
    protected static ?string $model = HorseSalePost::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.directory');
    }

    public static function getModelLabel(): string
    {
        return __('admin_horse_sale_post.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_horse_sale_post.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_horse_sale_post.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return PendingPosts::navigationBadge(static::$model);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return PendingPosts::navigationBadgeColor(static::$model);
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return PendingPosts::navigationBadgeTooltip(static::$model);
    }

    /** `ar_name` in the Arabic admin, else `en_name` (lookups, locations, posts). */
    private static function nameColumn(): string
    {
        return app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';
    }

    /** A lookup (type, gender, colour, country) by its name in the admin's language. */
    private static function lookup(string $field, string $relationship, string $label): Select
    {
        return Select::make($field)
            ->label($label)
            ->relationship($relationship, static::nameColumn())
            ->searchable()
            ->preload();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Horse Sale Post')
                    ->tabs([
                        Tab::make(__('admin_horse_sale_post.tabs.horse'))
                            ->schema([
                                TextInput::make('en_name')
                                    ->label(__('horses-for-sale.name_en'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('ar_name')
                                    ->label(__('horses-for-sale.name_ar'))
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                                static::lookup('type_id', 'type', __('horses-for-sale.type'))->required(),
                                static::lookup('gender_id', 'gender', __('horses-for-sale.gender'))->required(),
                                static::lookup('color_id', 'color', __('horses-for-sale.color')),
                                TextInput::make('breed')
                                    ->label(__('horses-for-sale.breed'))
                                    ->maxLength(255),
                                DatePicker::make('dob')
                                    ->label(__('horses-for-sale.dob'))
                                    ->maxDate(now()),
                            ])->icon('heroicon-o-information-circle')
                            ->columns(3),
                        Tab::make(__('admin_horse_sale_post.tabs.pedigree'))
                            ->schema([
                                TextInput::make('dam')
                                    ->label(__('admin_horse_sale_post.fields.dam'))
                                    ->maxLength(255),
                                TextInput::make('sire')
                                    ->label(__('admin_horse_sale_post.fields.sire'))
                                    ->maxLength(255),
                                static::lookup('birth_country_id', 'birthCountry', __('horses-for-sale.birth_country')),
                                TextInput::make('passport_number')
                                    ->label(__('horses-for-sale.passport_number'))
                                    ->maxLength(255),
                                // Same rules as the website/app form (HorseSalePostCreator): the file is public, so never HTML/SVG.
                                FileUpload::make('passport_document')
                                    ->label(__('horses-for-sale.passport_document'))
                                    ->helperText(__('admin_horse_sale_post.fields.passport_hint'))
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                    ->maxSize(4096)
                                    ->disk('public')
                                    ->directory('horse-sale/passports')
                                    ->columnSpanFull(),
                            ])->icon('heroicon-o-identification')
                            ->columns(2),
                        Tab::make(__('admin_horse_sale_post.tabs.location'))
                            ->schema([
                                Select::make('country_id')
                                    ->label(__('horses-for-sale.country'))
                                    ->options(fn () => Country::query()->orderBy(static::nameColumn())->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('region_id', null);
                                        $set('city_id', null);
                                    })
                                    ->required(),
                                Select::make('region_id')
                                    ->label(__('horses-for-sale.region'))
                                    ->options(fn (Get $get): Collection => Region::query()
                                        ->where('country_id', $get('country_id'))
                                        ->orderBy(static::nameColumn())
                                        ->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                                    ->required(),
                                Select::make('city_id')
                                    ->label(__('horses-for-sale.city'))
                                    ->options(fn (Get $get): Collection => City::query()
                                        ->where('region_id', $get('region_id'))
                                        ->orderBy(static::nameColumn())
                                        ->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->required(),
                            ])->icon('heroicon-o-map-pin')
                            ->columns(3),
                        Tab::make(__('admin_horse_sale_post.tabs.listing'))
                            ->schema([
                                TextInput::make('price')
                                    ->label(__('horses-for-sale.price'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix(fn () => SiteSetting::currencyHtml())
                                    ->required(),
                                Toggle::make('price_negotiable')
                                    ->label(__('horses-for-sale.price_negotiable'))
                                    ->inline(false),
                                Select::make('status')
                                    ->label(__('horses-for-sale.status'))
                                    ->options(fn () => HorseSalePost::statusOptions())
                                    ->required(),
                                TextInput::make('contact_number')
                                    ->label(__('horses-for-sale.contact_number'))
                                    ->tel()
                                    ->maxLength(32)
                                    ->required(),
                                PlaceFormFields::coverPhoto('horse-sale/covers', __('horses-for-sale.cover_photo'))
                                    ->required()
                                    ->columnSpanFull(),
                                PlaceFormFields::gallery('horse-sale/gallery', __('horses-for-sale.gallery_photos'), 'images')
                                    ->columnSpanFull(),
                                Textarea::make('description_en')
                                    ->label(__('admin_horse_sale_post.fields.description_en'))
                                    ->rows(3),
                                Textarea::make('description_ar')
                                    ->label(__('admin_horse_sale_post.fields.description_ar'))
                                    ->rows(3)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                            ])->icon('heroicon-o-currency-dollar')
                            ->columns(2),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $name = static::nameColumn();

        return $table
            // The type/gender/city columns are eager loaded by Filament; the country under the city is not.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('country'))
            ->columns([
                ImageColumn::make('cover_photo')
                    ->label(__('horses-for-sale.cover_photo'))
                    ->disk('public')
                    ->square(),
                TextColumn::make($name)
                    ->label(__('admin_horse_sale_post.fields.name'))
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable(),
                TextColumn::make('type.'.$name)
                    ->label(__('horses-for-sale.type'))
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable(),
                TextColumn::make('gender.'.$name)
                    ->label(__('horses-for-sale.gender'))
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('horses-for-sale.price'))
                    ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state, 3))
                    ->description(fn (HorseSalePost $record): ?string => $record->price_negotiable ? __('horses-for-sale.price_negotiable') : null)
                    ->sortable(),
                TextColumn::make('city.'.$name)
                    ->label(__('horses-for-sale.city'))
                    ->description(fn (HorseSalePost $record): string => (string) $record->country?->{$name})
                    ->searchable(['en_name', 'ar_name']),
                TextColumn::make('status')
                    ->label(__('horses-for-sale.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => HorseSalePost::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        PendingPosts::STATUS => 'warning',
                        'sold' => 'info',
                        'expired' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin_horse_sale_post.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('horses-for-sale.status'))
                    ->options(fn () => HorseSalePost::statusOptions()),
                SelectFilter::make('type_id')
                    ->label(__('horses-for-sale.type'))
                    ->relationship('type', $name)
                    ->preload(),
                SelectFilter::make('gender_id')
                    ->label(__('horses-for-sale.gender'))
                    ->relationship('gender', $name)
                    ->preload(),
                SelectFilter::make('country_id')
                    ->label(__('horses-for-sale.country'))
                    ->relationship('country', $name)
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                PendingPosts::approveAction(),
                ActionGroup::make([
                    ViewAction::make(),
                    ...WebsiteLinkActions::make(
                        url: fn (HorseSalePost $record): string => route('horses-for-sale.show', $record->id),
                        isPublic: fn (HorseSalePost $record): bool => $record->status === 'active',
                    ),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    PendingPosts::approveBulkAction(),
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHorseSalePosts::route('/'),
            'create' => CreateHorseSalePost::route('/create'),
            'view' => ViewHorseSalePost::route('/{record}'),
            'edit' => EditHorseSalePost::route('/{record}/edit'),
        ];
    }
}
