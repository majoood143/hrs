<?php

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\StableBookingStatus;
use App\Jobs\SendStablePackageNotification;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StableOffering;
use App\Models\StablePackage;
use App\Models\StablePackagePurchase;
use App\Models\StableReview;
use App\Models\User;
use App\Services\Payments\OrderPaymentService;
use App\Services\Reports\StableStatement;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\CreateStableBooking;
use App\Services\Stables\SlotGenerator;
use App\Services\Stables\StableBookingActions;
use App\Services\Stables\StablePackages;
use App\Services\Stables\StableReviews;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * Lesson packages and reviews. 15.000 a session, a 5-session package at 60.000 valid 30 days; 5% fee,
 * no VAT, 10% commission. "Now" is Sunday 2026-10-04 in Muscat, with slots on Tuesdays.
 */
class StablePackagesAndReviewsTest extends TestCase
{
    use PreparesStableBookings;

    private User $owner;

    private Stable $stable;

    private StableOffering $offering;

    private StablePackage $package;

    private BookingSlot $slot;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', 'vat.enabled' => false]);
        $this->withoutVite();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->owner = $this->makeOwner();
        $this->stable = $this->makeStable($this->owner);
        $this->offering = $this->makeOffering($this->stable, ['max_riders' => 3]);
        $this->makeFee('percentage', 5.0);
        app(SlotGenerator::class)->sync($this->makeSchedule($this->offering, [2], ['10:00']));
        $this->slot = BookingSlot::query()->where('date', '2026-10-06')->sole();

