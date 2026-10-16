<?php

namespace App\Http\Controllers\Site;

use App\Enums\StableOfferingType;
use App\Http\Controllers\Controller;
use App\Models\BookingSlot;
use App\Models\Country;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Models\StableOffering;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\CreateStableBooking;
use App\Services\Stables\SlotFinder;
use App\Services\Stables\StableBookingPricing;
use App\Services\Stables\StablePackages;
use App\Support\PhoneNumber;
use App\Support\Seo;
use App\Support\StableBookingForm;
use App\Support\StableBookingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The public booking engine: search every stable's open slots by date, place and riders; pick a
 * time on a service's page; fill in the riders and book. Paths all have two or more segments
 * (/bookings/…), so the /{slug} CMS route never shadows them.
 */
class StableBookingController extends Controller
{
    public const MAX_RIDERS = StableBookingForm::MAX_RIDERS;

    public function __construct(
        private readonly SlotFinder $finder,
        private readonly StableBookingPricing $pricing,
    ) {}

    public function search(Request $request): View
    {
        $today = BookingSlot::today();
        $maxDate = CarbonImmutable::parse($today)->addDays((int) config('stable_bookings.max_horizon_days', 120))->toDateString();

        $date = $this->date($request->query('date'), $today, $maxDate);
        $riders = $this->riders($request->query('riders'));
        $filters = [
            'region_id' => $request->integer('region_id') ?: null,
            'city_id' => $request->integer('city_id') ?: null,
            'type' => StableOfferingType::tryFrom((string) $request->query('type'))?->value,
            'time' => in_array($request->query('time'), SlotFinder::TIMES_OF_DAY, true) ? $request->query('time') : null,
        ];

        // one query for the whole week strip, then the chosen day out of it
        $weekStart = CarbonImmutable::parse($date)->max(CarbonImmutable::parse($today));
        $week = $this->finder->between($weekStart->toDateString(), $weekStart->addDays(6)->toDateString(), $riders, $filters);
        $slots = $week->filter(fn (BookingSlot $slot) => $slot->date->toDateString() === $date)->values();

        $countries = Country::query()->public()->with('region.city')->orderBy('en_name')->get();

        return view('site.bookings.search', [
            ...Seo::forSlug('bookings', __('stable_bookings.search.title').' — '.SiteSetting::siteName()),
            'date' => $date,
            'today' => $today,
            'riders' => $riders,
            'filters' => $filters,
            'results' => $slots->groupBy('stable_id')->map(fn ($stableSlots) => [
                'stable' => $stableSlots->first()->stable,
                'offerings' => $stableSlots->groupBy('stable_offering_id')->map(fn ($offeringSlots) => [
                    'offering' => $offeringSlots->first()->offering,
                    'slots' => $offeringSlots,
                ])->values(),
            ])->values(),
            'week' => collect(range(0, 6))->map(function (int $i) use ($weekStart, $week) {
                $day = $weekStart->addDays($i)->toDateString();

                return ['date' => $day, 'count' => $week->filter(fn (BookingSlot $slot) => $slot->date->toDateString() === $day)->count()];
            }),
            'nextDate' => $slots->isEmpty() ? $this->finder->nextAvailableDate($date, $riders, $filters) : null,
            'regions' => $countries->flatMap->region,
            'types' => StableOfferingType::options(),
        ]);
    }

    public function offering(Request $request, string $stable, StableOffering $offering): View
    {
        $stable = $this->liveStable($stable);
        abort_unless((int) $offering->stable_id === (int) $stable->getKey() && $offering->is_active, 404);

        $today = BookingSlot::today();
        $horizon = CarbonImmutable::parse($today)->addDays($stable->bookingSettings()->horizonDays() - 1)->toDateString();
        $riders = min($this->riders($request->query('riders') ?? $offering->min_riders), $offering->max_riders);
        $riders = max($riders, $offering->min_riders);

        $all = $this->finder->between($today, $horizon, $riders, ['offering_id' => $offering->getKey()]);
        $byDate = $all->groupBy(fn (BookingSlot $slot) => $slot->date->toDateString());
        $date = $this->date($request->query('date'), $today, $horizon);
        $date = $byDate->has($date) || $request->filled('date') ? $date : ($byDate->keys()->first() ?? $date);

        return view('site.bookings.offering', [
            'stable' => $stable,
            'offering' => $offering,
            'riders' => $riders,
            'date' => $date,
            'days' => $byDate->map->count(),
            'slots' => $byDate->get($date, collect()),
            'quote' => $this->pricing->quote($offering, $riders, $stable),
            'seoTitle' => $offering->name.' — '.$stable->name.' — '.SiteSetting::siteName(),
            'seoDescription' => $offering->description,
            'seoImage' => $offering->photo_url ?? $stable->cover_photo_url,
        ]);
    }

