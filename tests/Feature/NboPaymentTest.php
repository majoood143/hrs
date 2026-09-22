<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Events\ServiceOrderReceived;
use App\Models\PaymentGatewayLog;
use App\Models\ServiceOrder;
use App\Services\Payments\Gateways\NboGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparesOrderSite;
use Tests\TestCase;

class NboPaymentTest extends TestCase
{
    use PreparesOrderSite;

    private const ENDPOINT = 'https://unifiedpg.nbo.om/OLTPSTG/payment/hosted.htm';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareOrderSite([
            'enabled_gateways' => ['nbo'],
            'nbo.tranportal_id' => 'TP001',
            'nbo.tranportal_password' => 'super-secret-password',
            'nbo.resource_key' => 'abcdefghijklmnopqrstuvwxyz012345',
            'nbo.test_mode' => true,
        ]);
    }

    private function orderWithPayment(string $paymentId = 'PID123'): ServiceOrder
    {
        $order = $this->makeOrder();
        $order->update(['payment_session_id' => $paymentId, 'payment_method' => PaymentGateway::Nbo]);

        return $order->refresh();
    }

    public function test_paying_asks_nbo_for_a_payment_page_with_an_encrypted_request(): void
    {
        Http::fake([self::ENDPOINT => Http::response(json_encode([['status' => '1', 'result' => 'PID123:https://pay.nbo.test/page']]))]);
        $order = $this->makeOrder();

        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'nbo'])
            ->assertRedirect('https://pay.nbo.test/page?PaymentID=PID123');

        Http::assertSent(function (Request $request) use ($order) {
            $sent = $request->data()[0];
            $plain = app(NboGateway::class)->decrypt($sent['trandata']);

            return $sent['id'] === 'TP001'
                && $plain['amt'] === '11.025'
                && $plain['currencycode'] === '512'
                && $plain['password'] === 'super-secret-password'
                && $plain['udf1'] === str_replace('-', '', $order->order_number)
                && $plain['udf2'] === '96891234567'
                && $plain['billingInfo']['country'] === 'OM'
                && $plain['billingInfo']['email'] === 'ali@example.com'
                && ctype_digit($plain['trackId']);
        });

        $order->refresh();
        $this->assertSame('PID123', $order->payment_session_id);
        $this->assertSame(PaymentGateway::Nbo, $order->payment_method);
    }

    public function test_the_gateway_password_never_reaches_the_log(): void
    {
        Http::fake([self::ENDPOINT => Http::response(json_encode([['status' => '1', 'result' => 'PID123:https://pay.nbo.test/page']]))]);
        $order = $this->makeOrder();

        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'nbo']);

        $log = PaymentGatewayLog::where('event', 'initiate_payment')->firstOrFail();
        $this->assertSame('••••••••', $log->request_payload['plain']['password']);
        $this->assertStringNotContainsString('super-secret-password', json_encode($log->request_payload));
    }

    public function test_a_refusal_from_nbo_is_reported_to_the_customer(): void
    {
        Http::fake([self::ENDPOINT => Http::response(json_encode([['status' => '0', 'errorText' => 'Invalid tranportal id']]))]);
        $order = $this->makeOrder();

        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'nbo'])
            ->assertSessionHas('error', __('payments.could_not_start'));

        $this->assertTrue($order->refresh()->isPayable());
    }

    public function test_a_captured_result_with_the_right_amount_confirms_the_order(): void
    {
        Event::fake([ServiceOrderReceived::class]);
        $order = $this->orderWithPayment();

        // NBO really posts a flat, unencrypted form with inconsistent casing
        $this->post(route('payment.nbo.callback'), ['paymentid' => 'PID123', 'Result' => 'CAPTURED', 'amt' => '11.025', 'TranId' => 'T1', 'Error' => '0'])
            ->assertRedirect(route('orders.show', $order->order_number));

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame('PID123', $order->payment_reference);
        Event::assertDispatchedTimes(ServiceOrderReceived::class, 1);
        $this->assertSame('success', PaymentGatewayLog::where('event', 'callback')->first()->outcome);

        // replayed callbacks do nothing
        $this->post(route('payment.nbo.callback'), ['paymentid' => 'PID123', 'Result' => 'CAPTURED', 'amt' => '11.025'])->assertRedirect();
        Event::assertDispatchedTimes(ServiceOrderReceived::class, 1);
    }

    public function test_the_encrypted_trandata_shape_is_understood_too(): void
    {
        $order = $this->orderWithPayment();
        $trandata = app(NboGateway::class)->encrypt(['result' => 'APPROVED', 'amt' => '11.025', 'tranId' => 'T1']);

        $this->post(route('payment.nbo.callback'), ['paymentId' => 'PID123', 'trandata' => $trandata])->assertRedirect();

        $this->assertTrue($order->refresh()->isPaid());
    }

    public function test_a_captured_result_for_the_wrong_amount_is_treated_as_failed(): void
    {
        $order = $this->orderWithPayment();

        $this->post(route('payment.nbo.callback'), ['paymentid' => 'PID123', 'result' => 'CAPTURED', 'amt' => '0.100'])
            ->assertRedirect(route('payment.start', $order->order_number));

        $order->refresh();
        $this->assertFalse($order->isPaid());
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
    }

    public function test_a_forged_callback_without_the_amount_cannot_confirm_an_order(): void
    {
        $order = $this->orderWithPayment();

        $this->post(route('payment.nbo.callback'), ['paymentid' => 'PID123', 'result' => 'CAPTURED'])->assertRedirect();

        $this->assertFalse($order->refresh()->isPaid());
    }

    public function test_a_declined_or_cancelled_payment_leaves_the_order_payable(): void
    {
        $order = $this->orderWithPayment();

        $this->post(route('payment.nbo.callback'), ['paymentid' => 'PID123', 'result' => 'NOT CAPTURED', 'amt' => '11.025'])
            ->assertRedirect(route('payment.start', $order->order_number));

        $this->assertSame(PaymentStatus::Failed, $order->refresh()->payment_status);
        $this->assertTrue($order->isPayable());
    }

    public function test_an_explicit_error_from_nbo_shows_its_message(): void
    {
        $order = $this->orderWithPayment();

        $this->post(route('payment.nbo.callback'), ['paymentid' => 'PID123', 'Error' => 'IPAY0100114', 'ErrorText' => 'Duplicate Record'])
            ->assertRedirect(route('payment.start', $order->order_number))
            ->assertSessionHas('error', 'Duplicate Record');

        $this->assertSame('failed', PaymentGatewayLog::where('event', 'callback')->first()->outcome);
    }

    public function test_a_callback_for_an_unknown_payment_goes_home(): void
    {
        $this->post(route('payment.nbo.callback'), ['paymentid' => 'NOPE', 'result' => 'CAPTURED', 'amt' => '11.025'])->assertRedirect('/');
        $this->post(route('payment.nbo.callback'), [])->assertRedirect('/');
    }
}
