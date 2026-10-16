<?php

namespace Database\Seeders;

use App\Enums\FeeType;
use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\StableApprovalStatus;
use App\Enums\StableBookingStatus;
use App\Events\StableBookingCancelled;
use App\Events\StableBookingConfirmed;
use App\Events\StablePackageActivated;
use App\Events\StableReviewed;
use App\Models\BookingSlot;
use App\Models\City;
use App\Models\Customer;
use App\Models\OrderEvent;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StableOffering;
use App\Models\StablePackage;
use App\Models\StablePackagePurchase;
use App\Models\StableReview;
use App\Models\StableSchedule;
use App\Models\StableService;
use App\Models\StableTrainer;
use App\Models\User;
use App\Services\Payments\OrderPaymentService;
use App\Services\Stables\CreateStableBooking;
use App\Services\Stables\SlotGenerator;
use App\Services\Stables\StableBookingPricing;
use App\Services\Stables\StablePackages;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * Demo data for stable bookings (development and presentations only; refuses to run in production):
 * three approved stables with services, weekly schedules → open slots, lesson packages, past sessions
 * with reviews, other guests' bookings (so places run low), a stable owner for `/stable`, and a demo
 * customer with an upcoming booking, a session to review, a cancelled one and an active package.
 *
 *     php artisan db:seed --class=StableBookingDemoSeeder
 *
 * Re-runnable: it first deletes what it made before (stables whose slug starts with "demo-", their
 * orders, and the demo owner/customer). Booking notifications are held back while it runs.
 */
class StableBookingDemoSeeder extends Seeder
{
    public const OWNER_EMAIL = 'demo-owner@muhra.test';

    public const OWNER_PASSWORD = 'password';

    /** Sign in to the app with 90000002 (the code shows on screen while the SMS driver is "demo"). */
    public const CUSTOMER_PHONE = '96890000002';

    private const GUESTS = [
        ['Salim Al Harthy', '96891110001'], ['Maryam Al Balushi', '96891110002'], ['Ahmed Al Rawahi', '96891110003'],
        ['Fatma Al Hinai', '96891110004'], ['Khalid Al Saadi', '96891110005'], ['Noor Al Maskari', '96891110006'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('StableBookingDemoSeeder is for development only.');

            return;
        }

        Event::fake([StableBookingConfirmed::class, StableBookingCancelled::class, StablePackageActivated::class, StableReviewed::class]);

        $this->wipe();

        $owner = User::query()->forceCreate([
            'name' => 'Demo Stable Owner',
            'email' => self::OWNER_EMAIL,
            'password' => bcrypt(self::OWNER_PASSWORD),
            'type' => User::TYPE_STABLE_OWNER,
            'phone' => '96890000001',
            'locale' => 'en',
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
        ]);

        $customer = Customer::query()->forceCreate([
            'phone' => self::CUSTOMER_PHONE,
            'name' => 'Demo Customer',
            'email' => 'demo-customer@muhra.test',
            'locale' => 'en',
            'phone_verified_at' => now(),
        ]);

        $covers = $this->covers();
        $stables = [];

        foreach ($this->stables() as $i => $data) {
            $stables[] = $this->makeStable($data, $owner, $covers[$i % count($covers)]);
        }

        // other guests' bookings in the coming days, so some times show only a place or two left
        $this->guestBookings($stables);

        // sessions already held, with their reviews (the stables' ratings)
        foreach ($stables as $stable) {
            $this->pastSessions($stable);
        }

        $this->demoCustomer($customer, $stables);

        $this->command?->info(sprintf(
            'Demo stables: %s. Owner panel: /stable (%s / %s). App sign-in: %s.',
            collect($stables)->pluck('slug')->implode(', '),
            self::OWNER_EMAIL,
            self::OWNER_PASSWORD,
            substr(self::CUSTOMER_PHONE, 3),
        ));
    }

