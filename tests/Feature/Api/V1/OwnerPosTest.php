<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\StableBookingStatus;
use App\Events\ServiceOrderRefundDue;
use App\Filament\Stable\Pages\BookingSettings;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\PosPayment;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StableOffering;
use App\Models\StablePackage;
use App\Models\StablePackagePurchase;
use App\Models\User;
use App\Services\Reports\StableLedgerRow;
use App\Services\Reports\StableStatement;
use App\Services\Stables\SlotGenerator;
use App\Support\StablePos;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * The stable desk (POS) in the app's My stable: an owner sells a session or a lesson package to
 * someone at the desk (cash, card, a split, or a payment link), refunds it, and reads the desk's
 * report. Commission only: no service fee on desk sales. "Now" is Sunday 2026-10-04 09:30 in Muscat.
 */
class OwnerPosTest extends TestCase
{
    use PreparesStableBookings;

    private User $owner;

    private Stable $stable;

    private StableOffering $offering;

    private BookingSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', 'module_pos_enabled' => true]);
        (require database_path('migrations/2026_09_11_145142_create_personal_access_tokens_table.php'))->up();
        (require database_path('migrations/2026_10_13_120000_create_mobile_announcements_table.php'))->up();
        (require database_path('migrations/2026_11_30_000001_add_stable_pos.php'))->up();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->owner = $this->makeOwner();
        $this->stable = $this->makeStable($this->owner, ['slug' => 'desert-riders']);
        $this->stable->forceFill(['pos_enabled' => true])->save();
        $this->offering = $this->makeOffering($this->stable, ['max_riders' => 3]);
        $this->makeFee('percentage', 5.0); // the website's fee: never on a desk sale
        app(SlotGenerator::class)->sync($this->makeSchedule($this->offering, [2], ['10:00']));
        $this->slot = BookingSlot::query()->where('date', '2026-10-06')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function setting(string $key, mixed $value, string $type = 'boolean'): void
    {
        $all = Cache::get('site_settings.all');
        $all[$key] = (object) ['key' => $key, 'type' => $type, 'value' => is_bool($value) ? ($value ? 'true' : 'false') : $value];
        Cache::forever('site_settings.all', $all);
        SiteSetting::resetMemo();
    }

