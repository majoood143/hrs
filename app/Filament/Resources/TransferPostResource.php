<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferPostResource\Pages\CreateTransferPost;
use App\Filament\Resources\TransferPostResource\Pages\EditTransferPost;
use App\Filament\Resources\TransferPostResource\Pages\ListTransferPosts;
use App\Filament\Resources\TransferPostResource\Pages\ViewTransferPost;
use App\Filament\Support\PlaceFormFields;
use App\Filament\Support\WebsiteLinkActions;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\SiteSetting;
use App\Models\TransferPost;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class TransferPostResource extends Resource
{
    protected static ?string $model = TransferPost::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.directory');
    }

    public static function getModelLabel(): string
    {
        return __('admin_transfer_post.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_transfer_post.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_transfer_post.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::where('status', 'active')->count();
    }

    /** `ar_name` in the Arabic admin, else `en_name` (countries, regions, cities). */
    private static function nameColumn(): string
    {
        return app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';
    }

    /**
     * Country → region → city for one end of the route (`from` / `to`), in the admin's language.
     *
     * @return array<int, Select>
     */
    private static function locationFields(string $prefix): array
    {
        return [
            Select::make("{$prefix}_country_id")
                ->label(__('transportation.country'))
                ->options(fn () => Country::query()->orderBy(static::nameColumn())->pluck(static::nameColumn(), 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function (Set $set) use ($prefix): void {
                    $set("{$prefix}_region_id", null);
                    $set("{$prefix}_city_id", null);
                })
                ->required(),
            Select::make("{$prefix}_region_id")
                ->label(__('transportation.region'))
                ->options(fn (Get $get): Collection => Region::query()
                    ->where('country_id', $get("{$prefix}_country_id"))
                    ->orderBy(static::nameColumn())
                    ->pluck(static::nameColumn(), 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set("{$prefix}_city_id", null))
                ->required(),
            Select::make("{$prefix}_city_id")
                ->label(__('transportation.city'))
                ->options(fn (Get $get): Collection => City::query()
                    ->where('region_id', $get("{$prefix}_region_id"))
                    ->orderBy(static::nameColumn())
                    ->pluck(static::nameColumn(), 'id'))
                ->searchable()
                // Same rule as the website/app form (TransferPostCreator): a route goes somewhere else.
                ->when($prefix === 'to', fn (Select $field) => $field
                    ->notIn(fn (Get $get): array => array_filter([$get('from_city_id')]))
                    ->validationMessages(['not_in' => __('transportation.same_city')]))
                ->required(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label(__('admin_transfer_post.fields.type'))
                    ->options(fn () => TransferPost::typeOptions())
                    ->live()
                    ->required(),
                Select::make('status')
                    ->label(__('admin_transfer_post.fields.status'))
                    ->options(fn () => TransferPost::statusOptions())
                    ->required(),
                Section::make(__('admin_transfer_post.sections.from'))
                    ->icon('heroicon-o-map-pin')
                    ->schema(static::locationFields('from'))
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('admin_transfer_post.sections.to'))
                    ->icon('heroicon-o-flag')
                    ->schema(static::locationFields('to'))
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('admin_transfer_post.sections.details'))
                    ->icon('heroicon-o-truck')
                    ->schema([
                        TextInput::make('capacity')
                            ->label(__('transportation.capacity'))
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        DatePicker::make('transfer_date')
                            ->label(__('transportation.transfer_date'))
                            ->required(),
                        TextInput::make('price')
                            ->label(__('transportation.price'))
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn () => SiteSetting::currencyHtml())
                            ->visible(fn (Get $get) => $get('type') === 'offer')
                            ->required(fn (Get $get) => $get('type') === 'offer'),
                        TextInput::make('contact_number')
                            ->label(__('transportation.contact_number'))
                            ->tel()
                            ->maxLength(32)
                            ->required(),
                        PlaceFormFields::coverPhoto('transfer-board/covers', __('transportation.cover_photo_optional'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        $name = static::nameColumn();

        return $table
            // The city columns are eager loaded by Filament; the countries under them are not.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['fromCountry', 'toCountry']))
            ->columns([
                ImageColumn::make('cover_photo')
                    ->label(__('transportation.cover_photo'))
                    ->disk('public')
                    ->square(),
                TextColumn::make('type')
                    ->label(__('admin_transfer_post.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TransferPost::typeOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'offer' ? 'success' : 'info')
                    ->sortable(),
                TextColumn::make('fromCity.'.$name)
                    ->label(__('admin_transfer_post.fields.from'))
                    ->description(fn (TransferPost $record): string => (string) $record->fromCountry?->{$name})
                    ->searchable(['en_name', 'ar_name']),
                TextColumn::make('toCity.'.$name)
                    ->label(__('admin_transfer_post.fields.to'))
                    ->description(fn (TransferPost $record): string => (string) $record->toCountry?->{$name})
                    ->searchable(['en_name', 'ar_name']),
                TextColumn::make('capacity')
                    ->label(__('transportation.capacity'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('transfer_date')
                    ->label(__('transportation.transfer_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('transportation.price'))
                    ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state, 3))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('contact_number')
                    ->label(__('transportation.contact_number')),
                TextColumn::make('status')
                    ->label(__('admin_transfer_post.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TransferPost::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin_transfer_post.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin_transfer_post.fields.type'))
                    ->options(fn () => TransferPost::typeOptions()),
                SelectFilter::make('status')
                    ->label(__('admin_transfer_post.fields.status'))
                    ->options(fn () => TransferPost::statusOptions()),
                SelectFilter::make('from_country_id')
                    ->label(__('admin_transfer_post.fields.from_country'))
                    ->relationship('fromCountry', $name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('to_country_id')
                    ->label(__('admin_transfer_post.fields.to_country'))
                    ->relationship('toCountry', $name)
                    ->searchable()
                    ->preload(),
                Filter::make('transfer_date')
                    ->schema([
                        DatePicker::make('date_from')->label(__('transportation.filter_date_from')),
                        DatePicker::make('date_to')->label(__('transportation.filter_date_to')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['date_from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('transfer_date', '>=', $date))
                        ->when($data['date_to'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('transfer_date', '<=', $date))),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    ...WebsiteLinkActions::make(
                        url: fn (TransferPost $record): string => route('transfer-board.show', $record->id),
                        isPublic: fn (TransferPost $record): bool => $record->status === 'active' && $record->transfer_date?->gte(today()) === true,
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
            'index' => ListTransferPosts::route('/'),
            'create' => CreateTransferPost::route('/create'),
            'view' => ViewTransferPost::route('/{record}'),
            'edit' => EditTransferPost::route('/{record}/edit'),
        ];
    }
}
