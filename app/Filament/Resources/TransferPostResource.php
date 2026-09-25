<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferPostResource\Pages\CreateTransferPost;
use App\Filament\Resources\TransferPostResource\Pages\EditTransferPost;
use App\Filament\Resources\TransferPostResource\Pages\ListTransferPosts;
use App\Filament\Resources\TransferPostResource\Pages\ViewTransferPost;
use App\Models\City;
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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
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
        return static::$model::where('status', 'active')->count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options([
                        'offer' => 'Offer',
                        'request' => 'Request',
                    ])
                    ->live()
                    ->required(),
                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),

                Select::make('from_country_id')
                    ->label('From Country')
                    ->relationship(name: 'fromCountry', titleAttribute: 'en_name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Select::make('from_region_id')
                    ->label('From Region')
                    ->options(fn (Get $get): Collection => Region::query()
                        ->where('country_id', $get('from_country_id'))
                        ->pluck('en_name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Select::make('from_city_id')
                    ->label('From City')
                    ->options(fn (Get $get): Collection => City::query()
                        ->where('region_id', $get('from_region_id'))
                        ->pluck('en_name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('to_country_id')
                    ->label('To Country')
                    ->relationship(name: 'toCountry', titleAttribute: 'en_name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Select::make('to_region_id')
                    ->label('To Region')
                    ->options(fn (Get $get): Collection => Region::query()
                        ->where('country_id', $get('to_country_id'))
                        ->pluck('en_name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Select::make('to_city_id')
                    ->label('To City')
                    ->options(fn (Get $get): Collection => City::query()
                        ->where('region_id', $get('to_region_id'))
                        ->pluck('en_name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('capacity')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                DatePicker::make('transfer_date')
                    ->required(),
                TextInput::make('price')
                    ->numeric()
                    ->minValue(0)
                    ->prefix(fn () => SiteSetting::currencyHtml())
                    ->visible(fn (Get $get) => $get('type') === 'offer'),
                TextInput::make('contact_number')
                    ->tel()
                    ->required(),
                FileUpload::make('cover_photo')
                    ->image()
                    ->disk('public')
                    ->directory('transfer-board/covers')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_photo')
                    ->disk('public')
                    ->square(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'offer' ? 'success' : 'info')
                    ->sortable(),
                TextColumn::make('fromCity.en_name')
                    ->label('From')
                    ->description(fn (TransferPost $record): string => $record->fromCountry?->en_name ?? '')
                    ->searchable(),
                TextColumn::make('toCity.en_name')
                    ->label('To')
                    ->description(fn (TransferPost $record): string => $record->toCountry?->en_name ?? '')
                    ->searchable(),
                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('transfer_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('price')
                    ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state, 3))
                    ->sortable(),
                TextColumn::make('contact_number'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
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
                SelectFilter::make('type')
                    ->options([
                        'offer' => 'Offer',
                        'request' => 'Request',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('from_country_id')
                    ->label('From Country')
                    ->relationship('fromCountry', 'en_name'),
                SelectFilter::make('to_country_id')
                    ->label('To Country')
                    ->relationship('toCountry', 'en_name'),
                Filter::make('transfer_date')
                    ->schema([
                        DatePicker::make('date_from'),
                        DatePicker::make('date_to'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['date_from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('transfer_date', '>=', $date))
                        ->when($data['date_to'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('transfer_date', '<=', $date))),
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
            'index' => ListTransferPosts::route('/'),
            'create' => CreateTransferPost::route('/create'),
            'view' => ViewTransferPost::route('/{record}'),
            'edit' => EditTransferPost::route('/{record}/edit'),
        ];
    }
}
