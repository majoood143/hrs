<?php

namespace Tests\Feature;

use App\Filament\Resources\ServiceOrderResource\Pages\ViewServiceOrder;
use App\Models\ServiceOrder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

/** On an order's page, looking is not changing: refunds, completing, documents and resending take "update orders". */
class OrderAdminPermissionsTest extends TestCase
{
    use PreparesCustomerSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
        Mail::fake();
        $this->actingAs(new class(['name' => 'Support', 'email' => 'support@example.com']) extends Authenticatable
        {
            protected $guarded = [];

            public function getAuthIdentifier(): int
            {
                return 43;
            }
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Gate::define('ViewAny:ServiceOrder', fn () => true);
        Gate::define('View:ServiceOrder', fn () => true);
    }

    private function paidOrder(): ServiceOrder
    {
        $this->makeFee('percentage', 5);
        $form = $this->makeForm($this->makeService(10), ['notifications' => ['email' => true]]);
        $order = $this->paidOrderFor($this->makeCustomer());
        $order->update(['form_id' => $form->id]);

        return $order->refresh();
    }

    public function test_a_view_only_admin_sees_the_order_but_cannot_change_it(): void
    {
        $order = $this->paidOrder();
        $actions = ['refund', 'complete', 'uploadDocument', 'resend'];

        Gate::define('Update:ServiceOrder', fn () => false);
        $page = Livewire::test(ViewServiceOrder::class, ['record' => $order->getKey()])->assertOk();
        foreach ($actions as $action) {
            $page->assertActionHidden($action);
        }

        Gate::define('Update:ServiceOrder', fn () => true);
        $page = Livewire::test(ViewServiceOrder::class, ['record' => $order->getKey()]);
        foreach ($actions as $action) {
            $page->assertActionVisible($action);
        }
    }
}
