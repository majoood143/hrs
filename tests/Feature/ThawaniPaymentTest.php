<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Events\ServiceOrderReceived;
use App\Models\PaymentGatewayLog;
use App\Models\ServiceOrder;
use App\Services\Payments\OrderPaymentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparesOrderSite;
use Tests\TestCase;

class ThawaniPaymentTest extends TestCase
{
    use PreparesOrderSite;

    private const API = 'https://uatcheckout.thawani.om/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareOrderSite([
            'enabled_gateways' => ['thawani'],
            'thawani.secret_key' => 'sk_test',
            'thawani.publishable_key' => 'pk_test',
            'thawani.test_mode' => true,
        ]);
    }

    private function sessionBody(string $status, int $total = 11025, string $id = 'sess_1'): array
    {
        return ['success' => true, 'data' => [
            'session_id' => $id,
            'payment_status' => $status,
            'total_amount' => $total,
            'invoice' => 'INV-1',
        ]];
    }

    private function fakeSession(array $body, int $status = 200, string $id = 'sess_1'): void
    {
        Http::fake([self::API."/checkout/session/{$id}" => Http::response($body, $status)]);
    }

    private function orderWithSession(string $id = 'sess_1'): ServiceOrder
    {
        $order = $this->makeOrder();
        $order->update(['payment_session_id' => $id, 'payment_method' => PaymentGateway::Thawani]);

        return $order->refresh();
    }

    // ── Starting a payment ───────────────────────────────────────────────────

    public function test_paying_creates_a_session_for_the_whole_total_and_redirects_to_thawani(): void
    {
        Http::fake([self::API.'/checkout/session' => Http::response(['success' => true, 'data' => ['session_id' => 'sess_1']])]);
        $order = $this->makeOrder();

        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'thawani'])
            ->assertRedirect('https://uatcheckout.thawani.om/pay/sess_1?key=pk_test');

        Http::assertSent(function (Request $request) use ($order) {
            return $request->method() === 'POST'
                && $request->header('thawani-api-key') === ['sk_test']
                && $request['client_reference_id'] === $order->order_number
                && $request['products'][0]['unit_amount'] === 11025
                && $request['products'][0]['quantity'] === 1
                && str_contains($request['success_url'], 'payment/thawani/return?reference='.$order->order_number)
                && str_contains($request['cancel_url'], 'payment/thawani/cancel?reference='.$order->order_number);
        });

        $order->refresh();
        $this->assertSame('sess_1', $order->payment_session_id);
        $this->assertSame(PaymentGateway::Thawani, $order->payment_method);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertDatabaseHas('payment_gateway_logs', ['service_order_id' => $order->id, 'gateway' => 'thawani', 'event' => 'create_session', 'status_code' => 200]);
    }

    public function test_a_gateway_error_keeps_the_customer_on_the_page_and_the_order_payable(): void
    {
        Http::fake([self::API.'/checkout/session' => Http::response(['error' => 'bad key'], 401)]);
        $order = $this->makeOrder();

        $this->from(route('payment.start', $order->order_number))
            ->post(route('payment.begin', $order->order_number), ['gateway' => 'thawani'])
            ->assertRedirect(route('payment.start', $order->order_number))
            ->assertSessionHas('error', __('payments.could_not_start'));

        $this->assertTrue($order->refresh()->isPayable());
        $this->assertSame('error', PaymentGatewayLog::where('event', 'create_session')->first()->outcome);
    }

    public function test_an_unknown_or_unconfigured_gateway_is_refused(): void
    {
        Http::fake();
        $order = $this->makeOrder();

        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'nbo'])->assertSessionHas('error');
        $this->post(route('payment.begin', $order->order_number), [])->assertSessionHas('error');

        Http::assertNothingSent();
    }

    // ── Coming back ──────────────────────────────────────────────────────────

    public function test_a_paid_session_confirms_the_order_once(): void
    {
        Event::fake([ServiceOrderReceived::class]);
        $this->fakeSession($this->sessionBody('paid'));
        $order = $this->orderWithSession();

        $this->get(route('payment.thawani.return', ['reference' => $order->order_number]))
            ->assertRedirect(route('orders.show', $order->order_number));

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame('INV-1', $order->payment_reference);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('RC-'.now()->format('Y').'-000001', $order->receipt_number);
        Event::assertDispatchedTimes(ServiceOrderReceived::class, 1);

        // the browser return, the webhook and the sweeps can all arrive again: nothing changes
        $this->get(route('payment.thawani.return', ['reference' => $order->order_number]))->assertRedirect();
        $this->assertSame('RC-'.now()->format('Y').'-000001', $order->refresh()->receipt_number);
        Event::assertDispatchedTimes(ServiceOrderReceived::class, 1);
        $this->assertSame(['created', 'paid'], $order->events->pluck('type')->all());
    }

    public function test_receipt_numbers_run_in_sequence(): void
    {
        $this->fakeSession($this->sessionBody('paid', id: 'sess_a'), id: 'sess_a');
        $first = $this->orderWithSession('sess_a');
        $this->get(route('payment.thawani.return', ['reference' => $first->order_number]));

        Http::fake([self::API.'/checkout/session/sess_b' => Http::response($this->sessionBody('paid', id: 'sess_b'))]);
        $second = $this->orderWithSession('sess_b');
        $this->get(route('payment.thawani.return', ['reference' => $second->order_number]));

        $year = now()->format('Y');
        $this->assertSame("RC-{$year}-000001", $first->refresh()->receipt_number);
        $this->assertSame("RC-{$year}-000002", $second->refresh()->receipt_number);
    }

    public function test_an_unpaid_session_leaves_the_order_payable_and_sends_the_customer_back(): void
    {
        $this->fakeSession($this->sessionBody('unpaid'));
        $order = $this->orderWithSession();

        $this->get(route('payment.thawani.return', ['reference' => $order->order_number]))
            ->assertRedirect(route('payment.start', $order->order_number))
            ->assertSessionHas('error', __('payments.not_completed'));

        $order->refresh();
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertTrue($order->isPayable(), 'the customer may try again on the same order');
    }

    public function test_a_cancelled_session_says_so(): void
    {
        $this->fakeSession($this->sessionBody('cancelled'));
        $order = $this->orderWithSession();

        $this->get(route('payment.thawani.return', ['reference' => $order->order_number]))
            ->assertSessionHas('error', __('payments.cancelled'));
    }

    public function test_a_session_paid_for_another_amount_never_marks_the_order_paid(): void
    {
        $this->fakeSession($this->sessionBody('paid', total: 100));
        $order = $this->orderWithSession();

        $this->get(route('payment.thawani.return', ['reference' => $order->order_number]))
            ->assertRedirect(route('orders.show', $order->order_number))
            ->assertSessionHas('error', __('payments.paid_but_unconfirmed'));

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
        $this->assertSame('error', PaymentGatewayLog::where('event', 'amount_mismatch')->first()->outcome);
    }

    public function test_when_thawani_cannot_be_reached_the_order_is_left_alone(): void
    {
        $this->fakeSession(['error' => 'down'], 500);
        $order = $this->orderWithSession();

        $this->get(route('payment.thawani.return', ['reference' => $order->order_number]))
            ->assertRedirect(route('payment.start', $order->order_number))
            ->assertSessionHas('error', __('payments.verify_failed'));

        $order->refresh();
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
    }

    public function test_an_unknown_reference_goes_home(): void
    {
        $this->get(route('payment.thawani.return', ['reference' => 'SO-NOPE']))->assertRedirect('/');
        $this->get(route('payment.thawani.return'))->assertRedirect('/');
    }

    public function test_the_cancel_url_asks_thawani_before_giving_up(): void
    {
        $this->fakeSession($this->sessionBody('paid'));
        $order = $this->orderWithSession();

        $this->get(route('payment.thawani.cancel', ['reference' => $order->order_number]))
            ->assertRedirect(route('orders.show', $order->order_number));

        $this->assertTrue($order->refresh()->isPaid(), 'a payment that landed just before the cancel click is still honoured');
    }

    public function test_cancelling_leaves_the_order_payable(): void
    {
        $this->fakeSession($this->sessionBody('unpaid'));
        $order = $this->orderWithSession();

        $this->get(route('payment.thawani.cancel', ['reference' => $order->order_number]))
            ->assertRedirect(route('payment.start', $order->order_number));

        $this->assertTrue($order->refresh()->isPayable());
        $this->assertDatabaseHas('payment_gateway_logs', ['service_order_id' => $order->id, 'event' => 'cancel']);
    }

    // ── Webhook ──────────────────────────────────────────────────────────────

    private function webhook(string $body, ?string $signature = null)
    {
        return $this->call('POST', route('payment.thawani.webhook'), [], [], [], array_filter([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_THAWANI_SIGNATURE' => $signature,
        ]), $body);
    }

    public function test_the_webhook_settles_the_order_by_asking_thawani_not_by_believing_the_event(): void
    {
        $this->fakeSession($this->sessionBody('paid'));
        $order = $this->orderWithSession();

        $this->webhook(json_encode(['event_type' => 'checkout.completed', 'data' => ['session_id' => 'sess_1']]))
            ->assertOk();

        $this->assertTrue($order->refresh()->isPaid());
        $this->assertDatabaseHas('payment_gateway_logs', ['service_order_id' => $order->id, 'event' => 'webhook']);
    }

    public function test_a_forged_webhook_claiming_payment_changes_nothing(): void
    {
        $this->fakeSession($this->sessionBody('unpaid'));
        $order = $this->orderWithSession();

        $this->webhook(json_encode(['event_type' => 'checkout.completed', 'data' => ['session_id' => 'sess_1', 'payment_status' => 'paid']]))
            ->assertOk();

        $this->assertFalse($order->refresh()->isPaid());
    }

    public function test_the_webhook_signature_is_checked_when_a_secret_is_set(): void
    {
        $this->seedSiteSettings([
            'enabled_gateways' => ['thawani'], 'thawani.secret_key' => 'sk_test', 'thawani.publishable_key' => 'pk_test',
            'thawani.webhook_secret' => 'whsec',
        ]);
        $this->fakeSession($this->sessionBody('paid'));
        $order = $this->orderWithSession();
        $body = json_encode(['event_type' => 'checkout.completed', 'data' => ['session_id' => 'sess_1']]);

        $this->webhook($body, 'wrong')->assertStatus(401);
        $this->webhook($body)->assertStatus(401);
        $this->assertFalse($order->refresh()->isPaid());

        $this->webhook($body, hash_hmac('sha256', $body, 'whsec'))->assertOk();
        $this->assertTrue($order->refresh()->isPaid());
    }

    public function test_a_webhook_for_an_unknown_session_is_acknowledged_and_logged(): void
    {
        Http::fake();

        $this->webhook(json_encode(['event_type' => 'x', 'data' => ['session_id' => 'nope']]))->assertOk();

        $this->assertDatabaseHas('payment_gateway_logs', ['service_order_id' => null, 'event' => 'webhook']);
        Http::assertNothingSent();
    }

    // ── Sweeps ───────────────────────────────────────────────────────────────

    private function age(ServiceOrder $order, int $minutes): ServiceOrder
    {
        $order->forceFill(['created_at' => now()->subMinutes($minutes)])->saveQuietly();

        return $order->refresh();
    }

    public function test_the_expiry_sweep_cancels_old_unpaid_orders_and_spares_new_ones(): void
    {
        $this->fakeSession($this->sessionBody('unpaid'));
        $old = $this->age($this->orderWithSession(), 45);
        $fresh = $this->age($this->makeOrder(), 5);

        $this->artisan('orders:expire-pending')->assertSuccessful();

        $old->refresh();
        $this->assertSame(OrderStatus::Cancelled, $old->status);
        $this->assertSame(PaymentStatus::Cancelled, $old->payment_status);
        $this->assertSame('system', $old->cancellation_source);
        $this->assertNotNull($old->cancelled_at);
        $this->assertSame(OrderStatus::PendingPayment, $fresh->refresh()->status);
    }

    public function test_the_expiry_sweep_settles_an_order_that_was_paid_after_all(): void
    {
        $this->fakeSession($this->sessionBody('paid'));
        $order = $this->age($this->orderWithSession(), 45);

        $this->artisan('orders:expire-pending')->assertSuccessful();

        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame(OrderStatus::New, $order->status);
    }

    public function test_the_expiry_sweep_never_cancels_an_order_it_cannot_verify(): void
    {
        $this->fakeSession(['error' => 'down'], 500);
        $order = $this->age($this->orderWithSession(), 45);

        $this->artisan('orders:expire-pending')->assertSuccessful();

        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->status);
    }

    public function test_the_expiry_sweep_cancels_orders_that_never_reached_a_gateway(): void
    {
        Http::fake();
        $order = $this->age($this->makeOrder(), 45);

        $this->artisan('orders:expire-pending')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_a_late_payment_revives_an_order_the_system_cancelled(): void
    {
        Event::fake([ServiceOrderReceived::class]);
        $this->fakeSession($this->sessionBody('paid'));
        $order = $this->orderWithSession();
        $order->update(['status' => OrderStatus::Cancelled, 'payment_status' => PaymentStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_source' => 'system']);

        $this->artisan('orders:recover-late-payments')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNull($order->cancelled_at);
        $this->assertNull($order->cancellation_source);
        $this->assertNotNull($order->receipt_number);
        Event::assertDispatchedTimes(ServiceOrderReceived::class, 1);
        $this->assertDatabaseHas('payment_gateway_logs', ['service_order_id' => $order->id, 'event' => 'late_payment_recovered']);

        // settled orders are not looked at again
        $this->artisan('orders:recover-late-payments')->assertSuccessful();
        Event::assertDispatchedTimes(ServiceOrderReceived::class, 1);
    }

    public function test_a_payment_for_an_order_an_admin_cancelled_is_flagged_for_a_refund_not_revived(): void
    {
        Event::fake([ServiceOrderReceived::class]);
        $this->fakeSession($this->sessionBody('paid'));
        $order = $this->orderWithSession();
        $order->update(['status' => OrderStatus::Cancelled, 'payment_status' => PaymentStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_source' => 'admin']);

        $this->get(route('payment.thawani.return', ['reference' => $order->order_number]))
            ->assertRedirect(route('orders.show', $order->order_number));

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertTrue($order->meta['needs_refund']);
        Event::assertNotDispatched(ServiceOrderReceived::class);
        $this->assertSame('error', PaymentGatewayLog::where('event', 'paid_needs_manual_action')->first()->outcome);

        $this->get(route('orders.show', $order->order_number))->assertSee(__('orders.paid_but_cancelled'));
    }

    /**
     * Two requests can both see the order as unpaid (the browser return and the webhook arriving
     * together) and only then queue on the row lock: the second must find it already settled.
     */
    public function test_two_racing_payment_confirmations_apply_only_once(): void
    {
        Event::fake([ServiceOrderReceived::class]);
        $order = $this->orderWithSession();
        $payments = app(OrderPaymentService::class);
        $staleCopy = ServiceOrder::find($order->id);

        $this->assertSame(OrderPaymentService::ACTION_CONFIRM, $payments->applyPaid($order, PaymentGateway::Thawani, 'REF-1'));
        $this->assertSame(OrderPaymentService::ACTION_NONE, $payments->applyPaid($staleCopy, PaymentGateway::Thawani, 'REF-2'));

        $order->refresh();
        $this->assertSame('REF-1', $order->payment_reference);
        $this->assertSame('RC-'.now()->format('Y').'-000001', $order->receipt_number);
        Event::assertDispatchedTimes(ServiceOrderReceived::class, 1);
        $this->assertSame(1, $order->events->where('type', 'paid')->count());
    }

    public function test_a_payment_flagged_for_a_refund_is_flagged_only_once(): void
    {
        $order = $this->orderWithSession();
        $order->update(['status' => OrderStatus::Cancelled, 'payment_status' => PaymentStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_source' => 'admin']);
        $payments = app(OrderPaymentService::class);
        $staleCopy = ServiceOrder::find($order->id);

        $this->assertSame(OrderPaymentService::ACTION_NEEDS_REFUND, $payments->applyPaid($order, PaymentGateway::Thawani, 'REF-1'));
        $this->assertSame(OrderPaymentService::ACTION_NONE, $payments->applyPaid($staleCopy, PaymentGateway::Thawani, 'REF-1'));

        $this->assertSame(1, PaymentGatewayLog::where('event', 'paid_needs_manual_action')->count());
        $this->assertSame(1, $order->refresh()->events->where('type', 'paid_needs_refund')->count());
    }

    public function test_a_cancelled_order_cannot_be_paid_again_from_the_checkout_page(): void
    {
        $order = $this->makeOrder();
        $order->update(['status' => OrderStatus::Cancelled, 'payment_status' => PaymentStatus::Cancelled]);

        $this->get(route('payment.start', $order->order_number))->assertRedirect(route('orders.show', $order->order_number));
        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'thawani'])->assertRedirect(route('orders.show', $order->order_number));
    }
}
