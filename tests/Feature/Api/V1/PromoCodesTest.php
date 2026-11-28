<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OrderStatus;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\PromoCode;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Models\StableOffering;
use App\Models\StablePackage;
use App\Services\Stables\SlotGenerator;
use Illuminate\Support\Carbon;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * Promo codes on stable bookings and lesson packages (app and website): the discount comes off the
 * price, so the fee and VAT follow the lower price. Same stable as BookingsTest: 15.000 a rider,
 * a 5% fee and 5% VAT.
 */
class PromoCodesTest extends TestCase
{
    use PreparesStableBookings;

    private const SETTINGS = ['enabled_gateways' => ['demo'], 'sms.driver' => 'demo'];

    private Stable $stable;

    private StableOffering $offering;

    private BookingSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(self::SETTINGS);
        (require database_path('migrations/2026_09_11_145142_create_personal_access_tokens_table.php'))->up();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->stable = $this->makeStable($this->makeOwner(), ['slug' => 'desert-riders']);
        $this->offering = $this->makeOffering($this->stable, ['max_riders' => 3]);
        $this->makeFee('percentage', 5.0);
        app(SlotGenerator::class)->sync($this->makeSchedule($this->offering, [2], ['10:00']));
        $this->slot = BookingSlot::query()->where('date', '2026-10-06')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function signIn(string $phone = '91234567'): string
    {
        $code = $this->postJson('/api/v1/auth/request', ['phone' => $phone, 'channel' => 'sms'])->assertOk()->json('demo_code');
        $token = $this->postJson('/api/v1/auth/verify', ['phone' => $phone, 'code' => $code])->assertOk()->json('token');
        $this->app['auth']->forgetGuards();

        return $token;
    }

    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function code(array $attributes = []): PromoCode
    {
        return PromoCode::query()->create($attributes + ['code' => 'SAVE20', 'name' => 'Test', 'discount_type' => 'percentage', 'discount_value' => 20]);
    }

    private function form(array $extra = []): array
    {
        return $extra + ['name' => 'Aisha', 'payment_option' => 'online', 'riders' => [['name' => 'Rider 1', 'age' => 12, 'level' => 'beginner']]];
    }

    public function test_the_form_shows_the_price_less_the_discount_or_why_a_code_cannot_be_used(): void
    {
        $this->code();

        $this->getJson("/api/v1/bookings/slots/{$this->slot->id}?promo_code=save20")
            ->assertOk()
            ->assertJsonPath('promo_codes', true)
            ->assertJsonPath('promo_code_error', null)
            ->assertJsonPath('quote.discount.amount', '3.000')
            ->assertJsonPath('quote.price.amount', '12.000')
            ->assertJsonPath('quote.fee.amount', '0.600')
            ->assertJsonPath('quote.total.amount', '13.230');

        $this->getJson("/api/v1/bookings/slots/{$this->slot->id}?promo_code=NOPE")
            ->assertOk()
            ->assertJsonPath('promo_code_error', __('promo_codes.errors.invalid'))
            ->assertJsonPath('quote.discount', null)
            ->assertJsonPath('quote.total.amount', '16.538');
    }

    public function test_a_booking_keeps_the_code_and_a_single_use_code_works_once_until_its_checkout_is_called_off(): void
    {
        $this->code(['max_uses' => 1, 'discount_type' => 'fixed', 'discount_value' => 5]);
        $token = $this->signIn();

        $this->as($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(['promo_code' => 'SAVE20']))
            ->assertCreated()
            ->assertJsonPath('data.order.discount.amount', '5.000')
            ->assertJsonPath('data.order.price.amount', '10.000')
            ->assertJsonPath('data.order.total.amount', '11.025');

        $order = ServiceOrder::query()->sole();
        $this->assertSame(PromoCode::query()->sole()->id, (int) $order->promo_code_id);

        $this->as($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(['promo_code' => 'SAVE20']))
            ->assertStatus(409)
            ->assertJsonPath('message', __('promo_codes.errors.used_up'));

        // the unpaid checkout expired: the use comes back
        $order->forceFill(['status' => OrderStatus::Cancelled])->save();
        $this->as($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(['promo_code' => 'SAVE20']))->assertCreated();
    }

    public function test_a_code_follows_its_stable_its_mobile_its_dates_and_the_module_switch(): void
    {
        $other = $this->makeStable(null, ['slug' => 'other', 'en_name' => 'Other']);
        $token = $this->signIn();
        $url = "/api/v1/bookings/slots/{$this->slot->id}";

        $this->code(['code' => 'OTHER', 'stable_id' => $other->id]);
        $this->code(['code' => 'MINE', 'restricted_mobile' => '96899999999']);
        $this->code(['code' => 'OLD', 'valid_until' => now()->subDay()]);
        $this->code(['code' => 'OFF', 'is_active' => false]);

        foreach (['OTHER' => 'other_stable', 'MINE' => 'other_mobile', 'OLD' => 'expired', 'OFF' => 'invalid'] as $code => $error) {
            $this->as($token)->postJson($url, $this->form(['promo_code' => $code]))
                ->assertStatus(409)->assertJsonPath('message', __('promo_codes.errors.'.$error));
        }

        // the owner of the mobile may use it
        $this->as($this->signIn('99999999'))->postJson($url, $this->form(['promo_code' => 'MINE']))->assertCreated();

        // General Settings → Modules → Promo codes off: no code is taken
        $this->code(['code' => 'GOOD']);
        $this->stableSiteSettings(self::SETTINGS + [PromoCode::SETTING => false]);
        $this->getJson("{$url}?promo_code=GOOD")->assertJsonPath('promo_codes', false)->assertJsonPath('promo_code_error', __('promo_codes.errors.invalid'));
        $this->as($token)->postJson($url, $this->form(['promo_code' => 'GOOD']))->assertStatus(409);
    }