    /** @return list<array<string, mixed>> */
    private function stables(): array
    {
        $riding = ['type' => 'riding_training'];

        return [
            [
                'slug' => 'demo-al-rahba-riding-club',
                'en_name' => 'Al Rahba Riding Club',
                'ar_name' => 'نادي الرحبة للفروسية',
                'city' => 'Seeb',
                'en_description' => "A family riding club by the sea in Seeb. Lessons for every level on calm, well-schooled Arabian horses, with qualified instructors.\nHelmets and body protectors are provided.",
                'ar_description' => "نادٍ عائلي للفروسية قرب البحر في السيب. حصص لكل المستويات على خيول عربية هادئة ومدرّبة جيدًا، مع مدربين مؤهلين.\nنوفّر الخوذات وواقيات الجسم.",
                'phone' => '96824000101',
                'email' => 'bookings@alrahba.example',
                'map_link' => 'https://maps.google.com/?q=23.6705,58.1891',
                'settings' => [
                    'payment_options' => ['online', 'at_stable'],
                    'cancellation_hours' => 24,
                    'rider_fields' => ['waiver' => ['show' => true, 'required' => true], 'weight' => ['show' => true, 'required' => false]],
                    'waiver_text' => [
                        'en' => 'Horse riding carries risk. I confirm every rider is fit to ride, will follow the instructor and wear the safety gear provided.',
                        'ar' => 'ركوب الخيل ينطوي على مخاطر. أؤكد أن كل راكب لائق صحيًا، وسيتبع تعليمات المدرب ويرتدي معدات السلامة المقدمة.',
                    ],
                ],
                'trainers' => [['Coach Hamed', 'المدرب حامد'], ['Coach Laila', 'المدربة ليلى']],
                'offerings' => [
                    $riding + ['en_name' => 'Beginner group lesson', 'ar_name' => 'حصة جماعية للمبتدئين', 'en_description' => 'Your first steps in the saddle: mounting, walking and trotting in the arena, in a small group.', 'ar_description' => 'خطواتك الأولى على السرج: الركوب والمشي والخبب في الميدان ضمن مجموعة صغيرة.', 'price' => 12, 'duration_minutes' => 60, 'capacity' => 4, 'max_riders' => 2,
                        'schedule' => [[0, 2, 4, 6], ['08:00', '16:30', '18:00']]],
                    $riding + ['en_name' => 'Private lesson', 'ar_name' => 'حصة خاصة', 'en_description' => 'One rider, one instructor: work on your position, transitions or first canter at your own pace.', 'ar_description' => 'راكب واحد ومدرب واحد: حسّن جلستك وانتقالاتك أو ابدأ الكانتر بالسرعة التي تناسبك.', 'price' => 25, 'duration_minutes' => 45, 'capacity' => 1, 'max_riders' => 1,
                        'schedule' => [[0, 1, 2, 3, 4], ['07:00', '17:00']]],
                    $riding + ['en_name' => 'Beach ride', 'ar_name' => 'جولة على الشاطئ', 'en_description' => 'An early-morning ride along the beach for riders who can already trot confidently.', 'ar_description' => 'جولة صباحية على الشاطئ للراكبين القادرين على الخبب بثقة.', 'price' => 30, 'duration_minutes' => 90, 'capacity' => 6, 'max_riders' => 3, 'booking_cutoff_minutes' => 720,
                        'schedule' => [[5, 6], ['06:30']]],
                ],
                'packages' => [['Beginner group lesson', 'Five group lessons', 'خمس حصص جماعية', 5, 50, 60], ['Private lesson', 'Ten private lessons', 'عشر حصص خاصة', 10, 210, 90]],
            ],
            [
                'slug' => 'demo-wadi-al-khoud-equestrian',
                'en_name' => 'Wadi Al Khoud Equestrian Centre',
                'ar_name' => 'مركز وادي الخوض للفروسية',
                'city' => 'Bawshar',
                'en_description' => 'Children learn to ride on ponies, in a shaded arena next to the wadi. Parents can watch from the café.',
                'ar_description' => 'يتعلم الأطفال الركوب على المهور في ميدان مظلل بجانب الوادي، ويمكن للأهل المتابعة من المقهى.',
                'phone' => '96824000202',
                'email' => 'hello@wadialkhoud.example',
                'map_link' => 'https://maps.google.com/?q=23.5720,58.1230',
                'settings' => [
                    'payment_options' => ['online'],
                    'cancellation_hours' => 12,
                    'rider_fields' => ['guardian' => ['show' => true, 'required' => true], 'level' => ['show' => false, 'required' => false]],
                ],
                'trainers' => [['Coach Aisha', 'المدربة عائشة']],
                'offerings' => [
                    $riding + ['en_name' => 'Kids pony ride', 'ar_name' => 'جولة الأطفال على المهر', 'en_description' => 'A led pony ride for children aged 3 to 8, with a helper walking beside every pony.', 'ar_description' => 'جولة مقودة على المهر للأطفال من 3 إلى 8 سنوات، مع مرافق يمشي بجانب كل مهر.', 'price' => 6, 'duration_minutes' => 30, 'capacity' => 6, 'max_riders' => 3,
                        'schedule' => [[4, 5, 6], ['09:00', '10:00', '16:00', '17:00']]],
                    $riding + ['en_name' => 'Junior riding school', 'ar_name' => 'مدرسة الفروسية للناشئين', 'en_description' => 'Weekly lessons for 8 to 14 year olds: grooming, tacking up and riding on their own.', 'ar_description' => 'حصص أسبوعية للأعمار من 8 إلى 14 سنة: العناية بالخيل وتجهيزها والركوب باستقلالية.', 'price' => 15, 'duration_minutes' => 60, 'capacity' => 5, 'max_riders' => 2,
                        'schedule' => [[0, 2, 4], ['16:00', '17:30']]],
                ],
                'packages' => [['Junior riding school', 'Junior term (8 lessons)', 'فصل الناشئين (8 حصص)', 8, 100, 60]],
            ],
            [
                'slug' => 'demo-salalah-sands-stables',
                'en_name' => 'Salalah Sands Stables',
                'ar_name' => 'إسطبلات رمال صلالة',
                'city' => 'Salalah',
                'en_description' => 'Trail rides through the Dhofar khareef landscape and desert sunset rides on endurance Arabians.',
                'ar_description' => 'جولات في طبيعة خريف ظفار وجولات غروب صحراوية على خيول عربية للقدرة.',
                'phone' => '96823000303',
                'email' => 'ride@salalahsands.example',
                'map_link' => 'https://maps.google.com/?q=17.0194,54.0897',
                'settings' => [
                    'payment_options' => ['online', 'at_stable'],
                    'cancellation_hours' => 48,
                ],
                'trainers' => [['Guide Musallam', 'المرشد مسلّم']],
                'offerings' => [
                    $riding + ['en_name' => 'Desert sunset ride', 'ar_name' => 'جولة الغروب في الصحراء', 'en_description' => 'Two hours across the dunes, ending with Omani coffee as the sun goes down.', 'ar_description' => 'ساعتان عبر الكثبان تنتهيان بالقهوة العُمانية مع غروب الشمس.', 'price' => 35, 'duration_minutes' => 120, 'capacity' => 8, 'max_riders' => 4,
                        'schedule' => [[3, 4, 5, 6], ['16:30']]],
                    $riding + ['en_name' => 'Introduction to riding', 'ar_name' => 'مقدمة في ركوب الخيل', 'en_description' => 'A gentle first lesson for adults who have never ridden before.', 'ar_description' => 'حصة أولى هادئة للبالغين الذين لم يركبوا الخيل من قبل.', 'price' => 0, 'duration_minutes' => 30, 'capacity' => 3, 'max_riders' => 1,
                        'schedule' => [[6], ['10:00']]],
                ],
                'packages' => [],
            ],
        ];
    }

