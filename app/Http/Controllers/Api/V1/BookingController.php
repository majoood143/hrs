<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StableOfferingType;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Bookings\BookingResource;
use App\Http\Resources\V1\Bookings\BookingStableResource;
use App\Http\Resources\V1\Bookings\OfferingResource;
use App\Http\Resources\V1\Bookings\QuoteResource;
use App\Http\Resources\V1\Bookings\SlotResource;
use App\Http\Resources\V1\OptionResource;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\StableOffering;
use App\Models\StablePackagePurchase;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\CreateStableBooking;
use App\Services\Stables\SlotFinder;
use App\Services\Stables\StableBookingPricing;
use App\Services\Stables\StablePackages;
use App\Support\StableBookingForm;
use App\Support\StableBookingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The website's booking engine (`/bookings/*`, `Site\StableBookingController`) for the app: search
 * every stable's open times, a service's days and times, one time's booking form, and booking it.
 * What is bookable is decided by `SlotFinder` / `CreateStableBooking`, as on the site. Booking needs a
 * signed-in customer (the booking is theirs, with their phone).
 */
class BookingController extends Controller
{
    public function __construct(
        private readonly SlotFinder $finder,
        private readonly StableBookingPricing $pricing,
    ) {}

    /**
     * Search open times
     *
     * Every stable's bookable times on one day, grouped by stable and service, plus the 7-day strip
     * from that day (times per day) and, when the day is empty, the next day with an opening.
     *
     * @unauthenticated
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            /** Defaults to today (Muscat); clamped to the booking horizon. @example 2026-10-20 */
            'date' => ['nullable', 'date_format:Y-m-d'],
            'riders' => ['nullable', 'integer', 'min:1', 'max:'.StableBookingForm::MAX_RIDERS],
            'region_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(StableOfferingType::class)],
            'time' => ['nullable', Rule::in(SlotFinder::TIMES_OF_DAY)],
        ]);

        $today = BookingSlot::today();
        $maxDate = CarbonImmutable::parse($today)->addDays((int) config('stable_bookings.max_horizon_days', 120))->toDateString();
        $date = max($today, min($maxDate, $data['date'] ?? $today));
        $riders = StableBookingForm::riderCount($data['riders'] ?? 1);
        $filters = [
            'region_id' => $data['region_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'type' => $data['type'] ?? null,
            'time' => $data['time'] ?? null,
        ];

        // one query for the whole week strip, then the chosen day out of it (as the website does)
        $weekStart = CarbonImmutable::parse($date);
        $week = $this->finder->between($weekStart->toDateString(), $weekStart->addDays(6)->toDateString(), $riders, $filters);
        $slots = $week->filter(fn (BookingSlot $slot) => $slot->date->toDateString() === $date)->values();

        return response()->json([
            'date' => $date,
            'today' => $today,
            'max_date' => $maxDate,
            'riders' => $riders,
            /** Seven days from `date`, each with its number of open times. */
            'week' => collect(range(0, 6))->map(function (int $i) use ($weekStart, $week) {
                $day = $weekStart->addDays($i)->toDateString();

                return ['date' => $day, 'count' => $week->filter(fn (BookingSlot $slot) => $slot->date->toDateString() === $day)->count()];
            })->all(),
            /** Only when nothing is open on `date`. @var string|null */
            'next_date' => $slots->isEmpty() ? $this->finder->nextAvailableDate($date, $riders, $filters) : null,
            'results' => $slots->groupBy('stable_id')->map(fn ($stableSlots) => [
                'stable' => BookingStableResource::make($stableSlots->first()->stable),
                'offerings' => $stableSlots->groupBy('stable_offering_id')->map(fn ($offeringSlots) => [
                    'offering' => OfferingResource::make($offeringSlots->first()->offering),
                    'quote' => QuoteResource::make($this->pricing->quote($offeringSlots->first()->offering, $riders, $offeringSlots->first()->stable)),
                    'slots' => SlotResource::collection($offeringSlots),
                ])->values()->all(),
            ])->values()->all(),
            'types' => collect(StableOfferingType::options())->map(fn (string $label, string $value) => OptionResource::of($value, $label))->values()->all(),
            'times' => collect(SlotFinder::TIMES_OF_DAY)->map(fn (string $time) => OptionResource::of($time, __('stable_bookings.search.times.'.$time)))->all(),
        ]);
    }

    /**
     * A service's days and times
     *
     * Every day with open times up to the stable's horizon (`days`), and the times of `date` (the
     * first open day when no date is given).
     *
     * @unauthenticated
     */
    public function offering(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            /** Clamped to the service's min/max riders. */
            'riders' => ['nullable', 'integer', 'min:1', 'max:'.StableBookingForm::MAX_RIDERS],
        ]);

        $offering = $this->liveOffering($id);
        $stable = $offering->stable;

        $today = BookingSlot::today();
        $horizon = CarbonImmutable::parse($today)->addDays($stable->bookingSettings()->horizonDays() - 1)->toDateString();
        $riders = max($offering->min_riders, min($offering->max_riders, StableBookingForm::riderCount($data['riders'] ?? $offering->min_riders)));

        $byDate = $this->finder->between($today, $horizon, $riders, ['offering_id' => $offering->getKey()])
            ->groupBy(fn (BookingSlot $slot) => $slot->date->toDateString());
        $date = isset($data['date']) ? max($today, min($horizon, $data['date'])) : ($byDate->keys()->first() ?? $today);

        return response()->json([
            'stable' => BookingStableResource::make($stable->loadMissing(['region', 'city'])),
            'offering' => OfferingResource::make($offering),
            'riders' => $riders,
            'date' => $date,
            /** Days with open times, in order. */
            'days' => $byDate->map(fn ($slots, string $day) => ['date' => $day, 'count' => $slots->count()])->values()->all(),
            'slots' => SlotResource::collection($byDate->get($date, collect())),
            'quote' => QuoteResource::make($this->pricing->quote($offering, $riders, $stable)),
        ]);
    }

    /**
     * One time's booking form
     *
     * The time, the price for `riders`, the rider fields this stable asks for, how one can pay and,
     * for a signed-in customer, their packages that can pay for it. 409 `booking_unavailable` when
     * the time can no longer be booked for that many riders.
     *
     * @unauthenticated
     */
    public function slot(Request $request, int $id, CreateStableBooking $bookings): JsonResponse
    {
        $data = $request->validate([
            'riders' => ['nullable', 'integer', 'min:1', 'max:'.StableBookingForm::MAX_RIDERS],
        ]);

        $slot = BookingSlot::query()->with(['offering', 'stable.region', 'stable.city', 'trainer'])->findOrFail($id);
        $riders = StableBookingForm::riderCount($data['riders'] ?? $slot->offering?->min_riders);

        try {
            $bookings->assertBookable($slot, $riders);
        } catch (BookingUnavailable $e) {
            return $this->unavailable($e);
        }

        $settings = $slot->stable->bookingSettings();
        $quote = $this->pricing->quote($slot->offering, $riders, $slot->stable);
        $customer = $request->user('customer_api');
        $hours = $settings->cancellationHours();

        return response()->json([
            'stable' => BookingStableResource::make($slot->stable),
            'offering' => OfferingResource::make($slot->offering),
            'slot' => SlotResource::make($slot),
            'riders' => $riders,
            'quote' => QuoteResource::make($quote),
            /** The rider fields to ask, in order (name is always asked and required). */
            'rider_fields' => collect(StableBookingForm::RIDER_FIELDS)
                ->filter(fn (string $field) => $settings->showsRiderField($field))
                ->map(fn (string $field) => [
                    /** age | level | weight | height | guardian (name + phone) | notes */
                    'key' => $field,
                    'required' => $settings->requiresRiderField($field),
                ])->values()->all(),
            'levels' => collect(StableBookingSettings::LEVELS)->map(fn (string $level) => OptionResource::of($level, __('stable_bookings.levels.'.$level)))->all(),
            /** When set, the customer must accept it for every rider (`waiver: true`). @var string|null */
            'waiver' => $settings->showsRiderField('waiver') ? $settings->waiverText(app()->getLocale()) : null,
            /** Empty when it is free. */
            'payment_options' => $quote->isFree() ? [] : collect($settings->paymentOptions())->map(fn (string $option) => [
                'value' => $option,
                'label' => __('stable_bookings.payment_options.'.$option),
                'hint' => __('stable_bookings.payment_options.'.$option.'_hint'),
            ])->values()->all(),
            /** The signed-in customer's packages for this service with enough sessions left. */
            'packages' => $customer instanceof Customer
                ? app(StablePackages::class)->usableFor($customer, $slot->offering)
                    ->filter(fn (StablePackagePurchase $purchase) => $purchase->sessionsLeft() >= $riders && $slot->startsAt()->lte($purchase->expires_at))
                    ->map(fn (StablePackagePurchase $purchase) => [
                        'id' => (int) $purchase->id,
                        'name' => (string) ($purchase->package?->name ?? $purchase->name),
                        'sessions_left' => $purchase->sessionsLeft(),
                        'expires_at' => $purchase->expires_at?->toIso8601String(),
                    ])->values()->all()
                : [],
            'cancellation_policy' => $hours === null ? __('stable_bookings.policy.no_online_cancel') : __('stable_bookings.policy.cancel_until', ['hours' => $hours]),
        ]);
    }

    /**
     * Book a time
     *
     * One entry in `riders` per rider. Answers 201 with the booking: `pay_url` is set when it waits
     * for an online payment (open it in a browser; the places are held until the checkout expires),
     * else it is already confirmed (free, at the stable, or a package). 409 `booking_unavailable`
     * when the time was taken meanwhile.
     */
    public function store(Request $request, int $id, CreateStableBooking $bookings): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_api');
        $slot = BookingSlot::query()->with(['offering', 'stable'])->findOrFail($id);
        $settings = $slot->stable?->bookingSettings() ?? StableBookingSettings::from([]);
        $count = is_array($request->input('riders')) ? StableBookingForm::riderCount(count($request->input('riders'))) : 1;
        $quote = $slot->offering ? $this->pricing->quote($slot->offering, $count, $slot->stable) : null;

        $request->merge(['riders_count' => $count]);
        $data = $request->validate(
            StableBookingForm::rules($settings, $count, $quote?->isFree() ?? true, signedIn: true),
            [],
            StableBookingForm::attributeNames($count),
        );

        $withPackage = ($data['payment_option'] ?? null) === CreateStableBooking::PACKAGE;

        try {
            $booking = $bookings->handle($slot, StableBookingForm::riders($data, $count, $settings), [
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $customer->phone,
                'locale' => app()->getLocale(),
                'customer_id' => $customer->getKey(),
            ], $data['payment_option'] ?? CreateStableBooking::ONLINE, $withPackage ? (int) ($data['package_purchase_id'] ?? 0) : null);
        } catch (BookingUnavailable $e) {
            return $this->unavailable($e);
        }

        // the booking form fills these in next time
        $customer->forceFill(['name' => $customer->name ?: $data['name'], 'email' => $customer->email ?: ($data['email'] ?? null)])->save();

        return BookingResource::make($booking->refresh())->response()->setStatusCode(201);
    }

    private function liveOffering(int $id): StableOffering
    {
        $offering = StableOffering::query()->with('stable')->findOrFail($id);
        abort_unless($offering->is_active && $offering->stable?->acceptsBookings(), 404);

        return $offering;
    }

    private function unavailable(BookingUnavailable $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage(), 'code' => 'booking_unavailable'], 409);
    }
}
