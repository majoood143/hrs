<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StableBookingStatus;
use App\Filament\Resources\PaymentGatewayLogResource;
use App\Filament\Resources\PaymentGatewayLogResource\Pages\ListPaymentGatewayLogs;
use App\Filament\Resources\ServiceOrderResource\Pages\ListServiceOrders;
use App\Filament\Resources\ServiceOrderResource\Pages\ViewServiceOrder;
use App\Jobs\SendStableBookingNotification;
use App\Models\BookingSlot;
use App\Models\NotificationLog;
use App\Models\PaymentGatewayLog;
use App\Models\ServiceOrder;
use App\Models\StableBooking;
use App\Services\Stables\CreateStableBooking;
use App\Services\Stables\SlotGenerator;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/** An admin running a stable's booking from the orders list, for an owner who cannot reach the panel. */
class ServiceOrderAdminControlsTest extends TestCase
{
    use PreparesStableBookings;

    private StableBooking $booking;

    private ServiceOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo']);
        $this->withoutVite();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $stable = $this->makeStable($this->makeOwner());
        $offering = $this->makeOffering($stable, ['price' => 0]);
        app(SlotGenerator::class)->sync($this->makeSchedule($offering, [2], ['10:00']));
        $slot = BookingSlot::query()->where('date', '2026-10-06')->sole();

        $this->booking = app(CreateStableBooking::class)->handle($slot, [['name' => 'Omar', 'age' => 9, 'level' => 'beginner']], ['name' => 'Aisha', 'phone' => '91234567']);
        $this->order = $this->booking->order;

        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function list()
    {
        return Livewire::test(ListServiceOrders::class);
    }

    public function test_cancelling_from_the_row_cancels_the_booking_with_the_reason(): void
    {
        $this->list()
            ->callTableAction('cancelBooking', $this->order, ['reason' => 'The owner is away.'])
            ->assertHasNoTableActionErrors();

        $this->booking->refresh();
        $this->assertSame(StableBookingStatus::Cancelled, $this->booking->status);
        $this->assertSame('The owner is away.', $this->booking->cancellation_reason);
        $this->assertSame(OrderStatus::Cancelled, $this->order->refresh()->status);
    }

