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
use App\Support\StableBookingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The public booking engine: search every stable's open slots by date, place and riders; pick a
 * time on a service's page; fill in the riders and book. Paths all have two or more segments
 * (/bookings/…), so the /{slug} CMS route never shadows them.
 */
class StableBookingController extends Controller
{
    public const MAX_RIDERS = 20;

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
        $data = $request->validate($this->rules($settings, $count, $quote?->isFree() ?? true, (bool) $customer), [], $this->attributeNames($count));

        $riders = collect($data['riders'])->take($count)->map(fn (array $rider) => array_filter([
            'name' => trim($rider['name']),
            'age' => isset($rider['age']) && $rider['age'] !== '' ? (int) $rider['age'] : null,
            'level' => $rider['level'] ?? null,
            'weight' => isset($rider['weight']) && $rider['weight'] !== '' ? (float) $rider['weight'] : null,
            'height' => isset($rider['height']) && $rider['height'] !== '' ? (float) $rider['height'] : null,
            'guardian_name' => $rider['guardian_name'] ?? null,
            'guardian_phone' => PhoneNumber::normalize($rider['guardian_phone'] ?? null),
            'notes' => $rider['notes'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''))->values()->all();

        if ($settings->showsRiderField('waiver') && ($data['waiver'] ?? false)) {
            $riders = array_map(fn (array $rider) => $rider + ['waiver_accepted_at' => now()->toIso8601String()], $riders);
        }

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

    /** @return array<string, mixed> */
    private function rules(StableBookingSettings $settings, int $count, bool $free, bool $signedIn = false): array
    {
        $field = fn (string $name, array $rules) => $settings->showsRiderField($name)
            ? [$settings->requiresRiderField($name) ? 'required' : 'nullable', ...$rules]
            : ['prohibited'];

        return [
            'riders_count' => ['required', 'integer', 'min:1', 'max:'.self::MAX_RIDERS],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail): void {
                $phone = PhoneNumber::normalize($value);

                if ($phone === null || strlen($phone) < 10 || strlen($phone) > 15) {
                    $fail(__('account.phone_invalid'));
                }
            }],
            'email' => ['nullable', 'email', 'max:190'],
            'payment_option' => $free ? ['nullable'] : ['required', Rule::in([...$settings->paymentOptions(), ...($signedIn ? [CreateStableBooking::PACKAGE] : [])])],
            'package_purchase_id' => ['nullable', 'required_if:payment_option,package', 'integer'],
            'riders' => ['required', 'array', 'size:'.$count],
            'riders.*.name' => ['required', 'string', 'max:120'],
            'riders.*.age' => $field('age', ['integer', 'min:2', 'max:100']),
            'riders.*.level' => $field('level', [Rule::in(StableBookingSettings::LEVELS)]),
            'riders.*.weight' => $field('weight', ['numeric', 'min:10', 'max:250']),
            'riders.*.height' => $field('height', ['numeric', 'min:50', 'max:250']),
            'riders.*.guardian_name' => $field('guardian', ['string', 'max:120']),
            'riders.*.guardian_phone' => $field('guardian', ['string', 'max:20']),
            'riders.*.notes' => $field('notes', ['string', 'max:500']),
            'waiver' => $settings->showsRiderField('waiver') ? ['accepted'] : ['prohibited'],
        ];
    }

    /** "Rider 2: age" instead of "riders.1.age" in the error messages. */
    private function attributeNames(int $count): array
    {
        $names = [];

        foreach (range(0, $count - 1) as $i) {
            foreach (['name', 'age', 'level', 'weight', 'height', 'guardian_name', 'guardian_phone', 'notes'] as $field) {
                $names["riders.{$i}.{$field}"] = __('stable_bookings.form.rider_n', ['n' => $i + 1]).': '.__('stable_bookings.rider.'.$field);
            }
        }

        return $names;
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
        return max(1, min(self::MAX_RIDERS, (int) ($value ?: 1)));
    }
}
