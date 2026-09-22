<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\ServiceOrderRefunded;
use App\Mail\OrderRefundedMail;
use App\Models\ServiceOrder;
use App\Services\Orders\OrderRefundService;
use App\Services\Orders\OrderWorkflow;
use App\Services\Orders\RefundException;
use App\Services\Reports\IncomeStatement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\MakesReviewOrders;
use Tests\TestCase;

class OrderRefundTest extends TestCase
{
    use MakesReviewOrders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareReviewSite();
    }

    private function refunds(): OrderRefundService
    {
        return app(OrderRefundService::class);
    }

    /** A paid 10.000 OMR order with a 5% fee: total 11.025, of which 10.500 is the client's share. */
    private function paidOrder(): ServiceOrder
    {
        return $this->stagedOrder(stages: []);
    }

    // ── Recording ────────────────────────────────────────────────────────────

    public function test_a_full_refund_returns_the_price_and_its_vat_and_marks_the_order_refunded(): void
    {
        Event::fake([ServiceOrderRefunded::class]);
        $order = $this->paidOrder();

        $refund = $this->refunds()->record($order, 10500, 'Customer changed their mind', 7, 'gateway', 'THW-REF-1');

        $order->refresh();
        $this->assertSame('10.500', $refund->amount);
        $this->assertSame('gateway', $refund->method);
        $this->assertSame('THW-REF-1', $refund->reference);
        $this->assertSame(7, $refund->recorded_by);
        $this->assertSame('10.500', $order->refunded_amount);
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $this->assertSame(0.0, $order->refundableAmount());
        $this->assertSame(0.525, $order->feeShare(), 'the service fee stays ours');
        $this->assertTrue($order->events->firstWhere('type', 'refunded')->is_public);
        Event::assertDispatchedTimes(ServiceOrderRefunded::class, 1);
    }

    public function test_a_partial_refund_keeps_the_order_paid_and_the_rest_refundable(): void
    {
        $order = $this->paidOrder();

        $this->refunds()->record($order, 4000);

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('4.000', $order->refunded_amount);
        $this->assertSame(6.5, $order->refundableAmount());
        $this->assertSame(6500, $this->refunds()->refundable($order));

        $this->refunds()->record($order, 6500);
        $this->assertSame(PaymentStatus::Refunded, $order->refresh()->payment_status);
        $this->assertSame('10.500', $order->refunded_amount);
        $this->assertCount(2, $order->refunds);
    }

    public function test_the_service_fee_can_never_be_refunded(): void
    {
        $order = $this->paidOrder();

        foreach ([10501, 11025, 999999] as $baisa) {
            try {
                $this->refunds()->record($order, $baisa);
                $this->fail("{$baisa} baisa should be refused");
            } catch (RefundException $e) {
                $this->assertStringContainsString('OMR 10.500', $e->getMessage());
            }
        }

        $this->assertSame('0.000', $order->refresh()->refunded_amount);
        $this->assertCount(0, $order->refunds);
    }

    public function test_nothing_or_less_than_nothing_cannot_be_refunded(): void
    {
        $order = $this->paidOrder();

        foreach ([0, -500] as $baisa) {
            $this->expectException(RefundException::class);
            $this->refunds()->record($order, $baisa);
        }
    }

    public function test_only_a_paid_order_can_be_refunded(): void
    {
        $unpaid = $this->stagedOrder(stages: [], paid: false);
        $free = $this->stagedOrder(stages: [], price: 0);

        foreach ([$unpaid, $free] as $order) {
            try {
                $this->refunds()->record($order, 1000);
                $this->fail('a refund of an order that was never paid');
            } catch (RefundException $e) {
                $this->assertStringContainsString('paid', $e->getMessage());
            }
        }
    }

    public function test_two_refunds_that_together_exceed_the_share_cannot_both_be_recorded(): void
    {
        $order = $this->paidOrder();
        $stale = ServiceOrder::find($order->id);

        $this->refunds()->record($order, 6000);

        $this->expectException(RefundException::class);
        $this->refunds()->record($stale, 6000);
    }

    public function test_an_unknown_method_is_recorded_as_manual(): void
    {
        $this->assertSame('manual', $this->refunds()->record($this->paidOrder(), 1000, method: 'carrier-pigeon')->method);
    }

    // ── Refunds and the books ────────────────────────────────────────────────

    public function test_a_full_refund_leaves_the_fee_due_to_us_and_nothing_with_the_client(): void
    {
        $this->makeCommission('percentage', 10);
        $order = $this->paidOrder();
        $order->refresh();
        $this->assertSame('1.000', $order->commission_amount);

        $this->refunds()->record($order, 10500);

        $statement = new IncomeStatement(CarbonImmutable::now()->startOfDay(), CarbonImmutable::now()->endOfDay());
        $totals = $statement->totals();

        $this->assertSame(1, $totals['orders'], 'a refunded order stays in the statement');
        $this->assertSame(10500, $totals['refunded']);
        $this->assertSame(525, $totals['due_to_us'], 'the fee and its VAT are kept; the commission went with the refund');
        $this->assertSame(0, $totals['commission']);
        $this->assertSame(0, $totals['client_keeps']);
    }

    public function test_refunding_a_rejected_order_clears_the_refund_due_flag(): void
    {
        $order = $this->stagedOrder();
        app(OrderWorkflow::class)->reject($order, $this->reviewer(['reviewer']), 'No.');
        $order->refresh();

        $this->assertTrue($order->refundDue());

        $this->refunds()->record($order, 10500);

        $order->refresh();
        $this->assertFalse($order->refundDue());
        $this->assertSame(OrderStatus::Rejected, $order->status);
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
    }

    // ── Telling the customer ─────────────────────────────────────────────────

    public function test_the_customer_is_told_each_refund_and_that_the_fee_is_kept(): void
    {
        $order = $this->paidOrder();
        $order->form->update(['settings' => ['min_seconds' => 0, 'payment' => ['service_id' => $order->service_id], 'notifications' => ['email' => true, 'sms' => true]]]);

        $this->refunds()->record($order, 4000);
        $this->refunds()->record($order->refresh(), 2000);

        Mail::assertSent(OrderRefundedMail::class, 2);
        Mail::assertSent(OrderRefundedMail::class, function (OrderRefundedMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('ali@example.com')
                && str_contains($html, 'OMR 2.000')
                && str_contains($html, 'not refundable')
                && str_contains($html, 'OMR 0.525');
        });

        $sms = Http::recorded()->map(fn ($p) => $p[0]->body())->filter(fn ($b) => str_contains($b, '<Message>'))->values();
        $this->assertCount(2, $sms);
        $this->assertStringContainsString('OMR 4.000', $sms[0]);
        $this->assertStringContainsString('OMR 2.000', $sms[1]);
        $this->assertStringContainsString('not refundable', $sms[1]);
    }

    public function test_the_refund_email_speaks_arabic_to_an_arabic_customer(): void
    {
        $order = $this->paidOrder();
        $order->update(['locale' => 'ar']);

        $this->refunds()->record($order->refresh(), 5000);

        Mail::assertSent(OrderRefundedMail::class, fn (OrderRefundedMail $mail) => str_contains($mail->render(), 'dir="rtl"') && str_contains($mail->render(), 'غير قابلة للاسترداد'));
    }
}
