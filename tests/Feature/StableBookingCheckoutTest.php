<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\StableBookingStatus;
use App\Jobs\SendOrderNotification;
use App\Jobs\SendStableBookingNotification;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StableOffering;
use App\Models\StablePaymentAccount;
use App\Models\User;
use App\Services\Payments\OrderPaymentService;
use App\Services\Payments\PaymentGateways;
use App\Services\Reports\IncomeStatement;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\CreateStableBooking;
use App\Services\Stables\SlotGenerator;
use App\Services\Stables\StableBookingActions;
use App\Services\Stables\StableBookingMessages;
use App\Services\WhatsApp\BilingualMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * Booking a slot end to end. "Now" is Sunday 2026-10-04 09:30 in Muscat; the stable opens Tuesdays at
 * 10:00 (Oct 6, 13…), 4 places, 15.000 a rider, 1 to 3 riders a booking; a 5% site fee and 5% VAT;
 * the stable's commission is 10%.
 */
class StableBookingCheckoutTest extends TestCase
{
    use PreparesStableBookings;

    private User $owner;

    private Stable $stable;

    private StableOffering $offering;

    private BookingSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings([
            'enabled_gateways' => ['demo'],
            'sms.driver' => 'demo',
            'vat.enabled' => true,
            'vat.rate' => '5',
        ]);
        $this->withoutVite();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->owner = $this->makeOwner();
        $this->stable = $this->makeStable($this->owner, ['phone' => '96899001122', 'email' => 'desk@stable.test']);
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

    private function bookings(): CreateStableBooking
    {
        return app(CreateStableBooking::class);
    }

    private function actions(): StableBookingActions
    {
        return app(StableBookingActions::class);
    }

    /** @return list<array<string, mixed>> */
    private function riders(int $count): array
    {
        return array_map(fn (int $i) => ['name' => "Rider {$i}", 'age' => 12, 'level' => 'beginner'], range(1, $count));
    }

    private function book(int $riders = 2, string $option = 'online'): StableBooking
    {
        return $this->bookings()->handle($this->slot, $this->riders($riders), ['name' => 'Aisha', 'phone' => '91234567', 'email' => 'aisha@example.com'], $option);
    }

    private function settings(array $settings): void
    {
        $this->stable->forceFill(['booking_settings' => $settings + ($this->stable->booking_settings ?? [])])->save();
    }

    // ── money and places ─────────────────────────────────────────────────────

    public function test_an_online_booking_holds_its_places_and_waits_for_payment(): void
    {
        $booking = $this->book(2);
        $order = $booking->order;

        $this->assertSame(StableBookingStatus::Pending, $booking->status);
        $this->assertStringStartsWith('BK-', $booking->reference);
        $this->assertSame([OrderStatus::PendingPayment, PaymentStatus::Pending], [$order->status, $order->payment_status]);
        $this->assertSame($this->stable->id, $order->stable_id);
        $this->assertSame('platform', $order->collected_by);
        $this->assertNull($order->service_id);

        // 2 × 15.000 = 30.000, fee 5% 1.500, VAT 5% on both 1.575: 33.075; commission 10% of the price
        $this->assertSame(['30.000', '1.500', '1.500', '0.075', '33.075'], [$order->price, $order->fee_amount, $order->vat_on_price, $order->vat_on_fee, $order->total]);
        $this->assertSame(['3.000', 'percentage', '10.000'], [$order->commission_amount, $order->commission_type, $order->commission_value]);
        $this->assertSame('Beginner lesson · Desert Riders', $order->serviceName());

        $this->assertSame(2, $this->slot->remainingPlaces());
        $this->assertSame([], app(PaymentGateways::class)->forOrder($order) === [] ? ['none'] : []);
    }

    public function test_the_last_places_cannot_be_booked_twice(): void
    {
        $this->book(3);

        $this->expectException(BookingUnavailable::class);
        $this->expectExceptionMessage('Only 1 place is left');

        $this->book(2);
    }

