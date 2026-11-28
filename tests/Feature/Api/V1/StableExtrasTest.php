<?php

namespace Tests\Feature\Api\V1;

use App\Filament\Stable\Resources\StableExtras\Pages\ManageStableExtras;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\PromoCode;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Models\StableBookingExtra;
use App\Models\StableExtra;
use App\Models\StableOffering;
use App\Models\User;
use App\Services\Reports\StableInsights;
use App\Services\Stables\SlotGenerator;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * Extras on stable bookings (app API): part of the order's price, so the fee, VAT, commission and a
 * promo code follow it. Same stable as BookingsTest: 15.000 a rider, a 5% fee and 5% VAT.
 */
class StableExtrasTest extends TestCase
{
    use PreparesStableBookings;

    private const SETTINGS = ['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', StableExtra::SETTING => true, PromoCode::SETTING => true];

    private Stable $stable;

    private User $owner;

    private StableOffering $offering;

    private BookingSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(self::SETTINGS);
        (require database_path('migrations/2026_09_11_145142_create_personal_access_tokens_table.php'))->up();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->stable = $this->makeStable($this->owner = $this->makeOwner(), ['slug' => 'desert-riders']);
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

    private function extra(array $attributes = []): StableExtra
    {
        return $this->stable->extras()->create($attributes + ['en_name' => 'Photos', 'ar_name' => 'تصوير', 'price' => 5]);
    }

    private function form(array $extra = [], int $riders = 1): array
    {
        return $extra + ['name' => 'Aisha', 'payment_option' => 'online', 'riders' => array_map(fn ($i) => ['name' => "Rider {$i}", 'age' => 12, 'level' => 'beginner'], range(1, $riders))];
    }

    public function test_the_form_lists_the_extras_and_quotes_them_per_booking_and_per_rider(): void
    {
        $photos = $this->extra();
        $helmet = $this->extra(['en_name' => 'Helmet', 'ar_name' => 'خوذة', 'price' => 1, 'pricing' => StableExtra::PER_RIDER, 'max_quantity' => 2]);
        $this->extra(['en_name' => 'Off', 'ar_name' => 'متوقف', 'is_active' => false]);

        // 2 riders: 30.000 + photos 5.000 + helmet 1.000 × 2 riders × 1 = 37.000; fee 1.850; VAT on the fee 0.093
        $this->getJson("/api/v1/bookings/slots/{$this->slot->id}?riders=2&extras[{$photos->id}]=1&extras[{$helmet->id}]=1")
            ->assertOk()
            ->assertJsonCount(2, 'extras')
            ->assertJsonPath('extras.1.pricing.value', 'per_rider')
            ->assertJsonPath('extras_error', null)
            ->assertJsonPath('quote.session.amount', '30.000')
            ->assertJsonPath('quote.extras_total.amount', '7.000')
            ->assertJsonPath('quote.price.amount', '37.000')
            ->assertJsonPath('quote.fee.amount', '1.850')
            ->assertJsonCount(2, 'quote.extras');

        $this->getJson("/api/v1/bookings/slots/{$this->slot->id}?extras[{$helmet->id}]=3")
            ->assertOk()
            ->assertJsonPath('extras_error', __('stable_extras.errors.too_many', ['max' => 2, 'name' => 'Helmet']))
            ->assertJsonPath('quote.price.amount', '15.000');
    }

    public function test_a_booking_keeps_a_copy_of_its_extras_and_a_promo_code_comes_off_them_too(): void
    {
        $photos = $this->extra();
        PromoCode::query()->create(['code' => 'SAVE20', 'name' => 'Test', 'discount_type' => 'percentage', 'discount_value' => 20]);

        $this->withToken($this->signIn())->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(['extras' => [$photos->id => 1], 'promo_code' => 'SAVE20']))
            ->assertCreated()
            ->assertJsonPath('data.extras.0.name', 'Photos')
            ->assertJsonPath('data.extras.0.amount.amount', '5.000')
            // (15 + 5) less 20% = 16.000
            ->assertJsonPath('data.order.price.amount', '16.000')
            ->assertJsonPath('data.order.discount.amount', '4.000');

        $order = ServiceOrder::query()->sole();
        $this->assertSame(16.0 - 5.0 + 4.0, $order->priceBeforeDiscount());
        $this->assertSame(1, $order->extraLines()->count());

