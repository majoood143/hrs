<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FarrierResource\Pages\CreateFarrier;
use App\Filament\Resources\FarrierResource\Pages\EditFarrier;
use App\Filament\Resources\FarrierResource\Pages\ListFarriers;
use App\Filament\Resources\FarrierResource\Pages\ViewFarrier;
use App\Filament\Support\PendingPosts;
use App\Filament\Support\PlaceFormFields;
use App\Filament\Support\WebsiteLinkActions;
use App\Models\City;
use App\Models\Country;
use App\Models\Farrier;
use App\Models\Region;
use App\Models\SiteSetting;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

    /** `ar_name` in the Arabic admin, else `en_name` (locations, farriers). */
    private static function nameColumn(): string
    {
        return app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Farrier')
                    ->tabs([
                        Tab::make(__('admin_farrier.tabs.info'))
                            ->schema([
                                TextInput::make('en_name')
                                    ->label(__('farriers.name_en'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('ar_name')
                                    ->label(__('farriers.name_ar'))
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                                TextInput::make('specialty')
                                    ->label(__('farriers.specialty'))
                                    ->maxLength(255),
                                TextInput::make('years_experience')
                                    ->label(__('farriers.years_experience'))
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(80),
                            ])->icon('heroicon-o-information-circle')
                            ->columns(2),
                        Tab::make(__('admin_farrier.tabs.location'))
                            ->schema([
                                Select::make('country_id')
                                    ->label(__('farriers.country'))
                                    ->options(fn () => Country::query()->orderBy(static::nameColumn())->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('region_id', null);
                                        $set('city_id', null);
                                    })
                                    ->required(),
                                Select::make('region_id')
                                    ->label(__('farriers.region'))
                                    ->options(fn (Get $get): Collection => Region::query()
                                        ->where('country_id', $get('country_id'))
                                        ->orderBy(static::nameColumn())
                                        ->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                                    ->required(),
                                Select::make('city_id')
                                    ->label(__('farriers.city'))
                                    ->options(fn (Get $get): Collection => City::query()
                                        ->where('region_id', $get('region_id'))
                                        ->orderBy(static::nameColumn())
                                        ->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->required(),
                            ])->icon('heroicon-o-map-pin')
                            ->columns(3),
                        Tab::make(__('admin_farrier.tabs.listing'))
                            ->schema([
                                TextInput::make('price')
                                    ->label(__('farriers.price'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix(fn () => SiteSetting::currencyHtml())
                                    ->required(),
                                Select::make('status')
                                    ->label(__('farriers.status'))
                                    ->options(fn () => Farrier::statusOptions())
                                    ->required(),
                                TextInput::make('contact_number')
                                    ->label(__('farriers.contact_number'))
                                    ->tel()
                                    ->maxLength(32)
                                    ->required(),
                                PlaceFormFields::coverPhoto('farriers/covers', __('farriers.cover_photo'))
                                    ->required()
                                    ->columnSpanFull(),
                                Textarea::make('description_en')
                                    ->label(__('admin_farrier.fields.description_en'))
                                    ->rows(3),
                                Textarea::make('description_ar')
                                    ->label(__('admin_farrier.fields.description_ar'))
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
            // The city column is eager loaded by Filament; the country under it is not.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('country'))
            ->columns([
                ImageColumn::make('cover_photo')
                    ->label(__('farriers.cover_photo'))
                    ->disk('public')
                    ->square(),
                TextColumn::make($name)
                    ->label(__('admin_farrier.fields.name'))
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable(),
                TextColumn::make('specialty')
                    ->label(__('farriers.specialty'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('years_experience')
                    ->label(__('admin_farrier.fields.experience'))
                    ->suffix(' '.__('admin_farrier.fields.years_suffix'))
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('farriers.price'))
                    ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state, 3))
                    ->sortable(),
                TextColumn::make('city.'.$name)
                    ->label(__('farriers.city'))
                    ->description(fn (Farrier $record): string => (string) $record->country?->{$name})
                    ->searchable(['en_name', 'ar_name']),
                TextColumn::make('status')
                    ->label(__('farriers.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Farrier::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        PendingPosts::STATUS => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin_farrier.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('farriers.status'))
                    ->options(fn () => Farrier::statusOptions()),
                SelectFilter::make('country_id')
                    ->label(__('farriers.country'))
                    ->relationship('country', $name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('city_id')
                    ->label(__('farriers.city'))
                    ->relationship('city', $name)
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                PendingPosts::approveAction(),
                ActionGroup::make([
                    ViewAction::make(),
                    ...WebsiteLinkActions::make(
                        url: fn (Farrier $record): string => route('farriers.show', $record->id),
                        isPublic: fn (Farrier $record): bool => $record->status === 'active',
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
            'index' => ListFarriers::route('/'),
            'create' => CreateFarrier::route('/create'),
            'view' => ViewFarrier::route('/{record}'),
            'edit' => EditFarrier::route('/{record}/edit'),
        ];
    }
}