    public function test_bookings_respect_the_riders_range_the_cutoff_and_the_stable(): void
    {
        foreach ([
            fn () => $this->book(4),                                                            // more than max_riders
            fn () => $this->slot->forceFill(['is_open' => false])->save() && $this->book(1),     // closed slot
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The booking should have been refused.');
            } catch (BookingUnavailable) {
                $this->slot->forceFill(['is_open' => true])->save();
            }
        }

        // bookings close an hour before: Tuesday 09:00 Muscat
        Carbon::setTestNow('2026-10-06 05:01:00');
        $this->expectException(BookingUnavailable::class);
        $this->book(1);
    }

    public function test_a_stable_without_a_commission_takes_no_bookings(): void
    {
        $this->stable->forceFill(['commission_type' => null, 'commission_value' => null])->save();

        $this->expectException(BookingUnavailable::class);
        $this->book(1);
    }

    public function test_paying_confirms_the_booking_and_tells_both_sides(): void
    {
        Queue::fake();
        $booking = $this->book(2);

        app(OrderPaymentService::class)->applyPaid($booking->order, PaymentGateway::Demo, 'DEMO-1');

        $this->assertSame(StableBookingStatus::Confirmed, $booking->refresh()->status);
        $this->assertNotNull($booking->confirmed_at);

        // the booking's own messages, never the generic "order received" ones
        Queue::assertNotPushed(SendOrderNotification::class);
        Queue::assertPushed(SendStableBookingNotification::class, fn ($job) => $job->audience === 'customer' && $job->channel === 'sms' && $job->type === 'confirmed');
        Queue::assertPushed(SendStableBookingNotification::class, fn ($job) => $job->audience === 'customer' && $job->channel === 'email');
        // the stable's default alert channel is email
        Queue::assertPushed(SendStableBookingNotification::class, fn ($job) => $job->audience === 'stable' && $job->channel === 'email');
    }

    public function test_the_messages_carry_the_session_details(): void
    {
        $booking = $this->book(2);
        app(OrderPaymentService::class)->applyPaid($booking->order, PaymentGateway::Demo, 'DEMO-1');

        // the customer's SMS (demo SMS driver: logged, not sent)
        $sms = NotificationLog::query()->where('channel', 'sms')->where('type', 'booking_confirmed')->sole();
        $this->assertSame('96891234567', $sms->recipient);
        $this->assertStringContainsString($booking->reference, $sms->message);
        $this->assertStringContainsString('Tuesday 6 October 2026', $sms->message);
        $this->assertStringContainsString('10:00 – 11:00', $sms->message);
        $this->assertStringContainsString('2 riders', $sms->message);

        // the stable's email went to the stable's own address
        $this->assertTrue(NotificationLog::query()->where('channel', 'email')->where('type', 'booking_confirmed_stable')->where('recipient', 'desk@stable.test')->where('status', 'sent')->exists());
        $this->assertTrue(NotificationLog::query()->where('channel', 'email')->where('type', 'booking_confirmed')->where('recipient', 'aisha@example.com')->exists());
    }

    public function test_the_stables_whatsapp_alert_is_bilingual(): void
    {
        Queue::fake();
        $this->stableSiteSettings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', 'whatsapp.enabled' => true]);
        $this->settings(['alert_channels' => ['whatsapp', 'sms'], 'payment_options' => ['at_stable']]);
        $booking = $this->book(1, 'at_stable');

        Queue::assertPushed(SendStableBookingNotification::class, fn ($job) => $job->audience === 'stable' && $job->channel === 'whatsapp');
        Queue::assertPushed(SendStableBookingNotification::class, fn ($job) => $job->audience === 'stable' && $job->channel === 'sms');

        $text = BilingualMessage::make(fn () => app(StableBookingMessages::class)->stableText('confirmed', $booking));
        [$en, $ar] = explode(BilingualMessage::DIVIDER, $text);
        $this->assertStringContainsString('New booking '.$booking->reference, $en);
        $this->assertStringContainsString('حجز جديد '.$booking->reference, $ar);
        $this->assertStringContainsString('Rider 1', $en);
    }

