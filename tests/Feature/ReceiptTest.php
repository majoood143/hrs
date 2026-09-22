<?php

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\ServiceOrder;
use App\Services\Orders\CreateServiceOrder;
use App\Services\Orders\OrderReceiptPdf;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use PreparesCustomerSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
    }

    private function receipts(): OrderReceiptPdf
    {
        return app(OrderReceiptPdf::class);
    }

    // ── What it says ─────────────────────────────────────────────────────────

    public function test_the_receipt_lists_price_fee_vat_and_total(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer());

        $html = $this->receipts()->html($order);

        $this->assertStringContainsString(__('receipt.title'), $html);
        $this->assertStringContainsString($order->receipt_number, $html);
        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString('Horse passport', $html);
        $this->assertStringContainsString('OMR 10.000', $html);                            // service price
        $this->assertStringContainsString('OMR 0.500', $html);                             // fee (and VAT on the price)
        $this->assertStringContainsString('OMR 10.500', $html);                            // subtotal
        $this->assertStringContainsString(__('receipt.vat_on_service', ['rate' => 5]), $html);
        $this->assertStringContainsString(__('receipt.vat_on_fee', ['rate' => 5]), $html);
        $this->assertStringContainsString('OMR 0.025', $html);                             // VAT on the fee
        $this->assertStringContainsString('OMR 11.025', $html);                            // total
        $this->assertStringContainsString('Ali Al Balushi', $html);
        $this->assertStringContainsString('+96891234567', $html);
        $this->assertStringContainsString(PaymentGateway::Demo->label(), $html);
        $this->assertStringContainsString('DEMO-REF', $html);
    }

    public function test_the_fee_note_says_the_fee_is_not_refundable(): void
    {
        $html = $this->receipts()->html($this->paidOrderFor($this->makeCustomer()));

        $this->assertStringContainsString(__('receipt.fee_note'), $html);
    }

    public function test_an_order_without_a_fee_or_vat_has_only_the_lines_that_apply(): void
    {
        $this->seedSiteSettings(['vat.enabled' => false]);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder(10, null, ['phone' => $customer->phone]);
        $order->update(['payment_status' => PaymentStatus::Paid, 'receipt_number' => 'RC-2026-000001', 'paid_at' => now()]);

        $html = $this->receipts()->html($order->refresh());

        $this->assertStringContainsString('OMR 10.000', $html);
        $this->assertStringNotContainsString(__('orders.service_fee'), $html);
        $this->assertStringNotContainsString(__('receipt.fee_note'), $html);
        $this->assertStringNotContainsString('VAT on the', $html);
    }

    public function test_the_vat_registration_number_is_shown_only_when_set(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer());

        $this->assertStringNotContainsString(__('receipt.vat_number'), $this->receipts()->html($order));

        $this->seedSiteSettings(['vat.registration_number' => 'OM1100123456']);
        $html = $this->receipts()->html($order);

        $this->assertStringContainsString(__('receipt.vat_number'), $html);
        $this->assertStringContainsString('OM1100123456', $html);
    }

    public function test_the_amounts_add_up_on_the_receipt(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer(), 33.333);

        $sum = (float) $order->price + (float) $order->fee_amount + (float) $order->vat_on_price + (float) $order->vat_on_fee;

        $this->assertSame(round($sum, 3), round((float) $order->total, 3));
        $this->assertStringContainsString('OMR '.number_format((float) $order->total, 3), $this->receipts()->html($order));
    }

    // ── Language ─────────────────────────────────────────────────────────────

    public function test_the_receipt_is_in_the_language_the_customer_ordered_in(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer());
        $order->update(['locale' => 'ar']);

        $html = $this->receipts()->html($order->refresh());

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString(__('receipt.title', [], 'ar'), $html);
        $this->assertStringContainsString('جواز حصان', $html);
        $this->assertStringContainsString(__('receipt.fee_note', [], 'ar'), $html);
        $this->assertSame('en', app()->getLocale(), 'rendering it does not change the visitor\'s language');
    }

    public function test_a_language_can_be_asked_for_and_unknown_ones_are_ignored(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer());

        $this->assertSame('ar', $this->receipts()->locale($order, 'ar'));
        $this->assertSame('en', $this->receipts()->locale($order, 'xx'));
        $this->assertSame('en', $this->receipts()->locale($order, null));
        $this->assertStringContainsString('dir="rtl"', $this->receipts()->html($order, 'ar'));
        $this->assertStringContainsString('dir="ltr"', $this->receipts()->html($order, 'en'));
    }

    public function test_the_qr_code_opens_the_order_in_the_receipts_language(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer());

        $html = $this->receipts()->html($order, 'ar');

        $this->assertStringContainsString('<barcode code="'.route('orders.show', $order->order_number).'?lang=ar"', $html);
    }

    // ── The PDF ──────────────────────────────────────────────────────────────

    public function test_it_renders_a_real_pdf_in_both_languages_with_cairo_only(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer());

        $outputs = [];

        foreach (['en', 'ar'] as $locale) {
            $pdf = $this->receipts()->render($order, $locale);

            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertGreaterThan(5000, strlen($pdf));

            preg_match_all('#/BaseFont\s*/[A-Z]+\+([A-Za-z-]+)#', $pdf, $m);
            $fonts = array_values(array_unique($m[1]));
            sort($fonts);
            $this->assertSame(['Cairo-Bold', 'Cairo-Regular'], $fonts, $locale);

            $outputs[$locale] = $pdf;
        }

        $this->assertNotSame($outputs['en'], $outputs['ar']);
    }

    public function test_it_is_one_page_a4_portrait(): void
    {
        $pdf = $this->receipts()->render($this->paidOrderFor($this->makeCustomer()));

        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf));
        $this->assertMatchesRegularExpression('#/MediaBox\s*\[\s*0\s+0\s+595\.\d+\s+841\.\d+\s*\]#', $pdf);
    }

    public function test_only_a_paid_order_with_a_receipt_number_has_a_receipt(): void
    {
        $customer = $this->makeCustomer();
        $unpaid = $this->makeOrder(10, 5.0, ['phone' => $customer->phone]);
        $paid = $this->paidOrderFor($customer);
        $free = app(CreateServiceOrder::class)->handle($this->makeService(0), ['phone' => $customer->phone]);
        $paidNoNumber = ServiceOrder::find($paid->id)->replicate();
        $paidNoNumber->fill(['order_number' => 'SO-NONUMBER', 'receipt_number' => null])->save();

        $this->assertFalse($this->receipts()->available($unpaid));
        $this->assertFalse($this->receipts()->available($free));
        $this->assertFalse($this->receipts()->available($paidNoNumber));
        $this->assertTrue($this->receipts()->available($paid));
        $this->assertSame('receipt-'.$paid->receipt_number.'.pdf', $this->receipts()->filename($paid));
    }

    // ── Downloading it ───────────────────────────────────────────────────────

    public function test_the_customer_downloads_their_receipt(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->paidOrderFor($customer);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('account.orders.receipt', $order->order_number))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Robots-Tag', 'noindex');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment; filename="receipt-'.$order->receipt_number.'.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_the_language_can_be_chosen_on_the_download(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->paidOrderFor($customer);

        $en = $this->actingAs($customer, 'customer')->get(route('account.orders.receipt', $order->order_number))->assertOk()->getContent();
        $ar = $this->get(route('account.orders.receipt', $order->order_number).'?lang=ar')->assertOk()->getContent();

        $this->assertStringStartsWith('%PDF-', $en);
        $this->assertStringStartsWith('%PDF-', $ar);
        $this->assertNotSame($en, $ar);
    }

    public function test_an_unpaid_order_has_nothing_to_download(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder(10, 5.0, ['phone' => $customer->phone]);
        $order->update(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer')->get(route('account.orders.receipt', $order->order_number))->assertNotFound();
    }

    public function test_nobody_else_can_download_it(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer('96891234567'));

        $this->get(route('account.orders.receipt', $order->order_number))->assertRedirect(route('account.login'));

        $this->actingAs($this->makeCustomer('96899999999'), 'customer')
            ->get(route('account.orders.receipt', $order->order_number))
            ->assertNotFound();
    }
}
