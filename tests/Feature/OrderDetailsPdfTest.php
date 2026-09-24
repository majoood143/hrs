<?php

namespace Tests\Feature;

use App\Models\OrderEvent;
use App\Services\Orders\OrderDetailsPdf;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

class OrderDetailsPdfTest extends TestCase
{
    use PreparesCustomerSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
    }

    private function details(): OrderDetailsPdf
    {
        return app(OrderDetailsPdf::class);
    }

    // ── The timeline speaks the viewer's language ───────────────────────────

    public function test_an_event_saved_in_english_is_shown_in_arabic(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer());
        $event = $order->recordEvent('paid', 'Payment received');

        app()->setLocale('ar');

        $this->assertSame(__('orders.events.paid', [], 'ar'), $event->label());
        $this->assertSame('heroicon-o-banknotes', $event->icon());
    }

    public function test_an_event_without_a_text_of_its_own_keeps_its_message(): void
    {
        $event = new OrderEvent(['type' => 'stage_approved', 'message' => 'Vet check']);

        $this->assertSame('Vet check', $event->label());
    }

    public function test_the_account_page_shows_the_timeline_in_arabic(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->paidOrderFor($customer);
        $order->recordEvent('completed', 'Your order was completed');
        $order->recordEvent('document_added', 'A document was added to your order');

        $this->actingAs($customer, 'customer')
            ->get(route('account.orders.show', $order->order_number).'?lang=ar')
            ->assertOk()
            ->assertSee(__('orders.events.completed', [], 'ar'))
            ->assertSee(__('orders.events.document_added', [], 'ar'))
            ->assertDontSee('Your order was completed')
            ->assertDontSee('A document was added to your order')
            ->assertSee(route('account.orders.details', $order->order_number), false);
    }

    // ── The PDF ──────────────────────────────────────────────────────────────

    public function test_it_lists_the_order_its_amounts_and_its_progress(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer());
        $order->recordEvent('paid', 'Payment received');

        $html = $this->details()->html($order);

        $this->assertStringContainsString(__('order_pdf.title'), $html);
        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString($order->receipt_number, $html);
        $this->assertStringContainsString('Horse passport', $html);
        $this->assertStringContainsString('OMR 11.025', $html);
        $this->assertStringContainsString($order->status->label(), $html);
        $this->assertStringContainsString(__('orders.events.paid'), $html);
    }

    public function test_it_is_in_the_language_asked_for(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer());
        $order->recordEvent('paid', 'Payment received');

        $html = $this->details()->html($order, 'ar');

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString(__('order_pdf.title', [], 'ar'), $html);
        $this->assertStringContainsString(__('orders.events.paid', [], 'ar'), $html);
        $this->assertStringNotContainsString('Payment received', $html);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_an_unpaid_order_has_details_too(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder(10, 5.0, ['phone' => $customer->phone]);
        $order->update(['customer_id' => $customer->id]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('account.orders.details', $order->order_number).'?lang=ar')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Robots-Tag', 'noindex');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment; filename="order-'.$order->order_number.'.pdf"', $response->headers->get('Content-Disposition'));
    }

    public function test_nobody_else_can_download_it(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer('96891234567'));

        $this->get(route('account.orders.details', $order->order_number))->assertRedirect(route('account.login'));

        $this->actingAs($this->makeCustomer('96899999999'), 'customer')
            ->get(route('account.orders.details', $order->order_number))
            ->assertNotFound();
    }
}