    private function makeStable(array $data, User $owner, ?string $cover): Stable
    {
        $city = City::query()->where('en_name', $data['city'])->first() ?? City::query()->firstOrFail();
        $region = $city->region;

        $stable = new Stable([
            'en_name' => $data['en_name'],
            'ar_name' => $data['ar_name'],
            'country_id' => $region->country_id,
            'region_id' => $region->id,
            'city_id' => $city->id,
            'address' => $city->en_name.', '.$region->en_name,
            'map_link' => $data['map_link'],
            'en_description' => $data['en_description'],
            'ar_description' => $data['ar_description'],
            'cover_photo' => $cover,
            'phone' => $data['phone'],
            'email' => $data['email'],
            'is_active' => true,
            'opening_hours' => collect(Stable::DAYS)->mapWithKeys(fn (string $day) => [$day => ['opens_at' => '06:00', 'closes_at' => '20:00', 'closed' => $day === 'mon']])->all(),
        ]);
        $stable->slug = $data['slug'];
        $stable->forceFill([
            'approval_status' => StableApprovalStatus::Approved,
            'approved_at' => now(),
            'commission_type' => FeeType::Percentage,
            'commission_value' => 10,
            'booking_settings' => $data['settings'] + ['horizon_days' => 30, 'alert_channels' => ['email'], 'staff_enabled' => true],
        ])->save();

        $stable->members()->attach($owner->id, ['role' => 'owner']);
        $stable->services()->sync(StableService::query()->whereIn('en_name', ['Riding Lessons', 'Training'])->pluck('id'));

        $trainers = collect($data['trainers'])->map(fn (array $names) => StableTrainer::create(['stable_id' => $stable->id, 'en_name' => $names[0], 'ar_name' => $names[1]]));

        foreach ($data['offerings'] as $i => $offeringData) {
            [$weekdays, $times] = $offeringData['schedule'];
            unset($offeringData['schedule']);

            $offering = StableOffering::create($offeringData + ['stable_id' => $stable->id, 'photo' => $i === 0 ? $cover : null]);
            StableSchedule::create([
                'stable_id' => $stable->id,
                'stable_offering_id' => $offering->id,
                'weekdays' => $weekdays,
                'start_times' => $times,
                'trainer_id' => $trainers[$i % $trainers->count()]->id,
                'valid_from' => BookingSlot::today(),
            ]);
        }

        foreach ($data['packages'] as [$offeringName, $en, $ar, $sessions, $price, $days]) {
            StablePackage::create([
                'stable_id' => $stable->id,
                'stable_offering_id' => $stable->offerings()->where('en_name', $offeringName)->value('id'),
                'en_name' => $en,
                'ar_name' => $ar,
                'en_description' => "{$sessions} sessions to use within {$days} days.",
                'ar_description' => "{$sessions} حصص تُستخدم خلال {$days} يومًا.",
                'sessions' => $sessions,
                'price' => $price,
                'validity_days' => $days,
            ]);
        }

        app(SlotGenerator::class)->syncStable($stable);

        return $stable->refresh();
    }

