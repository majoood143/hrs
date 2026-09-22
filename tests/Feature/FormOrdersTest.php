<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\ServiceOrderReceived;
use App\Filament\Support\FormPaymentTab;
use App\Models\ServiceOrder;
use App\Support\FormOrderSettings;
use App\Support\PhoneNumber;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

class FormOrdersTest extends TestCase
{
    use MakesOrderForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareFormSite();
    }

    private function submitUrl(string $slug = 'passport'): string
    {
        return route('packstub-form-builder.submit', $slug);
    }

    // ── A submission becomes an order ────────────────────────────────────────

    public function test_submitting_a_paid_form_creates_an_order_and_sends_the_visitor_to_pay(): void
    {
        $service = $this->makeService(10);
        $this->makeFee('percentage', 5);
        $form = $this->makeForm($service);

        $response = $this->postJson($this->submitUrl(), $this->customerInput())->assertOk();

        $order = ServiceOrder::firstOrFail();
        $response->assertJson([
            'ok' => true,
            'order_number' => $order->order_number,
            'redirect' => route('payment.start', $order->order_number),
        ]);

        $this->assertSame($service->id, $order->service_id);
        $this->assertSame($form->id, $order->form_id);
        $this->assertSame('11.025', $order->total);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame('Ali Al Balushi', $order->customer_name);
        $this->assertSame('ali@example.com', $order->customer_email);
        $this->assertSame('96891234567', $order->customer_phone, 'the phone is stored in one spelling');

        $submission = FormSubmission::firstOrFail();
        $this->assertSame($submission->id, $order->submission_id);
        $this->assertSame($order->order_number, $submission->meta['order_number']);
        $this->assertSame('Ali Al Balushi', $submission->value('full_name'));
    }

    public function test_an_ordinary_html_post_is_redirected_to_the_payment_page(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeForm($this->makeService(10));

        $this->post($this->submitUrl(), $this->customerInput())
            ->assertRedirect(route('payment.start', ServiceOrder::firstOrFail()->order_number));
    }

    public function test_a_free_service_is_received_straight_away_and_the_visitor_lands_on_the_order(): void
    {
        Event::fake([ServiceOrderReceived::class]);
        $this->makeForm($this->makeService(0));

        $this->postJson($this->submitUrl(), $this->customerInput())->assertOk();

        $order = ServiceOrder::firstOrFail();
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame(PaymentStatus::Free, $order->payment_status);
        $this->assertSame('0.000', $order->total);
        Event::assertDispatchedTimes(ServiceOrderReceived::class, 1);

        $this->post($this->submitUrl(), $this->customerInput(['mobile' => '99887766']))
            ->assertRedirect(route('orders.show', ServiceOrder::latest('id')->first()->order_number));
    }

    public function test_a_form_without_a_service_behaves_exactly_as_before(): void
    {
        $form = $this->makeForm(null);
        $form->update(['success_message' => ['en' => 'Thanks, we will call you.']]);

        $this->postJson($this->submitUrl(), $this->customerInput())
            ->assertOk()
            ->assertJson(['ok' => true, 'message' => 'Thanks, we will call you.', 'redirect' => null])
            ->assertJsonMissing(['order_number']);

        $this->assertSame(0, ServiceOrder::count());
        $this->assertSame(1, FormSubmission::count());
    }

    public function test_a_form_with_its_own_redirect_and_no_service_still_uses_it(): void
    {
        $form = $this->makeForm(null);
        $form->update(['redirect_url' => 'https://example.com/thanks']);

        $this->post($this->submitUrl(), $this->customerInput())->assertRedirect('https://example.com/thanks');
    }

    public function test_an_invalid_submission_creates_neither_a_submission_nor_an_order(): void
    {
        $this->makeForm($this->makeService(10));

        $this->postJson($this->submitUrl(), $this->customerInput(['mobile' => '', 'email' => 'nope']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mobile', 'email'], 'errors');

        $this->assertSame(0, ServiceOrder::count());
        $this->assertSame(0, FormSubmission::count());
    }

    public function test_spam_never_becomes_an_order(): void
    {
        $this->makeForm($this->makeService(10));

        $this->postJson($this->submitUrl(), $this->customerInput(['_fb_website' => 'http://spam.example']))->assertOk();

        $this->assertSame(0, ServiceOrder::count());
        $this->assertSame(0, FormSubmission::count());
    }

    public function test_the_submission_is_kept_even_if_the_form_says_not_to_store_them(): void
    {
        $form = $this->makeForm($this->makeService(10));
        $form->update(['store_submissions' => false]);

        $this->postJson($this->submitUrl(), $this->customerInput())->assertOk();

        $order = ServiceOrder::firstOrFail();
        $this->assertNotNull($order->submission_id, 'the reviewers need the answers');
        $this->assertSame('Ali Al Balushi', $order->submission->value('full_name'));
    }

    public function test_the_same_submission_never_makes_two_orders(): void
    {
        $form = $this->makeForm($this->makeService(10));
        $result = FormBuilder::submit($form, $this->customerInput());

        event(new SubmissionReceived($form, $result->submission, new SubmissionContext));
        event(new SubmissionReceived($form, $result->submission, new SubmissionContext));

        $this->assertSame(1, ServiceOrder::count());
    }

    public function test_submitting_from_code_gets_the_payment_link_too(): void
    {
        $form = $this->makeForm($this->makeService(10));

        $result = FormBuilder::submit($form, $this->customerInput());

        $this->assertSame(route('payment.start', ServiceOrder::firstOrFail()->order_number), $result->redirectUrl());
        $this->assertSame(ServiceOrder::firstOrFail()->order_number, $result->toArray()['order_number']);
    }

    public function test_the_order_remembers_the_language_the_customer_used(): void
    {
        $this->makeForm($this->makeService(10));

        $this->withSession(['locale' => 'ar'])->postJson($this->submitUrl(), $this->customerInput())->assertOk();

        $this->assertSame('ar', ServiceOrder::firstOrFail()->locale);
    }

    // ── Which field is the customer's phone? ─────────────────────────────────

    public function test_the_customer_fields_are_detected_by_type_when_not_picked(): void
    {
        $form = $this->makeForm($this->makeService(10));
        $settings = FormOrderSettings::for($form);

        $this->assertSame('full_name', $settings->fieldKey('name'));
        $this->assertSame('mobile', $settings->fieldKey('phone'));
        $this->assertSame('email', $settings->fieldKey('email'));
    }

    public function test_a_picked_field_wins_over_detection(): void
    {
        $form = $this->makeForm($this->makeService(10), ['customer' => ['phone_field' => 'landline']]);
        $form->update(['fields' => [
            ...$form->fields,
            $this->fieldItem('phone', 'landline', 'Landline'),
        ]]);

        $this->postJson($this->submitUrl(), $this->customerInput(['landline' => '24001122']))->assertOk();

        $this->assertSame('96824001122', ServiceOrder::firstOrFail()->customer_phone);
    }

    public function test_a_picked_field_that_no_longer_exists_falls_back_to_detection(): void
    {
        $form = $this->makeForm($this->makeService(10), ['customer' => ['phone_field' => 'deleted_field']]);

        $this->assertSame('mobile', FormOrderSettings::for($form)->fieldKey('phone'));
    }

    public function test_phone_numbers_are_stored_in_one_spelling(): void
    {
        foreach ([
            '9123 4567' => '96891234567',
            '+968 9123 4567' => '96891234567',
            '00968-91234567' => '96891234567',
            '96891234567' => '96891234567',
            '+44 7700 900123' => '447700900123',
            '  ' => null,
            null => null,
            'abc' => null,
        ] as $raw => $expected) {
            $this->assertSame($expected, PhoneNumber::normalize($raw === '' ? null : (string) $raw), (string) $raw);
        }
    }

    // ── A service that is switched off closes its form ───────────────────────

    public function test_an_inactive_service_closes_its_form(): void
    {
        $service = $this->makeService(10);
        $form = $this->makeForm($service);
        $service->update(['is_active' => false]);

        $this->assertSame(__('orders.service_unavailable'), $form->refresh()->closedReason());

        $this->postJson($this->submitUrl(), $this->customerInput())->assertStatus(403);
        $this->assertSame(0, ServiceOrder::count());
        $this->assertSame(0, FormSubmission::count());
    }

    public function test_a_deleted_service_closes_its_form(): void
    {
        $service = $this->makeService(10);
        $form = $this->makeForm($service);
        $service->delete();

        $this->assertSame(__('orders.service_unavailable'), $form->refresh()->closedReason());
    }

    public function test_a_form_without_a_service_is_not_affected_by_the_check(): void
    {
        $this->assertNull($this->makeForm(null)->closedReason());
    }

    // ── What the customer sees before submitting ─────────────────────────────

    private function renderForm(string $slug = 'passport'): string
    {
        return Blade::render('<x-form-builder::form :form="$slug" />', ['slug' => $slug]);
    }

    public function test_the_form_shows_what_the_customer_will_pay(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeForm($this->makeService(10));

        $html = $this->renderForm();

        $this->assertStringContainsString(__('orders.you_will_pay'), $html);
        $this->assertStringContainsString('OMR 10.000', $html);
        $this->assertStringContainsString('OMR 0.500', $html);
        $this->assertStringContainsString('OMR 0.525', $html);
        $this->assertStringContainsString('OMR 11.025', $html);
        $this->assertStringContainsString(__('orders.pay_after_submit'), $html);
        $this->assertStringContainsString('name="full_name"', $html, 'the form itself is still there');
    }

    public function test_the_notice_speaks_arabic(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeForm($this->makeService(10));
        app()->setLocale('ar');

        $html = $this->renderForm();

        $this->assertStringContainsString(__('orders.you_will_pay', [], 'ar'), $html);
        $this->assertStringContainsString('جواز حصان', $html);
    }

    public function test_a_free_service_says_so(): void
    {
        $this->makeForm($this->makeService(0));

        $html = $this->renderForm();

        $this->assertStringContainsString(__('orders.free_service'), $html);
        $this->assertStringNotContainsString(__('orders.pay_after_submit'), $html);
    }

    public function test_an_ordinary_form_shows_no_price(): void
    {
        $this->makeForm(null);

        $html = $this->renderForm();

        $this->assertStringNotContainsString(__('orders.you_will_pay'), $html);
        $this->assertStringContainsString('name="full_name"', $html);
    }

    public function test_a_closed_service_form_shows_the_closed_message_not_a_price(): void
    {
        $service = $this->makeService(10);
        $this->makeForm($service);
        $service->update(['is_active' => false]);

        $html = $this->renderForm();

        $this->assertStringContainsString(__('orders.service_unavailable'), $html);
        $this->assertStringNotContainsString('OMR 10.000', $html);
    }

    // ── The whole journey ────────────────────────────────────────────────────

    public function test_fill_the_form_pay_with_the_demo_gateway_and_see_the_receipt(): void
    {
        Event::fake([ServiceOrderReceived::class]);
        $this->makeFee('percentage', 5);
        $this->makeForm($this->makeService(10));

        // 1. submit the form
        $payPage = $this->post($this->submitUrl(), $this->customerInput())->assertRedirect()->headers->get('Location');
        $order = ServiceOrder::firstOrFail();
        $this->assertSame(route('payment.start', $order->order_number), $payPage);

        // 2. the payment page shows the amounts
        $this->get($payPage)->assertOk()->assertSee('OMR 11.025')->assertSee('value="demo"', false);

        // 3. pay
        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'demo']);
        $this->post(route('payment.demo.decide', $order->order_number), ['decision' => 'approve'])
            ->assertRedirect(route('orders.show', $order->order_number));

        // 4. the order is received, with its receipt
        $order->refresh();
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->receipt_number);
        Event::assertDispatchedTimes(ServiceOrderReceived::class, 1);
        $this->get(route('orders.show', $order->order_number))->assertOk()->assertSee($order->receipt_number);
    }

    // ── The admin tab ────────────────────────────────────────────────────────

    public function test_the_tab_lists_only_the_forms_fields_of_the_right_kind(): void
    {
        $form = $this->makeForm(null);

        $phones = FormOrderSettings::fieldOptions($form->fields, ['phone']);
        $texts = FormOrderSettings::fieldOptions($form->fields, ['text']);

        $this->assertSame(['mobile' => 'Mobile (mobile)'], $phones);
        $this->assertSame(['full_name' => 'Full name (full_name)'], $texts);
        $this->assertSame([], FormOrderSettings::fieldOptions(null, ['phone']));
        $this->assertSame([], FormOrderSettings::fieldOptions([['type' => 'unknown', 'data' => ['key' => 'x']]], ['phone']));
    }

    public function test_the_tab_is_registered_on_the_form_editor(): void
    {
        $this->assertNotEmpty(collect(FormBuilder::formTabs())->filter(fn ($tab) => $tab->getLabel() === __('admin_form_payment.tab')));
        $this->assertInstanceOf(Tab::class, FormPaymentTab::make());
    }
}
