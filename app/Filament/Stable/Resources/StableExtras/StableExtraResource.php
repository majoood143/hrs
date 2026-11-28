<?php

namespace App\Filament\Stable\Resources\StableExtras;

use App\Filament\Stable\Resources\StableExtras\Pages\ManageStableExtras;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Models\StableExtra;
use App\Models\StableOffering;
use App\Support\Money;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a customer can add to a session (photos, helmet hire, transport…). The form and table are
 * shared with the admin's Extras tab on the stable (`ExtrasRelationManager`).
 */
class StableExtraResource extends Resource
{
    protected static ?string $model = StableExtra::class;

    protected static ?string $tenantRelationshipName = 'extras';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?int $navigationSort = 16;

    public static function canAccess(): bool
    {
        return StableExtra::enabled() && parent::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.setup');
    }

    public static function getModelLabel(): string
    {
        return __('stable_extras.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stable_extras.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('en_name')->label(__('stable_panel.fields.en_name'))->placeholder(__('stable_extras.placeholders.en_name'))->required()->maxLength(255),
                TextInput::make('ar_name')->label(__('stable_panel.fields.ar_name'))->placeholder(__('stable_extras.placeholders.ar_name'))->required()->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                Textarea::make('en_description')->label(__('stable_panel.fields.en_description'))->placeholder(__('stable_extras.placeholders.en_description'))->rows(2)->maxLength(1000),
                Textarea::make('ar_description')->label(__('stable_panel.fields.ar_description'))->placeholder(__('stable_extras.placeholders.ar_description'))->rows(2)->maxLength(1000)->extraInputAttributes(['dir' => 'rtl']),
                TextInput::make('price')
                    ->label(__('stable_extras.fields.price'))
                    ->placeholder(__('stable_extras.placeholders.price'))
                    ->helperText(__('stable_extras.hints.commission'))
                    ->prefix(fn () => SiteSetting::currencyHtml())
                    ->numeric()
                    ->minValue(0.001)
                    ->maxValue(99999)
                    ->step(0.001)
                    ->required(),
                Select::make('pricing')
                    ->label(__('stable_extras.fields.pricing'))
                    ->helperText(__('stable_extras.hints.pricing'))
                    ->options(__('stable_extras.pricing'))
                    ->default(StableExtra::PER_BOOKING)
                    ->native(false)
                    ->required(),
                TextInput::make('max_quantity')
                    ->label(__('stable_extras.fields.max_quantity'))
                    ->placeholder(__('stable_extras.placeholders.max_quantity'))
                    ->helperText(__('stable_extras.hints.max_quantity'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(20)
                    ->default(1)
                    ->required(),
                Select::make('offerings')
                    ->label(__('stable_extras.fields.offerings'))
                    ->placeholder(__('stable_extras.placeholders.offerings'))
                    ->helperText(__('stable_extras.hints.offerings'))
                    ->relationship('offerings', 'en_name', fn (Builder $query, $livewire) => $query->where('stable_offerings.stable_id', self::stableOf($livewire)?->getKey()))
                    ->getOptionLabelFromRecordUsing(fn (StableOffering $record) => $record->name)
                    ->multiple()
                    ->preload()
                    ->native(false),
                FileUpload::make('photo')
                    ->label(__('stable_panel.fields.photo'))
                    ->helperText(__('stable_extras.hints.photo'))
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(4096)
                    ->disk('public')
                    ->directory('stables/extras'),
                TextInput::make('sort')->label(__('stable_extras.fields.sort'))->placeholder(__('stable_extras.placeholders.sort'))->integer()->minValue(0)->maxValue(999)->default(0),
                Toggle::make('is_active')->label(__('stable_panel.fields.is_active'))->helperText(__('stable_extras.hints.active'))->default(true)->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('offerings')
                ->withSum(['bookingLines as sold_count' => fn ($q) => $q->sold()], 'quantity')
                ->withSum(['bookingLines as sold_amount' => fn ($q) => $q->sold()], 'amount'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                ImageColumn::make('photo')->label('')->disk('public')->square(),
                TextColumn::make('en_name')
                    ->label(__('stable_panel.fields.name'))
                    ->formatStateUsing(fn (StableExtra $record) => $record->name)
                    ->description(fn (StableExtra $record) => $record->offerings->isEmpty() ? __('stable_extras.placeholders.offerings') : $record->offerings->map->name->implode(', '))
                    ->searchable(['en_name', 'ar_name']),
                TextColumn::make('price')
                    ->label(__('stable_extras.fields.price'))
                    ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state))
                    ->description(fn (StableExtra $record) => __('stable_extras.pricing.'.$record->pricing)),
                TextColumn::make('max_quantity')->label(__('stable_extras.fields.max_quantity')),
                TextColumn::make('sold_count')->label(__('stable_extras.fields.sold'))->default(0)
                    ->description(fn (StableExtra $record) => Money::format((int) round((float) $record->sold_amount * 1000))),
                IconColumn::make('is_active')->label(__('stable_panel.fields.is_active'))->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateIcon('heroicon-o-sparkles')
            ->emptyStateHeading(__('stable_extras.panel.empty_heading'))
            ->emptyStateDescription(__('stable_extras.panel.empty_description'));
    }

    /** The stable being worked on: the panel's tenant, or the admin's stable page. */
    public static function stableOf(mixed $livewire): ?Stable
    {
        $stable = $livewire instanceof RelationManager ? $livewire->getOwnerRecord() : Filament::getTenant();

        return $stable instanceof Stable ? $stable : null;
    }

    public static function getPages(): array
    {
        return ['index' => ManageStableExtras::route('/')];
    }
}
