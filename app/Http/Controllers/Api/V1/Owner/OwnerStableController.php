<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Enums\StableBookingStatus;
use App\Http\Controllers\Api\V1\Owner\Concerns\FindsOwnerStable;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MoneyResource;
use App\Http\Resources\V1\OptionResource;
use App\Http\Resources\V1\Owner\OwnerBookingResource;
use App\Http\Resources\V1\Owner\OwnerStableResource;
use App\Models\BookingSlot;
use App\Models\Stable;
use App\Services\Reports\StableLedgerRow;
use App\Services\Reports\StableStatement;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A stable's day at a glance and its money, from the same sources as the /stable panel's dashboard
 * (today's bookings, the next seven days, this month) and Statement page (the ledger and balance).
 */
class OwnerStableController extends Controller
{
    use FindsOwnerStable;

    /** The periods the app offers (the statement page also has a custom range). */
    public const PERIODS = ['this_week', 'this_month', 'last_month', 'this_year'];

    /**
     * Today at my stable
     *
     * Today's bookings by time, how many past sessions still need marking, the next seven days'
     * places, this month's money and the balance with us.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $stable = $this->ownerStable($request, $slug);
        $today = BookingSlot::today();
        $month = StableStatement::forPeriod($stable, 'this_month')->totals();

        $todays = OwnerBookingController::query($stable)
            ->whereIn('stable_bookings.status', [StableBookingStatus::Pending->value, StableBookingStatus::Confirmed->value, StableBookingStatus::Completed->value, StableBookingStatus::NoShow->value])
            ->where('booking_slots.date', $today)
            ->orderBy('booking_slots.start_time')
            ->get();

        $week = BookingSlot::query()
            ->where('stable_id', $stable->getKey())
            ->whereBetween('date', [$today, CarbonImmutable::parse($today)->addDays(6)->toDateString()])
            ->where('is_open', true)
            ->withBookedRiders()
            ->get()
            ->reject(fn (BookingSlot $slot) => $slot->hasStarted());
        $booked = (int) $week->sum(fn (BookingSlot $slot) => $slot->bookedRiders());
        $free = (int) $week->sum(fn (BookingSlot $slot) => $slot->remainingPlaces());

        return response()->json([
            'stable' => OwnerStableResource::make($stable),
            /** @example 2026-10-16 */
            'today' => $today,
            'today_bookings' => OwnerBookingResource::collection($todays),
            'today_riders' => (int) $todays->whereIn('status', [StableBookingStatus::Confirmed, StableBookingStatus::Completed])->sum('riders'),
            /** Sessions that took place and are still confirmed: mark them attended or no-show. */
            'to_mark' => OwnerBookingController::toMarkQuery($stable)->count(),
            'next_7_days' => [
                'open_times' => $week->count(),
                'booked_riders' => $booked,
                'free_places' => $free,
                /** @var int|null */
                'occupancy_percent' => $booked + $free > 0 ? (int) round($booked * 100 / ($booked + $free)) : null,
            ],
            'this_month' => [
                'bookings' => $month['bookings'],
                'riders' => $month['riders'],
                'stable_share' => new MoneyResource($month['stable_share'] / 1000),
                'commission' => new MoneyResource(($month['commission'] + $month['vat_on_commission']) / 1000),
            ],
            'balance' => $this->balance($stable),
            /** Past pay-at-the-stable sessions not marked yet: they count in the money only once attended. */
            'unmarked_at_stable' => StableStatement::unmarkedAtStable($stable),
            /** @var string|null */
            'commission_rate' => $stable->formatted_commission,
        ]);
    }

    /**
     * My stable's income
     *
     * The statement for a period: what customers paid, the stable's share, our fee and commission,
     * refunds, and each booking that carried money (newest first, at most 200), plus the balance now.
     */
    public function income(Request $request, string $slug): JsonResponse
    {
        $stable = $this->ownerStable($request, $slug);
        $data = $request->validate([
            /** Defaults to this_month. */
            'period' => ['nullable', Rule::in(self::PERIODS)],
        ]);
        $period = $data['period'] ?? 'this_month';
        $statement = StableStatement::forPeriod($stable, $period);
        $t = $statement->totals();
        $money = fn (int $baisa) => new MoneyResource($baisa / 1000);

        return response()->json([
            'period' => [
                'value' => $period,
                'label' => __('admin_income_report.periods.'.$period),
                /** @example 2026-10-01 */
                'from' => $statement->from->toDateString(),
                /** @example 2026-10-31 */
                'to' => $statement->to->toDateString(),
            ],
            'periods' => collect(self::PERIODS)->map(fn (string $p) => OptionResource::of($p, __('admin_income_report.periods.'.$p)))->all(),
            'totals' => [
                'bookings' => $t['bookings'],
                'riders' => $t['riders'],
                /** Paid by customers (fees and VAT included). */
                'collected' => $money($t['collected']),
                'collected_by_us' => $money($t['collected_by_us']),
                'collected_by_stable' => $money($t['collected_by_stable']),
                'refunded' => $money($t['refunded']),
                'stable_share' => $money($t['stable_share']),
                'commission' => $money($t['commission'] + $t['vat_on_commission']),
                /** The service fee customers paid us, with its VAT. */
                'fee' => $money($t['fee']),
                'payouts' => $money($t['payouts']),
                'receipts' => $money($t['receipts']),
            ],
            'balance' => $this->balance($stable),
            'unmarked_at_stable' => StableStatement::unmarkedAtStable($stable),
            'rows' => $statement->rows()->sortByDesc(fn (StableLedgerRow $row) => $row->date->getTimestamp())->take(200)->values()->map(fn (StableLedgerRow $row) => [
                /** The day the money counted: paid, or attended for pay-at-the-stable. @example 2026-10-12 */
                'date' => $row->date->setTimezone((string) config('stable_bookings.timezone'))->toDateString(),
                /** @var string|null */
                'reference' => $row->bookingReference,
                'service' => $row->service,
                /** @var string|null */
                'session_date' => $row->sessionDate,
                'riders' => $row->riders,
                'kind' => OptionResource::of($row->kind, __('stable_statement.kinds.'.$row->kind)),
                'collected' => $money($row->collected()),
                'refunded' => $money($row->refunded),
                'stable_share' => $money($row->stableShare()),
            ])->all(),
        ]);
    }

    /** @return array{amount: MoneyResource, direction: string, label: string} positive = we owe the stable */
    private function balance(Stable $stable): array
    {
        $balance = StableStatement::balance($stable);
        $direction = $balance > 0 ? 'we_owe' : ($balance < 0 ? 'you_owe' : 'settled');

        return [
            'amount' => new MoneyResource(abs($balance) / 1000),
            /** we_owe (we pay the stable) | you_owe (the stable pays us) | settled */
            'direction' => $direction,
            'label' => __('stable_statement.widget.balance_'.$direction),
        ];
    }
}
