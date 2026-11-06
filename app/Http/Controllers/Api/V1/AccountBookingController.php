<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentGateway;
use App\Enums\StableBookingStatus;
use App\Http\Controllers\Api\V1\Concerns\PaginatesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Bookings\BookingResource;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\StableBooking;
use App\Services\Payments\Gateways\ThawaniGateway;
use App\Services\Payments\PaymentGateways;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\StableBookingActions;
use App\Services\Stables\StableReviews;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The signed-in customer's own bookings (those whose order is theirs, guest bookings from their phone
 * included once they signed in). Another customer's reference is simply not found.
 */
class AccountBookingController extends Controller
{
    use PaginatesListings;

    /**
     * My bookings
     *
     * `upcoming` (default): sessions from today on that are not cancelled, soonest first; `past`:
     * everything else, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'when' => ['nullable', Rule::in(['upcoming', 'past'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $today = BookingSlot::today();
        $upcoming = ($data['when'] ?? 'upcoming') === 'upcoming';
        $active = [StableBookingStatus::Pending->value, StableBookingStatus::Confirmed->value];

        $query = $this->bookings($request)
            ->join('booking_slots', 'booking_slots.id', '=', 'stable_bookings.booking_slot_id')
            ->select('stable_bookings.*')
            ->when(
                $upcoming,
                fn (Builder $q) => $q->where('booking_slots.date', '>=', $today)->whereIn('stable_bookings.status', $active)
                    ->orderBy('booking_slots.date')->orderBy('booking_slots.start_time'),
                fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('booking_slots.date', '<', $today)->orWhereNotIn('stable_bookings.status', $active))
                    ->orderByDesc('booking_slots.date')->orderByDesc('booking_slots.start_time'),
            );

        return BookingResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    /**
     * One of my bookings
     *
     * While it waits for an online payment, Thawani is asked about it first (at most every few
     * seconds), so a booking paid in the checkout shows as paid as soon as the app reopens it.
     */
    public function show(Request $request, string $reference, PaymentGateways $gateways, ThawaniGateway $thawani): BookingResource
    {
        $booking = $this->find($request, $reference);
        $order = $booking->order;

        // Thawani does not always send the browser back to our return page after a payment, and
        // the app refetches this the moment its checkout browser closes: settle it like that page would.
        if ($order && $order->isPayable() && $order->payment_method === PaymentGateway::Thawani && $order->payment_session_id
            && Cache::add('api.thawani-check.'.$order->getKey(), true, 5)) {
            try {
                $gateways->bind($thawani, $order)->reconcile($order);
                $booking->refresh();
            } catch (Throwable $e) {
                Log::warning('Thawani check on booking fetch failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);
            }
        }

        return BookingResource::make($booking);
    }

    /**
     * Cancel my booking
     *
     * Within the stable's cancellation window (an unpaid checkout any time before it starts). A paid
     * booking is refunded without the service fee. 409 `booking_unavailable` when it is too late.
     */
    public function cancel(Request $request, string $reference, StableBookingActions $actions): BookingResource|JsonResponse
    {
        $booking = $this->find($request, $reference);

        try {
            $actions->cancelByCustomer($booking);
        } catch (BookingUnavailable $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'booking_unavailable'], 409);
        }

        return BookingResource::make($booking->refresh());
    }

    /**
     * Review a session
     *
     * Once the stable marked it attended, within 60 days, once. Published at once as "First L.".
     */
    public function review(Request $request, string $reference, StableReviews $reviews): BookingResource|JsonResponse
    {
        $booking = $this->find($request, $reference);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $reviews->submit($booking, $this->customer($request), (int) $data['rating'], $data['comment'] ?? null);
        } catch (BookingUnavailable $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'booking_unavailable'], 409);
        }

        return BookingResource::make($booking->refresh());
    }

    /** @return Builder<StableBooking> */
    private function bookings(Request $request): Builder
    {
        return StableBooking::query()
            ->whereHas('order', fn (Builder $q) => $q->where('customer_id', $this->customer($request)->getKey()))
            // places left come with the slots, not one query per booking
            ->with(['slot' => fn ($q) => $q->withBookedRiders(), 'slot.offering', 'offering', 'stable.region', 'stable.city', 'order', 'review', 'packagePurchase.package']);
    }

    private function find(Request $request, string $reference): StableBooking
    {
        return $this->bookings($request)->where('reference', $reference)->firstOrFail();
    }

    private function customer(Request $request): Customer
    {
        return $request->user('customer_api');
    }
}
