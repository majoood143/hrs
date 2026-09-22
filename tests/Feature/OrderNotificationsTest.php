<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Events\ServiceOrderCompleted;
use App\Events\ServiceOrderReceived;
use App\Filament\Resources\ServiceOrderResource;
use App\Jobs\SendOrderNotification;
use App\Jobs\SendReviewerNotification;
use App\Listeners\CreateOrderFromSubmission;
use App\Listeners\NotifyOnOrderCompleted;
use App\Listeners\NotifyOnOrderReceived;
use App\Mail\OrderCompletedMail;
use App\Mail\OrderReceivedMail;
use App\Mail\ReviewerOrderMail;
use App\Models\NotificationLog;
use App\Models\ServiceOrder;
use App\Services\Notifications\OrderMessages;
use App\Services\Notifications\OrderNotifier;
use App\Services\Orders\OrderReceiptPdf;
use App\Services\Orders\OrderWorkflow;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\Mail\SubmissionNotification;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

class OrderNotificationsTest extends TestCase
{
    use PreparesCustomerSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
        $this->useTamimah();
        $this->tamimahAnswers();
        Mail::fake();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Fill in the form, then pay for the order with the demo gateway. */
    private function orderPaid(float $price = 10.0, array $formSettings = [], array $input = []): ServiceOrder
    {
        $order = $this->orderPlaced($price, $formSettings, $input);

        if ($order->isPayable()) {
            $this->post(route('payment.begin', $order->order_number), ['gateway' => 'demo']);
            $this->post(route('payment.demo.decide', $order->order_number), ['decision' => 'approve'])->assertRedirect();
        }

        return $order->refresh();
    }

    private function orderPlaced(float $price = 10.0, array $formSettings = [], array $input = []): ServiceOrder
    {
        $this->makeFee('percentage', 5);
        $form = $this->makeForm($this->makeService($price), $formSettings, slug: 'form-'.uniqid());

        $this->postJson(route('packstub-form-builder.submit', $form), $this->customerInput($input))->assertOk();

        return ServiceOrder::latest('id')->firstOrFail();
    }

    /** @return list<string> the text of every SMS sent to Tamimah, in order */
    private function smsTexts(): array
    {
        return Http::recorded()
            ->map(fn ($pair) => $pair[0]->body())
            ->filter(fn ($body) => str_contains($body, '<Message>'))
            ->map(function ($body) {
                preg_match('#<Message>(.*?)</Message>#s', $body, $m);

                return html_entity_decode($m[1], ENT_XML1 | ENT_QUOTES);
            })
            ->values()
            ->all();
    }

    private function smsCount(): int
    {
        return count($this->smsTexts());
    }

    private function logs(string $channel, string $type)
    {
        return NotificationLog::where('channel', $channel)->where('type', $type)->get();
    }

    // ── When the order comes in ──────────────────────────────────────────────

    public function test_a_paid_order_tells_the_customer_by_email_and_sms_with_the_order_number(): void
    {
        $order = $this->orderPaid(formSettings: ['notifications' => ['email' => true, 'sms' => true]]);

        Mail::assertSent(OrderReceivedMail::class, fn (OrderReceivedMail $mail) => $mail->hasTo('ali@example.com') && $mail->order->is($order));

        $sms = $this->smsTexts();
        $this->assertCount(1, $sms);
        $this->assertStringContainsString($order->order_number, $sms[0]);
        $this->assertStringContainsString('payment received', $sms[0]);
        $this->assertStringContainsString('Horse passport', $sms[0]);
        $this->assertStringContainsString(route('orders.show', $order->order_number), $sms[0]);
        Http::assertSent(fn ($r) => str_contains($r->body(), '<MSISDNs>96891234567</MSISDNs>'));

        $this->assertSame('sent', $this->logs('email', 'order_received')->sole()->status);
        $this->assertSame('sent', $this->logs('sms', 'order_received')->sole()->status);
        $this->assertSame($order->id, $this->logs('sms', 'order_received')->sole()->service_order_id);
    }

