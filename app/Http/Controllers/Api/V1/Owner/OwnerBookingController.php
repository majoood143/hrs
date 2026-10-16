<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Enums\StableBookingStatus;
use App\Http\Controllers\Api\V1\Concerns\PaginatesListings;
use App\Http\Controllers\Api\V1\Owner\Concerns\FindsOwnerStable;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Owner\OwnerBookingResource;
use App\Models\BookingSlot;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\StableBookingActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The stable's bookings (the /stable panel's Bookings list and its actions), through the same
 * StableBookingActions: attended / no-show complete the order (attended asks the customer for a
 * review), cancelling sends the customer the reason and leaves a paid order to be refunded.
 */
class OwnerBookingController extends Controller
{
    use FindsOwnerStable;
    use PaginatesListings;

    /**
     * My stable's bookings
     *
     * `date`: that day's bookings by time (cancelled ones left out). Else `when`: `upcoming`
     * (default; from today, waiting for payment or confirmed, soonest first), `to_mark` (sessions
     * that took place and are still confirmed, newest first), `past` (everything else, newest
     * first). `q` searches the reference, customer name and phone.
     */
    public function index(Request $request, string $slug): AnonymousResourceCollection
    {
        $stable = $this->ownerStable($request, $slug);
        $data = $request->validate([
            /** @example 2026-10-20 */
            'date' => ['nullable', 'date_format:Y-m-d'],
            'when' => ['nullable', Rule::in(['upcoming', 'to_mark', 'past'])],
            'q' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $today = BookingSlot::today();
        $active = [StableBookingStatus::Pending->value, StableBookingStatus::Confirmed->value];
        $query = static::query($stable);

        if (filled($data['date'] ?? null)) {
            $query->where('booking_slots.date', $data['date'])
                ->where('stable_bookings.status', '!=', StableBookingStatus::Cancelled->value)
                ->orderBy('booking_slots.start_time');
        } else {
            match ($data['when'] ?? 'upcoming') {
                'to_mark' => $query->whereIn('stable_bookings.id', static::toMarkQuery($stable)->select('stable_bookings.id'))
                    ->orderByDesc('booking_slots.date')->orderByDesc('booking_slots.start_time'),
                'past' => $query->where(fn (Builder $q) => $q->where('booking_slots.date', '<', $today)->orWhereNotIn('stable_bookings.status', $active))
                    ->orderByDesc('booking_slots.date')->orderByDesc('booking_slots.start_time'),
                default => $query->where('booking_slots.date', '>=', $today)->whereIn('stable_bookings.status', $active)
                    ->orderBy('booking_slots.date')->orderBy('booking_slots.start_time'),
            };
        }

        if (filled($search = trim((string) ($data['q'] ?? '')))) {
            $digits = preg_replace('/\D+/', '', $search);
            $query->where(fn (Builder $q) => $q
                ->where('stable_bookings.reference', 'like', '%'.$search.'%')
                ->orWhereHas('order', fn (Builder $o) => $o
                    ->where('customer_name', 'like', '%'.$search.'%')
                    ->when($digits !== '', fn (Builder $o) => $o->orWhere('customer_phone', 'like', '%'.$digits.'%'))));
        }

        return OwnerBookingResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    /** One of my stable's bookings */
    public function show(Request $request, string $slug, string $reference): OwnerBookingResource
    {
        return OwnerBookingResource::make($this->find($request, $slug, $reference));
    }

    /**
     * Mark attended
     *
     * From the session's day on (also to correct a no-show). Completes the order, counts a
     * pay-at-the-stable booking in the statement, and asks the customer for a review.
     */
    public function attended(Request $request, string $slug, string $reference, StableBookingActions $actions): OwnerBookingResource|JsonResponse
    {
        $booking = $this->find($request, $slug, $reference);

        if (! $this->due($booking)) {
            return $this->unavailable(__('api.owner.not_due'));
        }

        return $this->run(fn () => $actions->markAttended($booking));
    }

    /** Mark no-show (from the session's day on; also to correct "attended"). */
    public function noShow(Request $request, string $slug, string $reference, StableBookingActions $actions): OwnerBookingResource|JsonResponse
    {
        $booking = $this->find($request, $slug, $reference);

        if (! $this->due($booking)) {
            return $this->unavailable(__('api.owner.not_due'));
        }

        return $this->run(fn () => $actions->markNoShow($booking));
    }

    /**
     * Cancel a booking
     *
     * While it waits for payment or is confirmed. The customer is sent the reason; a paid booking
     * is left to refund. 409 `booking_unavailable` when it is no longer active.
     */
    public function cancel(Request $request, string $slug, string $reference, StableBookingActions $actions): OwnerBookingResource|JsonResponse
    {
        $booking = $this->find($request, $slug, $reference);
        $data = $request->validate([
            /** Sent to the customer. */
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->run(fn () => $actions->cancelByStable($booking, $data['reason']));
    }

    /** @return Builder<StableBooking> the stable's bookings joined to their times */
    public static function query(Stable $stable): Builder
    {
        return StableBooking::query()
            ->where('stable_bookings.stable_id', $stable->getKey())
            ->join('booking_slots', 'booking_slots.id', '=', 'stable_bookings.booking_slot_id')
            ->select('stable_bookings.*')
            // places left come with the slots, not one query per booking
            ->with(['slot' => fn ($q) => $q->withBookedRiders(), 'slot.trainer', 'offering', 'order', 'packagePurchase']);
    }

    /** @return Builder<StableBooking> sessions that have started and are still confirmed (attended or no-show is owed) */
    public static function toMarkQuery(Stable $stable): Builder
    {
        $today = BookingSlot::today();
        $now = now()->setTimezone((string) config('stable_bookings.timezone'))->format('H:i:s');

        return StableBooking::query()
            ->where('stable_bookings.stable_id', $stable->getKey())
            ->where('stable_bookings.status', StableBookingStatus::Confirmed->value)
            ->whereHas('slot', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('date', '<', $today)
                ->orWhere(fn (Builder $q) => $q->where('date', $today)->where('start_time', '<=', $now))));
    }

    private function find(Request $request, string $slug, string $reference): StableBooking
    {
        return static::query($this->ownerStable($request, $slug))->where('stable_bookings.reference', $reference)->firstOrFail();
    }

    /** The panel offers attended / no-show from the session's day on. */
    private function due(StableBooking $booking): bool
    {
        return $booking->slot !== null && $booking->slot->date->toDateString() <= BookingSlot::today();
    }

    private function run(callable $action): OwnerBookingResource|JsonResponse
    {
        try {
            return OwnerBookingResource::make($action());
        } catch (BookingUnavailable $e) {
            return $this->unavailable($e->getMessage());
        }
    }

    private function unavailable(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => 'booking_unavailable'], 409);
    }
}