    public function test_paying_at_the_stable_confirms_at_once_and_never_counts_as_our_income(): void
    {
        $this->settings(['payment_options' => ['online', 'at_stable']]);

        $booking = $this->book(1, 'at_stable');
        $order = $booking->order;

        $this->assertSame(StableBookingStatus::Confirmed, $booking->status);
        $this->assertSame([OrderStatus::New, PaymentStatus::OnSite, 'stable'], [$order->status, $order->payment_status, $order->collected_by]);
        // the fee and commission still apply (the stable owes them to us)
        $this->assertSame(['0.750', '1.500'], [$order->fee_amount, $order->commission_amount]);
        $this->assertFalse($order->isPayable());
    }

    public function test_paying_at_the_stable_needs_the_stable_to_allow_it(): void
    {
        $this->expectException(BookingUnavailable::class);

        $this->book(1, 'at_stable');
    }

    public function test_a_free_session_is_confirmed_at_once_without_fee_or_vat(): void
    {
        $this->offering->update(['price' => 0]);

        $order = $this->book(2)->order;

        $this->assertSame([PaymentStatus::Free, '0.000', '0.000', '0.000'], [$order->payment_status, $order->total, $order->fee_amount, $order->commission_amount]);
        $this->assertSame(StableBookingStatus::Confirmed, $order->stableBooking->status);
    }

    // ── expiry, cancelling, attendance ───────────────────────────────────────

    public function test_an_expired_checkout_gives_its_places_back(): void
    {
        $booking = $this->book(3);
        $this->assertSame(1, $this->slot->remainingPlaces());

        Carbon::setTestNow(now()->addHour());
        $this->artisan('orders:expire-pending')->assertSuccessful();

        $this->assertSame([StableBookingStatus::Cancelled, 'expired'], [$booking->refresh()->status, $booking->cancellation_source]);
        $this->assertSame(4, $this->slot->remainingPlaces());
    }

    public function test_a_late_payment_revives_the_booking(): void
    {
        $booking = $this->book(2);
        app(OrderPaymentService::class)->cancelPending($booking->order, 'system');
        $this->assertSame(StableBookingStatus::Cancelled, $booking->refresh()->status);

        app(OrderPaymentService::class)->applyPaid($booking->order->refresh(), PaymentGateway::Demo, 'LATE');

        $this->assertSame(StableBookingStatus::Confirmed, $booking->refresh()->status);
    }

    public function test_the_customer_may_cancel_until_the_stables_cutoff(): void
    {
        Queue::fake();
        $this->settings(['cancellation_hours' => 24]);
        $booking = $this->book(2);
        app(OrderPaymentService::class)->applyPaid($booking->order, PaymentGateway::Demo, 'DEMO-1');
        $booking->refresh();

        // Monday 10:30 Muscat is less than 24 hours before Tuesday 10:00
        Carbon::setTestNow('2026-10-05 06:30:00');
        $this->assertFalse($this->actions()->customerMayCancel($booking));

        Carbon::setTestNow('2026-10-05 05:00:00');
        $this->assertTrue($this->actions()->customerMayCancel($booking));
        $this->actions()->cancelByCustomer($booking);

        $order = $booking->order->refresh();
        $this->assertSame(StableBookingStatus::Cancelled, $booking->refresh()->status);
        $this->assertSame([OrderStatus::Cancelled, 'customer'], [$order->status, $order->cancellation_source]);
        // paid: the price and its VAT are due back, never the fee
        $this->assertTrue($order->refundDue());
        $this->assertSame(31.5, $order->refundableAmount());
        $this->assertSame(4, $this->slot->remainingPlaces());
        Queue::assertPushed(SendStableBookingNotification::class, fn ($job) => $job->type === 'cancelled' && $job->audience === 'stable');
    }

