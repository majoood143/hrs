<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\NotificationTexts;
use App\Mail\OrderCompletedMail;
use App\Mail\OrderReceivedMail;
use App\Mail\ReviewerOrderMail;
use App\Models\SiteSetting;
use App\Services\Orders\OrderWorkflow;
use App\Support\NotificationText;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\MakesReviewOrders;
use Tests\TestCase;

class NotificationTextsTest extends TestCase
{
    use MakesReviewOrders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareReviewSite();
    }

    private function override(array $texts): void
    {
        $this->seedSiteSettings([
            'notifications.texts' => json_encode($texts, JSON_UNESCAPED_UNICODE),
            'sms.driver' => 'tamimah', 'sms.tamimah.username' => 'u', 'sms.tamimah.password' => 'p', 'sms.tamimah.sender' => 'S',
        ]);
    }

    private function lastSms(): string
    {
        $body = Http::recorded()->map(fn ($pair) => $pair[0]->body())->filter(fn ($b) => str_contains($b, '<Message>'))->last();
        preg_match('#<Message>(.*?)</Message>#s', (string) $body, $m);

        return html_entity_decode($m[1] ?? '', ENT_XML1 | ENT_QUOTES);
    }

    // ── The lookup ───────────────────────────────────────────────────────────

    public function test_without_an_override_the_language_file_is_used(): void
    {
        $this->assertSame(__('notifications.sms.completed', ['site' => 'S', 'number' => 'N', 'url' => 'U']), NotificationText::get('sms.completed', ['site' => 'S', 'number' => 'N', 'url' => 'U']));
        $this->assertSame([], NotificationText::overrides());
    }

    public function test_an_override_replaces_the_text_for_its_language_only(): void
    {
        $this->override(['sms.completed' => ['en' => 'Done! Order :number is ready: :url']]);

        $this->assertSame('Done! Order SO-1 is ready: http://x', NotificationText::get('sms.completed', ['number' => 'SO-1', 'url' => 'http://x', 'site' => 'S'], 'en'));
        $this->assertSame(__('notifications.sms.completed', ['number' => 'SO-1', 'url' => 'http://x', 'site' => 'S'], 'ar'), NotificationText::get('sms.completed', ['number' => 'SO-1', 'url' => 'http://x', 'site' => 'S'], 'ar'), 'Arabic was not overridden');
    }

    public function test_an_empty_or_blank_override_is_ignored(): void
    {
        $this->override(['sms.completed' => ['en' => '   ', 'ar' => '']]);

        $this->assertSame(__('notifications.sms.completed', ['number' => 'N', 'url' => 'U', 'site' => 'S']), NotificationText::get('sms.completed', ['number' => 'N', 'url' => 'U', 'site' => 'S'], 'en'));
    }

    public function test_placeholders_are_filled_longest_first_and_unknown_ones_stay_as_written(): void
    {
        $this->override(['mail.greeting' => ['en' => 'Dear :name (:names), see :unknown']]);

        $this->assertSame('Dear Ali (Ali & co), see :unknown', NotificationText::get('mail.greeting', ['name' => 'Ali', 'names' => 'Ali & co'], 'en'));
    }

    public function test_the_catalogue_matches_the_language_files_in_both_languages(): void
    {
        foreach (NotificationText::EDITABLE as $keys) {
            foreach ($keys as $key) {
                foreach (['en', 'ar'] as $locale) {
                    $this->assertNotSame('notifications.'.$key, trans('notifications.'.$key, [], $locale), "{$key} in {$locale}");
                    $this->assertNotSame('', NotificationText::defaultText($key, $locale));
                }
                $this->assertArrayHasKey($key, trans('admin_notification_texts.labels', [], 'en'));
                $this->assertArrayHasKey($key, trans('admin_notification_texts.labels', [], 'ar'));
            }
        }
    }

    public function test_the_internal_reviewer_emails_are_not_editable(): void
    {
        $all = collect(NotificationText::EDITABLE)->flatten()->all();

        $this->assertEmpty(array_filter($all, fn ($key) => str_contains($key, 'reviewer') || str_contains($key, '.stage.')));
    }

    // ── What may be saved ────────────────────────────────────────────────────

    public function test_a_text_must_keep_the_placeholders_that_carry_the_details(): void
    {
        $this->assertNull(NotificationText::validate('sms.received_paid', 'Paid! Order :number, track: :url'), ':site and :service may go');
        $this->assertNull(NotificationText::validate('sms.received_paid', ''), 'empty means the default');
        $this->assertStringContainsString(':number', NotificationText::validate('sms.received_paid', 'Thanks, we got it: :url'));
        $this->assertStringContainsString(':url', NotificationText::validate('sms.completed', 'Order :number is done'));
        $this->assertStringContainsString(':amount', NotificationText::validate('sms.refunded', 'Order :number refunded: :url'));
        $this->assertStringContainsString(':name', NotificationText::validate('mail.greeting', 'Hello!'));
    }

    public function test_a_text_may_not_invent_placeholders(): void
    {
        $error = NotificationText::validate('sms.completed', 'Order :number done :url :nonsense');

        $this->assertStringContainsString(':nonsense', $error);
        $this->assertStringContainsString(':number', $error, 'says what can be used');
    }

    public function test_ordinary_colons_in_a_sentence_are_not_mistaken_for_placeholders(): void
    {
        $this->assertNull(NotificationText::validate('sms.completed', 'Ready: order :number at :url (note:this is fine, 10:30)'));
        $this->assertNull(NotificationText::validate('mail.received.next', 'Next steps: we review it. Time:soon'));
        $this->assertNull(NotificationText::validate('mail.track_button', 'Track: my order'));
    }

    public function test_placeholders_are_listed_from_both_languages_defaults(): void
    {
        $this->assertEqualsCanonicalizing(['site', 'number', 'url'], NotificationText::placeholders('sms.completed'));
        $this->assertSame([], NotificationText::placeholders('mail.received.next'));
        $this->assertContains('amount', NotificationText::placeholders('sms.refunded'));
    }

    // ── They reach the customer ──────────────────────────────────────────────

    public function test_a_rewritten_sms_is_what_the_customer_gets(): void
    {
        $order = $this->stagedOrder(stages: []);
        $order->form->update(['settings' => ['min_seconds' => 0, 'payment' => ['service_id' => $order->service_id], 'notifications' => ['email' => false, 'sms' => true]]]);
        $this->override(['sms.completed' => ['en' => 'Good news! Order :number is finished. See :url']]);

        app(OrderWorkflow::class)->complete($order);

        $this->assertSame('Good news! Order '.$order->order_number.' is finished. See '.route('orders.show', $order->order_number), $this->lastSms());
    }

    public function test_an_arabic_customer_gets_the_arabic_override(): void
    {
        $order = $this->stagedOrder(stages: []);
        $order->form->update(['settings' => ['min_seconds' => 0, 'payment' => ['service_id' => $order->service_id], 'notifications' => ['email' => false, 'sms' => true]]]);
        $order->update(['locale' => 'ar']);
        $this->override(['sms.completed' => ['en' => 'ENGLISH :number :url', 'ar' => 'تم الإنجاز :number ← :url']]);

        app(OrderWorkflow::class)->complete($order->refresh());

        $this->assertSame('تم الإنجاز '.$order->order_number.' ← '.route('orders.show', $order->order_number), $this->lastSms());
        $this->assertSame('en', app()->getLocale());
    }

    public function test_a_rewritten_email_subject_and_body_are_used(): void
    {
        $this->override([
            'mail.completed.subject' => ['en' => 'READY: :number'],
            'mail.completed.intro' => ['en' => 'We finished **:service** for you.'],
            'mail.greeting' => ['en' => 'Dear :name'],
            'mail.track_button' => ['en' => 'Open my order'],
        ]);
        $order = $this->stagedOrder(stages: []);

        app(OrderWorkflow::class)->complete($order);

        Mail::assertSent(OrderCompletedMail::class, function (OrderCompletedMail $mail) use ($order) {
            $html = $mail->render();

            $this->assertSame('READY: '.$order->order_number, $mail->envelope()->subject);
            $this->assertStringContainsString('We finished', $html);
            $this->assertStringContainsString('Dear Ali Al Balushi', $html);
            $this->assertStringContainsString('Open my order', $html);
            $this->assertStringContainsString(__('notifications.mail.completed.next'), strip_tags(html_entity_decode($html)), 'lines not rewritten are unchanged');

            return true;
        });
    }

    public function test_texts_not_rewritten_stay_exactly_as_they_were(): void
    {
        $this->override(['sms.completed' => ['en' => 'Custom :number :url']]);
        $this->stagedOrder(stages: []);

        Mail::assertSent(OrderReceivedMail::class, function (OrderReceivedMail $mail) {
            $this->assertStringContainsString(__('notifications.mail.received.next'), strip_tags(html_entity_decode($mail->render())));

            return true;
        });
    }

    public function test_the_reviewers_emails_ignore_the_overrides(): void
    {
        $this->override(['mail.greeting' => ['en' => 'Changed :name']]);
        $order = $this->stagedOrder(stages: []);

        $html = (new ReviewerOrderMail($order))->render();

        $this->assertStringContainsString(__('notifications.mail.reviewer.heading', ['number' => $order->order_number]), html_entity_decode($html));
        $this->assertStringNotContainsString('Changed', $html);
    }

    // ── The admin page ───────────────────────────────────────────────────────

    private function admin(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type')->default('text');
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->string('managed_by')->nullable();
            $table->timestamps();
        });
        SiteSetting::clearCache();
    }

    private function stored(): array
    {
        $value = DB::table('site_settings')->where('key', 'notifications.texts')->value('value');

        return $value ? json_decode($value, true) : [];
    }

    public function test_the_page_opens_with_todays_texts_filled_in(): void
    {
        $this->admin();

        Livewire::test(NotificationTexts::class)
            ->assertSuccessful()
            ->assertFormSet([
                'texts.sms__completed.en' => NotificationText::defaultText('sms.completed', 'en'),
                'texts.sms__completed.ar' => NotificationText::defaultText('sms.completed', 'ar'),
                'texts.mail__received__intro_paid.en' => NotificationText::defaultText('mail.received.intro_paid', 'en'),
            ])
            ->assertSee(__('admin_notification_texts.groups.sms'))
            ->assertSee(__('admin_notification_texts.placeholders', ['names' => ':site, :number, :url']));
    }

    public function test_saving_stores_only_what_was_changed(): void
    {
        $this->admin();

        Livewire::test(NotificationTexts::class)
            ->fillForm(['texts' => ['sms__completed' => ['en' => 'All done! Order :number: :url'], 'mail__track_button' => ['ar' => 'افتح طلبي']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([
            'sms.completed' => ['en' => 'All done! Order :number: :url'],
            'mail.track_button' => ['ar' => 'افتح طلبي'],
        ], $this->stored());
    }

    public function test_setting_a_text_back_to_its_default_removes_the_override(): void
    {
        $this->admin();
        NotificationText::save(['sms.completed' => ['en' => 'Custom :number :url']]);
        $this->assertNotEmpty($this->stored());

        Livewire::test(NotificationTexts::class)
            ->assertFormSet(['texts.sms__completed.en' => 'Custom :number :url'])
            ->fillForm(['texts' => ['sms__completed' => ['en' => NotificationText::defaultText('sms.completed', 'en')]]])
            ->call('save');

        $this->assertSame([], $this->stored());
    }

    public function test_a_text_that_drops_the_order_number_or_invents_a_placeholder_is_refused(): void
    {
        $this->admin();

        Livewire::test(NotificationTexts::class)
            ->fillForm(['texts' => ['sms__received_paid' => ['en' => 'Thank you! Track it: :url']]])
            ->call('save')
            ->assertHasFormErrors(['texts.sms__received_paid.en']);

        Livewire::test(NotificationTexts::class)
            ->fillForm(['texts' => ['sms__completed' => ['ar' => 'تم :number :url :bogus']]])
            ->call('save')
            ->assertHasFormErrors(['texts.sms__completed.ar']);

        $this->assertSame([], $this->stored());
    }

    public function test_save_refuses_an_invalid_text_even_called_directly_not_through_the_admin_form(): void
    {
        $this->admin();

        // The Filament page's field-level rule is not the only guard: NotificationText::save()
        // itself must not persist a text that drops a required placeholder, for a caller that
        // reaches it some other way (tinker, a seeder, a future API).
        NotificationText::save([
            'sms.received_paid' => ['en' => 'Thank you! Track it: :url'], // drops :number, refused
            'sms.completed' => ['en' => 'All done! Order :number: :url'], // valid, kept
        ]);

        $this->assertSame(['sms.completed' => ['en' => 'All done! Order :number: :url']], $this->stored());
    }

    public function test_reset_all_goes_back_to_the_defaults(): void
    {
        $this->admin();
        NotificationText::save(['sms.completed' => ['en' => 'Custom :number :url'], 'mail.track_button' => ['en' => 'Go']]);

        Livewire::test(NotificationTexts::class)
            ->callAction('reset')
            ->assertNotified(__('admin_notification_texts.notifications.reset'))
            ->assertFormSet(['texts.sms__completed.en' => NotificationText::defaultText('sms.completed', 'en')]);

        $this->assertSame([], $this->stored());
        $this->assertSame(__('notifications.mail.track_button'), NotificationText::get('mail.track_button', [], 'en'));
    }

    public function test_a_change_made_on_the_page_reaches_the_next_message(): void
    {
        $this->admin();

        Livewire::test(NotificationTexts::class)
            ->fillForm(['texts' => ['mail__track_button' => ['en' => 'See my order now']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('See my order now', NotificationText::get('mail.track_button', [], 'en'));
    }
}
