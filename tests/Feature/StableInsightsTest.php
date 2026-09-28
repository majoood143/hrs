<?php

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Filament\Insights\InsightsKpis;
use App\Filament\Insights\InsightsOccupancy;
use App\Filament\Insights\InsightsSalesChart;
use App\Filament\Insights\InsightsTopServices;
use App\Filament\Insights\InsightsTopStables;
use App\Filament\Pages\StableInsights as StableInsightsPage;
use App\Filament\Widgets\StableBookingsOverview;
use App\Models\BookingSlot;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StableOffering;
use App\Models\User;
use App\Services\Payments\OrderPaymentService;
use App\Services\Reports\StableInsights;
use App\Services\Stables\CreateStableBooking;
use App\Services\Stables\SlotGenerator;
use App\Services\Stables\StableBookingActions;
use App\Services\Stables\StableReviews;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * Insights over October 2026. 10.000 a rider, a 5% fee, no VAT, 10% commission; Tuesday sessions
 * at 10:00 (4 places). The story:
 *   Sep 29 session: Aisha (91234567) books and pays 1 rider             (September: before the period)
 *   Oct  6 session: Aisha 2 riders paid online; Salim 1 rider at the stable, attended
 *   Oct 13 session: Huda 1 rider paid then no-show; Omar cancels his paid booking
 */
class StableInsightsTest extends TestCase
{
    use PreparesStableBookings;

    private User $owner;

    private Stable $stable;

    private StableOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', 'vat.enabled' => false]);
        $this->withoutVite();

        $this->owner = $this->makeOwner();
        $this->stable = $this->makeStable($this->owner);
        $this->stable->forceFill(['booking_settings' => ['payment_options' => ['online', 'at_stable'], 'cancellation_hours' => 1, 'horizon_days' => 30]])->save();
        $this->offering = $this->makeOffering($this->stable, ['price' => 10, 'max_riders' => 3, 'en_name' => 'Beginner lesson']);
        $this->makeFee('percentage', 5.0);

        Carbon::setTestNow('2026-09-27 05:00:00');
        app(SlotGenerator::class)->sync($this->makeSchedule($this->offering, [2], ['10:00'], ['valid_from' => '2026-09-27']));

        $this->book('2026-09-29', 1, '91234567', 'Aisha');                       // paid 27 Sep

        Carbon::setTestNow('2026-10-04 05:00:00');
        $this->book('2026-10-06', 2, '91234567', 'Aisha');                       // paid 4 Oct
        $atStable = $this->book('2026-10-06', 1, '92222222', 'Salim', 'at_stable');

        Carbon::setTestNow('2026-10-11 05:00:00');
        $noShow = $this->book('2026-10-13', 1, '93333333', 'Huda');              // paid 11 Oct
        $cancelled = $this->book('2026-10-13', 1, '94444444', 'Omar');           // paid 11 Oct
        app(StableBookingActions::class)->cancelByCustomer($cancelled);

        Carbon::setTestNow('2026-10-06 08:00:00');
        app(StableBookingActions::class)->markAttended($atStable);
        Carbon::setTestNow('2026-10-13 08:00:00');
        app(StableBookingActions::class)->markNoShow($noShow);

