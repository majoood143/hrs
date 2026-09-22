<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\PaymentGateways;
use App\Filament\Pages\Settings\SmsSettings;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\NotificationLogResource\Pages\ListNotificationLogs;
use App\Filament\Resources\ServiceOrderResource\Pages\ViewServiceOrder;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\SiteSetting;
use App\Services\Sms\SmsManager;
use App\Support\SecretSetting;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

/** The admin screens added with customers, SMS and receipts, used as an admin who may do everything. */
class CustomerAdminPagesTest extends TestCase
{
    use PreparesCustomerSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function siteSettingsTable(): void
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
    }

    // ── SMS settings ─────────────────────────────────────────────────────────

    public function test_saving_the_tamimah_settings_encrypts_the_password(): void
    {
        $this->siteSettingsTable();

        Livewire::test(SmsSettings::class)
            ->fillForm(['driver' => 'tamimah', 'tamimah' => [
                'username' => 'hrs-user', 'password' => 'p@ssw0rd', 'sender' => 'HRS', 'app_id' => 'APP1',
                'priority' => 1, 'success_codes' => '200,201', 'endpoint' => '',
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = DB::table('site_settings')->pluck('value', 'key');
        $this->assertSame('tamimah', $stored['sms.driver']);
        $this->assertSame('hrs-user', $stored['sms.tamimah.username']);
        $this->assertStringStartsWith('enc:', $stored['sms.tamimah.password']);
        $this->assertStringNotContainsString('p@ssw0rd', $stored->implode('|'));
        $this->assertSame('p@ssw0rd', SecretSetting::get('sms.tamimah.password'));
        $this->assertSame('200,201', $stored['sms.tamimah.success_codes']);

        Livewire::test(SmsSettings::class)
            ->assertFormSet(['driver' => 'tamimah', 'tamimah.username' => 'hrs-user', 'tamimah.password' => 'p@ssw0rd', 'tamimah.sender' => 'HRS']);
    }

    public function test_tamimah_needs_its_credentials(): void
    {
        $this->siteSettingsTable();

        Livewire::test(SmsSettings::class)
            ->fillForm(['driver' => 'tamimah', 'tamimah' => ['username' => '', 'password' => '', 'sender' => '']])
            ->call('save')
            ->assertHasFormErrors(['tamimah.username' => 'required', 'tamimah.password' => 'required', 'tamimah.sender' => 'required']);
    }

    public function test_switching_to_the_demo_driver_saves_it_and_warns(): void
    {
        $this->siteSettingsTable();

        Livewire::test(SmsSettings::class)
            ->fillForm(['driver' => 'demo'])
            ->assertSee(__('admin_sms_settings.demo_warning'))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('demo', DB::table('site_settings')->where('key', 'sms.driver')->value('value'));
        $this->assertSame('demo', app(SmsManager::class)->driver());
    }

    public function test_the_log_driver_is_not_offered_on_a_live_site(): void
    {
        $this->siteSettingsTable();

        $offered = fn () => (fn () => $this->drivers())->call(Livewire::test(SmsSettings::class)->instance());

        $this->assertArrayHasKey('log', $offered());

        app()->detectEnvironment(fn () => 'production');
        $this->assertArrayNotHasKey('log', $offered());
        $this->assertArrayHasKey('tamimah', $offered());
    }

    public function test_the_test_message_action_sends_and_reports(): void
    {
        $this->useTamimah();
        $this->tamimahAnswers();

        Livewire::test(SmsSettings::class)
            ->callAction('test', ['phone' => '9123 4567'])
            ->assertNotified(__('admin_sms_settings.notifications.test_sent'));

        Http::assertSent(fn ($r) => str_contains($r->body(), '<MSISDNs>96891234567</MSISDNs>') && str_contains($r->body(), 'SMS is working'));
        $this->assertSame('test', NotificationLog::firstOrFail()->type);
    }

    public function test_a_failed_test_message_shows_why(): void
    {
        $this->useTamimah();
        $this->tamimahAnswers(processed: 0, description: 'Invalid sender');

        Livewire::test(SmsSettings::class)
            ->callAction('test', ['phone' => '9123 4567'])
            ->assertNotified(__('admin_sms_settings.notifications.test_failed'));

        $this->assertStringContainsString('Invalid sender', NotificationLog::firstOrFail()->error);
    }

    public function test_a_bad_phone_is_not_sent_anything(): void
    {
        $this->useTamimah();
        Http::fake();

        // digits, but not a phone number
        Livewire::test(SmsSettings::class)
            ->callAction('test', ['phone' => '12345'])
            ->assertNotified(__('admin_sms_settings.notifications.bad_phone'));

        // not even digits: the field itself refuses it
        Livewire::test(SmsSettings::class)
            ->callAction('test', ['phone' => 'abc'])
            ->assertHasActionErrors(['phone']);

        Http::assertNothingSent();
        $this->assertSame(0, NotificationLog::count());
    }

    // ── VAT registration number ──────────────────────────────────────────────

    public function test_the_vat_registration_number_is_saved_with_the_payment_settings(): void
    {
        $this->siteSettingsTable();

        Livewire::test(PaymentGateways::class)
            ->fillForm(['enabled_gateways' => ['demo'], 'vat' => ['enabled' => true, 'rate' => 5, 'registration_number' => '  OM1100123456 ']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('OM1100123456', DB::table('site_settings')->where('key', 'vat.registration_number')->value('value'));
    }

    // ── Customers, notification log, receipts ────────────────────────────────

    public function test_the_customer_list_shows_phone_orders_and_last_sign_in(): void
    {
        $ali = $this->makeCustomer('96891234567', ['last_login_at' => now()]);
        $this->makeCustomer('96899999999', ['name' => 'Second']);
        $this->paidOrderFor($ali);
        $this->paidOrderFor($ali);

        Livewire::test(ListCustomers::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Customer::all())
            ->assertSee('+96891234567')
            ->assertSee('Ali Al Balushi')
            ->assertTableColumnStateSet('orders_count', 2, $ali);
    }

    public function test_customers_cannot_be_created_edited_or_deleted_here(): void
    {
        $customer = $this->makeCustomer();

        $this->assertFalse(CustomerResource::canCreate());
        $this->assertFalse(CustomerResource::canEdit($customer));
        $this->assertFalse(CustomerResource::canDelete($customer));
    }

    public function test_the_notification_log_lists_messages_with_their_errors_and_filters(): void
    {
        $sent = NotificationLog::create(['channel' => 'sms', 'type' => 'order_received', 'recipient' => '96891234567', 'status' => 'sent']);
        $failed = NotificationLog::create(['channel' => 'sms', 'type' => 'otp', 'recipient' => '96899999999', 'status' => 'failed', 'error' => 'Insufficient balance']);

        Livewire::test(ListNotificationLogs::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$sent, $failed])
            ->assertSee('Insufficient balance')
            ->filterTable('status', 'failed')
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$sent]);
    }

    public function test_an_order_page_offers_the_receipt_only_when_paid_and_downloads_it(): void
    {
        $customer = $this->makeCustomer();
        $paid = $this->paidOrderFor($customer);
        $unpaid = $this->makeOrder(10, 5.0, ['phone' => $customer->phone]);

        Livewire::test(ViewServiceOrder::class, ['record' => $unpaid->getKey()])->assertActionHidden('receipt');

        Livewire::test(ViewServiceOrder::class, ['record' => $paid->getKey()])
            ->assertActionVisible('receipt')
            ->callAction('receipt')
            ->assertFileDownloaded('receipt-'.$paid->receipt_number.'.pdf');
    }

    public function test_an_order_page_says_whether_the_customer_has_signed_in(): void
    {
        $customer = $this->makeCustomer();
        $linked = $this->paidOrderFor($customer);
        $guest = $this->makeOrder(10, null, ['phone' => '96877777777']);

        Livewire::test(ViewServiceOrder::class, ['record' => $linked->getKey()])->assertSee(__('admin_service_order.fields.linked'));
        Livewire::test(ViewServiceOrder::class, ['record' => $guest->getKey()])->assertSee(__('admin_service_order.fields.guest'));
    }
}
