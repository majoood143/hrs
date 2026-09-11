<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HorseSalePostResource\Pages\ListHorseSalePosts;
use App\Filament\Resources\HorseSalePostResource\Pages\CreateHorseSalePost;
use App\Filament\Resources\HorseSalePostResource\Pages\ViewHorseSalePost;
use App\Filament\Resources\HorseSalePostResource\Pages\EditHorseSalePost;
use App\Filament\Resources\HorseSalePostResource\Pages;
use App\Models\City;
use App\Models\Country;
use App\Models\HorseSalePost;
use App\Models\Region;
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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class HorseSalePostResource extends Resource
{
    protected static ?string $model = HorseSalePost::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return __('admin_navigation.horse_marketplace');
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
        return static::$model::where('status', 'active')->count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Horse Sale Post')
                    ->tabs([
                        Tab::make('Horse Info')
                            ->schema([
                                TextInput::make('en_name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('ar_name')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('type_id')
                                    ->relationship(name: 'type', titleAttribute: 'en_name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('gender_id')
                                    ->relationship(name: 'gender', titleAttribute: 'en_name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('color_id')
                                    ->relationship(name: 'color', titleAttribute: 'en_name')
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('breed')
                                    ->maxLength(255),
                                DatePicker::make('dob'),
                            ])->icon('heroicon-o-information-circle')
                            ->columns(3),
                        Tab::make('Location')
                            ->schema([
                                Select::make('country_id')
                                    ->relationship(name: 'country', titleAttribute: 'en_name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required(),
                                Select::make('region_id')
                                    ->relationship(name: 'region', titleAttribute: 'en_name')
                                    ->options(fn (Get $get): Collection => Region::query()
                                        ->where('country_id', $get('country_id'))
                                        ->pluck('en_name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required(),
                                Select::make('city_id')
                                    ->relationship(name: 'city', titleAttribute: 'en_name')
                                    ->options(fn (Get $get): Collection => City::query()
                                        ->where('region_id', $get('region_id'))
                                        ->pluck('en_name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])->icon('heroicon-o-map-pin')
                            ->columns(3),
                        Tab::make('Listing')
                            ->schema([
                                TextInput::make('price')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix(fn () => \App\Models\SiteSetting::currency()['symbol'])
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'sold' => 'Sold',
                                        'expired' => 'Expired',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->required(),
                                TextInput::make('contact_number')
                                    ->tel()
                                    ->required(),
                                FileUpload::make('cover_photo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('horse-sale/covers')
                                    ->required()
                                    ->columnSpanFull(),
                                Textarea::make('description_en')
                                    ->rows(3),
                                Textarea::make('description_ar')
                                    ->rows(3)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                            ])->icon('heroicon-o-currency-dollar')
                            ->columns(2),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_photo')
                    ->disk('public')
                    ->square(),
                TextColumn::make('en_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type.en_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('gender.en_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->money(fn () => \App\Models\SiteSetting::currency()['code'])
                    ->sortable(),
                TextColumn::make('city.en_name')
                    ->label('City')
                    ->description(fn (HorseSalePost $record): string => $record->country?->en_name ?? '')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'sold' => 'info',
                        'expired' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'sold' => 'Sold',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('type_id')
                    ->label('Type')
                    ->relationship('type', 'en_name'),
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->relationship('country', 'en_name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
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
            'index' => ListHorseSalePosts::route('/'),
            'create' => CreateHorseSalePost::route('/create'),
            'view' => ViewHorseSalePost::route('/{record}'),
            'edit' => EditHorseSalePost::route('/{record}/edit'),
        ];
    }
}