        Carbon::setTestNow('2026-10-20 05:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function book(string $date, int $riders, string $phone, string $name, string $option = 'online'): StableBooking
    {
        $slot = BookingSlot::query()->where('stable_id', $this->stable->id)->where('date', $date)->sole();
        $booking = app(CreateStableBooking::class)->handle($slot, array_fill(0, $riders, ['name' => $name]), ['name' => $name, 'phone' => $phone], $option);

        if ($option === 'online') {
            app(OrderPaymentService::class)->applyPaid($booking->order, PaymentGateway::Demo, 'D-'.$booking->id);
        }

        return $booking->refresh();
    }

    private function october(): StableInsights
    {
        return StableInsights::forPeriod($this->stable, 'this_month');
    }

    public function test_the_money_of_the_period(): void
    {
        $m = $this->october()->money();

        // 2 riders (21.000) + at the stable (10.500) + no-show (10.500) + cancelled (10.500, refund not recorded yet)
        $this->assertSame(52500, $m['collected']);
        $this->assertSame(4, $m['bookings']);
        $this->assertSame(2500, $m['fee']);                           // 5% of 50.000
        $this->assertSame(5000, $m['commission']);                    // 10% of 50.000
        $this->assertSame(45000, $m['stable_share']);
        $this->assertSame(7500, $m['our_share']);                    // fee 2.500 + commission 5.000

        // September only had one rider: sales up
        $this->assertSame(10500, $this->october()->previous()->money()['collected'] ?? null);
    }

    public function test_the_series_has_every_day_of_the_month(): void
    {
        $series = $this->october()->series();

        $this->assertSame('day', $this->october()->bucket());
        $this->assertCount(31, $series);
        $this->assertSame(18000, $series['2026-10-04']['stable_share']);   // 20.000 less 10% commission
        $this->assertSame(2000, $series['2026-10-04']['commission']);
        $this->assertSame(9000, $series['2026-10-06']['stable_share']);    // at the stable, dated when attended
        $this->assertSame(0, $series['2026-10-05']['stable_share']);

        $this->assertSame('month', StableInsights::forPeriod($this->stable, 'this_year')->bucket());
    }

    public function test_occupancy_by_day_and_hour(): void
    {
        $o = $this->october()->occupancy();

        // Tuesday 10:00, two sessions of 4 places: 3 sold on the 6th, 1 held by the no-show on the 13th
        $this->assertSame([10], $o['hours']);
        $this->assertSame(['booked' => 4, 'capacity' => 8], $o['cells'][2][10]);
        $this->assertSame(50, $this->october()->occupancyRate());
    }

    public function test_customers_cancellations_and_no_shows(): void
    {
        $insights = $this->october();

        // Aisha came back (she rode in September); Salim and Huda are new; Omar cancelled
        $this->assertSame(['new' => 2, 'returning' => 1], $insights->customers());

        $a = $insights->attendance();
        $this->assertSame([1, 4, 25], [$a['cancelled'], $a['booked'], $a['cancel_rate']]);
        $this->assertSame([1, 1, 50], [$a['no_show'], $a['attended'], $a['no_show_rate']]);
    }

    public function test_ratings_and_what_sells(): void
    {
        $attended = StableBooking::query()->where('status', 'completed')->sole();
        $customer = Customer::create(['phone' => '96892222222', 'name' => 'Salim', 'phone_verified_at' => now()]);
        app(StableReviews::class)->submit($attended, $customer, 4, null);

        $this->assertSame(['average' => 4.0, 'count' => 1], $this->october()->rating());

        $top = $this->october()->topServices()->sole();
        $this->assertSame(['Beginner lesson', 4, 5], [$top['name'], $top['count'], $top['riders']]);
    }

    public function test_every_stable_together_and_ranked(): void
    {
        $other = $this->makeStable($this->makeOwner('b@example.com', '96890000001'), ['slug' => 'second-stable', 'en_name' => 'Second Stable']);
        $offering = $this->makeOffering($other, ['price' => 100]);
        Carbon::setTestNow('2026-10-18 05:00:00');
        app(SlotGenerator::class)->sync($this->makeSchedule($offering, [2], ['10:00']));
        $slot = BookingSlot::query()->where('stable_id', $other->id)->orderBy('date')->first();
        $booking = app(CreateStableBooking::class)->handle($slot, [['name' => 'X']], ['name' => 'X', 'phone' => '95555555']);
        app(OrderPaymentService::class)->applyPaid($booking->order, PaymentGateway::Demo, 'D-X');
        Carbon::setTestNow('2026-10-20 05:00:00');

        $all = StableInsights::forPeriod(null, 'this_month');
        $ranking = $all->byStable();

        $this->assertSame(52500 + 105000, $all->money()['collected']);
        $this->assertSame(['Second Stable', 'Desert Riders'], $ranking->map(fn ($r) => $r['stable']->en_name)->all());
        $this->assertSame(15000, $ranking[0]['our_share']);    // fee 5.000 + commission 10.000
    }

    public function test_the_deltas(): void
    {
        $this->assertSame(400, StableInsights::delta(52500, 10500));
        $this->assertSame(-50, StableInsights::delta(5, 10));
        $this->assertNull(StableInsights::delta(5, 0));
    }

    // ── screens ──────────────────────────────────────────────────────────────

    public function test_the_owner_insights_page_and_widgets(): void
    {
        $this->actingAs($this->owner)->get('/stable/'.$this->stable->slug.'/insights')->assertOk()->assertSee('Insights');

        $this->actingAs($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        Filament::setTenant($this->stable);
        Filament::bootCurrentPanel();
        $filters = ['pageFilters' => ['period' => 'this_month']];

        Livewire::test(InsightsKpis::class, $filters)
            ->assertSee('Your share')
            ->assertSee('45.000')
            ->assertSee('+400% vs the previous period')
            ->assertSee('2 new · 1 returning')
            ->assertSee('No-shows: 50%');
        Livewire::test(InsightsSalesChart::class, $filters)->assertOk()->assertSee('Sales by day');
        Livewire::test(InsightsOccupancy::class, $filters)->assertSee('50%')->assertSee('Overall 50% (4 of 8 places)');
        Livewire::test(InsightsTopServices::class, $filters)->assertSee('Beginner lesson');
    }

    public function test_the_admin_insights_page_dashboard_widget_and_export(): void
    {
        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $filters = ['pageFilters' => ['period' => 'this_month']];

        Livewire::test(StableInsightsPage::class)->assertOk()->callAction('rankingCsv')->assertFileDownloaded();
        Livewire::test(InsightsKpis::class, $filters)->assertSee('Our earnings')->assertSee('7.500');
        Livewire::test(InsightsTopStables::class, $filters)->assertSee('Desert Riders')->assertSee('Statement');
        // one stable picked: the ranking steps aside
        Livewire::test(InsightsTopStables::class, ['pageFilters' => ['period' => 'this_month', 'stable_id' => $this->stable->id]])->assertDontSee('Most sold first');

        Livewire::test(StableBookingsOverview::class)->assertSee('Stable bookings this month')->assertSee('Waiting for an admin');
    }

    // ── reminders ────────────────────────────────────────────────────────────

    public function test_customers_are_reminded_once_the_day_before(): void
    {
        Carbon::setTestNow('2026-10-25 05:00:00');                    // Sunday; next session Tuesday 27th
        app(SlotGenerator::class)->syncStable($this->stable);
        $booking = $this->book('2026-10-27', 1, '96666666', 'Maya');
        $late = null;

        // Monday 11:00 Muscat: 23 hours to go
        Carbon::setTestNow('2026-10-26 07:00:00');
        $late = $this->book('2026-10-27', 1, '97777777', 'Late');     // just confirmed: not reminded yet
        $this->artisan('stables:send-reminders')->assertSuccessful();
        $this->artisan('stables:send-reminders')->assertSuccessful();

        $reminders = NotificationLog::query()->where('type', 'booking_reminder')->get();
        $this->assertCount(1, $reminders);
        $this->assertSame('96896666666', $reminders->sole()->recipient);
        $this->assertStringContainsString('reminder: Beginner lesson at Desert Riders', $reminders->sole()->message);
        $this->assertNotNull($booking->refresh()->reminded_at);
        $this->assertNull($late->refresh()->reminded_at);

        // the late one is reminded later, once its confirmation is 12 hours old (the session is still 11 hours away)
        Carbon::setTestNow('2026-10-26 19:00:00');
        $this->artisan('stables:send-reminders')->assertSuccessful();
        $this->assertNotNull($late->refresh()->reminded_at);
        $this->assertSame(2, NotificationLog::query()->where('type', 'booking_reminder')->count());
    }

    public function test_a_stable_can_switch_reminders_off(): void
    {
        $this->stable->forceFill(['booking_settings' => ['payment_options' => ['online'], 'send_reminders' => false, 'horizon_days' => 30]])->save();
        Carbon::setTestNow('2026-10-25 05:00:00');
        app(SlotGenerator::class)->syncStable($this->stable);
        $this->book('2026-10-27', 1, '96666666', 'Maya');

        Carbon::setTestNow('2026-10-26 07:00:00');
        $this->artisan('stables:send-reminders')->assertSuccessful();

        $this->assertSame(0, NotificationLog::query()->where('type', 'booking_reminder')->count());
    }
}
