<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Events\ServiceOrderReceived;
use App\Models\PaymentGatewayLog;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\PreparesOrderSite;
use Tests\TestCase;

class DemoPaymentAndPagesTest extends TestCase
{
    use PreparesOrderSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareOrderSite(['enabled_gateways' => ['demo', 'thawani']]);
    }

    public function test_the_demo_gateway_walks_the_whole_flow_without_a_merchant_account(): void
    {
        Event::fake([ServiceOrderReceived::class]);
        $order = $this->makeOrder();

        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'demo'])
            ->assertRedirect(route('payment.demo', $order->order_number));

        $this->get(route('payment.demo', $order->order_number))
            ->assertOk()
            ->assertSee(__('payments.demo_notice'));

        $this->post(route('payment.demo.decide', $order->order_number), ['decision' => 'approve'])
            ->assertRedirect(route('orders.show', $order->order_number));

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(PaymentGateway::Demo, $order->payment_method);
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertNotNull($order->receipt_number);
        Event::assertDispatchedTimes(ServiceOrderReceived::class, 1);
        $this->assertSame('success', PaymentGatewayLog::where('gateway', 'demo')->where('event', 'callback')->first()->outcome);
    }

    public function test_declining_in_the_demo_fails_the_payment_but_keeps_the_order_payable(): void
    {
        $order = $this->makeOrder();
        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'demo']);

        $this->post(route('payment.demo.decide', $order->order_number), ['decision' => 'decline'])
            ->assertRedirect(route('payment.start', $order->order_number));

        $order->refresh();
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertTrue($order->isPayable());
    }

    public function test_the_demo_pages_do_not_exist_unless_the_demo_gateway_is_switched_on(): void
    {
        $this->seedSiteSettings(['enabled_gateways' => ['thawani']]);
        $order = $this->makeOrder();

        $this->get(route('payment.demo', $order->order_number))->assertNotFound();
        $this->post(route('payment.demo.decide', $order->order_number), ['decision' => 'approve'])->assertNotFound();

        $this->assertFalse($order->refresh()->isPaid());
    }

    public function test_the_checkout_page_shows_the_breakdown_and_only_usable_gateways(): void
    {
        // thawani is ticked but has no keys, so only the demo gateway is offered
        $order = $this->makeOrder();

        $this->get(route('payment.start', $order->order_number))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('OMR 10.000')
            ->assertSee('OMR 0.500')
            ->assertSee('OMR 0.525')
            ->assertSee('OMR 11.025')
            ->assertSee('value="demo"', false)
            ->assertDontSee('value="thawani"', false);
    }

    public function test_the_checkout_page_says_so_when_no_gateway_is_usable(): void
    {
        $this->seedSiteSettings(['enabled_gateways' => ['thawani']]);
        $order = $this->makeOrder();

        $this->get(route('payment.start', $order->order_number))->assertOk()->assertSee(__('payments.no_gateways'));
    }

    public function test_the_checkout_and_status_pages_speak_arabic(): void
    {
        $order = $this->makeOrder();

        $this->get(route('payment.start', $order->order_number).'?lang=ar')
            ->assertOk()
            ->assertSee(__('orders.total', [], 'ar'))
            ->assertSee('جواز حصان');
    }

    public function test_the_status_page_shows_progress_but_no_personal_data(): void
    {
        $order = $this->makeOrder();

        $this->get(route('orders.show', $order->order_number))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee(OrderStatus::PendingPayment->label())
            ->assertSee(__('orders.pay_now'))
            ->assertDontSee('Ali Al Balushi')
            ->assertDontSee('ali@example.com')
            ->assertDontSee('96891234567');

        $this->get(route('orders.show', 'SO-UNKNOWN0'))->assertNotFound();
    }

    public function test_the_status_page_of_a_paid_order_shows_the_receipt_and_the_timeline(): void
    {
        $order = $this->makeOrder();
        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'demo']);
        $this->post(route('payment.demo.decide', $order->order_number), ['decision' => 'approve']);
        $order->refresh();

        $this->get(route('orders.show', $order->order_number))
            ->assertOk()
            ->assertSee($order->receipt_number)
            ->assertSee(__('orders.payment_status.paid'))
            ->assertSee(__('orders.events.paid'))
            ->assertDontSee(__('orders.pay_now'))
            ->assertDontSee('payment_started');
    }
}
