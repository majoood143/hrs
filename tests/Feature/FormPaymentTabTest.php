<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Support\FormOrderSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Models\Form;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

/**
 * The "Service & payment" tab, used through the real form editor page.
 */
class FormPaymentTabTest extends TestCase
{
    use MakesOrderForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareFormSite();

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function editor(Form $form)
    {
        return Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    }

    public function test_the_editor_has_the_tab_and_offers_the_services(): void
    {
        $service = $this->makeService(10);
        $inactive = Service::create(['name' => 'Retired service', 'price' => 5, 'is_active' => false]);
        $form = $this->makeForm(null);

        $this->editor($form)
            ->assertSuccessful()
            ->assertSee(__('admin_form_payment.tab'))
            ->assertSee(__('admin_form_payment.service.label'))
            ->assertSee('Horse passport')
            ->assertSee('OMR 10.000')
            ->assertSee(__('admin_form_payment.service.inactive'));
    }

    public function test_linking_a_service_saves_it_with_the_customer_fields_and_notifications(): void
    {
        $service = $this->makeService(10);
        $form = $this->makeForm(null);

        $this->editor($form)
            ->fillForm(['settings' => [
                'payment' => ['service_id' => $service->id],
                'customer' => ['name_field' => 'full_name', 'phone_field' => 'mobile', 'email_field' => 'email'],
                'notifications' => ['email' => true, 'sms' => true],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $form->refresh();
        $this->assertSame($service->id, (int) $form->setting('payment.service_id'));
        $this->assertSame('mobile', $form->setting('customer.phone_field'));
        $this->assertTrue($form->setting('notifications.sms'));
        $this->assertSame($service->id, FormOrderSettings::for($form)->serviceId());
    }

    public function test_a_linked_form_must_say_which_field_is_the_phone(): void
    {
        $form = $this->makeForm(null);

        $this->editor($form)
            ->fillForm(['settings' => ['payment' => ['service_id' => $this->makeService(10)->id]]])
            ->call('save')
            ->assertHasFormErrors(['settings.customer.phone_field' => 'required']);
    }

    public function test_an_email_field_is_required_while_email_notifications_are_on(): void
    {
        $form = $this->makeForm(null);
        $service = $this->makeService(10);

        $this->editor($form)
            ->fillForm(['settings' => [
                'payment' => ['service_id' => $service->id],
                'customer' => ['phone_field' => 'mobile'],
                'notifications' => ['email' => true],
            ]])
            ->call('save')
            ->assertHasFormErrors(['settings.customer.email_field' => 'required']);

        $this->editor($form)
            ->fillForm(['settings' => [
                'payment' => ['service_id' => $service->id],
                'customer' => ['phone_field' => 'mobile'],
                'notifications' => ['email' => false],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_an_ordinary_form_needs_none_of_it(): void
    {
        $form = $this->makeForm(null);

        $this->editor($form)->call('save')->assertHasNoFormErrors();

        $this->assertNull(FormOrderSettings::for($form->refresh())->serviceId());
    }

    public function test_unlinking_the_service_makes_it_an_ordinary_form_again(): void
    {
        $form = $this->makeForm($this->makeService(10), ['customer' => ['phone_field' => 'mobile'], 'notifications' => ['email' => false]]);

        $this->editor($form)
            ->fillForm(['settings' => ['payment' => ['service_id' => null]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(FormOrderSettings::for($form->refresh())->hasService());
    }
}
