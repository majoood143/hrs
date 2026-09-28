<?php

namespace App\Filament\Stable\Resources\StablePackages;

use App\Filament\Stable\Resources\StablePackages\Pages\ManageStablePackages;
use App\Models\SiteSetting;
use App\Models\StableOffering;
use App\Models\StablePackage;
use App\Models\StablePackagePurchase;
use App\Support\Money;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Sessions of one service sold together at a package price, used within so many days. */
class StablePackageResource extends Resource
{
    protected static ?string $model = StablePackage::class;

    protected static ?string $tenantRelationshipName = 'packages';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 15;

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.setup');
    }

    public static function getModelLabel(): string
    {
        return __('stable_packages.panel.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stable_packages.panel.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('stable_offering_id')
                    ->label(__('stable_panel.fields.offering'))
                    ->relationship('offering', 'en_name')
                    ->getOptionLabelFromRecordUsing(fn (StableOffering $record) => $record->name)
                    ->required()
                    ->preload()
                    ->native(false)
                    ->disabledOn('edit')
                    ->columnSpanFull(),
                TextInput::make('en_name')->label(__('stable_panel.fields.en_name'))->required()->maxLength(255),
                TextInput::make('ar_name')->label(__('stable_panel.fields.ar_name'))->required()->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                Textarea::make('en_description')->label(__('stable_panel.fields.en_description'))->rows(3),
                Textarea::make('ar_description')->label(__('stable_panel.fields.ar_description'))->rows(3)->extraInputAttributes(['dir' => 'rtl']),
                TextInput::make('sessions')
                    ->label(__('stable_packages.fields.sessions'))
                    ->helperText(__('stable_packages.panel.sessions_hint'))
                    ->integer()
                    ->minValue(2)
                    ->maxValue(100)
                    ->required(),
                TextInput::make('price')
                    ->label(__('stable_packages.fields.price'))
                    ->helperText(__('stable_packages.panel.price_hint'))
                    ->prefix(fn () => SiteSetting::currencyHtml())
                    ->numeric()
                    ->minValue(0)
                    ->step(0.001)
                    ->required(),
                TextInput::make('validity_days')
                    ->label(__('stable_packages.fields.validity_days'))
                    ->helperText(__('stable_packages.panel.validity_hint'))
                    ->suffix(__('stable_packages.panel.days'))
                    ->integer()
                    ->minValue(7)
                    ->maxValue(730)
                    ->default(60)
                    ->required(),
                Toggle::make('is_active')->label(__('stable_panel.fields.is_active'))->helperText(__('stable_packages.panel.active_hint'))->default(true)->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('offering')->withCount([
                'purchases as sold_count' => fn ($q) => $q->where('status', StablePackagePurchase::ACTIVE),
                'purchases as usable_count' => fn ($q) => $q->usable(),
            ]))
            ->columns([
                TextColumn::make('en_name')
                    ->label(__('stable_panel.fields.name'))
                    ->formatStateUsing(fn (StablePackage $record) => $record->name)
                    ->description(fn (StablePackage $record) => $record->offering?->name),
                TextColumn::make('sessions')->label(__('stable_packages.fields.sessions'))->icon('heroicon-o-ticket'),
                TextColumn::make('price')
                    ->label(__('stable_packages.fields.price'))
                    ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state, 3))
                    ->description(fn (StablePackage $record) => $record->savingBaisa() > 0 ? __('stable_packages.saving', ['amount' => Money::format($record->savingBaisa())]) : null),
                TextColumn::make('validity_days')->label(__('stable_packages.fields.validity_days'))->suffix(' '.__('stable_packages.panel.days')),
                TextColumn::make('sold_count')->label(__('stable_packages.panel.sold'))->description(fn (StablePackage $record) => __('stable_packages.panel.in_use', ['count' => $record->usable_count])),
                IconColumn::make('is_active')->label(__('stable_panel.fields.is_active'))->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->emptyStateHeading(__('stable_packages.panel.empty_heading'))
            ->emptyStateDescription(__('stable_packages.panel.empty_description'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageStablePackages::route('/')];
    }
}