    /** @param  list<Stable>  $stables */
    private function guestBookings(array $stables): void
    {
        $bookings = app(CreateStableBooking::class);
        $guest = 0;

        foreach ($stables as $stable) {
            $slots = BookingSlot::query()->with(['offering', 'stable'])->where('stable_id', $stable->id)
                ->whereBetween('date', [BookingSlot::today(), CarbonImmutable::parse(BookingSlot::today())->addDays(10)->toDateString()])
                ->orderBy('date')->orderBy('start_time')->get()
                ->filter(fn (BookingSlot $slot) => $slot->bookingClosesAt()->isFuture() && $slot->offering->capacity > 1);

            // every third group time gets guests, leaving one or two places
            foreach ($slots->values() as $i => $slot) {
                if ($i % 3 !== 0) {
                    continue;
                }

                $riders = min($slot->offering->max_riders, max(1, $slot->capacity - 2 + ($i % 2)));
                [$name, $phone] = self::GUESTS[$guest++ % count(self::GUESTS)];

                try {
                    $booking = $bookings->handle($slot, $this->riders($riders, $name), ['name' => $name, 'phone' => $phone, 'locale' => 'en'], $this->offlineOption($stable));

                    // an online-only stable: paid (as through the demo gateway), or the expiry sweep would cancel it
                    if ($booking->order->isPayable()) {
                        app(OrderPaymentService::class)->applyPaid($booking->order, PaymentGateway::Demo, 'demo-seed-'.$booking->reference);
                    }
                } catch (\Throwable) {
                    // a free or online-only stable: leave that time open
                }
            }
        }
    }

    /** Three past sessions per stable, attended, two of them reviewed. */
    private function pastSessions(Stable $stable): void
    {
        $reviews = [
            [5, 'Patient instructor and lovely calm horses. My daughter cannot wait to come back!', 'مدرب صبور وخيول هادئة جدًا. ابنتي متحمسة للعودة!', 'Thank you! See you next week.'],
            [4, 'Well organised and safe. The arena gets busy in the evening.', 'منظم وآمن. الميدان مزدحم قليلًا في المساء.', null],
            [5, 'Best riding experience in Oman so far.', 'أفضل تجربة ركوب خيل في عُمان حتى الآن.', null],
        ];

        foreach ($stable->offerings()->where('price', '>', 0)->get()->take(2) as $o => $offering) {
            foreach ([3, 9, 16] as $k => $daysAgo) {
                $slot = $this->pastSlot($offering, $daysAgo, $k);
                [$name, $phone] = self::GUESTS[($o * 3 + $k) % count(self::GUESTS)];
                $booking = $this->heldBooking($slot, $name, $phone, 1, null);

                if ($k < 2) {
                    [$rating, $en, $ar, $reply] = $reviews[($o + $k) % count($reviews)];
                    StableReview::create([
                        'stable_id' => $stable->id,
                        'stable_booking_id' => $booking->id,
                        'stable_offering_id' => $offering->id,
                        'author_name' => $name,
                        'rating' => $rating,
                        'comment' => $o % 2 ? $ar : $en,
                        'reply' => $reply,
                        'replied_at' => $reply ? now() : null,
                    ]);
                }
            }
        }
    }