    public function test_a_reason_is_required_to_cancel(): void
    {
        $this->list()
            ->callTableAction('cancelBooking', $this->order, ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        $this->assertSame(StableBookingStatus::Confirmed, $this->booking->refresh()->status);
    }

    public function test_attended_and_no_show_appear_only_from_the_sessions_day(): void
    {
        $this->list()->assertTableActionHidden('attended', $this->order)->assertTableActionHidden('noShow', $this->order);

        Carbon::setTestNow('2026-10-06 12:00:00');

        $this->list()->callTableAction('attended', $this->order);
        $this->assertSame(StableBookingStatus::Completed, $this->booking->refresh()->status);
        $this->assertSame(OrderStatus::Completed, $this->order->refresh()->status);

        $this->list()->callTableAction('noShow', $this->order);
        $this->assertSame(StableBookingStatus::NoShow, $this->booking->refresh()->status);
    }

    public function test_changing_a_booking_takes_the_update_permission(): void
    {
        // Gate::before callbacks run in order and the first answer wins, so start over
        $this->app->forgetInstance(\Illuminate\Contracts\Auth\Access\Gate::class);
        Gate::clearResolvedInstances();
        Gate::before(fn ($user, string $ability) => $ability !== 'update');

        Livewire::test(ViewServiceOrder::class, ['record' => $this->order->getKey()])
            ->assertActionHidden('cancelBooking')
            ->assertActionVisible('ticket');
    }

    public function test_the_ticket_downloads_from_the_row_and_goes_once_the_booking_is_cancelled(): void
    {
        $this->list()->callTableAction('ticket', $this->order)->assertFileDownloaded('ticket-'.$this->booking->reference.'.pdf');

        $this->order->stableBooking->forceFill(['status' => StableBookingStatus::Cancelled])->save();
        $this->list()->assertTableActionHidden('ticket', $this->order);
    }

    public function test_resending_a_booking_sends_its_own_message_again_even_if_it_went_before(): void
    {
        Queue::fake();
        NotificationLog::create(['service_order_id' => $this->order->id, 'channel' => 'sms', 'type' => 'booking_confirmed', 'recipient' => '96891234567', 'status' => 'sent']);

        $this->list()->callTableAction('resend', $this->order, ['type' => SendStableBookingNotification::CONFIRMED]);

        Queue::assertPushed(SendStableBookingNotification::class, fn ($job) => $job->force
            && $job->audience === 'customer'
            && $job->channel === 'sms'
            && $job->type === SendStableBookingNotification::CONFIRMED);
    }

    public function test_a_pay_at_the_stable_booking_can_be_resent_but_not_refunded(): void
    {
        $this->order->forceFill(['payment_status' => PaymentStatus::OnSite])->save();

        $this->list()->assertTableActionVisible('resend', $this->order)->assertTableActionHidden('refund', $this->order);
    }

    public function test_an_expired_checkout_has_nothing_to_resend(): void
    {
        $this->booking->forceFill(['status' => StableBookingStatus::Cancelled, 'confirmed_at' => null])->save();

        $this->list()->assertTableActionHidden('resend', $this->order);
    }

    public function test_a_refund_can_be_recorded_from_the_row(): void
    {
        $this->order->forceFill(['payment_status' => PaymentStatus::Paid, 'price' => '10.000', 'total' => '10.000'])->save();

        $this->list()->callTableAction('refund', $this->order, ['amount' => 4, 'method' => 'manual', 'reference' => 'BT-1', 'reason' => 'x']);

        $this->assertSame(4.0, (float) $this->order->refresh()->refunded_amount);
    }

    public function test_the_payment_log_link_is_filtered_to_the_order(): void
    {
        $this->order->forceFill(['payment_method' => 'demo'])->save();

        $this->list()->assertTableActionHasUrl('paymentLogs', PaymentGatewayLogResource::getUrl('index', ['search' => $this->order->order_number]), $this->order);
    }

    public function test_an_unpaid_order_offers_its_payment_page_and_a_paid_one_does_not(): void
    {
        $this->list()->assertTableActionHidden('paymentLink', $this->order);

        $this->order->forceFill(['status' => OrderStatus::PendingPayment, 'payment_status' => PaymentStatus::Pending])->save();

        $this->list()->assertTableActionHasUrl('paymentLink', route('payment.start', $this->order->order_number), $this->order);
        $this->get(route('payment.start', $this->order->order_number))->assertOk();
    }

    public function test_the_payment_log_search_finds_only_this_orders_rows(): void
    {
        $other = $this->order->replicate(['order_number']);
        $other->order_number = 'SO-OTHER';
        $other->save();

        $mine = PaymentGatewayLog::create(['service_order_id' => $this->order->id, 'gateway' => 'demo', 'event' => 'callback']);
        $theirs = PaymentGatewayLog::create(['service_order_id' => $other->id, 'gateway' => 'demo', 'event' => 'callback']);

        Livewire::withQueryParams(['search' => $this->order->order_number])
            ->test(ListPaymentGatewayLogs::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_checking_an_unsettled_payment_says_so_and_changes_nothing(): void
    {
        $this->order->forceFill(['status' => OrderStatus::PendingPayment, 'payment_status' => PaymentStatus::Pending, 'payment_method' => 'demo', 'payment_session_id' => 'sess-1'])->save();

        $this->list()
            ->callTableAction('checkPayment', $this->order)
            ->assertNotified(__('admin_service_order.notifications.payment_not_confirmed'));

        $this->assertSame(OrderStatus::PendingPayment, $this->order->refresh()->status);
    }
}