    public function test_the_email_shows_the_number_the_amounts_the_receipt_and_carries_it_as_a_pdf(): void
    {
        $order = $this->orderPaid();

        Mail::assertSent(OrderReceivedMail::class, function (OrderReceivedMail $mail) use ($order) {
            $html = $mail->render();

            $this->assertStringContainsString($order->order_number, $html);
            $this->assertStringContainsString('Ali Al Balushi', $html);
            $this->assertStringContainsString('Horse passport', $html);
            $this->assertStringContainsString('OMR 10.000', $html);
            $this->assertStringContainsString('OMR 0.500', $html);
            $this->assertStringContainsString('OMR 11.025', $html);
            $this->assertStringContainsString($order->receipt_number, $html);
            $this->assertStringContainsString(route('orders.show', $order->order_number), $html);
            $this->assertStringContainsString('payment confirmed', $mail->envelope()->subject);
            $this->assertStringContainsString($order->order_number, $mail->envelope()->subject);

            return true;
        });
    }

    public function test_the_receipt_is_attached_to_the_email_of_a_paid_order(): void
    {
        $order = $this->orderPaid();

        Mail::assertSent(OrderReceivedMail::class, function (OrderReceivedMail $mail) use ($order) {
            $attachments = $mail->attachments();

            $this->assertCount(1, $attachments);
            $this->assertSame('receipt-'.$order->receipt_number.'.pdf', $attachments[0]->as);
            $this->assertSame('application/pdf', $attachments[0]->mime);

            return true;
        });
    }

    public function test_a_free_order_is_acknowledged_without_a_receipt(): void
    {
        $order = $this->orderPaid(price: 0);

        $this->assertSame(OrderStatus::New, $order->status);
        Mail::assertSent(OrderReceivedMail::class, function (OrderReceivedMail $mail) {
            $this->assertSame([], $mail->attachments());
            $this->assertStringNotContainsString('payment confirmed', $mail->envelope()->subject);
            $this->assertStringNotContainsString(__('receipt.total_paid'), $mail->render());

            return true;
        });
    }

    public function test_the_sms_wording_follows_paid_free_and_completed(): void
    {
        $paid = $this->orderPaid(formSettings: ['notifications' => ['sms' => true]]);
        $free = $this->orderPaid(price: 0, formSettings: ['notifications' => ['sms' => true]], input: ['mobile' => '9555 5555']);

        [$first, $second] = $this->smsTexts();
        $this->assertStringContainsString('payment received', $first);
        $this->assertStringContainsString('request was received', $second);
        $this->assertStringNotContainsString('payment received', $second);

        app(OrderWorkflow::class)->complete($paid);
        $this->assertStringContainsString('is completed', $this->smsTexts()[2]);
        $this->assertStringContainsString($paid->order_number, $this->smsTexts()[2]);
        unset($free);
    }

