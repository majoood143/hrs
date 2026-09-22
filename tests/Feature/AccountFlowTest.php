<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\ServiceOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Facades\FormBuilder;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

class AccountFlowTest extends TestCase
{
    use PreparesCustomerSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
        $this->useTamimah();
        $this->tamimahAnswers();
    }

    private function signIn(string $phone = '9123 4567'): void
    {
        $this->post(route('account.send'), ['phone' => $phone])->assertRedirect(route('account.verify'));
        $this->post(route('account.verify.check'), ['code' => $this->sentCode()])->assertRedirect();
    }

    // ── Signing in ───────────────────────────────────────────────────────────

    public function test_the_sign_in_page_asks_for_a_phone(): void
    {
        $this->get(route('account.login'))
            ->assertOk()
            ->assertSee(__('account.login_heading'))
            ->assertSee('name="phone"', false)
            ->assertSee(__('account.send_code'));
    }

    public function test_without_a_working_sms_channel_the_page_says_signing_in_is_unavailable(): void
    {
        $this->useSms('');

        $this->get(route('account.login'))
            ->assertOk()
            ->assertSee(__('account.sms_unavailable'))
            ->assertDontSee('name="phone"', false);
    }

    public function test_asking_for_a_code_leads_to_the_code_page_and_sends_the_sms(): void
    {
        $this->post(route('account.send'), ['phone' => '9123 4567'])
            ->assertRedirect(route('account.verify'))
            ->assertSessionHas('status', __('account.code_sent'));

        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->sentCode());

        $this->get(route('account.verify'))
            ->assertOk()
            ->assertSee('+968 ••••4567')
            ->assertSee('name="code"', false)
            ->assertDontSee($this->sentCode(), false);
    }

    public function test_the_answer_is_the_same_whether_or_not_the_number_ever_ordered(): void
    {
        $known = $this->makeCustomer('96891234567');
        $this->paidOrderFor($known);

        $a = $this->post(route('account.send'), ['phone' => '9123 4567']);
        $b = $this->post(route('account.send'), ['phone' => '9888 8888']);

        $a->assertRedirect(route('account.verify'))->assertSessionHas('status', __('account.code_sent'));
        $b->assertRedirect(route('account.verify'))->assertSessionHas('status', __('account.code_sent'));
    }

    public function test_a_bad_number_is_sent_back_with_a_message(): void
    {
        $this->from(route('account.login'))
            ->post(route('account.send'), ['phone' => 'abc'])
            ->assertRedirect(route('account.login'))
            ->assertSessionHasErrors(['phone' => __('account.phone_invalid')]);

        $this->post(route('account.send'), [])->assertSessionHasErrors('phone');
    }

    public function test_the_code_page_needs_a_number_first(): void
    {
        $this->get(route('account.verify'))->assertRedirect(route('account.login'));
        $this->post(route('account.verify.check'), ['code' => '123456'])->assertRedirect(route('account.login'));
        $this->post(route('account.resend'))->assertRedirect(route('account.login'));
    }

    public function test_the_right_code_signs_the_customer_in_and_lands_on_their_orders(): void
    {
        $this->post(route('account.send'), ['phone' => '9123 4567']);

        $this->post(route('account.verify.check'), ['code' => $this->sentCode()])
            ->assertRedirect(route('account.orders'));

        $this->assertAuthenticated('customer');
        $this->assertSame('96891234567', Auth::guard('customer')->user()->phone);
        $this->assertGuest('web');
        $this->get(route('account.orders'))->assertOk();
    }

    public function test_signing_in_starts_a_fresh_session(): void
    {
        // a browser that already holds a session id (an attacker may have planted it)
        $planted = Str::random(40);
        $this->withCookie(config('session.cookie'), $planted);

        $this->post(route('account.send'), ['phone' => '9123 4567']);
        $this->assertSame($planted, session()->getId(), 'the cookie is honoured until sign-in');

        $this->post(route('account.verify.check'), ['code' => $this->sentCode()])->assertRedirect();

        $this->assertAuthenticated('customer');
        $this->assertNotSame($planted, session()->getId(), 'a session id known before signing in must not stay valid after it');
    }

    public function test_a_wrong_code_shows_an_error_and_signs_nobody_in(): void
    {
        $this->post(route('account.send'), ['phone' => '9123 4567']);
        $wrong = $this->sentCode() === '000000' ? '111111' : '000000';

        $this->from(route('account.verify'))
            ->post(route('account.verify.check'), ['code' => $wrong])
            ->assertRedirect(route('account.verify'))
            ->assertSessionHasErrors(['code' => __('account.code_wrong')]);

        $this->assertGuest('customer');
    }

    public function test_a_locked_code_says_to_ask_for_a_new_one(): void
    {
        $this->post(route('account.send'), ['phone' => '9123 4567']);
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $ignored) {
            $this->post(route('account.verify.check'), ['code' => $wrong]);
        }

        $this->post(route('account.verify.check'), ['code' => $code])
            ->assertSessionHasErrors(['code' => __('account.code_expired')]);
        $this->assertGuest('customer');
    }

    public function test_asking_again_too_soon_keeps_the_customer_on_the_code_page_with_the_wait(): void
    {
        $this->post(route('account.send'), ['phone' => '9123 4567']);

        $this->post(route('account.resend'))
            ->assertRedirect(route('account.verify'))
            ->assertSessionHas('status');

        $this->assertStringContainsString(
            substr(__('account.wait_before_resend', ['seconds' => 1]), 0, 12),
            session('status'),
        );
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->signIn();

        $this->post(route('account.logout'))->assertRedirect(route('account.login'));

        $this->assertGuest('customer');
        $this->get(route('account.orders'))->assertRedirect(route('account.login'));
    }

    public function test_the_page_asked_for_before_signing_in_is_where_they_land_after(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->paidOrderFor($customer);
        Customer::query()->update(['phone' => '96891234567']);

        $this->get(route('account.orders.show', $order->order_number))->assertRedirect(route('account.login'));

        $this->post(route('account.send'), ['phone' => '96891234567']);
        $this->post(route('account.verify.check'), ['code' => $this->sentCode()])
            ->assertRedirect(route('account.orders.show', $order->order_number));
    }

    public function test_the_demo_channel_shows_the_code_on_the_page_and_the_real_one_never_does(): void
    {
        $this->useSms('demo');

        $this->post(route('account.send'), ['phone' => '9123 4567']);
        $html = $this->get(route('account.verify'))->assertOk()->assertSee(__('account.demo_badge'))->getContent();
        preg_match('#font-mono text-lg font-bold" dir="ltr">(\d{6})<#', $html, $match);
        $this->assertNotEmpty($match, 'the code is on the page');

        $this->post(route('account.verify.check'), ['code' => $match[1]])->assertRedirect(route('account.orders'));
        $this->assertAuthenticated('customer');

        // the real channel: the code goes to the phone, never onto the page
        $this->post(route('account.logout'));
        $this->useTamimah();
        $this->tamimahAnswers();
        $this->post(route('account.send'), ['phone' => '9555 5555']);
        $this->get(route('account.verify'))->assertDontSee(__('account.demo_badge'))->assertDontSee($this->sentCode(), false);
    }

    // ── Their orders ─────────────────────────────────────────────────────────

    public function test_a_customer_sees_only_their_own_orders(): void
    {
        $me = $this->makeCustomer('96891234567');
        $other = $this->makeCustomer('96899999999', ['name' => 'Someone Else']);
        $mine = $this->paidOrderFor($me);
        $theirs = $this->paidOrderFor($other);

        $this->actingAs($me, 'customer');

        $this->get(route('account.orders'))
            ->assertOk()
            ->assertSee($mine->order_number)
            ->assertDontSee($theirs->order_number)
            ->assertSee('OMR 11.025')
            ->assertSee(OrderStatus::New->label());
    }

    public function test_an_empty_account_says_so(): void
    {
        $this->actingAs($this->makeCustomer(), 'customer');

        $this->get(route('account.orders'))->assertOk()->assertSee(__('account.no_orders'));
    }

    public function test_an_order_page_shows_the_breakdown_the_answers_the_receipt_and_the_timeline(): void
    {
        $me = $this->makeCustomer('96891234567');
        $form = $this->makeForm($service = $this->makeService(10));
        $this->makeFee('percentage', 5);
        $result = FormBuilder::submit($form, $this->customerInput(['full_name' => 'Ali Al Balushi']));
        $order = ServiceOrder::firstOrFail();
        $order->update(['customer_id' => $me->id, 'payment_status' => PaymentStatus::Paid, 'status' => OrderStatus::New, 'receipt_number' => 'RC-2026-000009', 'paid_at' => now()]);

        $this->actingAs($me, 'customer');

        $this->get(route('account.orders.show', $order->order_number))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('OMR 11.025')
            ->assertSee(__('account.your_request'))
            ->assertSee('Ali Al Balushi')
            ->assertSee('ali@example.com')
            ->assertSee('RC-2026-000009')
            ->assertSee(route('account.orders.receipt', $order->order_number), false)
            ->assertSee(__('orders.events.created'))
            ->assertDontSee(__('orders.pay_now'));
    }

    public function test_uploaded_files_are_named_as_uploaded_not_linked(): void
    {
        $me = $this->makeCustomer('96891234567');
        $form = $this->makeForm($this->makeService(10), withFile: true);
        Storage::fake('local');
        $this->post(route('packstub-form-builder.submit', $form), $this->customerInput() + [
            'passport_copy' => UploadedFile::fake()->createWithContent('passport.pdf', '%PDF-1.4 x'),
        ]);
        $order = ServiceOrder::firstOrFail();
        $order->update(['customer_id' => $me->id]);

        $this->actingAs($me, 'customer')
            ->get(route('account.orders.show', $order->order_number))
            ->assertOk()
            ->assertSee(__('account.file_uploaded'))
            ->assertDontSee('form-files', false)
            ->assertDontSee('signature=', false);
    }

    public function test_an_unpaid_order_offers_to_pay(): void
    {
        $me = $this->makeCustomer();
        $order = $this->makeOrder(10, 5.0, ['phone' => $me->phone]);
        $order->update(['customer_id' => $me->id]);

        $this->actingAs($me, 'customer')
            ->get(route('account.orders.show', $order->order_number))
            ->assertOk()
            ->assertSee(route('payment.start', $order->order_number), false)
            ->assertDontSee(route('account.orders.receipt', $order->order_number), false);
    }

    public function test_another_customers_order_is_simply_not_found(): void
    {
        $me = $this->makeCustomer('96891234567');
        $theirs = $this->paidOrderFor($this->makeCustomer('96899999999'));

        $this->actingAs($me, 'customer');

        $this->get(route('account.orders.show', $theirs->order_number))->assertNotFound();
        $this->get(route('account.orders.receipt', $theirs->order_number))->assertNotFound();
        $this->get(route('account.orders.show', 'SO-NOSUCH00'))->assertNotFound();
    }

    public function test_a_guests_order_is_not_in_any_account_until_the_number_signs_in(): void
    {
        $guestOrder = $this->makeOrder(10, null, ['phone' => '96891234567']);
        $stranger = $this->makeCustomer('96877777777');

        $this->actingAs($stranger, 'customer')->get(route('account.orders.show', $guestOrder->order_number))->assertNotFound();
        $this->post(route('account.logout'));

        $this->signIn('96891234567');

        $this->get(route('account.orders.show', $guestOrder->order_number))->assertOk();
    }

    public function test_the_public_status_page_hands_its_owner_on_to_the_full_page(): void
    {
        $me = $this->makeCustomer('96891234567');
        $mine = $this->paidOrderFor($me);
        $other = $this->paidOrderFor($this->makeCustomer('96899999999'));

        $this->get(route('orders.show', $mine->order_number))
            ->assertOk()
            ->assertSee(__('account.sign_in_for_receipt'));

        $this->actingAs($me, 'customer');
        $this->get(route('orders.show', $mine->order_number))->assertRedirect(route('account.orders.show', $mine->order_number));
        $this->get(route('orders.show', $other->order_number))->assertOk()->assertDontSee('Someone Else');
    }

    public function test_a_new_order_by_a_signed_in_customer_lands_in_their_account(): void
    {
        $me = $this->makeCustomer('96891234567');
        $this->makeForm($this->makeService(10));
        $this->actingAs($me, 'customer');

        $this->postJson(route('packstub-form-builder.submit', 'passport'), $this->customerInput(['mobile' => '9123 4567']))->assertOk();
        $this->postJson(route('packstub-form-builder.submit', 'passport'), $this->customerInput(['mobile' => '9555 5555']))->assertOk();

        [$first, $second] = ServiceOrder::orderBy('id')->get()->all();
        $this->assertSame($me->id, $first->customer_id, 'their own number');
        $this->assertNull($second->customer_id, 'a different number is only attached once that number signs in');
    }

    // ── The site around it ───────────────────────────────────────────────────

    public function test_the_header_offers_sign_in_then_my_orders(): void
    {
        $this->get(route('account.login'))->assertSee(route('account.login'), false)->assertSee(__('account.login_link'));

        $this->actingAs($this->makeCustomer(), 'customer')
            ->get(route('account.orders'))
            ->assertSee(__('account.my_orders'));
    }

    public function test_the_pages_speak_arabic_and_are_kept_out_of_search(): void
    {
        $html = $this->get(route('account.login').'?lang=ar')->assertOk()->getContent();

        $this->assertStringContainsString(__('account.login_heading', [], 'ar'), $html);
        $this->assertStringContainsString('noindex', $html);
    }

    public function test_a_customer_cannot_use_the_admin_panel(): void
    {
        $this->actingAs($this->makeCustomer(), 'customer');

        $this->get('/admin')->assertRedirect();
        $this->assertStringContainsString('login', $this->get('/admin')->headers->get('Location'));
        $this->assertGuest('web');
    }

    public function test_the_route_shapes_never_collide_with_cms_page_slugs(): void
    {
        // every account path has two or more segments, so the /{slug} page route never sees them
        foreach (['account.login', 'account.verify', 'account.orders'] as $name) {
            $this->assertGreaterThanOrEqual(2, count(explode('/', trim(route($name, [], false), '/'))), $name);
        }
    }
}