    public function test_online_cancelling_can_be_switched_off(): void
    {
        $this->settings(['cancellation_hours' => null, 'payment_options' => ['at_stable']]);
        $booking = $this->book(1, 'at_stable');

        $this->assertFalse($this->actions()->customerMayCancel($booking));
        $this->expectException(BookingUnavailable::class);
        $this->actions()->cancelByCustomer($booking);
    }

    public function test_the_stable_cancels_with_a_reason_the_customer_is_sent(): void
    {
        $booking = $this->book(1);
        app(OrderPaymentService::class)->applyPaid($booking->order, PaymentGateway::Demo, 'DEMO-1');

        $this->actions()->cancelByStable($booking->refresh(), 'The arena is flooded.');

        $this->assertSame(['stable', 'The arena is flooded.'], [$booking->refresh()->cancellation_source, $booking->cancellation_reason]);
        $sms = NotificationLog::query()->where('type', 'booking_cancelled')->where('channel', 'sms')->sole();
        $this->assertStringContainsString('Desert Riders cancelled your booking', $sms->message);
        $this->assertStringContainsString('The arena is flooded.', $sms->message);
    }

    public function test_attendance_completes_the_order(): void
    {
        $this->offering->update(['price' => 0]);
        $booking = $this->book(1);

        $this->actions()->markAttended($booking);
        $this->assertSame([StableBookingStatus::Completed, OrderStatus::Completed], [$booking->refresh()->status, $booking->order->refresh()->status]);

        $this->actions()->markNoShow($booking);
        $this->assertSame(StableBookingStatus::NoShow, $booking->refresh()->status);
    }

    // ── the stable's own gateway ─────────────────────────────────────────────

    private function ownAccount(string $status = StablePaymentAccount::APPROVED): StablePaymentAccount
    {
        $this->settings(['payment_mode' => 'own', 'payment_gateway' => 'thawani']);
        $account = StablePaymentAccount::create([
            'stable_id' => $this->stable->id,
            'gateway' => 'thawani',
            'credentials' => ['secret_key' => 'sk_stable', 'publishable_key' => 'pk_stable', 'webhook_secret' => 'wh_stable'],
            'test_mode' => true,
        ]);
        $account->forceFill(['status' => $status])->save();

        return $account;
    }

    public function test_an_approved_own_account_takes_the_payment(): void
    {
        $account = $this->ownAccount();

        $order = $this->book(1)->order;

        $this->assertSame([$account->id, 'stable'], [$order->stable_payment_account_id, $order->collected_by]);

        // only the stable's gateway is offered, set up with the stable's keys
        $gateways = app(PaymentGateways::class)->forOrder($order);
        $this->assertCount(1, $gateways);
        $this->assertSame(PaymentGateway::Thawani, $gateways[0]->gateway());
        $this->assertSame($account->id, $gateways[0]->account()?->id);

        // money it took never shows as ours in the income statement
        app(OrderPaymentService::class)->applyPaid($order, PaymentGateway::Thawani, 'T-1');
        $this->assertCount(0, IncomeStatement::forPeriod('custom', '2026-10-01', '2026-10-31')->rows());
    }

    public function test_keys_waiting_for_approval_are_not_used(): void
    {
        $this->ownAccount(StablePaymentAccount::PENDING);

        $order = $this->book(1)->order;

        $this->assertSame([null, 'platform'], [$order->stable_payment_account_id, $order->collected_by]);
        $this->assertSame([PaymentGateway::Demo], array_map(fn ($g) => $g->gateway(), app(PaymentGateways::class)->forOrder($order)));
    }

