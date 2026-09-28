<?php

namespace App\Filament\Stable\Resources\StableBookings;

use App\Enums\StableBookingStatus;
use App\Filament\Stable\Resources\StableBookings\Pages\ListStableBookings;
use App\Models\BookingSlot;
use App\Models\SiteSetting;
use App\Models\StableBooking;
use App\Models\StableOffering;
use App\Services\Reports\StableLedgerRow;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\StableBookingActions;
use App\Support\Money;
use App\Support\StableBookingSettings;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * The stable's bookings: who comes when, with the riders' details, and what happened (attended,
 * no-show, or called off with a reason the customer is sent).
 */
class StableBookingResource extends Resource
{
    protected static ?string $model = StableBooking::class;

    protected static ?string $tenantRelationshipName = 'bookings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.bookings');
    }

    public static function getModelLabel(): string
    {
        return __('stable_panel.bookings.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stable_panel.bookings.plural');
    }

    /** Today's confirmed bookings, as the menu badge. */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->where('status', StableBookingStatus::Confirmed->value)
            ->whereHas('slot', fn (Builder $q) => $q->where('date', BookingSlot::today()))
            ->count();

        return $count ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('stable_panel.bookings.sections.session'))
                    ->icon('heroicon-o-calendar-days')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('reference')->label(__('stable_bookings.fields.reference'))->copyable(),
                        TextEntry::make('status')
                            ->label(__('stable_panel.bookings.status'))
                            ->badge()
                            ->formatStateUsing(fn (StableBookingStatus $state) => $state->label())
                            ->color(fn (StableBookingStatus $state) => $state->color()),
                        TextEntry::make('offering.en_name')->label(__('stable_panel.fields.offering'))->formatStateUsing(fn (StableBooking $record) => $record->offering?->name),
                        TextEntry::make('slot.date')->label(__('stable_panel.fields.date'))->date('l, Y-m-d'),
                        TextEntry::make('slot_time')->label(__('stable_bookings.fields.time'))->state(fn (StableBooking $record) => $record->slot?->timeRange()),
                        TextEntry::make('riders')->label(__('stable_bookings.fields.riders')),
                    ]),
                Section::make(__('stable_panel.bookings.sections.customer'))
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('order.customer_name')->label(__('stable_bookings.fields.customer'))->placeholder('—'),
                        TextEntry::make('order.customer_phone')
                            ->label(__('stable_panel.fields.phone'))
                            ->url(fn (StableBooking $record) => $record->order?->customer_phone ? 'tel:+'.$record->order->customer_phone : null)
                            ->placeholder('—'),
                        TextEntry::make('order.customer_email')->label(__('stable_panel.fields.email'))->placeholder('—'),
                        TextEntry::make('payment')
                            ->label(__('stable_bookings.fields.payment'))
                            ->state(fn (StableBooking $record) => static::paymentLabel($record)),
                        TextEntry::make('order.total')
                            ->label(__('stable_panel.bookings.total'))
                            ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state, 3)),
                        TextEntry::make('cancellation_reason')
                            ->label(__('stable_bookings.fields.reason'))
                            ->visible(fn (StableBooking $record) => $record->status === StableBookingStatus::Cancelled)
                            ->placeholder('—'),
                    ]),
                Section::make(__('stable_panel.bookings.sections.riders'))
                    ->icon('heroicon-o-user-group')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('riders_data')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('name')->label(__('stable_bookings.rider.name')),
                                TextEntry::make('age')->label(__('stable_bookings.rider.age'))->placeholder('—'),
                                TextEntry::make('level')
                                    ->label(__('stable_bookings.rider.level'))
                                    ->formatStateUsing(fn (?string $state) => in_array($state, StableBookingSettings::LEVELS, true) ? __('stable_bookings.levels.'.$state) : $state)
                                    ->placeholder('—'),
                                TextEntry::make('weight')->label(__('stable_bookings.rider.weight'))->placeholder('—'),
                                TextEntry::make('height')->label(__('stable_bookings.rider.height'))->placeholder('—'),
                                TextEntry::make('guardian_name')->label(__('stable_bookings.rider.guardian_name'))->placeholder('—'),
                                TextEntry::make('guardian_phone')->label(__('stable_bookings.rider.guardian_phone'))->placeholder('—'),
                                TextEntry::make('waiver_accepted_at')->label(__('stable_panel.bookings.waiver'))->dateTime()->placeholder('—'),
                                TextEntry::make('notes')->label(__('stable_bookings.rider.notes'))->columnSpanFull()->placeholder('—'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['slot', 'offering', 'order']))
            ->defaultSort(fn (Builder $query) => $query
                ->orderBy(BookingSlot::query()->select('date')->whereColumn('booking_slots.id', 'stable_bookings.booking_slot_id'))
                ->orderBy(BookingSlot::query()->select('start_time')->whereColumn('booking_slots.id', 'stable_bookings.booking_slot_id')))
            ->columns([
                TextColumn::make('slot.date')
                    ->label(__('stable_panel.fields.date'))
                    ->date('D, Y-m-d')
                    ->description(fn (StableBooking $record) => $record->slot?->timeRange()),
                TextColumn::make('reference')
                    ->label(__('stable_bookings.fields.reference'))
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),
                TextColumn::make('offering.en_name')
                    ->label(__('stable_panel.fields.offering'))
                    ->formatStateUsing(fn (StableBooking $record) => $record->offering?->name),
                TextColumn::make('order.customer_name')
                    ->label(__('stable_bookings.fields.customer'))
                    ->description(fn (StableBooking $record) => $record->order?->customer_phone)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('order', fn (Builder $q) => $q
                        ->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', '%'.preg_replace('/\D+/', '', $search).'%'))),
                TextColumn::make('riders')
                    ->label(__('stable_bookings.fields.riders'))
                    ->icon('heroicon-o-user-group')
                    ->description(fn (StableBooking $record) => str($record->riderNames())->limit(40)),
                TextColumn::make('payment')
                    ->label(__('stable_bookings.fields.payment'))
                    ->state(fn (StableBooking $record) => static::paymentLabel($record))
                    ->badge()
                    ->color(fn (StableBooking $record) => match (true) {
                        $record->payment_option === 'at_stable' => 'info',
                        (bool) $record->order?->isPaid() => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('commission')
                    ->label(__('stable_statement.fields.commission'))
                    ->state(fn (StableBooking $record) => static::money($record, 'commission'))
                    ->toggleable(),
                TextColumn::make('stable_share')
                    ->label(__('stable_statement.fields.stable_share'))
                    ->state(fn (StableBooking $record) => static::money($record, 'share'))
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('stable_panel.bookings.status'))
                    ->badge()
                    ->formatStateUsing(fn (StableBookingStatus $state) => $state->label())
                    ->color(fn (StableBookingStatus $state) => $state->color()),
            ])
            ->filters([
                Filter::make('dates')
                    ->schema([
                        DatePicker::make('from')->label(__('stable_panel.slots.from'))->default(fn () => BookingSlot::today()),
                        DatePicker::make('until')->label(__('stable_panel.slots.until')),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->whereHas('slot', fn (Builder $q) => $q
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('date', '>=', substr((string) $date, 0, 10)))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('date', '<=', substr((string) $date, 0, 10)))))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? __('stable_panel.slots.from').': '.substr((string) $data['from'], 0, 10) : null,
                        ($data['until'] ?? null) ? __('stable_panel.slots.until').': '.substr((string) $data['until'], 0, 10) : null,
                    ])),
                SelectFilter::make('status')
                    ->label(__('stable_panel.bookings.status'))
                    ->options(collect(StableBookingStatus::cases())->mapWithKeys(fn (StableBookingStatus $s) => [$s->value => $s->label()])),
                SelectFilter::make('stable_offering_id')
                    ->label(__('stable_panel.fields.offering'))
                    ->relationship('offering', 'en_name')
                    ->getOptionLabelFromRecordUsing(fn (StableOffering $record) => $record->name),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    static::attendedAction(),
                    static::noShowAction(),
                    static::cancelAction(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-ticket')
            ->emptyStateHeading(__('stable_panel.bookings.empty_heading'))
            ->emptyStateDescription(__('stable_panel.bookings.empty_description'));
    }

    /** The commission on a booking, or the stable's share of it (free ones: nothing). */
    public static function money(StableBooking $record, string $what): ?HtmlString
    {
        $order = $record->order;

        if (! $order || (float) $order->total <= 0) {
            return null;
        }

        $order->setRelation('stableBooking', $record);
        $row = StableLedgerRow::fromOrder($order);

        return Money::formatHtml($what === 'commission' ? $row->commission + $row->vatOnCommission : $row->stableShare());
    }

    public static function paymentLabel(StableBooking $record): string
    {
        return match (true) {
            $record->payment_option === 'at_stable' => __('stable_bookings.payment_options.at_stable'),
            $record->payment_option === 'free' => __('stable_bookings.payment.free'),
            $record->payment_option === 'package' => __('stable_packages.paid_with_package'),
            (bool) $record->order?->isPaid() => __('stable_bookings.payment.paid_online'),
            default => (string) $record->order?->payment_status?->label(),
        };
    }

    private static function sessionDue(StableBooking $record): bool
    {
        return $record->slot && $record->slot->date->toDateString() <= BookingSlot::today();
    }

    public static function attendedAction(): Action
    {
        return Action::make('attended')
            ->label(__('stable_panel.bookings.mark_attended'))
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (StableBooking $record) => in_array($record->status, [StableBookingStatus::Confirmed, StableBookingStatus::NoShow], true) && static::sessionDue($record))
            ->action(fn (StableBooking $record) => static::run(fn () => app(StableBookingActions::class)->markAttended($record)));
    }

    public static function noShowAction(): Action
    {
        return Action::make('noShow')
            ->label(__('stable_panel.bookings.mark_no_show'))
            ->icon('heroicon-o-user-minus')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (StableBooking $record) => in_array($record->status, [StableBookingStatus::Confirmed, StableBookingStatus::Completed], true) && static::sessionDue($record))
            ->action(fn (StableBooking $record) => static::run(fn () => app(StableBookingActions::class)->markNoShow($record)));
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancelBooking')
            ->label(__('stable_panel.bookings.cancel'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (StableBooking $record) => $record->isActive())
            ->modalDescription(fn (StableBooking $record) => $record->order?->isPaid()
                ? __('stable_panel.bookings.cancel_paid_hint')
                : __('stable_panel.bookings.cancel_hint'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('stable_bookings.fields.reason'))
                    ->helperText(__('stable_panel.bookings.reason_hint'))
                    ->required()
                    ->maxLength(500),
            ])
            ->action(fn (StableBooking $record, array $data) => static::run(fn () => app(StableBookingActions::class)->cancelByStable($record, $data['reason'])));
    }

    private static function run(callable $action): void
    {
        try {
            $action();
            Notification::make()->success()->title(__('stable_panel.bookings.updated'))->send();
        } catch (BookingUnavailable $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStableBookings::route('/'),
        ];
    }
}
