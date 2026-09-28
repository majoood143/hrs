<?php

namespace App\Filament\Stable\Widgets;

use App\Enums\StableBookingStatus;
use App\Filament\Stable\Resources\StableBookings\StableBookingResource;
use App\Models\BookingSlot;
use App\Models\StableBooking;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Who is coming today, by time: the riders and how they pay. */
class TodayBookingsWidget extends TableWidget
{
    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('stable_statement.widget.today'))
            ->query(fn () => StableBooking::query()
                ->where('stable_id', Filament::getTenant()?->getKey())
                ->whereIn('status', [StableBookingStatus::Confirmed->value, StableBookingStatus::Completed->value, StableBookingStatus::NoShow->value])
                ->whereHas('slot', fn (Builder $q) => $q->where('date', BookingSlot::today()))
                ->with(['slot', 'offering', 'order'])
                ->orderBy(BookingSlot::query()->select('start_time')->whereColumn('booking_slots.id', 'stable_bookings.booking_slot_id')))
            ->columns([
                TextColumn::make('slot.start_time')
                    ->label(__('stable_bookings.fields.time'))
                    ->state(fn (StableBooking $record) => $record->slot?->timeRange())
                    ->icon('heroicon-o-clock'),
                TextColumn::make('offering.en_name')
                    ->label(__('stable_panel.fields.offering'))
                    ->formatStateUsing(fn (StableBooking $record) => $record->offering?->name),
                TextColumn::make('riders')
                    ->label(__('stable_bookings.fields.riders'))
                    ->description(fn (StableBooking $record) => $record->riderNames()),
                TextColumn::make('order.customer_name')
                    ->label(__('stable_bookings.fields.customer'))
                    ->description(fn (StableBooking $record) => $record->order?->customer_phone),
                TextColumn::make('payment')
                    ->label(__('stable_bookings.fields.payment'))
                    ->state(fn (StableBooking $record) => StableBookingResource::paymentLabel($record))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('stable_panel.bookings.status'))
                    ->badge()
                    ->formatStateUsing(fn (StableBookingStatus $state) => $state->label())
                    ->color(fn (StableBookingStatus $state) => $state->color()),
            ])
            ->recordActions([
                StableBookingResource::attendedAction(),
                StableBookingResource::noShowAction(),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('stable_statement.widget.today_empty'))
            ->emptyStateIcon('heroicon-o-calendar');
    }
}
