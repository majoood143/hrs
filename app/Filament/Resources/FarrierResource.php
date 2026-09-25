<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FarrierResource\Pages\CreateFarrier;
use App\Filament\Resources\FarrierResource\Pages\EditFarrier;
use App\Filament\Resources\FarrierResource\Pages\ListFarriers;
use App\Filament\Resources\FarrierResource\Pages\ViewFarrier;
use App\Models\City;
use App\Models\Farrier;
use App\Models\Region;
use App\Models\SiteSetting;
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

class FarrierResource extends Resource
{
    protected static ?string $model = Farrier::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.directory');
    }

    public static function getModelLabel(): string
    {
        return __('admin_farrier.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_farrier.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_farrier.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::where('status', 'active')->count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Farrier')
                    ->tabs([
                        Tab::make('Farrier Info')
                            ->schema([
                                TextInput::make('en_name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('ar_name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('specialty')
                                    ->maxLength(255),
                                TextInput::make('years_experience')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(80),
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
                                    ->prefix(fn () => SiteSetting::currencyHtml())
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->required(),
                                TextInput::make('contact_number')
                                    ->tel()
                                    ->required(),
                                FileUpload::make('cover_photo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('farriers/covers')
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
                TextColumn::make('specialty')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('years_experience')
                    ->label('Experience')
                    ->suffix(' yrs')
                    ->sortable(),
                TextColumn::make('price')
                    ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state, 3))
                    ->sortable(),
                TextColumn::make('city.en_name')
                    ->label('City')
                    ->description(fn (Farrier $record): string => $record->country?->en_name ?? '')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
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
                        'inactive' => 'Inactive',
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
            'index' => ListFarriers::route('/'),
            'create' => CreateFarrier::route('/create'),
            'view' => ViewFarrier::route('/{record}'),
            'edit' => EditFarrier::route('/{record}/edit'),
        ];
    }
}
