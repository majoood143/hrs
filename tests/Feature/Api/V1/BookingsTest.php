<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StableBookingStatus;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StableOffering;
use App\Models\StablePackage;
use App\Models\StablePackagePurchase;
use App\Models\User;
use App\Services\Stables\SlotGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * Stable bookings from the app: sign-in with a phone code, the booking engine (search, a service's
 * days, a time's form, booking), the customer's bookings, packages and the checkout's way back to
 * the app. "Now" is Sunday 2026-10-04 09:30 in Muscat; the stable opens Tuesdays at 10:00, 4 places,
 * 15.000 a rider, 1 to 3 riders; a 5% fee and 5% VAT (the default).
 */
class BookingsTest extends TestCase
{
    use PreparesStableBookings;

    private Stable $stable;

    private StableOffering $offering;

    private BookingSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo']);
        (require database_path('migrations/2026_09_11_145142_create_personal_access_tokens_table.php'))->up();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->stable = $this->makeStable($this->makeOwner(), ['slug' => 'desert-riders']);
        $this->offering = $this->makeOffering($this->stable, ['max_riders' => 3]);
        $this->makeFee('percentage', 5.0);
        app(SlotGenerator::class)->sync($this->makeSchedule($this->offering, [2], ['10:00']));
        $this->slot = BookingSlot::query()->where('date', '2026-10-06')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Signs in through the API like the app does and returns the bearer token. */
    private function signIn(string $phone = '91234567'): string
    {
        $code = $this->postJson('/api/v1/auth/request', ['phone' => $phone, 'channel' => 'sms'])->assertOk()->json('demo_code');

        return $this->postJson('/api/v1/auth/verify', ['phone' => $phone, 'code' => $code])->assertOk()->json('token');
    }

    /**
     * The next request as this customer. Guards are forgotten first: within one test the app (and its
     * guards, which remember the user they resolved) lives on between requests, unlike in production.
     */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function settings(array $settings): void
    {
        $this->stable->forceFill(['booking_settings' => $settings + ($this->stable->booking_settings ?? [])])->save();
    }

    /** @return array<string, mixed> */
    private function form(int $riders = 1, array $extra = []): array
    {
        return $extra + [
            'name' => 'Aisha',
            'payment_option' => 'online',
            'riders' => array_map(fn (int $i) => ['name' => "Rider {$i}", 'age' => 12, 'level' => 'beginner'], range(1, $riders)),
        ];
    }

    // ── sign-in ──────────────────────────────────────────────────────────────

    public function test_a_customer_signs_in_with_a_code_and_gets_a_token(): void
    {
        $sent = $this->postJson('/api/v1/auth/request', ['phone' => '91234567', 'channel' => 'sms'])
            ->assertOk()
            ->assertJsonPath('channel', 'sms');

        $this->postJson('/api/v1/auth/verify', ['phone' => '91234567', 'code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');

        $answer = $this->postJson('/api/v1/auth/verify', ['phone' => '91234567', 'code' => $sent->json('demo_code')])
            ->assertOk()
            ->assertJsonPath('customer.phone', '96891234567');

        $this->as($answer->json('token'))->getJson('/api/v1/account')->assertOk()->assertJsonPath('data.phone', '96891234567');
        $this->as($answer->json('token'))->postJson('/api/v1/account', ['name' => 'Aisha', 'email' => 'aisha@example.com'])->assertOk()->assertJsonPath('data.name', 'Aisha');

        $this->as($answer->json('token'))->postJson('/api/v1/auth/logout')->assertOk();
        $this->as($answer->json('token'))->getJson('/api/v1/account')->assertUnauthorized();
    }

    public function test_the_account_needs_a_customer_token(): void
    {
        $this->getJson('/api/v1/account')->assertUnauthorized();
        $this->withToken('nonsense')->getJson('/api/v1/account')->assertUnauthorized();

        // an admin user's token is not a customer's
        $admin = User::create(['name' => 'Admin', 'email' => 'a@example.com', 'password' => 'secret-password', 'type' => 'admin']);
        $this->withToken($admin->createToken('x')->plainTextToken)->getJson('/api/v1/account')->assertUnauthorized();
    }

    public function test_a_customer_token_never_reaches_the_admin_api(): void
    {
        Route::middleware('auth:sanctum')->get('/api/test-admin-only', fn () => 'admin');
        $token = $this->signIn();

        $this->as($token)->getJson('/api/test-admin-only')->assertUnauthorized();
    }

    // ── the booking engine ───────────────────────────────────────────────────

    public function test_the_search_finds_open_times_by_day_and_riders(): void
    {
        $this->getJson('/api/v1/bookings/search?date=2026-10-06&riders=2')
            ->assertOk()
            ->assertJsonPath('results.0.stable.slug', 'desert-riders')
            ->assertJsonPath('results.0.offerings.0.offering.name', 'Beginner lesson')
            ->assertJsonPath('results.0.offerings.0.slots.0.start_time', '10:00')
            ->assertJsonPath('results.0.offerings.0.slots.0.places_left', 4)
            // 2 × 15.000 + 5% fee, 5% VAT on both
            ->assertJsonPath('results.0.offerings.0.quote.total.amount', '33.075')
            ->assertJsonPath('week.0.date', '2026-10-06')
            ->assertJsonPath('week.0.count', 1)
            ->assertJsonPath('next_date', null);

        // too many riders for the service, and an empty day pointing at the next opening
        $this->getJson('/api/v1/bookings/search?date=2026-10-06&riders=4')->assertOk()->assertJsonCount(0, 'results');
        $this->getJson('/api/v1/bookings/search?date=2026-10-05')->assertOk()->assertJsonCount(0, 'results')->assertJsonPath('next_date', '2026-10-06');

        $this->withHeader('Accept-Language', 'ar')->getJson('/api/v1/bookings/search?date=2026-10-06')
            ->assertOk()
            ->assertJsonPath('results.0.stable.name', 'فرسان الصحراء')
            ->assertJsonPath('results.0.offerings.0.offering.name', 'حصة للمبتدئين');

        $this->getJson('/api/v1/bookings/search?time=midnight')->assertUnprocessable();
    }

    public function test_a_service_lists_its_open_days_and_times(): void
    {
        $this->getJson("/api/v1/bookings/offerings/{$this->offering->id}")
            ->assertOk()
            ->assertJsonPath('date', '2026-10-06')
            ->assertJsonPath('days.0.date', '2026-10-06')
            ->assertJsonPath('slots.0.id', $this->slot->id)
            ->assertJsonPath('quote.price.amount', '15.000');

        $this->offering->update(['is_active' => false]);
        $this->getJson("/api/v1/bookings/offerings/{$this->offering->id}")->assertNotFound();
    }

    public function test_a_time_describes_its_form(): void
    {
        $this->settings(['payment_options' => ['online', 'at_stable'], 'rider_fields' => ['weight' => ['show' => true, 'required' => true]]]);

        $form = $this->getJson("/api/v1/bookings/slots/{$this->slot->id}?riders=2")
            ->assertOk()
            ->assertJsonPath('riders', 2)
            ->assertJsonPath('quote.total.amount', '33.075')
            ->assertJsonPath('payment_options.0.value', 'online')
            ->assertJsonPath('payment_options.1.value', 'at_stable');

        $this->assertContains(['key' => 'weight', 'required' => true], $form->json('rider_fields'));

        $this->getJson("/api/v1/bookings/slots/{$this->slot->id}?riders=5")->assertStatus(409)->assertJsonPath('code', 'booking_unavailable');
    }

    public function test_the_stable_page_offers_its_services_and_packages(): void
    {
        StablePackage::create(['stable_id' => $this->stable->id, 'stable_offering_id' => $this->offering->id, 'en_name' => 'Five lessons', 'ar_name' => 'خمس حصص', 'sessions' => 5, 'price' => 60]);

        $this->getJson('/api/v1/stables/desert-riders')
            ->assertOk()
            ->assertJsonPath('data.booking.offerings.0.offering.id', $this->offering->id)
            ->assertJsonPath('data.booking.offerings.0.next_date', '2026-10-06')
            ->assertJsonPath('data.booking.packages.0.name', 'Five lessons')
            ->assertJsonPath('data.booking.packages.0.saving.amount', '15.000');
    }

    // ── booking ──────────────────────────────────────────────────────────────

    public function test_booking_needs_a_signed_in_customer(): void
    {
        $this->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form())->assertUnauthorized();

        $this->assertSame(0, StableBooking::query()->count());
    }

    public function test_an_online_booking_waits_for_payment_with_a_checkout_link(): void
    {
        $token = $this->signIn();

        $booking = $this->as($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(2))
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.riders', 2)
            ->assertJsonPath('data.order.total.amount', '33.075')
            ->assertJsonPath('data.can_cancel', true);

        $order = ServiceOrder::query()->sole();
        $this->assertSame(route('payment.start', $order->order_number), $booking->json('data.pay_url'));
        $this->assertSame(['96891234567', 'Aisha'], [$order->customer_phone, $order->customer_name]);
        $this->assertNotNull($order->customer_id);
        $this->assertSame(2, $this->slot->remainingPlaces());
        // the name is kept for the next booking form
        $this->assertSame('Aisha', Customer::query()->sole()->name);
    }

    public function test_paying_at_the_stable_confirms_at_once(): void
    {
        $this->settings(['payment_options' => ['online', 'at_stable']]);

        $this->as($this->signIn())->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(1, ['payment_option' => 'at_stable']))
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'confirmed')
            ->assertJsonPath('data.pay_url', null)
            ->assertJsonPath('data.payment_option', 'at_stable');
    }

    public function test_the_form_is_checked_against_the_stables_choices(): void
    {
        $token = $this->signIn();

        // at the stable is not allowed here, and the stable asks every rider's weight
        $this->settings(['rider_fields' => ['weight' => ['show' => true, 'required' => true]]]);
        $this->as($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(1, ['payment_option' => 'at_stable']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_option', 'riders.0.weight']);

        // more riders than the service takes
        $this->as($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(4, ['riders' => array_fill(0, 4, ['name' => 'X', 'age' => 10, 'level' => 'beginner', 'weight' => 40])]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'booking_unavailable');

        $this->assertSame(0, StableBooking::query()->count());
    }

    public function test_booking_is_refused_while_the_feature_is_off(): void
    {
        $this->seedSiteSettings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', 'mobile.features' => ['bookings' => ['enabled' => false]]]);

        $this->as($this->signIn())->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form())
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_disabled');
    }

    // ── my bookings ──────────────────────────────────────────────────────────

    public function test_customers_see_and_cancel_only_their_own_bookings(): void
    {
        $token = $this->signIn();
        $reference = $this->as($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form())->json('data.reference');

        $this->as($token)->getJson('/api/v1/account/bookings')->assertOk()->assertJsonPath('data.0.reference', $reference);
        $this->as($token)->getJson('/api/v1/account/bookings?when=past')->assertOk()->assertJsonCount(0, 'data');

        $other = $this->signIn('92223333');
        $this->as($other)->getJson("/api/v1/account/bookings/{$reference}")->assertNotFound();
        $this->as($other)->postJson("/api/v1/account/bookings/{$reference}/cancel")->assertNotFound();

        $this->as($token)->postJson("/api/v1/account/bookings/{$reference}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled')
            ->assertJsonPath('data.cancelled_by', 'customer');

        $this->assertSame(4, $this->slot->remainingPlaces());
        $this->as($token)->getJson('/api/v1/account/bookings?when=past')->assertOk()->assertJsonPath('data.0.reference', $reference);
    }

    public function test_an_attended_session_can_be_reviewed_once(): void
    {
        $this->settings(['payment_options' => ['at_stable']]);
        $token = $this->signIn();
        $reference = $this->as($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(1, ['payment_option' => 'at_stable']))->json('data.reference');

        $this->as($token)->postJson("/api/v1/account/bookings/{$reference}/review", ['rating' => 5])->assertStatus(409);

        StableBooking::query()->where('reference', $reference)->update(['status' => StableBookingStatus::Completed->value, 'attended_at' => now()]);

        $this->as($token)->getJson("/api/v1/account/bookings/{$reference}")->assertJsonPath('data.can_review', true);
        $this->as($token)->postJson("/api/v1/account/bookings/{$reference}/review", ['rating' => 5, 'comment' => 'Lovely'])
            ->assertOk()
            ->assertJsonPath('data.review.rating', 5)
            ->assertJsonPath('data.can_review', false);
    }

    public function test_a_package_is_bought_and_then_pays_for_a_booking(): void
    {
        $package = StablePackage::create(['stable_id' => $this->stable->id, 'stable_offering_id' => $this->offering->id, 'en_name' => 'Five lessons', 'ar_name' => 'خمس حصص', 'sessions' => 5, 'price' => 60]);
        $token = $this->signIn();

        $bought = $this->as($token)->postJson("/api/v1/packages/{$package->id}/buy")
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.total.amount', '66.150');
        $this->assertNotNull($bought->json('data.pay_url'));

        // paid: it shows on the time's form and pays for the booking
        $purchase = StablePackagePurchase::query()->sole();
        $purchase->order->forceFill(['status' => OrderStatus::New, 'payment_status' => PaymentStatus::Paid, 'paid_at' => now()])->save();
        $purchase->forceFill(['status' => StablePackagePurchase::ACTIVE, 'activated_at' => now(), 'expires_at' => now()->addDays(60)])->save();

        $this->as($token)->getJson("/api/v1/bookings/slots/{$this->slot->id}")->assertJsonPath('packages.0.id', $purchase->id);
        $this->as($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(1, ['payment_option' => 'package', 'package_purchase_id' => $purchase->id]))
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'confirmed')
            ->assertJsonPath('data.payment_option', 'package');

        $this->as($token)->getJson('/api/v1/account/packages')->assertOk()->assertJsonPath('data.0.sessions_left', 4);
    }

    public function test_an_account_is_deleted_only_when_nothing_is_running(): void
    {
        $token = $this->signIn();
        $reference = $this->as($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form())->json('data.reference');

        $this->as($token)->deleteJson('/api/v1/account')->assertStatus(409)->assertJsonPath('code', 'account_busy');

        $this->as($token)->postJson("/api/v1/account/bookings/{$reference}/cancel")->assertOk();
        $this->as($token)->deleteJson('/api/v1/account')->assertOk();

        $this->assertSame(0, Customer::query()->count());
        // the order stays, with the phone it was made with
        $this->assertSame([null, '96891234567'], [ServiceOrder::query()->sole()->customer_id, ServiceOrder::query()->sole()->customer_phone]);
    }

    // ── checkout from the app ────────────────────────────────────────────────

    public function test_the_checkout_returns_to_the_app_once_paid(): void
    {
        $token = $this->signIn();
        $this->as($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form());
        $number = ServiceOrder::query()->sole()->order_number;

        // the app's address is remembered when the checkout opens (the page itself is the website's)
        $this->withSession([])->get(route('payment.start', ['order' => $number, 'app_return' => 'muhra://checkout']));
        $this->assertSame('muhra://checkout', session('payment.app_return.'.$number));

        $this->post(route('payment.begin', $number), ['gateway' => 'demo']);
        $this->post(route('payment.demo.decide', $number), ['decision' => 'approve'])
            ->assertRedirect('muhra://checkout?order='.$number.'&result=paid');

        $this->assertSame(StableBookingStatus::Confirmed, StableBooking::query()->sole()->status);
    }

    public function test_only_the_apps_own_addresses_are_returned_to(): void
    {
        $this->settings(['payment_options' => ['at_stable']]);
        $this->as($this->signIn())->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(1, ['payment_option' => 'at_stable']));
        $number = ServiceOrder::query()->sole()->order_number;

        // nothing to pay: straight back to the app, or to the order page for anything else
        $this->get(route('payment.start', ['order' => $number, 'app_return' => 'muhra://checkout']))->assertRedirect('muhra://checkout?order='.$number.'&result=new');
        $this->get(route('payment.start', ['order' => $number, 'app_return' => 'https://evil.example/steal']))->assertRedirect(route('orders.show', $number));
        $this->get(route('payment.start', ['order' => $number, 'app_return' => 'javascript:alert(1)']))->assertRedirect(route('orders.show', $number));
    }
}
