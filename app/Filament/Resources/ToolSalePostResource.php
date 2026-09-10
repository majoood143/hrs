<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToolSalePostResource\Pages\ListToolSalePosts;
use App\Filament\Resources\ToolSalePostResource\Pages\CreateToolSalePost;
use App\Filament\Resources\ToolSalePostResource\Pages\ViewToolSalePost;
use App\Filament\Resources\ToolSalePostResource\Pages\EditToolSalePost;
use App\Filament\Resources\ToolSalePostResource\Pages;
use App\Models\City;
use App\Models\Region;
use App\Models\ToolSalePost;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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

class ToolSalePostResource extends Resource
{
    protected static ?string $model = ToolSalePost::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-wrench';

    public static string | \UnitEnum | null $navigationGroup = 'Tool Marketplace';

    protected static ?string $modelLabel = 'Tool for Sale';

    public static function getNavigationBadge(): ?string
    {
        return static::$model::where('status', 'active')->count();
    }

    protected static function categoryOptions(): array
    {
        return [
            'saddlery_tack' => 'Saddlery & Tack',
            'grooming' => 'Grooming',
            'farrier_tools' => 'Farrier Tools',
            'feeding_stable' => 'Feeding & Stable',
            'health_first_aid' => 'Health & First Aid',
            'transport_equipment' => 'Transport Equipment',
            'other' => 'Other',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tool Sale Post')
                    ->tabs([
                        Tab::make('Tool Info')
                            ->schema([
                                TextInput::make('en_name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('ar_name')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('category')
                                    ->options(static::categoryOptions())
                                    ->required(),
                                Select::make('condition')
                                    ->options([
                                        'new' => 'New',
                                        'used' => 'Used',
                                    ])
                                    ->required(),
                                TextInput::make('brand')
                                    ->maxLength(255),
                            ])->icon('heroicon-o-information-circle')
                            ->columns(2),
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
                                    ->directory('tools-for-sale/covers')
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
                TextColumn::make('category')
                    ->formatStateUsing(fn (string $state): string => static::categoryOptions()[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('condition')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'new' ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('price')
                    ->money(fn () => \App\Models\SiteSetting::currency()['code'])
                    ->sortable(),
                TextColumn::make('city.en_name')
                    ->label('City')
                    ->description(fn (ToolSalePost $record): string => $record->country?->en_name ?? '')
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
                SelectFilter::make('category')
                    ->options(static::categoryOptions()),
                SelectFilter::make('condition')
                    ->options([
                        'new' => 'New',
                        'used' => 'Used',
                    ]),
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
            'index' => ListToolSalePosts::route('/'),
            'create' => CreateToolSalePost::route('/create'),
            'view' => ViewToolSalePost::route('/{record}'),
            'edit' => EditToolSalePost::route('/{record}/edit'),
        ];
    }
}