    /** @param  list<Stable>  $stables */
    private function demoCustomer(Customer $customer, array $stables): void
    {
        [$rahba, $wadi, $salalah] = $stables;
        $who = ['name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone, 'locale' => 'en', 'customer_id' => $customer->id];

        // upcoming: a confirmed lesson paid at the stable, in a few days
        $slot = BookingSlot::query()->with(['offering', 'stable'])->where('stable_id', $rahba->id)
            ->where('date', '>=', CarbonImmutable::parse(BookingSlot::today())->addDays(2)->toDateString())
            ->whereHas('offering', fn ($q) => $q->where('en_name', 'Beginner group lesson'))
            ->orderBy('date')->orderBy('start_time')->get()
            ->first(fn (BookingSlot $slot) => $slot->remainingPlaces() >= 2);

        if ($slot) {
            app(CreateStableBooking::class)->handle($slot, [
                ['name' => 'Demo Customer', 'age' => 34, 'level' => 'beginner', 'waiver_accepted_at' => now()->toIso8601String()],
                ['name' => 'Sara', 'age' => 10, 'level' => 'beginner', 'waiver_accepted_at' => now()->toIso8601String()],
            ], $who, CreateStableBooking::AT_STABLE);
        }

        // past and attended, not reviewed yet: the app offers "Rate your session"
        $past = $this->pastSlot($wadi->offerings()->where('en_name', 'Junior riding school')->first(), 4, 0);
        $this->heldBooking($past, $customer->name, $customer->phone, 1, $customer);

        // cancelled by the stable, with a reason
        $later = BookingSlot::query()->where('stable_id', $salalah->id)
            ->whereHas('offering', fn ($q) => $q->where('price', '>', 0))
            ->where('date', '>=', CarbonImmutable::parse(BookingSlot::today())->addDays(5)->toDateString())
            ->orderBy('date')->first();

        if ($later) {
            $cancelled = $this->heldBooking($later, $customer->name, $customer->phone, 2, $customer, StableBookingStatus::Confirmed);
            $cancelled->forceFill(['status' => StableBookingStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_source' => 'stable', 'cancellation_reason' => 'Strong winds are forecast in the desert that evening. Sorry!'])->save();
            $cancelled->order->forceFill(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()])->save();
        }

        // an active package with sessions left, bought and paid two weeks ago
        $package = StablePackage::query()->where('stable_id', $rahba->id)->where('en_name', 'Five group lessons')->first();

        if ($package) {
            $purchase = app(StablePackages::class)->purchase($package, $customer);
            $purchase->order->forceFill(['status' => OrderStatus::New, 'payment_status' => PaymentStatus::Paid, 'payment_method' => PaymentGateway::Demo, 'paid_at' => now()->subDays(14)])->save();
            app(StablePackages::class)->activate($purchase);
        }
    }

    /** A slot in the past (the generator only makes future ones). */
    private function pastSlot(StableOffering $offering, int $daysAgo, int $variant): BookingSlot
    {
        $date = CarbonImmutable::parse(BookingSlot::today())->subDays($daysAgo)->toDateString();
        $start = ['09:00:00', '16:30:00', '18:00:00'][$variant % 3];

        return BookingSlot::query()->firstOrCreate(
            ['stable_offering_id' => $offering->id, 'date' => $date, 'start_time' => $start],
            [
                'stable_id' => $offering->stable_id,
                'end_time' => CarbonImmutable::parse($date.' '.$start)->addMinutes($offering->duration_minutes)->format('H:i:s'),
                'capacity' => $offering->capacity,
            ],
        )->load(['offering', 'stable']);
    }

    /**
     * A booking made outside the booking engine (which refuses past or started times): paid at the
     * stable, attended unless another status is asked for.
     */
    private function heldBooking(BookingSlot $slot, string $name, string $phone, int $riders, ?Customer $customer, StableBookingStatus $status = StableBookingStatus::Completed): StableBooking
    {
        $slot->loadMissing(['offering', 'stable']);
        $pricing = app(StableBookingPricing::class);
        $quote = $pricing->quote($slot->offering, $riders, $slot->stable);
        $done = $status === StableBookingStatus::Completed;
        $when = $slot->startsAt()->subDays(3);

        $order = ServiceOrder::create([
            'stable_id' => $slot->stable_id,
            'collected_by' => $quote->isFree() ? null : 'stable',
            'customer_id' => $customer?->id,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'locale' => 'en',
            'status' => $done ? OrderStatus::Completed : OrderStatus::New,
            'payment_status' => $quote->isFree() ? PaymentStatus::Free : PaymentStatus::OnSite,
            'completed_at' => $done ? $slot->endsAt() : null,
        ] + $pricing->orderAttributes($quote, $slot->stable));
        $order->forceFill(['created_at' => $when, 'updated_at' => $when])->save();

        $booking = StableBooking::create([
            'stable_id' => $slot->stable_id,
            'booking_slot_id' => $slot->id,
            'stable_offering_id' => $slot->stable_offering_id,
            'service_order_id' => $order->id,
            'customer_id' => $customer?->id,
            'riders' => $riders,
            'riders_data' => $this->riders($riders, $name),
            'payment_option' => $quote->isFree() ? 'free' : 'at_stable',
            'status' => $status,
            'confirmed_at' => $when,
            'attended_at' => $done ? $slot->endsAt() : null,
        ]);
        $booking->forceFill(['created_at' => $when, 'updated_at' => $when])->save();

        return $booking;
    }

    /** @return list<array<string, mixed>> */
    private function riders(int $count, string $name): array
    {
        return array_map(fn (int $i) => ['name' => $i === 1 ? $name : strtok($name, ' ')." (rider {$i})", 'age' => 12 + $i * 5, 'level' => 'beginner'], range(1, $count));
    }

    private function offlineOption(Stable $stable): string
    {
        return in_array(CreateStableBooking::AT_STABLE, $stable->bookingSettings()->paymentOptions(), true) ? CreateStableBooking::AT_STABLE : CreateStableBooking::ONLINE;
    }

    /** Two of the site's own horse photos, copied for the demo stables (null when there are none). */
    private function covers(): array
    {
        $disk = Storage::disk('public');
        $sources = ['cms/hero/01M20GXZJ9DGSRHZR76N1EMXJD.jpg', 'horse-sale/covers/v3NjFVODwmGLzyb3GAuX4FELmNn971UeimdMdiE2.jpg'];
        $covers = [];

        foreach ($sources as $i => $source) {
            if ($disk->exists($source)) {
                $target = "stables/demo/cover-{$i}.jpg";

                if (! $disk->exists($target)) {
                    $disk->copy($source, $target);
                }

                $covers[] = $target;
            }
        }

        return $covers ?: [null];
    }

    /** Everything an earlier run made. */
    private function wipe(): void
    {
        $stableIds = Stable::query()->where('slug', 'like', 'demo-%')->pluck('id');
        $orderIds = ServiceOrder::query()->whereIn('stable_id', $stableIds)->pluck('id');

        DB::transaction(function () use ($stableIds, $orderIds): void {
            StableReview::query()->whereIn('stable_id', $stableIds)->delete();
            StableBooking::query()->whereIn('stable_id', $stableIds)->delete();
            StablePackagePurchase::query()->whereIn('stable_id', $stableIds)->delete();
            OrderEvent::query()->whereIn('service_order_id', $orderIds)->delete();
            ServiceOrder::query()->whereIn('id', $orderIds)->delete();
            BookingSlot::query()->whereIn('stable_id', $stableIds)->delete();

            foreach (Stable::query()->whereIn('id', $stableIds)->get() as $stable) {
                $stable->members()->detach();
                $stable->services()->detach();
                $stable->delete();
            }

            User::query()->where('email', self::OWNER_EMAIL)->delete();

            if ($customer = Customer::query()->where('phone', self::CUSTOMER_PHONE)->first()) {
                $customer->tokens()->delete();
                $customer->delete();
            }
        });
    }
}
