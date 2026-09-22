<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\ServiceOrderResource;
use App\Filament\Resources\ServiceOrderResource\Pages\ListServiceOrders;
use App\Filament\Resources\ServiceOrderResource\Pages\ViewServiceOrder;
use App\Jobs\SendStageReviewNotification;
use App\Mail\StageReviewMail;
use App\Models\NotificationLog;
use App\Models\OrderStage;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Orders\OrderWorkflow;
use App\Support\FormOrderSettings;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Spatie\Permission\Models\Role;
use Tests\Concerns\MakesReviewOrders;
use Tests\TestCase;

/** Review, rejection, documents and refunds as an admin on an order's page, and the reviewers' email. */
class OrderReviewAdminTest extends TestCase
{
    use MakesReviewOrders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareReviewSite();
        Storage::fake('local');

        // the order page shows who decided a stage, which reads the users table
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();

        Gate::before(fn () => true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function page(ServiceOrder $order)
    {
        return Livewire::test(ViewServiceOrder::class, ['record' => $order->getKey()]);
    }

    private function as(array $roles, int $id = 1): void
    {
        $this->actingAs($this->reviewer($roles, $id));
    }

    /** The real permission tables (portable), for what needs Spatie itself. */
    private function realUsersAndRoles(): void
    {
        (require database_path('migrations/2025_08_24_201524_create_permission_tables.php'))->up();
    }

    // ── Approving and rejecting from the page ────────────────────────────────

    public function test_the_current_stages_role_sees_approve_and_reject_and_others_do_not(): void
    {
        $order = $this->stagedOrder();

        $this->as(['reviewer']);
        $this->page($order)->assertActionVisible('approve')->assertActionVisible('reject')->assertActionHidden('complete');

        $this->as(['manager']);
        $this->page($order)->assertActionHidden('approve')->assertActionHidden('reject');

        $this->as([]);
        $this->page($order)->assertActionHidden('approve')->assertActionHidden('reject');
    }

    public function test_the_button_names_the_stage_it_decides(): void
    {
        $order = $this->stagedOrder();
        $this->as(['reviewer']);

        $this->page($order)->assertSee('Approve (Technical check)');
    }

    public function test_approving_from_the_page_moves_the_order_to_the_next_stage(): void
    {
        $order = $this->stagedOrder();
        $this->as(['reviewer'], 5);

        $this->page($order)
            ->callAction('approve', ['comment' => 'Looks fine'])
            ->assertNotified(__('admin_service_order.notifications.approved'))
            ->assertActionHidden('approve');

        $stage = $order->stages()->first();
        $this->assertSame(OrderStage::APPROVED, $stage->status);
        $this->assertSame('Looks fine', $stage->comment);
        $this->assertSame(5, $stage->decided_by);

        $this->as(['manager'], 6);
        $this->page($order->refresh())->assertActionVisible('approve')->assertSee('Approve (Final approval)');
    }

    public function test_the_last_approval_makes_the_order_completable(): void
    {
        $order = $this->stagedOrder(stages: [['name' => ['en' => 'Only step'], 'role' => 'reviewer']]);
        $this->as(['reviewer']);

        $this->page($order)->assertActionHidden('complete')->assertSee(__('admin_service_order.blockers.pending_stages'));
        $this->page($order)->callAction('approve');

        $this->page($order->refresh())
            ->assertActionVisible('complete')
            ->assertSee(OrderStatus::Processing->label())
            ->assertDontSee(__('admin_service_order.blockers.pending_stages'));
    }

    public function test_rejecting_needs_a_reason_and_ends_the_request(): void
    {
        $order = $this->stagedOrder();
        $this->as(['reviewer']);

        $this->page($order)->callAction('reject', ['reason' => ''])->assertHasActionErrors(['reason' => 'required']);
        $this->assertSame(OrderStatus::InReview, $order->refresh()->status);

        $this->page($order)
            ->callAction('reject', ['reason' => 'Documents are missing.'])
            ->assertNotified(__('admin_service_order.notifications.rejected'));

        $order->refresh();
        $this->assertSame(OrderStatus::Rejected, $order->status);
        $this->assertSame('Documents are missing.', $order->rejectionReason());
    }

    public function test_a_rejected_paid_order_shows_the_refund_due_and_can_be_refunded_from_the_page(): void
    {
        $order = $this->stagedOrder();
        app(OrderWorkflow::class)->reject($order, $this->reviewer(['reviewer']), 'No.');
        $order->refresh();
        $this->as(['reviewer']);

        $this->page($order)
            ->assertSee(__('admin_service_order.fields.refund_due', ['amount' => 'OMR 10.500']))
            ->assertActionVisible('refund')
            ->callAction('refund', ['amount' => 10.5, 'method' => 'gateway', 'reference' => 'R-77', 'reason' => 'Rejected request'])
            ->assertNotified(__('admin_service_order.notifications.refunded'))
            ->assertActionHidden('refund')
            ->assertDontSee(__('admin_service_order.fields.refund_due', ['amount' => 'OMR 0.000']));

        $order->refresh();
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $this->assertSame('R-77', $order->refunds[0]->reference);
    }

    // ── Refunds ──────────────────────────────────────────────────────────────

    public function test_the_refund_form_offers_the_refundable_amount_and_no_more(): void
    {
        $order = $this->stagedOrder(stages: []);
        $this->as(['super_admin']);

        $this->page($order)
            ->mountAction('refund')
            ->assertSchemaStateSet(['amount' => 10.5, 'method' => 'gateway']);

        $this->page($order)->callAction('refund', ['amount' => 10.6, 'method' => 'manual'])->assertHasActionErrors(['amount']);
        $this->assertSame('0.000', $order->refresh()->refunded_amount);
    }

    public function test_a_partial_refund_leaves_the_action_available_and_lists_the_refunds(): void
    {
        $order = $this->stagedOrder(stages: []);
        $this->as(['super_admin']);

        $this->page($order)->callAction('refund', ['amount' => 4, 'method' => 'manual', 'reference' => 'BANK-1', 'reason' => 'Goodwill']);

        $this->page($order->refresh())
            ->assertActionVisible('refund')
            ->assertSee(__('admin_service_order.sections.refunds'))
            ->assertSee('OMR 4.000')
            ->assertSee('BANK-1')
            ->assertSee('Goodwill');
    }

    public function test_an_unpaid_order_has_no_refund_action(): void
    {
        $order = $this->stagedOrder(stages: [], paid: false);
        $this->as(['super_admin']);

        $this->page($order)->assertActionHidden('refund');
    }

    public function test_the_order_list_can_be_filtered_to_refunds_due(): void
    {
        $rejected = $this->stagedOrder();
        app(OrderWorkflow::class)->reject($rejected, $this->reviewer(['reviewer']), 'No.');
        $fine = $this->stagedOrder(stages: []);
        $this->as(['super_admin']);

        Livewire::test(ListServiceOrders::class)
            ->filterTable('needs_refund')
            ->assertCanSeeTableRecords([$rejected])
            ->assertCanNotSeeTableRecords([$fine]);
    }

    // ── Documents ────────────────────────────────────────────────────────────

    public function test_a_document_is_uploaded_from_the_page_and_shown_with_a_link(): void
    {
        $order = $this->stagedOrder(stages: []);
        $this->as(['super_admin'], 3);

        $this->page($order)
            ->callAction('uploadDocument', ['title' => 'Passport copy', 'file' => UploadedFile::fake()->create('scan.pdf', 50, 'application/pdf')])
            ->assertNotified(__('admin_service_order.notifications.document_added'));

        $document = $order->documents()->firstOrFail();
        $this->assertSame('Passport copy', $document->title);
        $this->assertSame(3, $document->uploaded_by);
        $this->assertStringStartsWith('order-documents/'.$order->id.'/', $document->path);
        Storage::disk('local')->assertExists($document->path);

        $this->page($order->refresh())->assertSee(__('admin_service_order.sections.documents'))->assertSee('Passport copy')->assertSee('/order-documents/'.$document->id, false);
    }

    public function test_a_document_needs_a_title_and_a_file(): void
    {
        $order = $this->stagedOrder(stages: []);
        $this->as(['super_admin']);

        $this->page($order)->callAction('uploadDocument', ['title' => '', 'file' => null])->assertHasActionErrors(['title' => 'required', 'file' => 'required']);
        $this->assertSame(0, $order->documents()->count());
    }

    public function test_a_rejected_order_takes_no_more_documents(): void
    {
        $order = $this->stagedOrder();
        app(OrderWorkflow::class)->reject($order, $this->reviewer(['reviewer']), 'No.');
        $this->as(['super_admin']);

        $this->page($order->refresh())->assertActionHidden('uploadDocument');
    }

    public function test_a_required_document_is_explained_and_unlocks_completion(): void
    {
        $order = $this->stagedOrder(stages: [], approval: ['requires_document' => true]);
        $this->as(['super_admin']);

        $this->page($order)->assertActionHidden('complete')->assertSee(__('admin_service_order.blockers.needs_document'));

        $this->page($order)->callAction('uploadDocument', ['title' => 'Result', 'file' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf')]);

        $this->page($order->refresh())->assertActionVisible('complete')->assertDontSee(__('admin_service_order.blockers.needs_document'));
    }

    public function test_a_document_can_be_replaced_from_the_page(): void
    {
        $order = $this->stagedOrder(stages: []);
        $this->as(['super_admin']);
        $this->page($order)->callAction('uploadDocument', ['title' => 'Result', 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $document = $order->documents()->firstOrFail();
        $oldPath = $document->path;

        $this->page($order->refresh())
            ->assertActionVisible('replaceDocument')
            ->callAction('replaceDocument', ['document_id' => $document->id, 'title' => 'Result v2', 'file' => UploadedFile::fake()->create('b.pdf', 20, 'application/pdf')])
            ->assertNotified(__('admin_service_order.notifications.document_replaced'));

        $document->refresh();
        $this->assertSame('Result v2', $document->title);
        $this->assertNotSame($oldPath, $document->path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($document->path);
        $this->assertSame(1, $order->documents()->count());
    }

    public function test_a_document_can_be_deleted_from_the_page_and_the_order_needs_one_again_if_required(): void
    {
        $order = $this->stagedOrder(stages: [], approval: ['requires_document' => true]);
        $this->as(['super_admin']);
        $this->page($order)->callAction('uploadDocument', ['title' => 'Result', 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $document = $order->documents()->firstOrFail();

        $this->page($order->refresh())->assertActionVisible('complete');

        $this->page($order)
            ->callAction('deleteDocument', ['document_id' => $document->id])
            ->assertNotified(__('admin_service_order.notifications.document_deleted'))
            ->assertActionHidden('deleteDocument')
            ->assertActionHidden('replaceDocument')
            ->assertActionHidden('complete');

        Storage::disk('local')->assertMissing($document->path);
    }

    public function test_another_orders_document_cannot_be_replaced_or_deleted_from_this_page(): void
    {
        $mine = $this->stagedOrder(stages: []);
        $other = $this->stagedOrder(stages: []);
        $this->as(['super_admin']);
        $this->page($other)->callAction('uploadDocument', ['title' => 'Theirs', 'file' => UploadedFile::fake()->create('t.pdf', 10, 'application/pdf')]);
        $this->page($mine)->callAction('uploadDocument', ['title' => 'Mine', 'file' => UploadedFile::fake()->create('m.pdf', 10, 'application/pdf')]);
        $theirs = $other->documents()->firstOrFail();

        $this->page($mine->refresh())->callAction('deleteDocument', ['document_id' => $theirs->id])->assertHasActionErrors(['document_id']);

        $this->assertTrue($theirs->fresh()->exists());
    }

    // ── The stages on the page ───────────────────────────────────────────────

    public function test_the_page_lists_every_stage_with_its_status_and_who_decided(): void
    {
        $this->realUsersAndRoles();
        $rita = User::create(['name' => 'Rita Reviewer', 'email' => 'rita@example.com', 'password' => 'secret']);
        $order = $this->stagedOrder();
        app(OrderWorkflow::class)->approve($order, $this->reviewer(['reviewer'], $rita->id), 'Checked twice');
        $this->as(['super_admin']);

        $this->page($order->refresh())
            ->assertSee(__('admin_service_order.sections.review'))
            ->assertSee('Technical check')
            ->assertSee('Final approval')
            ->assertSee(__('admin_service_order.stage_statuses.approved'))
            ->assertSee(__('admin_service_order.stage_statuses.pending'))
            ->assertSee('Rita Reviewer')
            ->assertSee('Checked twice');
    }

    public function test_an_order_without_stages_has_no_review_section(): void
    {
        $order = $this->stagedOrder(stages: []);
        $this->as(['super_admin']);

        $this->page($order)->assertDontSee(__('admin_service_order.sections.review'));
    }

    // ── Telling the reviewers ────────────────────────────────────────────────

    public function test_the_holders_of_the_first_stages_role_are_emailed_when_an_order_enters_review(): void
    {
        $this->realUsersAndRoles();
        Role::create(['name' => 'reviewer', 'guard_name' => 'web']);
        Role::create(['name' => 'manager', 'guard_name' => 'web']);
        $rita = User::create(['name' => 'Rita', 'email' => 'rita@example.com', 'password' => 'x']);
        $mo = User::create(['name' => 'Mo', 'email' => 'mo@example.com', 'password' => 'x']);
        $rita->assignRole('reviewer');
        $mo->assignRole('manager');

        $order = $this->stagedOrder();

        Mail::assertSent(StageReviewMail::class, 1);
        Mail::assertSent(StageReviewMail::class, function (StageReviewMail $mail) use ($order) {
            $html = $mail->render();

            $this->assertTrue($mail->hasTo('rita@example.com'));
            $this->assertStringContainsString($order->order_number, $mail->envelope()->subject);
            $this->assertStringContainsString('Technical check', $html);
            $this->assertStringContainsString('Ali Al Balushi', $html);
            $this->assertStringContainsString(ServiceOrderResource::getUrl('view', ['record' => $order->id]), $html);

            return true;
        });
        $this->assertSame('sent', NotificationLog::where('type', 'stage_review')->sole()->status);

        // approving the first stage calls in the next role, and nobody is emailed twice
        app(OrderWorkflow::class)->approve($order, $this->reviewer(['reviewer']));
        Mail::assertSent(StageReviewMail::class, fn (StageReviewMail $mail) => $mail->hasTo('mo@example.com'));
        SendStageReviewNotification::dispatch($order->id, 2);
        Mail::assertSent(StageReviewMail::class, 2);
    }

    public function test_a_stage_nobody_holds_is_simply_not_emailed_and_does_not_break_the_order(): void
    {
        $this->realUsersAndRoles();
        Role::create(['name' => 'reviewer', 'guard_name' => 'web']);

        $order = $this->stagedOrder();

        Mail::assertNotSent(StageReviewMail::class);
        $this->assertSame(OrderStatus::InReview, $order->status);
    }

    // ── The form's review tab ────────────────────────────────────────────────

    public function test_stages_and_the_document_rule_are_set_on_the_form(): void
    {
        $this->realUsersAndRoles();
        Role::create(['name' => 'reviewer', 'guard_name' => 'web']);
        Role::create(['name' => 'manager', 'guard_name' => 'web']);
        $service = $this->makeService(10);
        $form = $this->makeForm($service, ['customer' => ['phone_field' => 'mobile'], 'notifications' => ['email' => false]]);
        $this->actingAs($this->reviewer(['super_admin']));

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->fillForm(['settings' => [
                'payment' => ['service_id' => $service->id],
                'customer' => ['phone_field' => 'mobile'],
                'notifications' => ['email' => false],
                'approval' => [
                    'stages' => [
                        ['name' => ['en' => 'Technical check', 'ar' => 'فحص فني'], 'role' => 'reviewer'],
                        ['name' => ['en' => 'Final approval', 'ar' => 'الموافقة النهائية'], 'role' => 'manager'],
                    ],
                    'requires_document' => true,
                ],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = FormOrderSettings::for($form->refresh());
        $this->assertSame(['reviewer', 'manager'], array_column($settings->stages(), 'role'));
        $this->assertSame('فحص فني', $settings->stages()[0]['name']['ar']);
        $this->assertTrue($settings->requiresDocument());
    }

    public function test_a_stage_needs_a_name_and_a_role(): void
    {
        $this->realUsersAndRoles();
        $service = $this->makeService(10);
        $form = $this->makeForm($service, ['customer' => ['phone_field' => 'mobile'], 'notifications' => ['email' => false]]);
        $this->actingAs($this->reviewer(['super_admin']));

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->fillForm(['settings' => [
                'payment' => ['service_id' => $service->id],
                'customer' => ['phone_field' => 'mobile'],
                'notifications' => ['email' => false],
                'approval' => ['stages' => [['name' => ['en' => ''], 'role' => null]]],
            ]])
            ->call('save')
            ->assertHasFormErrors();
    }
}
