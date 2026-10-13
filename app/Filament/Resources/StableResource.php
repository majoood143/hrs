<?php

namespace App\Filament\Resources;

use App\Enums\StableApprovalStatus;
use App\Filament\Resources\StableResource\Pages\CreateStable;
use App\Filament\Resources\StableResource\Pages\EditStable;
use App\Filament\Resources\StableResource\Pages\ListStables;
use App\Filament\Resources\StableResource\Pages\ViewStable;
use App\Filament\Resources\StableResource\RelationManagers\OfferingsRelationManager;
use App\Filament\Resources\StableResource\RelationManagers\PaymentAccountsRelationManager;
use App\Filament\Resources\StableResource\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\StableResource\RelationManagers\SettlementsRelationManager;
use App\Filament\Resources\StableResource\StableApprovalActions;
use App\Filament\Stable\Schemas\StableProfileForm;
use App\Filament\Support\WebsiteLinkActions;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Stable;
use App\Models\StableService;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
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

    /** @return array{total: int, pending: int} counted once per request for the badge, colour and tooltip */
    private static function badgeCounts(): array
    {
        return once(function (): array {
            $row = Stable::query()
                ->toBase()
                ->selectRaw('count(*) as total, sum(case when approval_status = ? then 1 else 0 end) as pending', [StableApprovalStatus::Pending->value])
                ->first();

            return ['total' => (int) $row?->total, 'pending' => (int) $row?->pending];
        });
    }

    /** Stables waiting for approval when there are any, else how many there are. */
    public static function getNavigationBadge(): ?string
    {
        $counts = static::badgeCounts();

        return (string) ($counts['pending'] ?: $counts['total']);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::badgeCounts()['pending'] ? 'warning' : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return static::badgeCounts()['pending'] ? __('admin_stable.approval.pending_badge') : null;
    }

    /** `ar_name` in the Arabic admin, else `en_name` (countries, regions, cities, services, stables). */
    private static function nameColumn(): string
    {
        return app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';
    }

    public static function getRelations(): array
    {
        return [
            OfferingsRelationManager::class,
            PaymentAccountsRelationManager::class,
            SettlementsRelationManager::class,
            ReviewsRelationManager::class,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        [$enName, $arName] = StableProfileForm::names();

        return $schema
            ->components([
                Tabs::make('Stable')
                    ->tabs([
                        Tab::make(__('stable_panel.profile.tabs.info'))
                            ->schema([
                                // The slug follows the English name only while creating: renaming a live stable keeps its links.
                                $enName
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                                        if ($operation === 'create') {
                                            $set('slug', Str::slug((string) $state));
                                        }
                                    }),
                                $arName,
                                TextInput::make('slug')
                                    ->label(__('admin_stable.fields.slug'))
                                    ->helperText(__('admin_stable.fields.slug_helper'))
                                    ->required()
                                    ->alphaDash()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                Toggle::make('is_active')
                                    ->label(__('stable_panel.fields.is_active'))
                                    ->default(true),
                                ...StableProfileForm::contact(),
                            ])->icon('heroicon-o-information-circle')
                            ->columns(2),
                        Tab::make(__('stable_panel.profile.tabs.location'))
                            ->schema([
                                Select::make('country_id')
                                    ->label(__('stable_panel.fields.country'))
                                    ->options(fn () => Country::query()->orderBy(static::nameColumn())->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('region_id', null);
                                        $set('city_id', null);
                                    })
                                    ->required(),
                                Select::make('region_id')
                                    ->label(__('stable_panel.fields.region'))
                                    ->options(fn (Get $get): Collection => Region::query()
                                        ->where('country_id', $get('country_id'))
                                        ->orderBy(static::nameColumn())
                                        ->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                                    ->required(),
                                Select::make('city_id')
                                    ->label(__('stable_panel.fields.city'))
                                    ->options(fn (Get $get): Collection => City::query()
                                        ->where('region_id', $get('region_id'))
                                        ->orderBy(static::nameColumn())
                                        ->pluck(static::nameColumn(), 'id'))
                                    ->searchable()
                                    ->required(),
                                ...collect(StableProfileForm::location())
                                    ->filter(fn ($field) => in_array($field->getName(), ['address', 'map_link'], true))
                                    ->map(fn ($field) => $field->columnSpanFull())
                                    ->values()
                                    ->all(),
                            ])->icon('heroicon-o-map-pin')
                            ->columns(3),
                        Tab::make(__('stable_panel.profile.tabs.description'))
                            ->schema(StableProfileForm::description())
                            ->icon('heroicon-o-document-text'),
                        Tab::make(__('admin_stable.tabs.services'))
                            ->schema([
                                Select::make('services')
                                    ->label(__('admin_stable.fields.services'))
                                    ->relationship('services', static::nameColumn())
                                    ->getOptionLabelFromRecordUsing(fn (StableService $record) => $record->name)
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])->icon('heroicon-o-wrench-screwdriver'),
                        Tab::make(__('stable_panel.profile.tabs.hours'))
                            ->schema(StableProfileForm::openingHours())
                            ->icon('heroicon-o-clock'),
                        Tab::make(__('admin_stable.tabs.owner'))
                            ->icon('heroicon-o-check-badge')
                            ->visibleOn(['view', 'edit'])
                            ->columns(2)
                            ->schema([
                                TextEntry::make('approval_status')
                                    ->label(__('admin_stable.fields.approval_status'))
                                    ->badge()
                                    ->formatStateUsing(fn (?StableApprovalStatus $state) => $state?->label())
                                    ->color(fn (?StableApprovalStatus $state) => $state?->color()),
                                TextEntry::make('formatted_commission')
                                    ->label(__('admin_stable.fields.commission'))
                                    ->placeholder(__('admin_stable.approval.no_commission')),
                                TextEntry::make('owners')
                                    ->label(__('admin_stable.fields.owners'))
                                    ->state(fn (?Stable $record) => $record?->owners->map(fn ($owner) => $owner->name.' · '.$owner->email.' · '.$owner->phone)->all())
                                    ->listWithLineBreaks()
                                    ->placeholder(__('admin_stable.fields.no_owner')),
                                TextEntry::make('rejection_reason')
                                    ->label(__('admin_stable.fields.reason'))
                                    ->placeholder('—'),
                                TextEntry::make('approved_at')
                                    ->label(__('admin_stable.fields.approved_at'))
                                    ->dateTime()
                                    ->placeholder('—'),
                            ]),
                        Tab::make(__('stable_panel.profile.tabs.photos'))
                            ->schema(StableProfileForm::photos())
                            ->icon('heroicon-o-photo')
                            ->columns(1),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $name = static::nameColumn();

        return $table
            // The city/services/owners columns are eager loaded by Filament; the country under the city is not.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('country'))
            ->columns([
                ImageColumn::make('cover_photo')
                    ->label(__('stable_panel.fields.cover_photo'))
                    ->disk('public')
                    ->square(),
                TextColumn::make($name)
                    ->label(__('stable_panel.fields.name'))
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable(),
                TextColumn::make('city.'.$name)
                    ->label(__('stable_panel.fields.city'))
                    ->description(fn (Stable $record): string => (string) $record->country?->{$name})
                    ->searchable(['en_name', 'ar_name']),
                TextColumn::make('services.'.$name)
                    ->label(__('admin_stable.fields.services'))
                    ->badge()
                    ->separator(','),
                TextColumn::make('approval_status')
                    ->label(__('admin_stable.fields.approval_status'))
                    ->badge()
                    ->formatStateUsing(fn (?StableApprovalStatus $state) => $state?->label())
                    ->color(fn (?StableApprovalStatus $state) => $state?->color())
                    ->icon(fn (?StableApprovalStatus $state) => $state?->icon())
                    ->sortable(),
                TextColumn::make('owners.name')
                    ->label(__('admin_stable.fields.owners'))
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('formatted_commission')
                    ->label(__('admin_stable.fields.commission'))
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label(__('stable_panel.fields.is_active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin_stable.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort($name)
            ->filters([
                SelectFilter::make('country_id')
                    ->label(__('stable_panel.fields.country'))
                    ->relationship('country', $name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('city_id')
                    ->label(__('stable_panel.fields.city'))
                    ->relationship('city', $name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('services')
                    ->label(__('admin_stable.fields.services'))
                    ->relationship('services', $name)
                    ->multiple()
                    ->preload(),
                SelectFilter::make('is_active')
                    ->label(__('stable_panel.fields.is_active'))
                    ->options([1 => __('admin_stable.fields.active'), 0 => __('admin_stable.fields.inactive')]),
                SelectFilter::make('approval_status')
                    ->label(__('admin_stable.fields.approval_status'))
                    ->options(StableApprovalStatus::options()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    StableApprovalActions::openPanel(),
                    ...StableApprovalActions::all(),
                    ...WebsiteLinkActions::make(
                        url: fn (Stable $record): string => route('stables.show', $record->slug),
                        isPublic: fn (Stable $record): bool => $record->is_active && $record->isApproved() && filled($record->slug),
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
            'index' => ListStables::route('/'),
            'create' => CreateStable::route('/create'),
            'view' => ViewStable::route('/{record}'),
            'edit' => EditStable::route('/{record}/edit'),
        ];
    }
}
