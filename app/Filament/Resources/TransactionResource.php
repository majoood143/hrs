<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages\CreateTransaction;
use App\Filament\Resources\TransactionResource\Pages\EditTransaction;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\TransactionResource\Pages\ViewTransaction;
use App\Filament\Support\AttachmentFields;
use App\Models\Horse;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Transaction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getModelLabel(): string
    {
        return __('admin_transaction.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_transaction.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_transaction.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::count();
    }

    private static function label(string $key): string
    {
        return __("admin_transaction.fields.{$key}");
    }

    /** @return array<int, string> services by their name in the admin's language */
    public static function serviceOptions(): array
    {
        // Once per request: the table formats every row's service with it.
        return once(fn () => Service::query()->orderBy('id')->get()->mapWithKeys(fn (Service $service) => [$service->getKey() => $service->localizedName()])->all());
    }

    /** The transaction's own fields (also used on the horse page, where the horse is known). */
    public static function detailFields(bool $withHorse = true): array
    {
        $name = app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';

        return array_values(array_filter([
            $withHorse ? Select::make('horse_id')
                ->label(static::label('horse'))
                ->options(fn () => Horse::query()->orderBy($name)->pluck($name, 'id'))
                ->searchable()
                ->required() : null,
            Select::make('service_id')
                ->label(static::label('service'))
                ->options(fn () => static::serviceOptions())
                ->searchable()
                ->live()
                // The service's price is the starting amount; the admin can change it.
                ->afterStateUpdated(fn (Set $set, $state) => $set('amount', $state ? Service::query()->whereKey($state)->value('price') : null))
                ->required(),
            TextInput::make('amount')
                ->label(static::label('amount'))
                ->prefix(fn () => SiteSetting::currencyHtml())
                ->numeric()
                ->minValue(0)
                ->required(),
            TextInput::make('currency')
                ->label(static::label('currency'))
                ->default(fn () => SiteSetting::currency()['code'])
                ->length(3)
                ->alpha()
                ->required(),
            Select::make('status')
                ->label(static::label('status'))
                ->options(fn () => Transaction::statusOptions())
                ->default('pending')
                ->required(),
            Textarea::make('description')
                ->label(static::label('description'))
                ->rows(2)
                ->maxLength(255)
                ->columnSpanFull(),
        ]));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make(__('admin_transaction.steps.details'))
                        ->schema(static::detailFields())
                        ->columns(3),
                    Step::make(__('admin_transaction.steps.attachments'))
                        ->schema([
                            Repeater::make('attachement')
                                ->label(static::label('attachments'))
                                ->relationship()
                                ->schema(AttachmentFields::form(withHorse: false))
                                // Each paper belongs to the transaction's horse and to whoever uploads it.
                                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, $livewire): array => [
                                    ...$data,
                                    'horse_id' => data_get($livewire, 'data.horse_id'),
                                    'user_id' => auth()->id(),
                                ])
                                ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, $livewire): array => [
                                    ...$data,
                                    'horse_id' => data_get($livewire, 'data.horse_id'),
                                ])
                                ->defaultItems(0)
                                ->columns(2)
                                ->collapsible()
                                ->reorderableWithButtons()
                                ->addActionAlignment(Alignment::Start)
                                ->addActionLabel(static::label('add_attachment'))
                                ->columnSpanFull(),
                        ]),
                ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $name = app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';

        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(static::label('id'))
                    ->sortable(),
                TextColumn::make('horse.'.$name)
                    ->label(static::label('horse'))
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable(),
                TextColumn::make('service_id')
                    ->label(static::label('service'))
                    ->formatStateUsing(fn (?int $state): string => static::serviceOptions()[$state] ?? '—'),
                TextColumn::make('amount')
                    ->label(static::label('amount'))
                    ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state, 3))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(static::label('status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Transaction::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(static::label('created_by'))
                    ->toggleable(),
                TextColumn::make('attachement_count')
                    ->label(static::label('attachments'))
                    ->counts('attachement')
                    ->badge(),
                TextColumn::make('description')
                    ->label(static::label('description'))
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(static::label('created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(static::label('status'))
                    ->options(fn () => Transaction::statusOptions()),
                SelectFilter::make('service_id')
                    ->label(static::label('service'))
                    ->options(fn () => static::serviceOptions()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
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
            'index' => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'view' => ViewTransaction::route('/{record}'),
            'edit' => EditTransaction::route('/{record}/edit'),
        ];
    }
}