        $this->package = StablePackage::create([
            'stable_id' => $this->stable->id,
            'stable_offering_id' => $this->offering->id,
            'en_name' => '5 lessons',
            'ar_name' => '5 حصص',
            'sessions' => 5,
            'price' => 60,
            'validity_days' => 30,
        ]);
        $this->customer = Customer::create(['phone' => '96891234567', 'name' => 'Aisha Al Harthy', 'phone_verified_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function activePurchase(?Customer $customer = null): StablePackagePurchase
    {
        $purchase = app(StablePackages::class)->purchase($this->package, $customer ?? $this->customer);
        app(OrderPaymentService::class)->applyPaid($purchase->order, PaymentGateway::Demo, 'PK-PAY');

        return $purchase->refresh();
    }

    private function bookWithPackage(StablePackagePurchase $purchase, int $riders = 1, ?Customer $customer = null): StableBooking
    {
        $customer ??= $this->customer;

        return app(CreateStableBooking::class)->handle(
            $this->slot,
            array_fill(0, $riders, ['name' => 'Omar']),
            ['name' => $customer->name, 'phone' => $customer->phone, 'customer_id' => $customer->id],
            CreateStableBooking::PACKAGE,
            $purchase->id,
        );
    }

    // ── packages ─────────────────────────────────────────────────────────────

    public function test_a_package_is_paid_like_a_booking_and_activated_by_the_payment(): void
    {
        Queue::fake();

        $purchase = app(StablePackages::class)->purchase($this->package, $this->customer);
        $order = $purchase->order;

        $this->assertSame(StablePackagePurchase::PENDING, $purchase->status);
        $this->assertStringStartsWith('PK-', $purchase->reference);
        $this->assertSame(['60.000', '3.000', '63.000', '6.000'], [$order->price, $order->fee_amount, $order->total, $order->commission_amount]);
        $this->assertSame('5 lessons · Desert Riders', $order->serviceName());

        app(OrderPaymentService::class)->applyPaid($order, PaymentGateway::Demo, 'PK-PAY');
        $purchase->refresh();

        $this->assertSame(StablePackagePurchase::ACTIVE, $purchase->status);
        $this->assertSame('2026-11-03 23:59:59', $purchase->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(5, $purchase->sessionsLeft());
        Queue::assertPushed(SendStablePackageNotification::class, fn ($job) => $job->audience === 'customer' && $job->channel === 'sms');
        Queue::assertPushed(SendStablePackageNotification::class, fn ($job) => $job->audience === 'stable' && $job->channel === 'email');

        // it is income like any booking: in the stable's statement, commission included
        $row = StableStatement::forPeriod($this->stable, 'this_month')->rows()->sole();
        $this->assertSame([6000, 54000, 54000], [$row->commission, $row->stableShare(), $row->net()]);
    }

    public function test_an_unpaid_package_is_dropped_by_the_expiry_sweep(): void
    {
        $purchase = app(StablePackages::class)->purchase($this->package, $this->customer);

        Carbon::setTestNow(now()->addHour());
        $this->artisan('orders:expire-pending')->assertSuccessful();

        $this->assertSame(StablePackagePurchase::CANCELLED, $purchase->refresh()->status);
    }

    public function test_booking_with_a_package_uses_its_sessions_and_cancelling_gives_them_back(): void
    {
        $purchase = $this->activePurchase();

        $booking = $this->bookWithPackage($purchase, 2);

        $this->assertSame([StableBookingStatus::Confirmed, 'package'], [$booking->status, $booking->payment_option]);
        $this->assertSame([PaymentStatus::Free, '0.000'], [$booking->order->payment_status, $booking->order->total]);
        $this->assertSame(3, $purchase->sessionsLeft());
        // a package session carries no money of its own: nothing new in the statement
        $this->assertCount(1, StableStatement::forPeriod($this->stable, 'this_month')->rows());

        app(StableBookingActions::class)->cancelByCustomer($booking);
        $this->assertSame(5, $purchase->sessionsLeft());
    }

    public function test_a_package_only_pays_for_its_own_customer_service_and_dates(): void
    {
        $purchase = $this->activePurchase();
        $someoneElse = Customer::create(['phone' => '96899999999', 'name' => 'Salim', 'phone_verified_at' => now()]);

        $refusals = [
            fn () => $this->bookWithPackage($purchase, 1, $someoneElse),                   // not theirs
            fn () => $this->bookWithPackage($purchase, 6),                                   // more riders than sessions (and than max_riders)
        ];

        foreach ($refusals as $attempt) {
            try {
                $attempt();
                $this->fail('The package should have been refused.');
            } catch (BookingUnavailable) {
                $this->addToAssertionCount(1);
            }
        }

        // a slot after the package expires
        $purchase->forceFill(['expires_at' => '2026-10-05 23:59:59'])->save();
        $this->expectException(BookingUnavailable::class);
        $this->expectExceptionMessage('expires on 2026-10-05');
        $this->bookWithPackage($purchase, 1);
    }

    public function test_the_last_sessions_cannot_be_spent_twice(): void
    {
        $purchase = $this->activePurchase();
        $this->bookWithPackage($purchase, 3);
        $this->slot->forceFill(['capacity' => 10])->save();

        $this->expectException(BookingUnavailable::class);
        $this->expectExceptionMessage('only 2 sessions left');
        $this->bookWithPackage($purchase, 3);
    }

    public function test_buying_needs_the_customer_to_sign_in_and_then_goes_to_payment(): void
    {
        $this->get(route('account.packages.buy', $this->package))->assertRedirect(route('account.login'));

        $this->actingAs($this->customer, 'customer')
            ->get(route('account.packages.buy', $this->package))
            ->assertOk()
            ->assertSee('5 lessons')
            ->assertSee('Save OMR 15.000');

        $response = $this->actingAs($this->customer, 'customer')->post(route('account.packages.purchase', $this->package));
        $purchase = StablePackagePurchase::query()->sole();
        $response->assertRedirect(route('payment.start', $purchase->order->order_number));

        // the service page offers it
        $this->get(route('bookings.offering', ['stable' => $this->stable->slug, 'offering' => $this->offering->id]))->assertOk()->assertSee('5 lessons');
    }

    public function test_the_booking_form_offers_the_customers_package(): void
    {
        $purchase = $this->activePurchase();

        $this->actingAs($this->customer, 'customer')
            ->get(route('bookings.create', ['slot' => $this->slot->id, 'riders' => 1]))
            ->assertOk()
            ->assertSee('Use my package: 5 lessons')
            ->assertSee('5 sessions left, until 2026-11-03');

        $this->actingAs($this->customer, 'customer')
            ->post(route('bookings.store', $this->slot), [
                'riders_count' => 1,
                'name' => 'Aisha',
                'phone' => '91234567',
                'payment_option' => 'package',
                'package_purchase_id' => $purchase->id,
                'riders' => [['name' => 'Omar', 'age' => 9, 'level' => 'beginner']],
            ])
            ->assertRedirect();

        $this->assertSame(4, $purchase->sessionsLeft());

        $this->actingAs($this->customer, 'customer')->get(route('account.packages'))->assertOk()->assertSee('4 sessions left');
    }

    public function test_a_guest_cannot_pay_with_a_package(): void
    {
        $purchase = $this->activePurchase();

        $this->post(route('bookings.store', $this->slot), [
            'riders_count' => 1, 'name' => 'Aisha', 'phone' => '91234567',
            'payment_option' => 'package', 'package_purchase_id' => $purchase->id,
            'riders' => [['name' => 'Omar', 'age' => 9, 'level' => 'beginner']],
        ])->assertSessionHasErrors('payment_option');

        $this->assertSame(5, $purchase->sessionsLeft());
    }

    // ── reviews ──────────────────────────────────────────────────────────────

    private function attendedBooking(): StableBooking
    {
        $this->offering->update(['price' => 0]);
        $booking = app(CreateStableBooking::class)->handle($this->slot, [['name' => 'Omar']], ['name' => 'Aisha Al Harthy', 'phone' => '91234567', 'customer_id' => $this->customer->id]);

        Carbon::setTestNow('2026-10-06 08:00:00');

        return app(StableBookingActions::class)->markAttended($booking);
    }

    public function test_attending_invites_a_review_that_shows_on_the_stable_page(): void
    {
        $booking = $this->attendedBooking();

        $invite = NotificationLog::query()->where('type', 'booking_review_invite')->sole();
        $this->assertStringContainsString('How was your session?', $invite->message);
        $this->assertStringContainsString('#review', $invite->message);

        $page = route('account.orders.show', $booking->order->order_number);
        $this->actingAs($this->customer, 'customer')->get($page)->assertOk()->assertSee('How was your session?');

        $this->actingAs($this->customer, 'customer')
            ->post(route('account.orders.review', $booking->order->order_number), ['rating' => 4, 'comment' => 'Patient trainer, calm horse.'])
            ->assertSessionHas('status');

        $review = StableReview::query()->sole();
        $this->assertSame([4, 'Aisha Al Harthy'], [$review->rating, $review->author_name]);

        // one review per session
        $this->actingAs($this->customer, 'customer')
            ->post(route('account.orders.review', $booking->order->order_number), ['rating' => 1])
            ->assertSessionHas('error');
        $this->assertSame(1, StableReview::query()->count());

        app(StableReviews::class)->reply($review, 'Thank you, see you next week!');

        $this->get(route('stables.show', $this->stable->slug))
            ->assertOk()
            ->assertSee('What riders say')
            ->assertSee('Aisha A.')
            ->assertDontSee('Al Harthy')
            ->assertSee('Patient trainer, calm horse.')
            ->assertSee('Thank you, see you next week!')
            ->assertSee('4.0');

        $this->assertSame(['average' => 4.0, 'count' => 1], $this->stable->rating());

        // an admin hides it: gone from the page and the average
        app(StableReviews::class)->setVisible($review, false);
        $this->get(route('stables.show', $this->stable->slug))->assertDontSee('Patient trainer');
        $this->assertSame(['average' => null, 'count' => 0], $this->stable->rating());
    }

    public function test_only_attended_sessions_of_the_customer_can_be_reviewed(): void
    {
        $this->offering->update(['price' => 0]);
        $booking = app(CreateStableBooking::class)->handle($this->slot, [['name' => 'Omar']], ['name' => 'Aisha', 'phone' => '91234567', 'customer_id' => $this->customer->id]);
        $reviews = app(StableReviews::class);

        $this->assertFalse($reviews->canReview($booking, $this->customer));                 // not attended yet

        Carbon::setTestNow('2026-10-06 08:00:00');
        app(StableBookingActions::class)->markAttended($booking);
        $stranger = Customer::create(['phone' => '96899999999', 'name' => 'Salim', 'phone_verified_at' => now()]);

        $this->assertTrue($reviews->canReview($booking->refresh(), $this->customer));
        $this->assertFalse($reviews->canReview($booking, $stranger));

        Carbon::setTestNow('2026-12-10 08:00:00');                                          // too long ago
        $this->assertFalse($reviews->canReview($booking, $this->customer));
    }

    // ── owner screens ────────────────────────────────────────────────────────

    public function test_the_owner_screens_render(): void
    {
        $purchase = $this->activePurchase();
        $booking = $this->attendedBooking();
        app(StableReviews::class)->submit($booking, $this->customer, 5, 'Great!');

        $this->actingAs($this->owner);
        $base = '/stable/'.$this->stable->slug;

        $this->get($base.'/stable-packages')->assertOk()->assertSee('5 lessons');
        $this->get($base.'/stable-package-purchases')->assertOk()->assertSee($purchase->reference)->assertSee('5 / 5');
        $this->get($base.'/stable-reviews')->assertOk()->assertSee('Great!');
    }
}
