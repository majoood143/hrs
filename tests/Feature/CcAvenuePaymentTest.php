<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Events\ServiceOrderReceived;
use App\Models\PaymentGatewayLog;
use App\Models\ServiceOrder;
use App\Services\Payments\Gateways\CcAvenueGateway;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\PreparesOrderSite;
use Tests\TestCase;

class CcAvenuePaymentTest extends TestCase
{
    use PreparesOrderSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareOrderSite([
            'enabled_gateways' => ['ccavenue'],
            'ccavenue.merchant_id' => 'M123',
            'ccavenue.access_code' => 'AVACCESS',
            'ccavenue.working_key' => 'abcdefghijklmnopqrstuvwxyz012345',
            'ccavenue.test_mode' => true,
        ]);
    }

    private function gateway(): CcAvenueGateway
    {
        return app(CcAvenueGateway::class);
    }

    private function orderWithPayment(): ServiceOrder
    {
        $order = $this->makeOrder();
        $order->update(['payment_session_id' => $order->compactNumber(), 'payment_method' => PaymentGateway::CcAvenue]);

        return $order->refresh();
    }

    private function encResp(ServiceOrder $order, array $overrides = []): string
    {
        return $this->gateway()->encrypt(http_build_query($overrides + [
            'order_id' => $order->compactNumber(),
            'order_status' => 'Success',
            'amount' => '11.025',
            'tracking_id' => 'TRK1',
        ]));
    }

    public function test_encryption_round_trips_and_rejects_tampering(): void
    {
        $encrypted = $this->gateway()->encrypt('order_id=1&amount=2.000');

        $this->assertSame('order_id=1&amount=2.000', $this->gateway()->decrypt($encrypted));
        $this->assertNotSame($encrypted, $this->gateway()->encrypt('order_id=1&amount=2.000'), 'a fresh IV each time');

        $tampered = substr($encrypted, 0, -2).(str_ends_with($encrypted, '00') ? '11' : '00');
        $this->expectException(\RuntimeException::class);
        $this->gateway()->decrypt($tampered);
    }

    public function test_paying_renders_a_form_that_posts_the_encrypted_request_to_the_gateway(): void
    {
        $order = $this->makeOrder();

        $response = $this->post(route('payment.begin', $order->order_number), ['gateway' => 'ccavenue']);

        $response->assertOk()
            ->assertSee('action="https://mti.bankmuscat.com:6443/transaction.do?command=initiateTransaction"', false)
            ->assertSee('name="access_code" value="AVACCESS"', false);

        preg_match('/name="encRequest" value="([0-9a-f]+)"/', $response->getContent(), $match);
        parse_str($this->gateway()->decrypt($match[1]), $plain);

        $compact = str_replace('-', '', $order->order_number);
        $this->assertSame($compact, $plain['order_id'], 'Bank Muscat only accepts alphanumeric order ids');
        $this->assertSame('11.025', $plain['amount']);
        $this->assertSame('OMR', $plain['currency']);
        $this->assertSame('M123', $plain['merchant_id']);
        $this->assertSame(route('payment.ccavenue.callback'), $plain['redirect_url']);

        $order->refresh();
        $this->assertSame($compact, $order->payment_session_id);
        $this->assertSame(PaymentGateway::CcAvenue, $order->payment_method);
        $this->assertSame('••••••••', PaymentGatewayLog::where('event', 'initiate_payment')->first()->request_payload['plain']['billing_email']);
    }

    public function test_a_successful_response_with_the_right_amount_confirms_the_order(): void
    {
        Event::fake([ServiceOrderReceived::class]);
        $order = $this->orderWithPayment();

        $this->post(route('payment.ccavenue.callback'), ['encResp' => $this->encResp($order)])
            ->assertRedirect(route('orders.show', $order->order_number));

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame('TRK1', $order->payment_reference);
        $this->assertSame('success', PaymentGatewayLog::where('event', 'callback')->first()->outcome);

        $this->post(route('payment.ccavenue.callback'), ['encResp' => $this->encResp($order)])->assertRedirect();
        Event::assertDispatchedTimes(ServiceOrderReceived::class, 1);
    }

    public function test_a_success_for_the_wrong_amount_is_treated_as_failed(): void
    {
        $order = $this->orderWithPayment();

        $this->post(route('payment.ccavenue.callback'), ['encResp' => $this->encResp($order, ['amount' => '1.000'])])
            ->assertRedirect(route('payment.start', $order->order_number));

        $this->assertFalse($order->refresh()->isPaid());
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
    }

    public function test_a_failure_or_abort_keeps_the_order_payable_and_shows_the_reason(): void
    {
        $order = $this->orderWithPayment();

        $this->post(route('payment.ccavenue.callback'), ['encResp' => $this->encResp($order, ['order_status' => 'Failure', 'failure_message' => 'Card declined'])])
            ->assertRedirect(route('payment.start', $order->order_number))
            ->assertSessionHas('error', 'Card declined');

        $order->refresh();
        $this->assertTrue($order->isPayable());
        $this->assertSame('failed', PaymentGatewayLog::where('event', 'callback')->first()->outcome);
    }

    public function test_a_response_that_does_not_decrypt_is_rejected_and_logged(): void
    {
        $order = $this->orderWithPayment();

        $this->post(route('payment.ccavenue.callback'), ['encResp' => bin2hex(random_bytes(48))])->assertRedirect('/');
        $this->post(route('payment.ccavenue.callback'), ['encResp' => 'not-hex'])->assertRedirect('/');
        $this->post(route('payment.ccavenue.callback'), [])->assertRedirect('/');

        $this->assertFalse($order->refresh()->isPaid());
        $this->assertSame('error', PaymentGatewayLog::where('event', 'callback')->first()->outcome);
    }

    public function test_a_response_for_an_unknown_order_goes_home(): void
    {
        $order = $this->orderWithPayment();
        $stranger = $this->gateway()->encrypt(http_build_query(['order_id' => 'SOXXXXXXXX', 'order_status' => 'Success', 'amount' => '11.025']));

        $this->post(route('payment.ccavenue.callback'), ['encResp' => $stranger])->assertRedirect('/');

        $this->assertFalse($order->refresh()->isPaid());
    }
}
