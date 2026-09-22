<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StableResource\Pages\CreateStable;
use App\Filament\Resources\StableResource\Pages\EditStable;
use App\Filament\Resources\StableResource\Pages\ListStables;
use App\Filament\Resources\StableResource\Pages\ViewStable;
use App\Models\City;
use App\Models\Region;
use App\Models\Stable;
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
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class StableResource extends Resource
{
    protected static ?string $model = Stable::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.directory');
    }

    public static function getModelLabel(): string
    {
        return __('admin_stable.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_stable.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_stable.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    protected static function dayLabels(): array
    {
        return [
            'mon' => 'Monday',
            'tue' => 'Tuesday',
            'wed' => 'Wednesday',
            'thu' => 'Thursday',
            'fri' => 'Friday',
            'sat' => 'Saturday',
            'sun' => 'Sunday',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Stable')
                    ->tabs([
                        Tab::make('Info')
                            ->schema([
                                TextInput::make('en_name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),
                                TextInput::make('ar_name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('slug')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                Toggle::make('is_active')
                                    ->default(true),
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
                                TextInput::make('address')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('map_link')
                                    ->label('Map Link')
                                    ->url()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ])->icon('heroicon-o-map-pin')
                            ->columns(3),
                        Tab::make('Description')
                            ->schema([
                                Textarea::make('en_description')
                                    ->label('Description (English)')
                                    ->rows(4),
                                Textarea::make('ar_description')
                                    ->label('Description (Arabic)')
                                    ->rows(4)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                            ])->icon('heroicon-o-document-text')
                            ->columns(2),
                        Tab::make('Services')
                            ->schema([
                                Select::make('services')
                                    ->relationship('services', 'en_name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])->icon('heroicon-o-wrench-screwdriver'),
                        Tab::make('Opening Hours')
                            ->schema(collect(static::dayLabels())->map(
                                fn (string $label, string $day) => Fieldset::make($label)
                                    ->schema([
                                        Toggle::make("opening_hours.{$day}.closed")
                                            ->label('Closed')
                                            ->live(),
                                        TimePicker::make("opening_hours.{$day}.opens_at")
                                            ->label('Opens at')
                                            ->seconds(false)
                                            ->hidden(fn (Get $get) => $get("opening_hours.{$day}.closed")),
                                        TimePicker::make("opening_hours.{$day}.closes_at")
                                            ->label('Closes at')
                                            ->seconds(false)
                                            ->hidden(fn (Get $get) => $get("opening_hours.{$day}.closed")),
                                    ])
                                    ->columns(3)
                            )->values()->all())
                            ->icon('heroicon-o-clock'),
                        Tab::make('Photos')
                            ->schema([
                                FileUpload::make('cover_photo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('stables/covers'),
                                FileUpload::make('gallery')
                                    ->image()
                                    ->multiple()
                                    ->disk('public')
                                    ->directory('stables/gallery'),
                            ])->icon('heroicon-o-photo')
                            ->columns(1),
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
                TextColumn::make('city.en_name')
                    ->label('City')
                    ->description(fn (Stable $record): string => $record->country?->en_name ?? '')
                    ->searchable(),
                TextColumn::make('services.en_name')
                    ->label('Services')
                    ->badge()
                    ->separator(','),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('en_name')
            ->filters([
                SelectFilter::make('city_id')
                    ->label('City')
                    ->relationship('city', 'en_name'),
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->relationship('country', 'en_name'),
                SelectFilter::make('services')
                    ->relationship('services', 'en_name'),
                SelectFilter::make('is_active')
                    ->options([1 => 'Active', 0 => 'Inactive']),
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
            'index' => ListStables::route('/'),
            'create' => CreateStable::route('/create'),
            'view' => ViewStable::route('/{record}'),
            'edit' => EditStable::route('/{record}/edit'),
        ];
    }
}
