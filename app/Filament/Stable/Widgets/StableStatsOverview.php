<?php

namespace App\Filament\Stable\Widgets;

use App\Models\BookingSlot;
use App\Models\Stable;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The next seven days at a glance: slots open, places left, riders booked. */
class StableStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -5;

    protected function getStats(): array
    {
        /** @var Stable $stable */
        $stable = Filament::getTenant();
        $today = BookingSlot::today();
        $weekEnd = CarbonImmutable::parse($today)->addDays(6)->toDateString();

        $slots = BookingSlot::query()
            ->where('stable_id', $stable->getKey())
            ->whereBetween('date', [$today, $weekEnd])
            ->where('is_open', true)
            ->withBookedRiders()
            ->get()
            ->reject(fn (BookingSlot $slot) => $slot->hasStarted());

        $booked = (int) $slots->sum(fn (BookingSlot $slot) => $slot->bookedRiders());
        $free = (int) $slots->sum(fn (BookingSlot $slot) => $slot->remainingPlaces());
        $places = $booked + $free;

        return [
            Stat::make(__('stable_panel.stats.offerings'), $stable->offerings()->active()->count())
                ->icon('heroicon-o-academic-cap'),
            Stat::make(__('stable_panel.stats.open_slots'), $slots->count())
                ->description(__('stable_panel.stats.next_7_days'))
                ->icon('heroicon-o-calendar-days'),
            Stat::make(__('stable_panel.stats.free_places'), $free)
                ->description(__('stable_panel.stats.next_7_days'))
                ->icon('heroicon-o-ticket'),
            Stat::make(__('stable_panel.stats.booked_riders'), $booked)
                ->description($places > 0 ? __('stable_panel.stats.occupancy', ['percent' => (int) round($booked * 100 / $places)]) : __('stable_panel.stats.next_7_days'))
                ->color($booked > 0 ? 'success' : 'gray')
                ->icon('heroicon-o-user-group'),
        ];
    }
}
