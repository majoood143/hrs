<?php

namespace App\Filament\Stable\Resources\BookingSlots;

use App\Filament\Stable\Concerns\InteractsWithStable;
use App\Filament\Stable\Resources\BookingSlots\Pages\ManageBookingSlots;
use App\Models\BookingSlot;
use App\Models\StableOffering;
use App\Models\StableTrainer;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The stable's dated slots: what is open, how full, and one-off changes (close a slot, change its
 * places or trainer, add a one-off slot). Slots from a schedule can be closed but not deleted, or
 * the nightly run would bring them back.
 */
class BookingSlotResource extends Resource
{
    use InteractsWithStable;

    protected static ?string $model = BookingSlot::class;

    protected static ?string $tenantRelationshipName = 'slots';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.bookings');
    }

    public static function getModelLabel(): string
    {
        return __('stable_panel.slots.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stable_panel.slots.plural');
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
                DatePicker::make('date')
                    ->label(__('stable_panel.fields.date'))
                    ->minDate(fn () => BookingSlot::today())
                    ->required()
                    ->disabledOn('edit'),
                TimePicker::make('start_time')
                    ->label(__('stable_panel.fields.start_time'))
                    ->seconds(false)
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('capacity')
                    ->label(__('stable_panel.fields.capacity'))
                    ->integer()
                    ->required()
                    ->minValue(fn (?BookingSlot $record) => max(1, $record?->bookedRiders() ?? 0))
                    ->maxValue(200)
                    ->helperText(fn (?BookingSlot $record) => $record?->hasBookings()
                        ? __('stable_panel.slots.capacity_booked_hint', ['booked' => $record->bookedRiders()])
                        : null),
                Select::make('trainer_id')
                    ->label(__('stable_panel.fields.trainer'))
                    ->relationship('trainer', 'en_name', fn ($query) => $query->where('is_active', true))
                    ->getOptionLabelFromRecordUsing(fn (StableTrainer $record) => $record->name)
                    ->preload()
                    ->visible(fn () => static::staffEnabled()),
                Toggle::make('is_open')
                    ->label(__('stable_panel.fields.is_open'))
                    ->helperText(__('stable_panel.slots.open_hint'))
                    ->default(true),
                TextInput::make('notes')
                    ->label(__('stable_panel.fields.notes'))
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['offering', 'trainer'])->withBookedRiders())
            ->defaultSort(fn (Builder $query) => $query->orderBy('date')->orderBy('start_time'))
            ->columns([
                TextColumn::make('date')
                    ->label(__('stable_panel.fields.date'))
                    ->date('D, Y-m-d')
                    ->description(fn (BookingSlot $record) => $record->timeRange())
                    ->sortable(),
                TextColumn::make('offering.en_name')
                    ->label(__('stable_panel.fields.offering'))
                    ->formatStateUsing(fn (BookingSlot $record) => $record->offering?->name)
                    ->description(fn (BookingSlot $record) => $record->stable_schedule_id ? null : __('stable_panel.slots.one_off')),
                TextColumn::make('trainer.en_name')
                    ->label(__('stable_panel.fields.trainer'))
                    ->formatStateUsing(fn (BookingSlot $record) => $record->trainer?->name)
                    ->placeholder('—')
                    ->visible(fn () => static::staffEnabled()),
                TextColumn::make('booked_riders')
                    ->label(__('stable_panel.slots.booked'))
                    ->state(fn (BookingSlot $record) => $record->bookedRiders().' / '.$record->capacity)
                    ->icon('heroicon-o-user-group'),
                TextColumn::make('remaining')
                    ->label(__('stable_panel.slots.remaining'))
                    ->state(fn (BookingSlot $record) => $record->remainingPlaces())
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state === 0 => 'danger',
                        $state <= 2 => 'warning',
                        default => 'success',
                    }),
                ToggleColumn::make('is_open')
                    ->label(__('stable_panel.fields.is_open')),
                TextColumn::make('notes')
                    ->label(__('stable_panel.fields.notes'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('dates')
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('stable_panel.slots.from'))
                            ->default(fn () => BookingSlot::today()),
                        DatePicker::make('until')
                            ->label(__('stable_panel.slots.until')),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('date', '>=', substr((string) $date, 0, 10)))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('date', '<=', substr((string) $date, 0, 10))))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? __('stable_panel.slots.from').': '.substr((string) $data['from'], 0, 10) : null,
                        ($data['until'] ?? null) ? __('stable_panel.slots.until').': '.substr((string) $data['until'], 0, 10) : null,
                    ])),
                SelectFilter::make('stable_offering_id')
                    ->label(__('stable_panel.fields.offering'))
                    ->relationship('offering', 'en_name')
                    ->getOptionLabelFromRecordUsing(fn (StableOffering $record) => $record->name),
                TernaryFilter::make('is_open')
                    ->label(__('stable_panel.fields.is_open')),
                Filter::make('has_bookings')
                    ->label(__('stable_panel.slots.has_bookings'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('holdingBookings')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('close')
                        ->label(__('stable_panel.slots.close_selected'))
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => static::setOpen($records, false)),
                    BulkAction::make('open')
                        ->label(__('stable_panel.slots.open_selected'))
                        ->icon('heroicon-o-lock-open')
                        ->action(fn (Collection $records) => static::setOpen($records, true)),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading(__('stable_panel.slots.empty_heading'))
            ->emptyStateDescription(__('stable_panel.slots.empty_description'));
    }

    /** @param  Collection<int, BookingSlot>  $records */
    private static function setOpen(Collection $records, bool $open): void
    {
        // one by one, so each change goes into the stable's change log
        $count = $records->filter(fn (BookingSlot $slot) => $slot->is_open !== $open)
            ->each(fn (BookingSlot $slot) => $slot->update(['is_open' => $open]))
            ->count();

        Notification::make()->success()->title(__('stable_panel.slots.updated', ['count' => $count]))->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBookingSlots::route('/'),
        ];
    }
}