    public function test_changing_keys_sends_the_account_back_for_approval(): void
    {
        $account = $this->ownAccount();

        $account->update(['credentials' => ['secret_key' => 'sk_new', 'publishable_key' => 'pk_stable']]);

        $this->assertSame(StablePaymentAccount::PENDING, $account->refresh()->status);
        // stored encrypted
        $this->assertStringNotContainsString('sk_new', (string) DB::table('stable_payment_accounts')->value('credentials'));
        $this->assertSame('••••••_new', $account->masked('secret_key'));
    }

    public function test_the_thawani_webhook_is_checked_with_the_stables_secret(): void
    {
        $this->ownAccount();
        $order = $this->book(1)->order;
        $order->update(['payment_session_id' => 'sess_stable', 'payment_method' => PaymentGateway::Thawani]);

        Http::fake(['*checkout/session/sess_stable' => Http::response(['data' => [
            'payment_status' => 'paid', 'total_amount' => $order->totalBaisa(), 'invoice' => 'INV-1',
        ]])]);

        $payload = json_encode(['event_type' => 'checkout.completed', 'data' => ['session_id' => 'sess_stable']]);

        // signed with our own secret: refused
        $this->call('POST', route('payment.thawani.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_THAWANI_SIGNATURE' => hash_hmac('sha256', $payload, 'wrong')], $payload)->assertStatus(401);

        $this->call('POST', route('payment.thawani.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_THAWANI_SIGNATURE' => hash_hmac('sha256', $payload, 'wh_stable')], $payload)->assertOk();

        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame(StableBookingStatus::Confirmed, $order->stableBooking->status);
        // the session was fetched with the stable's secret key
        Http::assertSent(fn ($request) => $request->hasHeader('thawani-api-key', 'sk_stable'));
    }

    public function test_a_ccavenue_answer_is_read_with_the_stables_own_key(): void
    {
        $this->settings(['payment_mode' => 'own', 'payment_gateway' => 'ccavenue']);
        $account = StablePaymentAccount::create([
            'stable_id' => $this->stable->id,
            'gateway' => 'ccavenue',
            'credentials' => ['merchant_id' => 'M1', 'access_code' => 'AC1', 'working_key' => str_repeat('w', 32)],
            'test_mode' => true,
        ]);
        $account->forceFill(['status' => StablePaymentAccount::APPROVED])->save();
        $order = $this->book(1)->order;

        $gateway = app(PaymentGateways::class)->findForOrder($order, 'ccavenue');
        $redirect = $gateway->initiate($order);
        $this->assertSame('AC1', $redirect->fields['access_code']);

        $answer = $gateway->encrypt(http_build_query([
            'order_id' => $order->compactNumber(),
            'order_status' => 'Success',
            'amount' => $order->total,
            'tracking_id' => 'TRK-1',
        ]));

        // without the account in the URL it cannot even be read (we have no key of our own)
        $this->post(route('payment.ccavenue.callback'), ['encResp' => $answer]);
        $this->assertFalse($order->refresh()->isPaid());

        $this->post(route('payment.ccavenue.callback', ['account' => $account->id]), ['encResp' => $answer])
            ->assertRedirect(route('orders.show', $order->order_number));
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame(StableBookingStatus::Confirmed, $order->stableBooking->status);
    }

    // ── public pages ─────────────────────────────────────────────────────────

    public function test_the_search_finds_open_times_by_date_and_riders(): void
    {
        $this->get(route('bookings.search', ['date' => '2026-10-06', 'riders' => 2]))
            ->assertOk()
            ->assertSee('Desert Riders')
            ->assertSee('Beginner lesson')
            ->assertSee('4 places left')
            ->assertSee(route('bookings.create', ['slot' => $this->slot->id, 'riders' => 2]), false);

        // more riders than a booking takes: nothing, with the next opening offered when there is one
        $this->get(route('bookings.search', ['date' => '2026-10-06', 'riders' => 4]))->assertOk()->assertSee('Nothing open on this day');
        $this->get(route('bookings.search', ['date' => '2026-10-05']))->assertOk()->assertSee('Next opening: Tuesday 6 October');

        // Arabic
        $this->get(route('bookings.search', ['date' => '2026-10-06', 'lang' => 'ar']))->assertOk()->assertSee('فرسان الصحراء');
    }