    public function test_everything_is_sent_in_the_customers_language(): void
    {
        $order = $this->orderPlaced(formSettings: ['notifications' => ['email' => true, 'sms' => true]]);
        $order->update(['locale' => 'ar']);
        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'demo']);
        $this->post(route('payment.demo.decide', $order->order_number), ['decision' => 'approve']);

        $this->assertStringContainsString('تم استلام الدفع', $this->smsTexts()[0]);
        $this->assertStringContainsString('جواز حصان', $this->smsTexts()[0]);

        Mail::assertSent(OrderReceivedMail::class, function (OrderReceivedMail $mail) {
            $html = $mail->render();

            $this->assertStringContainsString('dir="rtl"', $html);
            $this->assertStringContainsString('شكراً لك', $html);
            app()->setLocale('ar');
            $this->assertStringContainsString('تأكيد الدفع', $mail->envelope()->subject);
            app()->setLocale('en');

            return true;
        });

        $this->assertSame('en', app()->getLocale(), 'the visitor\'s own language is put back');
    }

    // ── What the form allows ─────────────────────────────────────────────────

    public function test_by_default_the_customer_gets_an_email_and_no_sms(): void
    {
        $this->orderPaid();

        Mail::assertSent(OrderReceivedMail::class);
        $this->assertSame(0, $this->smsCount());
        $this->assertCount(0, NotificationLog::where('channel', 'sms')->get());
    }

    public function test_the_forms_switches_choose_the_channels(): void
    {
        $this->orderPaid(formSettings: ['notifications' => ['email' => false, 'sms' => true]]);

        Mail::assertNotSent(OrderReceivedMail::class);
        $this->assertSame(1, $this->smsCount());
    }

    public function test_with_both_switched_off_nothing_is_sent_and_the_order_still_completes(): void
    {
        $order = $this->orderPaid(formSettings: ['notifications' => ['email' => false, 'sms' => false]]);

        Mail::assertNothingSent();
        $this->assertSame(0, $this->smsCount());
        $this->assertSame(0, NotificationLog::count());
        $this->assertTrue($order->isPaid());
        $this->assertTrue(app(OrderWorkflow::class)->complete($order));
    }

    public function test_an_enabled_channel_with_no_address_is_logged_as_skipped(): void
    {
        $order = $this->orderPaid(formSettings: ['notifications' => ['email' => true, 'sms' => true]]);
        $order->update(['customer_email' => null, 'customer_phone' => null]);

        app(OrderNotifier::class)->notify($order, OrderNotifier::COMPLETED);

        $email = $this->logs('email', 'order_completed')->sole();
        $sms = $this->logs('sms', 'order_completed')->sole();
        $this->assertSame('skipped', $email->status);
        $this->assertStringContainsString('no email', $email->error);
        $this->assertSame('skipped', $sms->status);
        $this->assertStringContainsString('no phone', $sms->error);
    }

    public function test_an_order_whose_form_was_deleted_still_gets_its_email(): void
    {
        $order = $this->orderPlaced();
        $order->form->delete();

        Event::dispatch(new ServiceOrderReceived($order->refresh()));

        Mail::assertSent(OrderReceivedMail::class, 1);
        $this->assertSame(0, $this->smsCount());
    }

    // ── Never twice, never a broken payment ──────────────────────────────────

    public function test_the_same_message_is_not_sent_twice(): void
    {
        $order = $this->orderPaid(formSettings: ['notifications' => ['email' => true, 'sms' => true]]);

        Event::dispatch(new ServiceOrderReceived($order));
        Event::dispatch(new ServiceOrderReceived($order));

        Mail::assertSent(OrderReceivedMail::class, 1);
        $this->assertSame(1, $this->smsCount());
        $this->assertSame(1, $this->logs('sms', 'order_received')->count());
    }

    public function test_a_resend_is_sent_even_though_it_was_sent_before(): void
    {
        $order = $this->orderPaid(formSettings: ['notifications' => ['email' => true, 'sms' => true]]);

        $channels = app(OrderNotifier::class)->notify($order, OrderNotifier::RECEIVED, force: true);

        $this->assertSame(['email', 'sms'], $channels);
        Mail::assertSent(OrderReceivedMail::class, 2);
        $this->assertSame(2, $this->smsCount());
    }

    public function test_a_failing_sms_is_logged_and_the_email_and_the_payment_are_unaffected(): void
    {
        $this->tamimahAnswers(processed: 0, description: 'Insufficient balance');

        $order = $this->orderPaid(formSettings: ['notifications' => ['email' => true, 'sms' => true]]);

        $this->assertTrue($order->isPaid());
        Mail::assertSent(OrderReceivedMail::class, 1);
        $log = $this->logs('sms', 'order_received')->sole();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Insufficient balance', $log->error);
    }

    public function test_a_failing_mail_server_is_logged_and_the_sms_and_the_payment_are_unaffected(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP server is down'));

        $order = $this->orderPaid(formSettings: ['notifications' => ['email' => true, 'sms' => true]]);

        $this->assertTrue($order->isPaid());
        $this->assertSame(1, $this->smsCount());
        $log = $this->logs('email', 'order_received')->sole();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('SMTP server is down', $log->error);
    }

    public function test_a_receipt_that_cannot_be_drawn_never_stops_the_email(): void
    {
        $order = $this->orderPaid();
        $this->mock(OrderReceiptPdf::class, function ($mock) {
            $mock->shouldReceive('available')->andReturn(true);
            $mock->shouldReceive('render')->andThrow(new \RuntimeException('mPDF ran out of memory'));
            $mock->shouldReceive('filename')->andReturn('receipt.pdf');
        });

        $this->assertSame([], (new OrderReceivedMail($order))->attachments());
    }

    public function test_the_messages_are_queued_not_sent_inside_the_request(): void
    {
        Queue::fake();

        $order = $this->orderPaid(formSettings: ['notifications' => ['email' => true, 'sms' => true]]);

        Queue::assertPushed(SendOrderNotification::class, 2);
        Queue::assertPushed(SendOrderNotification::class, fn ($job) => $job->channel === 'email' && $job->type === 'order_received' && $job->orderId === $order->id);
        Queue::assertPushed(SendOrderNotification::class, fn ($job) => $job->channel === 'sms');
        Mail::assertNothingSent();
        $this->assertSame(0, $this->smsCount());
    }

    public function test_a_queue_that_is_down_never_breaks_the_payment(): void
    {
        $order = $this->orderPlaced();
        $this->mock(Dispatcher::class, fn ($mock) => $mock->shouldReceive('dispatch')->andThrow(new \RuntimeException('queue database is down')));

        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'demo']);
        $this->post(route('payment.demo.decide', $order->order_number), ['decision' => 'approve'])
            ->assertRedirect(route('orders.show', $order->order_number));

        $this->assertTrue($order->refresh()->isPaid());
    }

    // ── The reviewers ────────────────────────────────────────────────────────

    public function test_reviewers_of_a_paid_form_hear_about_it_only_once_it_is_paid(): void
    {
        $order = $this->orderPlaced(formSettings: []);
        $order->form->update(['notification_emails' => ['ops@example.com']]);
        $answers = $this->postJson(route('packstub-form-builder.submit', $order->form), $this->customerInput(['full_name' => 'Second Person', 'mobile' => '9777 7777']));
        $answers->assertOk();

        Mail::assertNotQueued(SubmissionNotification::class);
        Mail::assertNotSent(ReviewerOrderMail::class);

        $second = ServiceOrder::latest('id')->first();
        $this->post(route('payment.begin', $second->order_number), ['gateway' => 'demo']);
        $this->post(route('payment.demo.decide', $second->order_number), ['decision' => 'approve']);

        Mail::assertSent(ReviewerOrderMail::class, function (ReviewerOrderMail $mail) use ($second) {
            $html = $mail->render();

            $this->assertTrue($mail->hasTo('ops@example.com'));
            $this->assertStringContainsString($second->order_number, $mail->envelope()->subject);
            $this->assertStringContainsString('Second Person', $html);
            $this->assertStringContainsString('+96897777777', $html);
            $this->assertStringContainsString('OMR 11.025', $html);
            $this->assertStringContainsString(ServiceOrderResource::getUrl('view', ['record' => $second->id]), $html);

            return true;
        });
        $this->assertSame('sent', $this->logs('email', SendReviewerNotification::TYPE)->sole()->status);
    }

    public function test_reviewers_are_told_once_even_if_the_payment_event_repeats(): void
    {
        $order = $this->orderPlaced();
        $order->form->update(['notification_emails' => ['ops@example.com', 'boss@example.com']]);
        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'demo']);
        $this->post(route('payment.demo.decide', $order->order_number), ['decision' => 'approve']);

        Event::dispatch(new ServiceOrderReceived($order->refresh()));

        Mail::assertSent(ReviewerOrderMail::class, 2); // one each, not four
    }

    public function test_a_free_form_keeps_the_ordinary_submission_email_and_sends_no_second_one(): void
    {
        $form = $this->makeForm($this->makeService(0));
        $form->update(['notification_emails' => ['ops@example.com']]);

        $this->postJson(route('packstub-form-builder.submit', $form), $this->customerInput())->assertOk();

        Mail::assertQueued(SubmissionNotification::class, 1);
        Mail::assertNotSent(ReviewerOrderMail::class);
    }

    public function test_an_ordinary_form_is_untouched(): void
    {
        $form = $this->makeForm(null);
        $form->update(['notification_emails' => ['ops@example.com']]);

        $this->postJson(route('packstub-form-builder.submit', $form), $this->customerInput())->assertOk();

        Mail::assertQueued(SubmissionNotification::class, 1);
        Mail::assertNotSent(OrderReceivedMail::class);
    }

    // ── Completing an order ──────────────────────────────────────────────────

    public function test_completing_an_order_tells_the_customer(): void
    {
        $order = $this->orderPaid(formSettings: ['notifications' => ['email' => true, 'sms' => true]]);

        $this->assertTrue(app(OrderWorkflow::class)->complete($order, userId: 7));

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->completed_at);
        $last = $order->events()->reorder()->latest('id')->first();
        $this->assertSame('completed', $last->type);
        $this->assertTrue($last->is_public);
        $this->assertSame(7, $last->user_id);

        Mail::assertSent(OrderCompletedMail::class, function (OrderCompletedMail $mail) use ($order) {
            $html = $mail->render();
            $this->assertTrue($mail->hasTo('ali@example.com'));
            $this->assertStringContainsString($order->order_number, $mail->envelope()->subject);
            $this->assertStringContainsString('has been completed', $html);
            $this->assertStringContainsString(route('orders.show', $order->order_number), $html);

            return true;
        });
        $this->assertStringContainsString('is completed', $this->smsTexts()[1]);
        $this->assertSame('sent', $this->logs('email', 'order_completed')->sole()->status);
        $this->assertSame('sent', $this->logs('sms', 'order_completed')->sole()->status);
    }

    public function test_an_order_completes_only_once(): void
    {
        Event::fake([ServiceOrderCompleted::class]);
        $order = $this->orderPaid();

        $this->assertTrue(app(OrderWorkflow::class)->complete($order));
        $this->assertFalse(app(OrderWorkflow::class)->complete($order->refresh()));

        Event::assertDispatchedTimes(ServiceOrderCompleted::class, 1);
        $this->assertSame(1, $order->events->where('type', 'completed')->count());
    }

    public function test_only_a_paid_or_free_order_that_is_still_open_can_be_completed(): void
    {
        $workflow = app(OrderWorkflow::class);
        $unpaid = $this->orderPlaced();
        $free = $this->orderPlaced(price: 0, input: ['mobile' => '9555 5555']);
        $cancelled = $this->orderPaid(input: ['mobile' => '9666 6666']);
        $cancelled->update(['status' => OrderStatus::Cancelled]);

        $this->assertFalse($workflow->canComplete($unpaid));
        $this->assertFalse($workflow->complete($unpaid));
        $this->assertFalse($workflow->canComplete($cancelled));
        $this->assertTrue($workflow->canComplete($free));
        $this->assertTrue($workflow->complete($free));
        $this->assertSame(OrderStatus::PendingPayment, $unpaid->refresh()->status);
    }

    public function test_the_completion_messages_are_in_the_customers_language(): void
    {
        $order = $this->orderPaid(formSettings: ['notifications' => ['email' => true, 'sms' => true]]);
        $order->update(['locale' => 'ar']);

        app(OrderWorkflow::class)->complete($order->refresh());

        $this->assertStringContainsString('تم إكمال طلبك', $this->smsTexts()[1]);
        Mail::assertSent(OrderCompletedMail::class, function (OrderCompletedMail $mail) {
            app()->setLocale('ar');
            $subject = $mail->envelope()->subject;
            app()->setLocale('en');

            return str_contains($subject, 'تم إكمال طلبك') && str_contains($mail->render(), 'dir="rtl"');
        });
    }

    // ── The texts themselves ─────────────────────────────────────────────────

    public function test_the_sms_texts_are_short_enough_for_one_message(): void
    {
        $order = $this->orderPaid();
        $messages = app(OrderMessages::class);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            foreach ([OrderNotifier::RECEIVED, OrderNotifier::COMPLETED] as $type) {
                // English fits one 160-character SMS; Arabic (UCS-2) fits 70 characters per part, so a few parts at most
                $limit = $locale === 'en' ? 200 : 260;
                $this->assertLessThanOrEqual($limit, mb_strlen($messages->sms($type, $order)), "{$locale} {$type}");
            }
        }
    }

    public function test_every_notification_string_exists_in_both_languages(): void
    {
        $flatten = function (array $array, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($array as $key => $value) {
                is_array($value) ? $keys = [...$keys, ...$flatten($value, $prefix.$key.'.')] : $keys[] = $prefix.$key;
            }

            return $keys;
        };

        $en = $flatten(require lang_path('en/notifications.php'));
        $ar = $flatten(require lang_path('ar/notifications.php'));

        $this->assertEqualsCanonicalizing($en, $ar);
        $this->assertNotEmpty($en);
    }

    public function test_each_listener_is_registered_exactly_once(): void
    {
        $registered = fn (string $event, string $class) => collect(Event::getListeners($event))->count() > 0
            ? collect(app('events')->getRawListeners()[$event] ?? [])->filter(fn ($listener) => is_string($listener) && str_starts_with($listener, $class))->count()
            : 0;

        $this->assertSame(1, $registered(ServiceOrderReceived::class, NotifyOnOrderReceived::class));
        $this->assertSame(1, $registered(ServiceOrderCompleted::class, NotifyOnOrderCompleted::class));
        $this->assertSame(1, $registered(SubmissionReceived::class, CreateOrderFromSubmission::class));
    }
}