        // editing the extra never changes the booking
        $photos->update(['price' => 9, 'en_name' => 'Changed']);
        $this->assertSame('Photos', StableBookingExtra::query()->sole()->en_name);
        $this->assertSame('5.000', StableBookingExtra::query()->sole()->amount);
    }

    public function test_extras_of_another_stable_or_service_or_switched_off_are_refused(): void
    {
        $other = $this->makeStable(null, ['slug' => 'other', 'en_name' => 'Other']);
        $foreign = $other->extras()->create(['en_name' => 'X', 'ar_name' => 'X', 'price' => 1]);
        $elsewhere = $this->extra();
        $elsewhere->offerings()->attach($this->makeOffering($this->stable)->id);
        $token = $this->signIn();
        $url = "/api/v1/bookings/slots/{$this->slot->id}";

        foreach ([$foreign, $elsewhere] as $extra) {
            $this->withToken($token)->postJson($url, $this->form(['extras' => [$extra->id => 1]]))
                ->assertStatus(409)->assertJsonPath('message', __('stable_extras.errors.unavailable'));
        }

        $mine = $this->extra(['en_name' => 'Mine']);
        $this->stable->forceFill(['extras_enabled' => false])->save();
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'extras');
        $this->withToken($token)->postJson($url, $this->form(['extras' => [$mine->id => 1]]))->assertStatus(409);

        $this->stable->forceFill(['extras_enabled' => true])->save();
        $this->stableSiteSettings([StableExtra::SETTING => false] + self::SETTINGS);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'extras');

        $this->assertSame(0, ServiceOrder::query()->count());
    }

    public function test_insights_count_the_extras_of_bookings_that_went_ahead(): void
    {
        $photos = $this->extra();
        $this->stable->forceFill(['booking_settings' => ['payment_options' => ['online', 'at_stable']]])->save();
        $token = $this->signIn();
        $this->withToken($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(['extras' => [$photos->id => 1], 'payment_option' => 'at_stable']))->assertCreated();
        $this->withToken($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", $this->form(['payment_option' => 'at_stable']))->assertCreated();

        $extras = StableInsights::forPeriod($this->stable, 'this_month')->extras();

        $this->assertSame(5000, $extras['revenue']);
        $this->assertSame(50, $extras['rate']);
        $this->assertSame('Photos', $extras['top']->first()['name']);
    }

    public function test_the_website_form_offers_the_extras_and_books_them(): void
    {
        $photos = $this->extra();
        $this->actingAs(Customer::create(['phone' => '96891234567', 'name' => 'Aisha', 'phone_verified_at' => now()]), 'customer');

        // "Update the price" goes back to the form with the extras in the quote: (15 + 5) + 5% VAT on it + 5% fee + VAT on the fee = 22.050
        $this->post(route('bookings.store', $this->slot), ['apply_promo' => 1, 'riders_count' => 1, 'extras' => [$photos->id => 1]])
            ->assertRedirect(route('bookings.create', ['slot' => $this->slot, 'riders' => 1, 'extras' => [$photos->id => 1]]));
        $this->get(route('bookings.create', ['slot' => $this->slot->id, 'extras' => [$photos->id => 1]]))
            ->assertOk()->assertSee('Photos')->assertSee('22.050');

        $this->post(route('bookings.store', $this->slot), [
            'riders_count' => 1,
            'name' => 'Aisha',
            'payment_option' => 'online',
            'extras' => [$photos->id => 1],
            'riders' => [['name' => 'Omar', 'age' => 9, 'level' => 'beginner']],
        ])->assertRedirect();

        $this->assertSame('20.000', ServiceOrder::query()->sole()->price);
        $this->assertSame(1, StableBookingExtra::query()->count());
    }

    public function test_the_owner_adds_an_extra_for_one_service_from_the_panel(): void
    {
        $this->actingAs($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        Filament::setTenant($this->stable);
        Filament::bootCurrentPanel();

        Livewire::test(ManageStableExtras::class)
            ->assertOk()
            ->callAction(TestAction::make('create'), [
                'en_name' => 'Transport', 'ar_name' => 'نقل', 'price' => '8', 'pricing' => StableExtra::PER_RIDER,
                'max_quantity' => 1, 'offerings' => [$this->offering->id], 'is_active' => true, 'sort' => 0,
            ])
            ->assertHasNoActionErrors();

        $extra = $this->stable->extras()->sole();
        $this->assertSame([$this->offering->id], $extra->offerings()->pluck('stable_offerings.id')->all());
        $this->assertSame(16000, $extra->amountBaisa(1, 2));
    }
}