    public function test_a_pending_stable_is_not_searchable(): void
    {
        $this->stable->forceFill(['approval_status' => 'pending'])->save();

        $this->get(route('bookings.search', ['date' => '2026-10-06']))->assertOk()->assertDontSee('Desert Riders');
        $this->get(route('bookings.offering', ['stable' => $this->stable->slug, 'offering' => $this->offering->id]))->assertNotFound();
    }

    public function test_the_service_page_and_the_stable_page_lead_to_booking(): void
    {
        $this->get(route('stables.show', $this->stable->slug))->assertOk()->assertSee('Book a session here');

        $this->get(route('bookings.offering', ['stable' => $this->stable->slug, 'offering' => $this->offering->id]))
            ->assertOk()
            ->assertSee('10:00 – 11:00')
            ->assertSee('Free cancellation online up to 24 hours');
    }

    public function test_the_form_books_and_sends_the_customer_to_pay(): void
    {
        $this->get(route('bookings.create', ['slot' => $this->slot->id, 'riders' => 2]))
            ->assertOk()
            ->assertSee('Rider 2')
            ->assertSee('33.075');

        $response = $this->post(route('bookings.store', $this->slot), [
            'riders_count' => 2,
            'name' => 'Aisha',
            'phone' => '9123 4567',
            'email' => 'aisha@example.com',
            'payment_option' => 'online',
            'riders' => [
                ['name' => 'Omar', 'age' => 9, 'level' => 'beginner', 'notes' => 'First time'],
                ['name' => 'Sara', 'age' => 11, 'level' => 'intermediate'],
            ],
        ]);

        $order = ServiceOrder::query()->sole();
        $response->assertRedirect(route('payment.start', $order->order_number));
        $this->assertSame('96891234567', $order->customer_phone);
        $this->assertSame([['name' => 'Omar', 'age' => 9, 'level' => 'beginner', 'notes' => 'First time'], ['name' => 'Sara', 'age' => 11, 'level' => 'intermediate']], $order->stableBooking->riders_data);

        // paying with the demo gateway confirms it and shows the booking on the order page
        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'demo']);
        $this->post(route('payment.demo.decide', $order->order_number), ['decision' => 'approve']);
        $this->get(route('orders.show', $order->order_number))->assertOk()->assertSee('Confirmed')->assertSee($order->stableBooking->reference);
    }

    public function test_the_form_asks_only_what_the_stable_chose(): void
    {
        $this->settings(['rider_fields' => ['weight' => ['show' => true, 'required' => true], 'age' => ['show' => false, 'required' => false]]]);

        $this->post(route('bookings.store', $this->slot), [
            'riders_count' => 1,
            'name' => 'Aisha',
            'phone' => '91234567',
            'payment_option' => 'online',
            'riders' => [['name' => 'Omar', 'age' => 9, 'level' => 'beginner']],
        ])->assertSessionHasErrors(['riders.0.weight', 'riders.0.age']);

        $this->assertSame(0, StableBooking::query()->count());
    }

    public function test_a_signed_in_customer_cancels_from_their_order_page(): void
    {
        $this->settings(['payment_options' => ['at_stable']]);
        $booking = $this->book(1, 'at_stable');
        $customer = Customer::create(['phone' => '96891234567', 'name' => 'Aisha', 'phone_verified_at' => now()]);
        $booking->order->update(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer')
            ->get(route('account.orders.show', $booking->order->order_number))
            ->assertOk()
            ->assertSee('Cancel booking')
            ->assertSee('Rider 1');

        $this->actingAs($customer, 'customer')
            ->post(route('account.orders.cancel-booking', $booking->order->order_number))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(StableBookingStatus::Cancelled, $booking->refresh()->status);
    }
}