    public function test_a_code_is_for_online_payment_and_a_full_discount_books_it_free(): void
    {
        $this->stable->forceFill(['booking_settings' => ['payment_options' => ['online', 'at_stable']]])->save();
        $this->code(['code' => 'FREE', 'discount_value' => 100]);
        $token = $this->signIn();
        $url = "/api/v1/bookings/slots/{$this->slot->id}";

        $this->as($token)->postJson($url, $this->form(['promo_code' => 'FREE', 'payment_option' => 'at_stable']))
            ->assertCreated(); // free once discounted: nothing to collect at the desk either

        $this->code(['code' => 'HALF', 'discount_value' => 50]);
        $this->as($token)->postJson($url, $this->form(['promo_code' => 'HALF', 'payment_option' => 'at_stable']))
            ->assertStatus(409)->assertJsonPath('message', __('promo_codes.errors.online_only'));

        // the free form sends no payment option
        $free = $this->as($token)->postJson($url, ['name' => 'Aisha', 'promo_code' => 'FREE', 'riders' => [['name' => 'R', 'age' => 12, 'level' => 'beginner']]])
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'confirmed')
            ->assertJsonPath('data.pay_url', null)
            ->assertJsonPath('data.order.discount.amount', '15.000');
        $this->assertSame('FREE', PromoCode::query()->find(ServiceOrder::query()->where('order_number', $free->json('data.order.number'))->value('promo_code_id'))?->code);
    }

    public function test_a_package_is_bought_with_a_code(): void
    {
        $package = StablePackage::create(['stable_id' => $this->stable->id, 'stable_offering_id' => $this->offering->id, 'en_name' => 'Five lessons', 'ar_name' => 'خمس حصص', 'sessions' => 5, 'price' => 60]);
        $this->code(['max_discount_amount' => 10]); // 20% of 60 = 12, capped at 10
        $token = $this->signIn();

        $this->as($token)->postJson("/api/v1/packages/{$package->id}/buy", ['promo_code' => 'NOPE'])->assertStatus(409);
        $this->as($token)->postJson("/api/v1/packages/{$package->id}/buy", ['promo_code' => 'save20'])
            ->assertCreated()
            ->assertJsonPath('data.total.amount', '55.125'); // 50 + 2.500 fee + 5% VAT on both

        $this->assertSame('10.000', ServiceOrder::query()->sole()->discount_amount);
    }

    public function test_the_website_booking_page_applies_a_code(): void
    {
        $this->code();
        $this->signIn();
        $customer = Customer::query()->sole();

        $this->actingAs($customer, 'customer')
            ->get(route('bookings.create', $this->slot).'?promo_code=save20')
            ->assertOk()
            ->assertSee(__('promo_codes.site.applied'))
            ->assertSee('value="SAVE20"', false);

        // Apply keeps what was typed and books nothing
        $this->actingAs($customer, 'customer')
            ->post(route('bookings.store', $this->slot), $this->form(['riders_count' => 1, 'promo_code' => 'save20', 'apply_promo' => '1', 'riders' => [['name' => 'Typed Rider']]]))
            ->assertRedirect(route('bookings.create', ['slot' => $this->slot, 'riders' => 1, 'promo_code' => 'save20']));
        $this->assertSame(0, ServiceOrder::query()->count());
        $this->actingAs($customer, 'customer')
            ->get(route('bookings.create', ['slot' => $this->slot, 'riders' => 1, 'promo_code' => 'save20']))
            ->assertSee('value="Typed Rider"', false)
            ->assertSee(__('promo_codes.site.applied'));

        $this->actingAs($customer, 'customer')
            ->post(route('bookings.store', $this->slot), $this->form(['riders_count' => 1, 'promo_code' => 'SAVE20']))
            ->assertRedirect();
        $order = ServiceOrder::query()->sole();
        $this->assertSame('3.000', $order->discount_amount);

        // the order's lines: the session before the discount, then the discount, then the fee
        $lines = view('site.orders._breakdown', ['order' => $order])->render();
        $this->assertStringContainsString(__('promo_codes.discount').' (SAVE20)', $lines);
        $this->assertStringContainsString('15.000', $lines);
        $this->assertStringContainsString('−OMR 3.000', $lines);
    }
}
