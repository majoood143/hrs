<?php

namespace App\Filament\Public\Resources;

use App\Filament\Public\Resources\TransferPostResource\Pages\CreateTransferPost;
use App\Filament\Public\Resources\TransferPostResource\Pages\ListTransferPosts;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\TransferPost;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MarcoGermani87\FilamentCaptcha\Forms\Components\CaptchaField;

class TransferPostResource extends Resource
{
    protected static ?string $model = TransferPost::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-truck';

    public static function getModelLabel(): string
    {
        return __('transportation.transfer');
    }

    public static function getNavigationLabel(): string
    {
        return __('transportation.board_title');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make(__('transportation.step_type'))
                        ->schema([
                            ToggleButtons::make('type')
                                ->hiddenLabel()
                                ->options([
                                    'offer' => __('transportation.type_offer'),
                                    'request' => __('transportation.type_request'),
                                ])
                                ->icons([
                                    'offer' => 'heroicon-o-truck',
                                    'request' => 'heroicon-o-hand-raised',
                                ])
                                ->colors([
                                    'offer' => 'success',
                                    'request' => 'info',
                                ])
                                ->inline()
                                ->required()
                                ->live(),
                        ]),
                    Step::make(__('transportation.step_from'))
                        ->schema(self::locationFields('from')),
                    Step::make(__('transportation.step_to'))
                        ->schema(self::locationFields('to')),
                    Step::make(__('transportation.step_details'))
                        ->schema([
                            TextInput::make('capacity')
                                ->label(__('transportation.capacity'))
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                            DatePicker::make('transfer_date')
                                ->label(__('transportation.transfer_date'))
                                ->minDate(now())
                                ->native(false)
                                ->required(),
                            TextInput::make('price')
                                ->label(__('transportation.price'))
                                ->numeric()
                                ->minValue(0)
                                ->prefix(fn () => \App\Models\SiteSetting::currency()['symbol'])
                                ->visible(fn (Get $get) => $get('type') === 'offer')
                                ->required(fn (Get $get) => $get('type') === 'offer'),
                            TextInput::make('contact_number')
                                ->label(__('transportation.contact_number'))
                                ->tel()
                                ->required(),
                        ])->columns(2),
                    Step::make(__('transportation.step_verify'))
                        ->schema([
                            CaptchaField::make('captcha'),
                        ]),
                ]),
            ]);
    }

    /**
     * @return array<int, Select>
     */
    protected static function locationFields(string $prefix): array
    {
        return [
            Select::make("{$prefix}_country_id")
                ->label(__('transportation.country'))
                ->options(fn () => Country::query()->public()->get()->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->live()
                ->required(),
            Select::make("{$prefix}_region_id")
                ->label(__('transportation.region'))
                ->options(fn (Get $get) => Region::query()
                    ->where('country_id', $get("{$prefix}_country_id"))
                    ->get()
                    ->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->live()
                ->required(),
            Select::make("{$prefix}_city_id")
                ->label(__('transportation.city'))
                ->options(fn (Get $get) => City::query()
                    ->where('region_id', $get("{$prefix}_region_id"))
                    ->get()
                    ->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->columns([
                TextColumn::make('type')
                    ->label(__('transportation.step_type'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'offer' ? 'success' : 'info')
                    ->formatStateUsing(fn (string $state): string => __("transportation.type_{$state}")),
                TextColumn::make('route')
                    ->label(__('transportation.route'))
                    ->getStateUsing(fn (TransferPost $record): string => sprintf(
                        '%s, %s → %s, %s',
                        $record->fromCity?->name,
                        $record->fromCountry?->name,
                        $record->toCity?->name,
                        $record->toCountry?->name,
                    ))
                    ->wrap(),
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
                    ->formatStateUsing(fn (?string $state, TransferPost $record): string => $record->type === 'offer' && filled($state)
                        ? \App\Models\SiteSetting::formatCurrency($state)
                        : '—')
                    ->sortable(),
                TextColumn::make('contact_number')
                    ->label(__('transportation.contact_number'))
                    ->icon('heroicon-o-phone'),
            ])
            ->defaultSort('transfer_date')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('transportation.step_type'))
                    ->options([
                        'offer' => __('transportation.type_offer'),
                        'request' => __('transportation.type_request'),
                    ]),
                Filter::make('capacity')
                    ->schema([
                        TextInput::make('capacity_min')->numeric()->label(__('transportation.capacity')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['capacity_min'] ?? null, fn (Builder $query, $value): Builder => $query->where('capacity', '>=', $value))),
                Filter::make('transfer_date')
                    ->schema([
                        DatePicker::make('date_from')->label(__('transportation.transfer_date')),
                        DatePicker::make('date_to')->label(__('transportation.transfer_date')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['date_from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('transfer_date', '>=', $date))
                        ->when($data['date_to'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('transfer_date', '<=', $date))),
                SelectFilter::make('from_country_id')
                    ->label(__('transportation.step_from') . ' — ' . __('transportation.country'))
                    ->relationship('fromCountry', 'en_name'),
                SelectFilter::make('to_country_id')
                    ->label(__('transportation.step_to') . ' — ' . __('transportation.country'))
                    ->relationship('toCountry', 'en_name'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visible();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransferPosts::route('/'),
            'create' => CreateTransferPost::route('/post'),
        ];
    }
}
