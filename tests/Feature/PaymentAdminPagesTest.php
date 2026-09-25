<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Pages\Settings\PaymentGateways;
use App\Filament\Resources\PaymentGatewayLogResource\Pages\ViewPaymentGatewayLog;
use App\Filament\Resources\ServiceFeeSettingResource\Pages\CreateServiceFeeSetting;
use App\Filament\Resources\ServiceFeeSettingResource\Pages\EditServiceFeeSetting;
use App\Filament\Resources\ServiceFeeSettingResource\Pages\ListServiceFeeSettings;
use App\Filament\Resources\ServiceOrderResource;
use App\Filament\Resources\ServiceOrderResource\Pages\ListServiceOrders;
use App\Filament\Resources\ServiceOrderResource\Pages\ViewServiceOrder;
use App\Filament\Resources\ServiceResource\Pages\CreateService;
use App\Models\PaymentGatewayLog;
use App\Models\Service;
use App\Models\ServiceFeeSetting;
use App\Models\SiteSetting;
use App\Support\SecretSetting;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Packstub\FormBuilder\Models\Form;
use Tests\Concerns\PreparesOrderSite;
use Tests\TestCase;

/**
 * The admin screens for orders, fees, the gateway log and the payment settings, rendered through
 * Livewire as an admin who may do everything (permissions themselves are Shield's job).
 *
 * The gateway log's list page is not rendered here: its tabs and filters use MySQL JSON functions.
 */
class PaymentAdminPagesTest extends TestCase
{
    use PreparesOrderSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareOrderSite();

        (require base_path('packages/packstub/filament-form-builder/database/migrations/create_form_builder_tables.php.stub'))->up();

        Gate::before(fn () => true);
        // a plain user: the real one asks the (MySQL-only) permissions tables about every ability
        $this->actingAs(new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_orders_list_shows_orders_with_their_status_and_amounts(): void
    {
        $order = $this->makeOrder();

        Livewire::test(ListServiceOrders::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$order])
            ->assertSee($order->order_number)
            ->assertSee('OMR 11.025')
            ->assertSee(OrderStatus::PendingPayment->label())
            ->assertSee('Horse passport')
            ->filterTable('payment_status', [PaymentStatus::Paid->value])
            ->assertCanNotSeeTableRecords([$order]);
    }

    public function test_the_orders_list_finds_a_paid_but_cancelled_order_needing_a_refund(): void
    {
        $normal = $this->makeOrder();
        $stuck = $this->makeOrder();
        $stuck->update(['payment_status' => PaymentStatus::Paid, 'status' => OrderStatus::Cancelled]);

        Livewire::test(ListServiceOrders::class)
            ->filterTable('needs_refund')
            ->assertCanSeeTableRecords([$stuck])
            ->assertCanNotSeeTableRecords([$normal]);
    }

    public function test_an_order_page_shows_who_gets_what(): void
    {
        $order = $this->makeOrder();
        $order->update(['payment_status' => PaymentStatus::Paid, 'receipt_number' => 'RC-2026-000001']);

        Livewire::test(ViewServiceOrder::class, ['record' => $order->getKey()])
            ->assertSuccessful()
            ->assertSee('Ali Al Balushi')
            ->assertSee('OMR 10.000')
            ->assertSee('OMR 0.500')
            ->assertSee('OMR 0.025')
            ->assertSee('OMR 11.025')
            ->assertSee('OMR 10.500')  // the client's share
            ->assertSee('OMR 0.525')   // ours
            ->assertSee('RC-2026-000001')
            ->assertSee(__('orders.events.created'));
    }

    public function test_orders_cannot_be_created_or_deleted_by_hand(): void
    {
        $order = $this->makeOrder();

        $this->assertFalse(ServiceOrderResource::canCreate());
        $this->assertFalse(ServiceOrderResource::canDelete($order));
        $this->assertFalse(ServiceOrderResource::canEdit($order));
    }

    public function test_a_gateway_log_page_shows_the_exchange(): void
    {
        $order = $this->makeOrder();
        $log = PaymentGatewayLog::log($order, 'thawani', 'get_session', ['session_id' => 'sess_1'], ['data' => ['payment_status' => 'paid']], 200);

        Livewire::test(ViewPaymentGatewayLog::class, ['record' => $log->getKey()])
            ->assertSuccessful()
            ->assertSee($order->order_number)
            ->assertSee('sess_1')
            ->assertSee(__('admin_payment_gateway_log.outcomes.success'));
    }

