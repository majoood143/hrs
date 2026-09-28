<?php

namespace App\Filament\Stable\Resources\StableOfferings;

use App\Enums\StableOfferingType;
use App\Filament\Stable\Resources\StableOfferings\Pages\CreateStableOffering;
use App\Filament\Stable\Resources\StableOfferings\Pages\EditStableOffering;
use App\Filament\Stable\Resources\StableOfferings\Pages\ListStableOfferings;
use App\Models\SiteSetting;
use App\Models\StableOffering;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** What the stable sells by the slot. Riding training for now. */
class StableOfferingResource extends Resource
{
    protected static ?string $model = StableOffering::class;

    protected static ?string $tenantRelationshipName = 'offerings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.setup');
    }

    public static function getModelLabel(): string
    {
        return __('stable_panel.offerings.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stable_panel.offerings.plural');
    }

    /** "Until it starts", 30 minutes … 2 days. */
    public static function cutoffOptions(): array
    {
        return collect([0, 30, 60, 120, 180, 360, 720, 1440, 2880])
            ->mapWithKeys(fn (int $minutes) => [$minutes => match (true) {
                $minutes === 0 => __('stable_panel.offerings.cutoff_none'),
                $minutes < 60 => trans_choice('stable_panel.units.minutes', $minutes, ['count' => $minutes]),
                default => trans_choice('stable_panel.units.hours', intdiv($minutes, 60), ['count' => intdiv($minutes, 60)]),
            }])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('stable_panel.offerings.sections.details'))
                    ->icon('heroicon-o-information-circle')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label(__('stable_panel.fields.type'))
                            ->options(StableOfferingType::options())
                            ->default(StableOfferingType::RidingTraining->value)
                            ->required()
                            ->native(false),
                        Toggle::make('is_active')
                            ->label(__('stable_panel.fields.is_active'))
                            ->helperText(__('stable_panel.offerings.active_hint'))
                            ->default(true)
                            ->inline(false),
                        TextInput::make('en_name')
                            ->label(__('stable_panel.fields.en_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('ar_name')
                            ->label(__('stable_panel.fields.ar_name'))
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['dir' => 'rtl']),
                        Textarea::make('en_description')
                            ->label(__('stable_panel.fields.en_description'))
                            ->rows(4),
                        Textarea::make('ar_description')
                            ->label(__('stable_panel.fields.ar_description'))
                            ->rows(4)
                            ->extraInputAttributes(['dir' => 'rtl']),
                        FileUpload::make('photo')
                            ->label(__('stable_panel.fields.photo'))
                            ->image()
                            ->disk('public')
                            ->directory('stables/offerings')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('stable_panel.offerings.sections.places'))
                    ->icon('heroicon-o-banknotes')
                    ->columns(3)
                    ->schema([
                        TextInput::make('price')
                            ->label(__('stable_panel.fields.price_per_rider'))
                            ->helperText(__('stable_panel.offerings.price_hint'))
                            ->prefix(fn () => SiteSetting::currencyHtml())
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(99999)
                            ->step(0.001)
                            ->required(),
                        TextInput::make('duration_minutes')
                            ->label(__('stable_panel.fields.duration'))
                            ->suffix(__('stable_panel.units.min'))
                            ->integer()
                            ->minValue(10)
                            ->maxValue(720)
                            ->default(60)
                            ->required(),
                        Select::make('booking_cutoff_minutes')
                            ->label(__('stable_panel.fields.cutoff'))
                            ->helperText(__('stable_panel.offerings.cutoff_hint'))
                            ->options(static::cutoffOptions())
                            ->default(60)
                            ->required()
                            ->native(false),
                        TextInput::make('capacity')
                            ->label(__('stable_panel.fields.capacity'))
                            ->helperText(__('stable_panel.offerings.capacity_hint'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(200)
                            ->default(1)
                            ->live(onBlur: true)
                            ->required(),
                        TextInput::make('min_riders')
                            ->label(__('stable_panel.fields.min_riders'))
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->maxValue(fn (Get $get) => max(1, (int) $get('max_riders')))
                            ->required(),
                        TextInput::make('max_riders')
                            ->label(__('stable_panel.fields.max_riders'))
                            ->helperText(__('stable_panel.offerings.max_riders_hint'))
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->maxValue(fn (Get $get) => max(1, (int) $get('capacity')))
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('')
                    ->disk('public')
                    ->square(),
                TextColumn::make('en_name')
                    ->label(__('stable_panel.fields.name'))
                    ->formatStateUsing(fn (StableOffering $record) => $record->name)
                    ->description(fn (StableOffering $record) => $record->type?->label())
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('stable_panel.fields.price_per_rider'))
                    ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state, 3))
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->label(__('stable_panel.fields.duration'))
                    ->formatStateUsing(fn ($state) => trans_choice('stable_panel.units.minutes', (int) $state, ['count' => $state])),
                TextColumn::make('capacity')
                    ->label(__('stable_panel.fields.capacity'))
                    ->icon('heroicon-o-user-group'),
                IconColumn::make('is_active')
                    ->label(__('stable_panel.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('en_name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateIcon('heroicon-o-academic-cap')
            ->emptyStateHeading(__('stable_panel.offerings.empty_heading'))
            ->emptyStateDescription(__('stable_panel.offerings.empty_description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStableOfferings::route('/'),
            'create' => CreateStableOffering::route('/create'),
            'edit' => EditStableOffering::route('/{record}/edit'),
        ];
    }
}
