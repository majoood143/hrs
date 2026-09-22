<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Events\ServiceOrderCompleted;
use App\Filament\Resources\NotificationLogResource\Pages\ListNotificationLogs;
use App\Filament\Resources\ServiceOrderResource\Pages\ViewServiceOrder;
use App\Mail\OrderCompletedMail;
use App\Mail\OrderReceivedMail;
use App\Models\NotificationLog;
use App\Models\ServiceOrder;
use App\Services\Orders\OrderWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

/** "Mark as completed", "Resend notification" and the notification history, as an admin on an order's page. */
class OrderAdminActionsTest extends TestCase
{
    use PreparesCustomerSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
        $this->useTamimah();
        $this->tamimahAnswers();
        Mail::fake();

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];

            public function getAuthIdentifier(): int
            {
                return 42;
            }
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function paidOrder(array $formSettings = ['notifications' => ['email' => true, 'sms' => true]]): ServiceOrder
    {
        $this->makeFee('percentage', 5);
        $form = $this->makeForm($this->makeService(10), $formSettings);
        $order = $this->paidOrderFor($this->makeCustomer());
        $order->update(['form_id' => $form->id]);

        return $order->refresh();
    }

    private function page(ServiceOrder $order)
    {
        return Livewire::test(ViewServiceOrder::class, ['record' => $order->getKey()]);
    }

    private function smsCount(): int
    {
        return Http::recorded()->filter(fn ($pair) => str_contains($pair[0]->body(), '<Message>'))->count();
    }

    // ── Mark as completed ────────────────────────────────────────────────────

    public function test_completing_from_the_order_page_finishes_it_and_tells_the_customer(): void
    {
        $order = $this->paidOrder();

        $this->page($order)
            ->assertActionVisible('complete')
            ->callAction('complete')
            ->assertNotified(__('admin_service_order.notifications.completed'));

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(42, $order->events()->reorder()->latest('id')->first()->user_id, 'the timeline says who did it');
        Mail::assertSent(OrderCompletedMail::class, 1);
        $this->assertSame(1, $this->smsCount());
    }

    public function test_the_button_goes_away_once_it_is_done_and_the_page_shows_the_new_status(): void
    {
        $order = $this->paidOrder();

        $this->page($order)
            ->callAction('complete')
            ->assertActionHidden('complete')
            ->assertSee(OrderStatus::Completed->label());
    }

    public function test_an_unpaid_or_cancelled_order_cannot_be_completed_from_the_page(): void
    {
        $unpaid = $this->makeOrder(10, 5.0);
        $cancelled = $this->paidOrder();
        $cancelled->update(['status' => OrderStatus::Cancelled]);

        $this->page($unpaid)->assertActionHidden('complete')->assertActionHidden('resend');
        $this->page($cancelled)->assertActionHidden('complete');
    }

    public function test_two_admins_completing_the_same_order_at_once_complete_it_only_once(): void
    {
        Event::fake([ServiceOrderCompleted::class]);
        $order = $this->paidOrder();
        $workflow = app(OrderWorkflow::class);

        // the second admin's page still holds the order as it was before the first one finished
        $stale = ServiceOrder::find($order->id);

        $this->assertTrue($workflow->complete($order));
        $this->assertFalse($workflow->complete($stale));

        Event::assertDispatchedTimes(ServiceOrderCompleted::class, 1);
        $this->assertSame(1, $order->events()->where('type', 'completed')->count());
    }

    // ── Resend ───────────────────────────────────────────────────────────────

    public function test_resending_sends_the_message_again(): void
    {
        $order = $this->paidOrder();

        $this->page($order)
            ->callAction('resend', ['type' => 'order_received'])
            ->assertNotified(__('admin_service_order.notifications.resent', ['channels' => 'EMAIL + SMS']));
        $this->page($order)->callAction('resend', ['type' => 'order_received']);

        Mail::assertSent(OrderReceivedMail::class, 2);
        $this->assertSame(2, $this->smsCount());
    }

    public function test_the_completion_message_can_only_be_resent_once_the_order_is_completed(): void
    {
        $order = $this->paidOrder();

        $this->page($order)->mountAction('resend')->assertSchemaStateSet(['type' => 'order_received']);
        $this->page($order)->callAction('resend', ['type' => 'order_completed'])->assertHasActionErrors(['type']);

        app(OrderWorkflow::class)->complete($order);
        Mail::fake();

        $this->page($order->refresh())->callAction('resend', ['type' => 'order_completed'])->assertHasNoActionErrors();
        Mail::assertSent(OrderCompletedMail::class, 1);
    }

    public function test_resending_with_both_channels_off_says_there_is_nothing_to_send(): void
    {
        $order = $this->paidOrder(['notifications' => ['email' => false, 'sms' => false]]);

        $this->page($order)
            ->callAction('resend', ['type' => 'order_received'])
            ->assertNotified(__('admin_service_order.notifications.resend_none'));

        Mail::assertNothingSent();
    }

    // ── The history ──────────────────────────────────────────────────────────

    public function test_the_order_page_lists_what_was_sent_and_what_failed(): void
    {
        $order = $this->paidOrder();
        NotificationLog::create(['service_order_id' => $order->id, 'channel' => 'email', 'type' => 'order_received', 'recipient' => 'ali@example.com', 'status' => 'sent']);
        NotificationLog::create(['service_order_id' => $order->id, 'channel' => 'sms', 'type' => 'order_received', 'recipient' => '96891234567', 'status' => 'failed', 'error' => 'Insufficient balance']);
        NotificationLog::create(['service_order_id' => $order->id, 'channel' => 'sms', 'type' => 'order_completed', 'recipient' => '', 'status' => 'skipped', 'error' => 'The order has no phone number.']);

        $this->page($order)
            ->assertSee(__('admin_service_order.sections.notifications'))
            ->assertSee('ali@example.com')
            ->assertSee(__('admin_service_order.notification_types.order_received'))
            ->assertSee(__('admin_notification_log.statuses.sent'))
            ->assertSee(__('admin_notification_log.statuses.failed'))
            ->assertSee(__('admin_notification_log.statuses.skipped'));
    }

    public function test_an_order_with_no_messages_says_so(): void
    {
        $order = $this->paidOrder();

        $this->page($order)->assertSee(__('admin_service_order.notifications.none'));
    }

    public function test_the_notification_log_lists_skipped_messages_and_filters_by_them(): void
    {
        $sent = NotificationLog::create(['channel' => 'email', 'type' => 'order_received', 'recipient' => 'a@example.com', 'status' => 'sent']);
        $skipped = NotificationLog::create(['channel' => 'sms', 'type' => 'order_received', 'recipient' => '', 'status' => 'skipped', 'error' => 'The order has no phone number.']);

        Livewire::test(ListNotificationLogs::class)
            ->filterTable('status', 'skipped')
            ->assertCanSeeTableRecords([$skipped])
            ->assertCanNotSeeTableRecords([$sent]);
    }
}