    public function create(Request $request, BookingSlot $slot, CreateStableBooking $bookings): View|RedirectResponse
    {
        $slot->load(['offering', 'stable']);
        $riders = $this->riders($request->query('riders') ?? $slot->offering?->min_riders);

        try {
            $bookings->assertBookable($slot, $riders);
        } catch (BookingUnavailable $e) {
            return $this->backToOffering($slot, $e->getMessage());
        }

        $customer = Auth::guard('customer')->user();

        return view('site.bookings.create', [
            'slot' => $slot,
            'stable' => $slot->stable,
            'offering' => $slot->offering,
            'riders' => $riders,
            'settings' => $slot->stable->bookingSettings(),
            'quote' => $this->pricing->quote($slot->offering, $riders, $slot->stable),
            'customer' => $customer,
            'packages' => app(StablePackages::class)->usableFor($customer, $slot->offering)
                ->filter(fn ($purchase) => $purchase->sessionsLeft() >= $riders && $slot->startsAt()->lte($purchase->expires_at))
                ->values(),
            'seoTitle' => __('stable_bookings.form.title').' — '.SiteSetting::siteName(),
            'noindex' => true,
        ]);
    }

    public function store(Request $request, BookingSlot $slot, CreateStableBooking $bookings): RedirectResponse
    {
        $slot->load(['offering', 'stable']);
        $settings = $slot->stable?->bookingSettings() ?? StableBookingSettings::from([]);
        $count = $this->riders($request->input('riders_count'));
        $quote = $slot->offering ? $this->pricing->quote($slot->offering, $count, $slot->stable) : null;

        $customer = Auth::guard('customer')->user();
        $data = $request->validate([
            ...StableBookingForm::rules($settings, $count, $quote?->isFree() ?? true, (bool) $customer),
            'phone' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail): void {
                $phone = PhoneNumber::normalize($value);

                if ($phone === null || strlen($phone) < 10 || strlen($phone) > 15) {
                    $fail(__('account.phone_invalid'));
                }
            }],
        ], [], StableBookingForm::attributeNames($count));

        $riders = StableBookingForm::riders($data, $count, $settings);

        $withPackage = ($data['payment_option'] ?? null) === CreateStableBooking::PACKAGE;

        try {
            $booking = $bookings->handle($slot, $riders, [
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'locale' => app()->getLocale(),
                // a package is the signed-in customer's own, whatever number they typed
                'customer_id' => $customer && ($withPackage || PhoneNumber::normalize($data['phone']) === $customer->phone) ? $customer->getKey() : null,
            ], $data['payment_option'] ?? CreateStableBooking::ONLINE, $withPackage ? (int) ($data['package_purchase_id'] ?? 0) : null);
        } catch (BookingUnavailable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $order = $booking->order;

        return $order->isPayable()
            ? redirect()->route('payment.start', $order->order_number)
            : redirect()->route('orders.show', $order->order_number)->with('status', __('stable_bookings.form.booked'));
    }

    private function liveStable(string $slug): Stable
    {
        $stable = Stable::query()->active()->where('slug', $slug)->firstOrFail();
        abort_unless($stable->acceptsBookings(), 404);

        return $stable;
    }

    private function backToOffering(BookingSlot $slot, string $message): RedirectResponse
    {
        if ($slot->stable && $slot->offering && $slot->stable->acceptsBookings()) {
            return redirect()
                ->route('bookings.offering', ['stable' => $slot->stable->slug, 'offering' => $slot->offering->getKey(), 'date' => $slot->date->toDateString()])
                ->with('error', $message);
        }

        return redirect()->route('bookings.search')->with('error', $message);
    }

    private function date(mixed $value, string $min, string $max): string
    {
        $valid = is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
        $date = $valid ? $value : $min;

        return max($min, min($max, $date));
    }

    private function riders(mixed $value): int
    {
        return StableBookingForm::riderCount($value);
    }
}
