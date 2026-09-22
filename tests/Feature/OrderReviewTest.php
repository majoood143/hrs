<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Events\ServiceOrderCompleted;
use App\Events\ServiceOrderRejected;
use App\Mail\OrderRejectedMail;
use App\Models\OrderStage;
use App\Models\ServiceOrder;
use App\Services\Orders\CreateServiceOrder;
use App\Services\Orders\OrderWorkflow;
use App\Services\Payments\OrderPaymentService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\MakesReviewOrders;
use Tests\TestCase;

class OrderReviewTest extends TestCase
{
    use MakesReviewOrders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareReviewSite();
    }

    private function workflow(): OrderWorkflow
    {
        return app(OrderWorkflow::class);
    }

    // ── Entering review ──────────────────────────────────────────────────────

    public function test_a_paid_order_of_a_form_with_stages_enters_review_with_a_copy_of_them(): void
    {
        $order = $this->stagedOrder();

        $this->assertSame(OrderStatus::InReview, $order->status);
        $stages = $order->stages;
        $this->assertCount(2, $stages);
        $this->assertSame([1, 2], $stages->pluck('position')->all());
        $this->assertSame(['reviewer', 'manager'], $stages->pluck('role_name')->all());
        $this->assertSame(['Technical check', 'Final approval'], $stages->map(fn ($s) => $s->getTranslation('name', 'en'))->all());
        $this->assertSame('فحص فني', $stages[0]->getTranslation('name', 'ar'));
        $this->assertTrue($stages->every(fn ($s) => $s->status === OrderStage::PENDING));
        $this->assertTrue($order->events->firstWhere('type', 'review_started')->is_public);
    }

    public function test_a_free_order_enters_review_straight_away(): void
    {
        $order = $this->stagedOrder(price: 0);

        $this->assertSame(OrderStatus::InReview, $order->status);
        $this->assertCount(2, $order->stages);
    }

    public function test_an_unpaid_order_is_not_in_review_yet(): void
    {
        $order = $this->stagedOrder(paid: false);

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertCount(0, $order->stages);
    }

    public function test_a_form_without_stages_skips_review(): void
    {
        $order = $this->stagedOrder(stages: []);

        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertCount(0, $order->stages);
        $this->assertTrue($this->workflow()->canComplete($order));
    }

    public function test_starting_review_twice_changes_nothing(): void
    {
        $order = $this->stagedOrder();

        $this->assertFalse($this->workflow()->startReview($order));

        $this->assertCount(2, $order->refresh()->stages);
        $this->assertSame(1, $order->events->where('type', 'review_started')->count());
    }

    public function test_editing_or_deleting_the_form_never_changes_an_order_under_review(): void
    {
        $order = $this->stagedOrder();

        $order->form->update(['settings' => ['approval' => ['stages' => [['name' => ['en' => 'Something else'], 'role' => 'ceo']]]]]);
        $order->form->delete();

        $stages = $order->refresh()->stages;
        $this->assertSame(['reviewer', 'manager'], $stages->pluck('role_name')->all());
        $this->assertSame('Technical check', $stages[0]->getTranslation('name', 'en'));
    }

    public function test_the_stage_settings_ignore_entries_without_a_role(): void
    {
        $order = $this->stagedOrder(stages: [['name' => ['en' => 'Nobody'], 'role' => ''], ['name' => [], 'role' => 'reviewer']]);

        $this->assertCount(1, $order->stages);
        $this->assertSame('reviewer', $order->stages[0]->role_name);
        $this->assertSame('reviewer', $order->stages[0]->getTranslation('name', 'en'), 'a stage with no name is called after its role');
    }

    // ── Who may decide ───────────────────────────────────────────────────────

    public function test_only_the_current_stages_role_may_approve(): void
    {
        $order = $this->stagedOrder();

        $this->assertSame(OrderWorkflow::NOT_ALLOWED, $this->workflow()->approve($order, $this->reviewer(['manager'])), 'the manager stage is not current yet');
        $this->assertSame(OrderWorkflow::NOT_ALLOWED, $this->workflow()->approve($order, $this->reviewer([])));
        $this->assertSame(OrderWorkflow::NOT_ALLOWED, $this->workflow()->approve($order, null));
        $this->assertSame(OrderStage::PENDING, $order->stages()->first()->status);

        $this->assertSame(OrderWorkflow::OK, $this->workflow()->approve($order, $this->reviewer(['reviewer'])));
    }

    public function test_a_super_admin_may_decide_any_stage(): void
    {
        $order = $this->stagedOrder();

        $this->assertTrue($this->workflow()->canDecide($order, $this->reviewer(['super_admin'])));
        $this->assertSame(OrderWorkflow::OK, $this->workflow()->approve($order, $this->reviewer(['super_admin'], 9)));
    }

    public function test_who_can_decide_follows_the_current_stage(): void
    {
        $order = $this->stagedOrder();
        $rita = $this->reviewer(['reviewer'], 5);
        $mo = $this->reviewer(['manager'], 6);

        $this->assertTrue($this->workflow()->canDecide($order, $rita));
        $this->assertFalse($this->workflow()->canDecide($order, $mo));

        $this->workflow()->approve($order, $rita);

        $this->assertFalse($this->workflow()->canDecide($order->refresh(), $rita));
        $this->assertTrue($this->workflow()->canDecide($order, $mo));
    }

    // ── Approving ────────────────────────────────────────────────────────────

    public function test_an_approval_is_recorded_with_who_when_and_why(): void
    {
        $order = $this->stagedOrder();

        $this->workflow()->approve($order, $this->reviewer(['reviewer'], 5), 'Photos are clear.');

        $stage = $order->stages()->first();
        $this->assertSame(OrderStage::APPROVED, $stage->status);
        $this->assertSame(5, $stage->decided_by);
        $this->assertNotNull($stage->decided_at);
        $this->assertSame('Photos are clear.', $stage->comment);
        $this->assertSame(OrderStatus::InReview, $order->refresh()->status, 'one more stage to go');

        $event = $order->events->firstWhere('type', 'stage_approved');
        $this->assertFalse($event->is_public, 'internal stage names are not shown to the customer');
        $this->assertSame(5, $event->user_id);
    }

    public function test_the_last_approval_moves_the_order_to_processing_and_tells_the_customer_publicly(): void
    {
        $order = $this->stagedOrder();

        $this->workflow()->approve($order, $this->reviewer(['reviewer'], 5));
        $this->workflow()->approve($order->refresh(), $this->reviewer(['manager'], 6));

        $order->refresh();
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertTrue($order->stages->every(fn ($s) => $s->status === OrderStage::APPROVED));
        $this->assertTrue($order->events->firstWhere('type', 'approved')->is_public);
    }

    public function test_a_stage_cannot_be_approved_twice(): void
    {
        $order = $this->stagedOrder();
        $rita = $this->reviewer(['reviewer'], 5);

        $this->assertSame(OrderWorkflow::OK, $this->workflow()->approve($order, $rita));
        // a second reviewer with the same role, on a page opened before: the current stage is now the manager's
        $this->assertSame(OrderWorkflow::NOT_ALLOWED, $this->workflow()->approve($order, $this->reviewer(['reviewer'], 7)));

        $this->assertSame([OrderStage::APPROVED, OrderStage::PENDING], $order->stages()->pluck('status')->all());
        $this->assertSame(1, $order->events()->where('type', 'stage_approved')->count());
    }

    public function test_nothing_can_be_approved_once_review_is_over(): void
    {
        $order = $this->stagedOrder(stages: [['name' => ['en' => 'Only'], 'role' => 'reviewer']]);
        $rita = $this->reviewer(['reviewer', 'super_admin']);

        $this->assertSame(OrderWorkflow::OK, $this->workflow()->approve($order, $rita));
        $this->assertSame(OrderWorkflow::NO_STAGE, $this->workflow()->approve($order->refresh(), $rita));
        $this->assertNull($this->workflow()->currentStage($order));
    }

    public function test_a_form_without_stages_has_nothing_to_approve(): void
    {
        $order = $this->stagedOrder(stages: []);

        $this->assertSame(OrderWorkflow::NO_STAGE, $this->workflow()->approve($order, $this->reviewer(['super_admin'])));
    }

    // ── Rejecting ────────────────────────────────────────────────────────────

    public function test_a_rejection_ends_the_request_and_keeps_the_reason(): void
    {
        Event::fake([ServiceOrderRejected::class]);
        $order = $this->stagedOrder();

        $result = $this->workflow()->reject($order, $this->reviewer(['reviewer'], 5), 'The photos are too blurry to read.');

        $this->assertSame(OrderWorkflow::OK, $result);
        $order->refresh();
        $this->assertSame(OrderStatus::Rejected, $order->status);
        $this->assertSame(OrderStage::REJECTED, $order->stages[0]->status);
        $this->assertSame(OrderStage::PENDING, $order->stages[1]->status, 'the later stages are never reached');
        $this->assertSame('The photos are too blurry to read.', $order->rejectionReason());
        $this->assertTrue($order->events->firstWhere('type', 'rejected')->is_public);
        $this->assertNull($this->workflow()->currentStage($order));
        Event::assertDispatchedTimes(ServiceOrderRejected::class, 1);
    }

    public function test_only_the_current_stages_role_may_reject(): void
    {
        $order = $this->stagedOrder();

        $this->assertSame(OrderWorkflow::NOT_ALLOWED, $this->workflow()->reject($order, $this->reviewer(['manager']), 'Nope'));
        $this->assertSame(OrderStatus::InReview, $order->refresh()->status);
    }

    public function test_a_later_stage_can_reject_too(): void
    {
        $order = $this->stagedOrder();
        $this->workflow()->approve($order, $this->reviewer(['reviewer']));

        $this->workflow()->reject($order->refresh(), $this->reviewer(['manager'], 6), 'Not eligible.');

        $order->refresh();
        $this->assertSame(OrderStatus::Rejected, $order->status);
        $this->assertSame([OrderStage::APPROVED, OrderStage::REJECTED], $order->stages->pluck('status')->all());
        $this->assertSame('Not eligible.', $order->rejectionReason());
    }

    public function test_a_rejected_order_cannot_be_approved_or_completed(): void
    {
        $order = $this->stagedOrder();
        $this->workflow()->reject($order, $this->reviewer(['reviewer']), 'No.');
        $order->refresh();

        $this->assertSame(OrderWorkflow::NO_STAGE, $this->workflow()->approve($order, $this->reviewer(['super_admin'])));
        $this->assertFalse($this->workflow()->canComplete($order));
        $this->assertFalse($this->workflow()->complete($order));
    }

    public function test_the_customer_is_told_of_a_rejection_with_the_reason_and_the_refund(): void
    {
        $order = $this->stagedOrder();
        $order->form->update(['settings' => ['min_seconds' => 0, 'payment' => ['service_id' => $order->service_id], 'approval' => ['stages' => $this->twoStages()], 'notifications' => ['email' => true, 'sms' => true]]]);

        $this->workflow()->reject($order, $this->reviewer(['reviewer']), 'The photos are too blurry to read.');

        Mail::assertSent(OrderRejectedMail::class, function (OrderRejectedMail $mail) use ($order) {
            $html = $mail->render();

            $this->assertTrue($mail->hasTo('ali@example.com'));
            $this->assertStringContainsString('The photos are too blurry to read.', $html);
            $this->assertStringContainsString($order->order_number, $html);
            $this->assertStringContainsString('OMR 10.500', $html, 'the price and its VAT come back');
            $this->assertStringNotContainsString('OMR 11.025', $html, 'not the fee');

            return true;
        });

        $sms = Http::recorded()->map(fn ($p) => $p[0]->body())->filter(fn ($b) => str_contains($b, '<Message>'))->last();
        $this->assertStringContainsString('could not be approved', $sms);
        $this->assertStringContainsString($order->order_number, $sms);
        $this->assertStringNotContainsString('blurry', $sms, 'the reason is for the email and the order page, not an SMS');
    }

    public function test_a_rejected_paid_order_is_due_a_refund_and_a_free_one_is_not(): void
    {
        $paid = $this->stagedOrder();
        $free = $this->stagedOrder(price: 0);

        $this->workflow()->reject($paid, $this->reviewer(['reviewer']), 'No.');
        $this->workflow()->reject($free, $this->reviewer(['reviewer']), 'No.');

        $this->assertTrue($paid->refresh()->refundDue());
        $this->assertSame(10.5, $paid->refundableAmount());
        $this->assertFalse($free->refresh()->refundDue());
    }

    // ── Completing ───────────────────────────────────────────────────────────

    public function test_pending_stages_block_completion_until_all_are_approved(): void
    {
        Event::fake([ServiceOrderCompleted::class]);
        $order = $this->stagedOrder();

        $this->assertSame([OrderWorkflow::BLOCKER_STAGES], $this->workflow()->completionBlockers($order));
        $this->assertFalse($this->workflow()->canComplete($order));
        $this->assertFalse($this->workflow()->complete($order));

        $this->workflow()->approve($order, $this->reviewer(['reviewer']));
        $this->assertFalse($this->workflow()->canComplete($order->refresh()), 'one stage left');

        $this->workflow()->approve($order->refresh(), $this->reviewer(['manager']));
        $this->assertTrue($this->workflow()->canComplete($order->refresh()));
        $this->assertTrue($this->workflow()->complete($order));
        Event::assertDispatchedTimes(ServiceOrderCompleted::class, 1);
    }

    public function test_a_required_document_blocks_completion_until_one_is_uploaded(): void
    {
        $order = $this->stagedOrder(stages: [], approval: ['requires_document' => true]);

        $this->assertSame([OrderWorkflow::BLOCKER_DOCUMENT], $this->workflow()->completionBlockers($order));
        $this->assertFalse($this->workflow()->complete($order));

        $order->documents()->create(['title' => 'Result', 'path' => 'order-documents/1/result.pdf']);

        $this->assertSame([], $this->workflow()->completionBlockers($order->refresh()));
        $this->assertTrue($this->workflow()->complete($order));
    }

    public function test_both_blockers_are_listed_together(): void
    {
        $order = $this->stagedOrder(approval: ['requires_document' => true]);

        $this->assertSame([OrderWorkflow::BLOCKER_STAGES, OrderWorkflow::BLOCKER_DOCUMENT], $this->workflow()->completionBlockers($order));
    }

    public function test_the_document_requirement_is_a_snapshot_not_the_forms_live_setting(): void
    {
        $this->makeFee('percentage', 5);
        $service = $this->makeService(10.0);
        $form = $this->makeForm($service, ['approval' => ['stages' => [], 'requires_document' => true]], slug: 'form-'.uniqid());

        $order = app(CreateServiceOrder::class)->handle($service, [
            'name' => 'Ali Al Balushi', 'email' => 'ali@example.com', 'phone' => '96891234567',
        ], $form->id);

        // Flipping the form's setting off after the order exists must not retroactively unblock it.
        $form->update(['settings' => array_replace_recursive($form->settings, ['approval' => ['requires_document' => false]])]);

        $this->assertTrue($order->refresh()->requires_document);
        $this->assertSame([OrderWorkflow::BLOCKER_DOCUMENT], $this->workflow()->completionBlockers($order));

        // Deleting the form entirely must not silently skip a requirement the order was created with.
        $form->delete();

        $this->assertSame([OrderWorkflow::BLOCKER_DOCUMENT], $this->workflow()->completionBlockers($order->refresh()->unsetRelation('form')));
    }

    public function test_a_paid_order_that_was_recovered_from_a_cancellation_still_enters_review(): void
    {
        $order = $this->stagedOrder(paid: false);
        $order->update(['status' => OrderStatus::Cancelled, 'payment_status' => PaymentStatus::Cancelled, 'cancellation_source' => 'system', 'cancelled_at' => now()]);

        app(OrderPaymentService::class)->applyPaid($order, PaymentGateway::Thawani, 'LATE');

        $order->refresh();
        $this->assertSame(OrderStatus::InReview, $order->status);
        $this->assertCount(2, $order->stages);
        $this->assertInstanceOf(ServiceOrder::class, $order);
    }
}