    private function token(): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/owner/auth/login', ['email' => 'owner@example.com', 'password' => 'secret-password'])->assertOk()->json('token');
    }

    private function sell(string $token, array $body): TestResponse
    {
        return $this->withToken($token)->postJson('/api/v1/owner/stables/desert-riders/pos/sales', $body + [
            'slot_id' => $this->slot->id,
            'riders' => ['Aisha', 'Omar'],
            'name' => 'Khalid Al Balushi',
            'phone' => '92345678',
        ]);
    }

    public function test_the_desk_follows_the_module_and_the_stables_own_switch(): void
    {
        $token = $this->token();
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders')->assertJsonPath('stable.pos', true);
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/pos')->assertOk()
            ->assertJsonPath('methods.0.value', 'cash')
            ->assertJsonCount(5, 'methods');

        $this->stable->forceFill(['pos_enabled' => false])->save();
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/pos')->assertForbidden()->assertJsonPath('code', 'pos_closed');
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders')->assertJsonPath('stable.pos', false);

        $this->stable->forceFill(['pos_enabled' => true])->save();
        $this->setting('module_pos_enabled', false);
        $this->assertSame('hidden', $this->getJson('/api/v1/app-status')->json('data.features.stable_pos'));
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/pos')->assertForbidden()->assertJsonPath('code', 'feature_disabled');
    }

    public function test_the_desk_sells_extras_with_a_session(): void
    {
        $this->setting('module_extras_enabled', true);
        $photos = $this->stable->extras()->create(['en_name' => 'Photos', 'ar_name' => 'تصوير', 'price' => 5]);
        $token = $this->token();

        // 30.000 + photos 5.000, 5% VAT on the price (VAT-registered) = 36.750
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/pos/quote?slot_id='.$this->slot->id.'&riders=2&extras['.$photos->id.']=1')
            ->assertOk()
            ->assertJsonPath('extras.0.name', 'Photos')
            ->assertJsonPath('extra_lines.0.amount.amount', '5.000')
            ->assertJsonPath('total.amount', '36.750');

        $this->sell($token, ['extras' => [$photos->id => 1], 'payments' => [['method' => 'cash', 'amount' => '36.750']]])->assertCreated();
        $this->assertSame('Photos', StableBooking::query()->sole()->extras()->sole()->en_name);
    }

    public function test_a_session_paid_at_the_desk_in_two_methods_carries_the_commission_only(): void
    {
        $token = $this->token();

        // 2 riders × 15.000, 5% VAT on the price (the stable is VAT-registered), no service fee
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/pos/quote?slot_id='.$this->slot->id.'&riders=2')
            ->assertOk()->assertJsonPath('total.amount', '31.500')->assertJsonPath('commission.amount', '3.000');

        $this->sell($token, ['payments' => [['method' => 'cash', 'amount' => '20'], ['method' => 'card', 'amount' => '10.000']]])
            ->assertStatus(409)->assertJsonPath('code', 'booking_unavailable');
        $this->assertSame(0, StableBooking::query()->count());

        $sale = $this->sell($token, ['payments' => [['method' => 'cash', 'amount' => '20'], ['method' => 'card', 'amount' => '11.500', 'reference' => 'slip 4411']]])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'session')
            ->assertJsonPath('data.status.value', 'paid')
            ->assertJsonPath('data.total.amount', '31.500')
            ->assertJsonPath('data.payments.1.method.value', 'card')
            ->assertJsonPath('data.payments.1.reference', 'slip 4411')
            ->assertJsonPath('data.sold_by', 'Salim Al Hinai')
            ->assertJsonPath('data.can_refund', true);
        $this->assertNotNull($sale->json('data.receipt_url'));

        $order = ServiceOrder::query()->sole();
        $this->assertSame(PaymentGateway::Desk, $order->payment_method);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('stable', $order->collected_by);
        $this->assertSame('0.000', $order->fee_amount);
        $this->assertSame('3.000', $order->commission_amount);
        $this->assertSame('96892345678', $order->customer_phone);
        $this->assertNotNull($order->receipt_number);
        $this->assertSame(StableBookingStatus::Confirmed, $order->stableBooking->status);
        $this->assertSame(['Aisha', 'Omar'], array_column($order->stableBooking->riders_data, 'name'));
        $this->assertSame(2, PosPayment::query()->count());

        // in the statement the day it was paid, held by the stable: it owes us the commission
        $row = StableStatement::forPeriod($this->stable, 'this_month')->rows()->sole();
        $this->assertSame(StableLedgerRow::DESK, $row->kind);
        $this->assertSame(-3000, $row->net());
        $this->assertSame('Sold at the desk', $row->label());

        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/pos/sales')
            ->assertOk()
            ->assertJsonPath('totals.count', 1)
            ->assertJsonPath('totals.sales.amount', '31.500')
            ->assertJsonPath('totals.commission.amount', '3.000')
            ->assertJsonPath('totals.stable_share.amount', '28.500')
            ->assertJsonPath('by_method.0.method.value', 'cash')
            ->assertJsonPath('by_method.0.total.amount', '20.000')
            ->assertJsonPath('by_method.1.total.amount', '11.500')
            ->assertJsonPath('by_item.0.item', 'Beginner lesson')
            ->assertJsonPath('by_seller.0.name', 'Salim Al Hinai')
            ->assertJsonPath('data.0.number', $order->order_number);
    }

    public function test_the_desk_sells_a_time_after_the_websites_cutoff_until_it_starts(): void
    {
        $this->offering->forceFill(['booking_cutoff_minutes' => 120])->save();
        app(SlotGenerator::class)->sync($this->makeSchedule($this->offering, [0], ['10:00']));
        $today = BookingSlot::query()->where('date', '2026-10-04')->sole(); // starts in 30 minutes
        $token = $this->token();

        $this->sell($token, ['slot_id' => $today->id, 'riders' => ['Aisha'], 'payments' => [['method' => 'cash', 'amount' => '15.750']]])->assertCreated();

        Carbon::setTestNow('2026-10-04 06:01:00');
        $this->sell($token, ['slot_id' => $today->id, 'riders' => ['Omar'], 'payments' => [['method' => 'cash', 'amount' => '15.750']]])->assertStatus(409);
    }

    public function test_only_the_methods_the_stable_takes_are_accepted(): void
    {
        $this->stable->forceFill(['booking_settings' => ['pos_methods' => ['cash', 'wallet']]])->save();
        $this->setting('pos_payment_methods', json_encode(['cash', 'card', 'link']), 'json');
        $token = $this->token();

        // the site no longer allows wallets: only cash is left for this stable
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/pos')->assertJsonCount(1, 'methods');
        $this->sell($token, ['payments' => [['method' => 'card', 'amount' => '31.500']]])->assertStatus(409);
        $this->sell($token, ['link' => true])->assertStatus(409);
        $this->sell($token, ['payments' => [['method' => 'cash', 'amount' => '31.500']]])->assertCreated();
    }

    public function test_a_payment_link_leaves_the_booking_waiting_for_the_customer(): void
    {
        $token = $this->token();

        $sale = $this->sell($token, ['link' => true])
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'awaiting_payment')
            ->assertJsonPath('data.via_link', true)
            ->assertJsonPath('data.can_refund', false);
        $this->assertStringContainsString('/pay/', $sale->json('data.pay_url'));

        $order = ServiceOrder::query()->sole();
        $this->assertSame('0.000', $order->fee_amount);
        $this->assertSame(StableBookingStatus::Pending, $order->stableBooking->status);

        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/pos/sales')
            ->assertJsonPath('totals.count', 0)
            ->assertJsonPath('totals.unpaid_links_count', 1)
            ->assertJsonPath('totals.unpaid_links.amount', '31.500');
    }

    public function test_a_package_sold_at_the_desk_starts_at_once_for_the_phone(): void
    {
        $package = StablePackage::query()->create(['stable_id' => $this->stable->id, 'stable_offering_id' => $this->offering->id, 'en_name' => 'Ten lessons', 'ar_name' => 'عشر حصص', 'sessions' => 10, 'price' => 120, 'validity_days' => 90, 'is_active' => true]);
        $token = $this->token();

        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/pos')->assertJsonPath('packages.0.name', 'Ten lessons');
        $this->withToken($token)->postJson('/api/v1/owner/stables/desert-riders/pos/sales', ['package_id' => $package->id, 'name' => 'Khalid', 'payments' => [['method' => 'cash', 'amount' => '126']]])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->withToken($token)->postJson('/api/v1/owner/stables/desert-riders/pos/sales', ['package_id' => $package->id, 'name' => 'Khalid', 'phone' => '92345678', 'payments' => [['method' => 'bank_transfer', 'amount' => '126', 'reference' => 'TRX-1']]])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'package')
            ->assertJsonPath('data.status.value', 'paid')
            ->assertJsonPath('data.item', 'Ten lessons');

        $purchase = StablePackagePurchase::query()->sole();
        $this->assertSame(StablePackagePurchase::ACTIVE, $purchase->status);
        $this->assertSame('Khalid', Customer::query()->where('phone', '96892345678')->sole()->name);
        $this->assertSame($purchase->customer_id, Customer::query()->sole()->id);
    }

    public function test_a_refund_gives_the_money_back_and_cancels_the_session_without_asking_anyone_else(): void
    {
        Event::fake([ServiceOrderRefundDue::class]);
        $token = $this->token();
        $number = $this->sell($token, ['payments' => [['method' => 'cash', 'amount' => '31.500']]])->json('data.number');

        Carbon::setTestNow('2026-10-04 08:00:00');
        $this->withToken($token)->postJson("/api/v1/owner/stables/desert-riders/pos/sales/{$number}/refund", ['reason' => 'The horse is lame'])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'refunded')
            ->assertJsonPath('data.refunded.amount', '31.500')
            ->assertJsonPath('data.can_refund', false);

        $order = ServiceOrder::query()->sole();
        $this->assertSame(StableBookingStatus::Cancelled, $order->stableBooking->status);
        $this->assertSame('The horse is lame', $order->stableBooking->cancellation_reason);
        $this->assertSame('desk', $order->refunds()->sole()->reference);
        Event::assertNotDispatched(ServiceOrderRefundDue::class);

        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/pos/sales')
            ->assertJsonPath('totals.refunds.amount', '31.500')
            ->assertJsonPath('totals.net.amount', '0.000')
            ->assertJsonPath('totals.commission.amount', '0.000');

        $this->withToken($token)->postJson("/api/v1/owner/stables/desert-riders/pos/sales/{$number}/refund", ['reason' => 'again'])->assertStatus(409);
    }

    public function test_cancelling_a_desk_sale_from_the_bookings_screen_records_the_refund_too(): void
    {
        Event::fake([ServiceOrderRefundDue::class]);
        $token = $this->token();
        $reference = $this->sell($token, ['payments' => [['method' => 'cash', 'amount' => '31.500']]])->json('data.reference');

        $this->withToken($token)->postJson("/api/v1/owner/stables/desert-riders/bookings/{$reference}/cancel", ['reason' => 'Weather'])->assertOk();

        $this->assertSame(PaymentStatus::Refunded, ServiceOrder::query()->sole()->payment_status);
        Event::assertNotDispatched(ServiceOrderRefundDue::class);
    }

    public function test_the_customer_lookup_only_knows_this_stables_customers(): void
    {
        $token = $this->token();
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/pos/customer?phone=92345678')->assertJsonPath('known', false)->assertJsonPath('name', null);

        $this->sell($token, ['payments' => [['method' => 'cash', 'amount' => '31.500']], 'email' => 'khalid@example.com']);

        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/pos/customer?phone=+968 9234 5678')
            ->assertJsonPath('known', true)
            ->assertJsonPath('name', 'Khalid Al Balushi')
            ->assertJsonPath('email', 'khalid@example.com');
    }

    public function test_the_owner_picks_the_desk_methods_in_the_panel_and_other_saves_keep_them(): void
    {
        $this->withoutVite();
        $this->actingAs($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        Filament::setTenant($this->stable);
        Filament::bootCurrentPanel();

        Livewire::test(BookingSettings::class)
            ->assertFormSet(['pos_methods' => StablePos::METHODS])
            ->fillForm(['pos_methods' => ['cash', 'card']])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['cash', 'card'], StablePos::methodsFor($this->stable->refresh()));

        // with the desk switched off the section is hidden, and saving leaves the choice alone
        $this->stable->forceFill(['pos_enabled' => false])->save();
        Livewire::test(BookingSettings::class)->call('save')->assertHasNoFormErrors();
        $this->assertSame(['cash', 'card'], $this->stable->refresh()->booking_settings['pos_methods']);
    }
}
