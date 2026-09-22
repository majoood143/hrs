<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\ServiceOrderReceived;
use App\Models\ServiceOrder;
use App\Services\Orders\CreateServiceOrder;
use App\Services\Orders\OrderPricing;
use App\Services\Orders\PriceBreakdown;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\PreparesOrderSite;
use Tests\TestCase;

class OrderPricingTest extends TestCase
{
    use PreparesOrderSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareOrderSite();
    }

    private function quote(float $price, ?int $formId = null): PriceBreakdown
    {
        return app(OrderPricing::class)->quote($this->makeService($price), $formId);
    }

    public function test_a_percentage_fee_and_vat_are_added_on_top_of_the_price(): void
    {
        $this->makeFee('percentage', 5);

        $quote = $this->quote(10);

        $this->assertSame(10000, $quote->price);
        $this->assertSame(500, $quote->fee);
        $this->assertSame(500, $quote->vatOnPrice);
        $this->assertSame(25, $quote->vatOnFee);
        $this->assertSame(11025, $quote->total());
        $this->assertSame(5.0, $quote->vatRate);
    }

    public function test_a_fixed_fee_is_the_same_whatever_the_price(): void
    {
        $this->makeFee('fixed', 0.75);

        $quote = $this->quote(10);

        $this->assertSame(750, $quote->fee);
        // 5% of 750 baisa is 37.5, rounded half up
        $this->assertSame(38, $quote->vatOnFee);
        $this->assertSame(10000 + 750 + 500 + 38, $quote->total());
    }

    public function test_without_a_fee_rule_only_vat_is_added(): void
    {
        $quote = $this->quote(10);

        $this->assertSame(0, $quote->fee);
        $this->assertSame(0, $quote->vatOnFee);
        $this->assertSame(10500, $quote->total());
        $this->assertNull($quote->feeSetting);
    }

    public function test_a_free_service_has_no_fee_and_no_vat(): void
    {
        $this->makeFee('percentage', 5);

        $quote = $this->quote(0);

        $this->assertTrue($quote->isFree());
        $this->assertSame(0, $quote->total());
        $this->assertSame(0.0, $quote->vatRate);
    }

    public function test_vat_can_be_switched_off(): void
    {
        $this->seedSiteSettings(['vat.enabled' => false]);
        $this->makeFee('percentage', 5);

        $quote = $this->quote(10);

        $this->assertSame(0, $quote->vatOnPrice + $quote->vatOnFee);
        $this->assertSame(10500, $quote->total());
    }

    public function test_the_vat_rate_comes_from_the_settings(): void
    {
        $this->seedSiteSettings(['vat.rate' => '10']);

        $this->assertSame(11000, $this->quote(10)->total());
    }

    public function test_amounts_are_worked_out_in_whole_baisa(): void
    {
        $this->makeFee('percentage', 5);

        // 5% of 33.333 OMR = 1666.65 baisa: rounds to 1667, never a fraction of a baisa
        $quote = $this->quote(33.333);

        $this->assertSame(33333, $quote->price);
        $this->assertSame(1667, $quote->fee);
        $this->assertSame(
            $quote->price + $quote->fee + $quote->vatOnPrice + $quote->vatOnFee,
            $quote->total(),
        );
        $this->assertSame('35.700', PriceBreakdown::toOmr(35700));
    }

    public function test_the_most_specific_fee_wins_form_then_service_then_global(): void
    {
        $service = $this->makeService(10);
        $global = $this->makeFee('percentage', 1);
        $forService = $this->makeFee('percentage', 2, ['service_id' => $service->id]);
        $forForm = $this->makeFee('percentage', 3, ['form_id' => 7]);

        $pricing = app(OrderPricing::class);

        $this->assertSame($forForm->id, $pricing->quote($service, 7)->feeSetting->id);
        $this->assertSame($forService->id, $pricing->quote($service, 8)->feeSetting->id, 'a form with no rule of its own falls back to the service');
        $this->assertSame($forService->id, $pricing->quote($service)->feeSetting->id);

        $other = $this->makeService(10, 'Other service');
        $this->assertSame($global->id, $pricing->quote($other)->feeSetting->id);
    }

    public function test_inactive_and_out_of_date_fees_are_ignored(): void
    {
        $this->makeFee('percentage', 9, extra: ['is_active' => false]);
        $this->makeFee('percentage', 8, extra: ['effective_to' => '2026-01-01']);
        $this->makeFee('percentage', 7, extra: ['effective_from' => '2999-01-01']);
        $live = $this->makeFee('percentage', 4, extra: ['effective_from' => '2026-01-01', 'effective_to' => '2999-01-01']);

        $this->assertSame($live->id, $this->quote(10)->feeSetting->id);
    }

    public function test_the_order_keeps_the_amounts_it_was_created_with(): void
    {
        $fee = $this->makeFee('percentage', 5);
        $service = $this->makeService(10);

        $order = app(CreateServiceOrder::class)->handle($service, ['name' => 'Ali', 'phone' => '96891234567']);

        $this->assertSame('10.000', $order->price);
        $this->assertSame('0.500', $order->fee_amount);
        $this->assertSame('0.500', $order->vat_on_price);
        $this->assertSame('0.025', $order->vat_on_fee);
        $this->assertSame('11.025', $order->total);
        $this->assertSame(11025, $order->totalBaisa());
        $this->assertSame($fee->id, $order->service_fee_setting_id);
        $this->assertSame('percentage', $order->service_fee_type);
        $this->assertSame('5.000', $order->service_fee_value);

        // later edits to the service and the rule never reach an existing order
        $service->update(['price' => 99]);
        $fee->update(['fee_value' => 50]);

        $order->refresh();
        $this->assertSame('10.000', $order->price);
        $this->assertSame('0.500', $order->fee_amount);
        $this->assertSame('5.000', $order->service_fee_value);
    }

    public function test_the_shares_split_the_total_between_the_client_and_us(): void
    {
        $order = $this->makeOrder();

        $this->assertSame(10.5, $order->clientShare());
        $this->assertSame(0.525, $order->feeShare());
        $this->assertSame(11.025, round($order->clientShare() + $order->feeShare(), 3));
        $this->assertSame(0.525, $order->vatAmount());
    }

    public function test_a_refund_returns_the_price_and_its_vat_but_never_the_fee(): void
    {
        $order = $this->makeOrder();

        $this->assertSame(0.0, $order->refundableAmount(), 'nothing to refund before it is paid');

        $order->update(['payment_status' => PaymentStatus::Paid]);
        $this->assertSame(10.5, $order->refundableAmount());

        $order->update(['refunded_amount' => 4]);
        $this->assertSame(6.5, $order->refresh()->refundableAmount());
    }

    public function test_a_paid_service_waits_for_payment(): void
    {
        Event::fake([ServiceOrderReceived::class]);

        $order = $this->makeOrder();

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertTrue($order->isPayable());
        Event::assertNotDispatched(ServiceOrderReceived::class);
        $this->assertSame(['created'], $order->events->pluck('type')->all());
    }

    public function test_a_free_service_is_received_straight_away(): void
    {
        Event::fake([ServiceOrderReceived::class]);

        $order = app(CreateServiceOrder::class)->handle($this->makeService(0), ['name' => 'Ali', 'phone' => '96891234567']);

        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame(PaymentStatus::Free, $order->payment_status);
        $this->assertFalse($order->isPayable());
        $this->assertSame('0.000', $order->total);
        Event::assertDispatched(ServiceOrderReceived::class, fn ($event) => $event->order->is($order));
    }

    public function test_order_numbers_are_short_random_and_unique(): void
    {
        $numbers = collect(range(1, 20))->map(fn () => ServiceOrder::newOrderNumber());

        $this->assertCount(20, $numbers->unique());
        $numbers->each(fn ($number) => $this->assertMatchesRegularExpression('/^SO-[A-HJ-NP-Z2-9]{8}$/', $number));
    }
}