    public function test_a_fee_is_created_in_both_languages_and_edited_back(): void
    {
        $service = $this->makeService();
        $form = Form::create(['name' => ['en' => 'Passport form', 'ar' => 'نموذج الجواز'], 'slug' => 'passport']);

        Livewire::test(CreateServiceFeeSetting::class)
            ->fillForm([
                'name' => ['en' => 'Platform fee', 'ar' => 'رسوم المنصة'],
                'fee_type' => 'percentage',
                'fee_value' => '2.5',
                'service_id' => $service->id,
                'form_id' => $form->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $fee = ServiceFeeSetting::firstOrFail();
        $this->assertSame(['en' => 'Platform fee', 'ar' => 'رسوم المنصة'], $fee->getTranslations('name'));
        $this->assertSame($service->id, $fee->service_id);
        $this->assertSame($form->id, $fee->form_id);
        $this->assertSame(__('admin_service_fee_setting.scope.form', ['id' => $form->id]), $fee->scope_name);
        $this->assertSame('2.5%', $fee->formatted_value);

        Livewire::test(EditServiceFeeSetting::class, ['record' => $fee->getKey()])
            ->assertFormSet(['name.en' => 'Platform fee', 'name.ar' => 'رسوم المنصة', 'fee_value' => '2.500'])
            ->fillForm(['fee_value' => '3'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('3.000', $fee->refresh()->fee_value);
    }

    public function test_a_fee_needs_a_name_and_a_value(): void
    {
        Livewire::test(CreateServiceFeeSetting::class)
            ->fillForm(['name' => ['en' => ''], 'fee_value' => ''])
            ->call('create')
            ->assertHasFormErrors(['name.en' => 'required', 'fee_value' => 'required']);
    }

    public function test_the_fee_list_shows_where_each_rule_applies(): void
    {
        $service = $this->makeService(10, 'Vaccination');
        $global = $this->makeFee('percentage', 5);
        $forService = $this->makeFee('fixed', 0.5, ['service_id' => $service->id]);

        Livewire::test(ListServiceFeeSettings::class)
            ->assertCanSeeTableRecords([$global, $forService])
            ->assertSee(__('admin_service_fee_setting.scope.global'))
            ->assertSee('Vaccination')
            ->assertSee('5%')
            ->assertSee('OMR 0.500');
    }

    public function test_the_fee_list_shows_the_uploaded_currency_icon(): void
    {
        $this->seedSiteSettings(['currency_icon' => 'branding/rial.svg']);
        $fee = $this->makeFee('fixed', 0.5);

        Livewire::test(ListServiceFeeSettings::class)
            ->assertCanSeeTableRecords([$fee])
            ->assertSeeHtml('branding/rial.svg')
            ->assertSee('0.500')
            ->assertDontSee('0.500 OMR');
    }

    public function test_a_service_is_saved_with_an_arabic_name_and_a_three_decimal_price(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm(['name' => 'Passport', 'ar_name' => 'جواز', 'price' => '12.345', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::firstOrFail();
        $this->assertSame('12.345', $service->price);
        $this->assertSame('جواز', $service->ar_name);

        Livewire::test(CreateService::class)
            ->fillForm(['name' => 'Bad', 'price' => '-1'])
            ->call('create')
            ->assertHasFormErrors(['price']);
    }

    public function test_the_service_name_follows_the_language(): void
    {
        $service = $this->makeService();

        app()->setLocale('en');
        $this->assertSame('Horse passport', $service->localizedName());

        app()->setLocale('ar');
        $this->assertSame('جواز حصان', $service->localizedName());

        $service->update(['ar_name' => null]);
        $this->assertSame('Horse passport', $service->refresh()->localizedName(), 'falls back to English');
    }

    public function test_saving_the_payment_settings_encrypts_the_credentials_and_keeps_the_vat(): void
    {
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

        Livewire::test(PaymentGateways::class)
            ->fillForm([
                'enabled_gateways' => ['thawani', 'demo'],
                'vat' => ['enabled' => true, 'rate' => 5],
                'thawani' => ['secret_key' => 'sk_live_abc', 'publishable_key' => 'pk_live_abc', 'test_mode' => false, 'webhook_secret' => 'whsec_abc', 'base_url' => ''],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = DB::table('site_settings')->pluck('value', 'key');
        $this->assertStringStartsWith('enc:', $stored['thawani.secret_key']);
        $this->assertStringStartsWith('enc:', $stored['thawani.webhook_secret']);
        $this->assertStringNotContainsString('sk_live_abc', $stored->implode('|'));
        $this->assertStringNotContainsString('whsec_abc', $stored->implode('|'));
        $this->assertSame('pk_live_abc', $stored['thawani.publishable_key'], 'the publishable key is public by design');
        $this->assertSame('sk_live_abc', SecretSetting::get('thawani.secret_key'));
        $this->assertSame(['thawani', 'demo'], json_decode($stored['enabled_gateways']));
        $this->assertSame('5', (string) $stored['vat.rate']);
        $this->assertSame('true', $stored['vat.enabled']);
        $this->assertSame('false', $stored['thawani.test_mode']);

        // opening the page again shows what was saved, decrypted for the admin
        Livewire::test(PaymentGateways::class)
            ->assertFormSet(['thawani.secret_key' => 'sk_live_abc', 'enabled_gateways' => ['thawani', 'demo'], 'vat.rate' => '5']);
    }

    public function test_old_installs_with_free_and_cash_ticked_open_cleanly(): void
    {
        $this->seedSiteSettings(['enabled_gateways' => ['free', 'cash', 'nbo']]);

        Livewire::test(PaymentGateways::class)->assertFormSet(['enabled_gateways' => ['nbo']]);
    }
}
