<?php

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Filament\Pages\StableBalances;
use App\Filament\Pages\StableStatementReport;
use App\Filament\Stable\Pages\Statement;
use App\Filament\Stable\Widgets\StableFinanceOverview;
use App\Jobs\SendStableEmail;
use App\Models\BookingSlot;
use App\Models\NotificationLog;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StablePaymentAccount;
use App\Models\StableSettlement;
use App\Models\User;
use App\Services\Payments\OrderPaymentService;
use App\Services\Reports\StableStatement;
use App\Services\Reports\StableStatementExport;
use App\Services\Stables\CreateStableBooking;
use App\Services\Stables\SlotGenerator;
use App\Services\Stables\StableBookingActions;
use App\Services\Stables\StableSettlements;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * A stable's account with us. 15.000 a rider, 5% site fee, 5% VAT, 10% commission; "now" is
 * Sunday 2026-10-04 in Muscat with slots on Tuesdays.
 *
 * One rider: price 15.000 + VAT 0.750, fee 0.750 + VAT 0.038 (total 16.538); commission 1.500.
 *   paid through us     → we owe the stable 15.750 - 1.500 = 14.250
 *   paid to the stable  → the stable owes us 0.750 + 0.038 + 1.500 = 2.288
 */
class StableStatementTest extends TestCase
{
    use PreparesStableBookings;

    private User $owner;

    private Stable $stable;

    private BookingSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', 'vat.enabled' => true, 'vat.rate' => '5']);
        $this->withoutVite();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->owner = $this->makeOwner();
        $this->stable = $this->makeStable($this->owner);
        $this->stable->forceFill(['booking_settings' => ['payment_options' => ['online', 'at_stable']]])->save();
        $offering = $this->makeOffering($this->stable, ['max_riders' => 3]);
        $this->makeFee('percentage', 5.0);
        app(SlotGenerator::class)->sync($this->makeSchedule($offering, [2], ['10:00'], ['capacity' => 20]));
        $this->slot = BookingSlot::query()->where('date', '2026-10-06')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function booking(int $riders = 1, string $option = 'online'): StableBooking
    {
        return app(CreateStableBooking::class)->handle($this->slot, array_fill(0, $riders, ['name' => 'Omar']), ['name' => 'Aisha', 'phone' => '91234567'], $option);
    }

    private function paidBooking(int $riders = 1): StableBooking
    {
        $booking = $this->booking($riders);
        app(OrderPaymentService::class)->applyPaid($booking->order, PaymentGateway::Demo, 'D-'.$booking->id);

        return $booking->refresh();
    }

    private function ownGateway(): void
    {
        $this->stable->forceFill(['booking_settings' => ['payment_options' => ['online', 'at_stable'], 'payment_mode' => 'own', 'payment_gateway' => 'thawani']])->save();
        $account = StablePaymentAccount::create(['stable_id' => $this->stable->id, 'gateway' => 'thawani', 'credentials' => ['secret_key' => 'sk', 'publishable_key' => 'pk']]);
        $account->forceFill(['status' => StablePaymentAccount::APPROVED])->save();
    }

    private function month(): StableStatement
    {
        return StableStatement::forPeriod($this->stable->refresh(), 'this_month');
    }

    public function test_who_holds_the_money_decides_who_owes_whom(): void
    {
        $this->paidBooking(2);                             // through us: we owe 2 × 14.250

        $this->ownGateway();
        $this->paidBooking(1);                             // stable's gateway: it owes 2.288

        $atStable = $this->booking(1, 'at_stable');        // counted once attended: it owes 2.288
        $notYet = $this->booking(1, 'at_stable');          // not attended: not counted

        Carbon::setTestNow('2026-10-06 08:00:00');
        app(StableBookingActions::class)->markAttended($atStable);

        $t = $this->month()->totals();

        $this->assertSame(3, $t['bookings']);
        $this->assertSame(28500, $t['owed_to_stable']);
        $this->assertSame(4576, $t['owed_by_stable']);
        $this->assertSame(28500 - 4576, $t['net']);
        $this->assertSame(33075, $t['collected_by_us']);
        $this->assertSame(2 * 16538, $t['collected_by_stable']);
        $this->assertSame(3000 + 1500 + 1500, $t['commission']);
        $this->assertSame(23924, StableStatement::balance($this->stable));

        // the one nobody marked yet is flagged, not counted
        Carbon::setTestNow('2026-10-07 08:00:00');
        $this->assertSame(1, StableStatement::unmarkedAtStable($this->stable));
        $this->assertNotNull($notYet);
    }

    public function test_payouts_and_receipts_move_the_balance(): void
    {
        $this->paidBooking(2);

        app(StableSettlements::class)->record($this->stable, StableSettlement::PAYOUT, '20', '2026-10-04');
        $this->assertSame(8500, StableStatement::balance($this->stable));

        app(StableSettlements::class)->record($this->stable, StableSettlement::RECEIPT, '1.5', '2026-10-04');
        $this->assertSame(10000, StableStatement::balance($this->stable));

        $t = $this->month()->totals();
        $this->assertSame([20000, 1500], [$t['payouts'], $t['receipts']]);
    }

    public function test_a_refund_shrinks_the_stables_share_and_our_commission(): void
    {
        $booking = $this->paidBooking(2);
        // the whole client share (31.500) went back to the customer: nothing left to share, the fee stays ours
        $booking->order->forceFill(['refunded_amount' => '31.500', 'payment_status' => 'refunded'])->save();

        $row = $this->month()->rows()->sole();

        $this->assertSame([31500, 0, 0, 0], [$row->refunded, $row->commission, $row->stableShare(), $row->net()]);
        $this->assertSame(1575, $row->collected());
    }

    public function test_the_period_carries_its_opening_balance(): void
    {
        Carbon::setTestNow('2026-09-20 05:30:00');
        $this->paidBooking(1);                              // September: 14.250 owed

        Carbon::setTestNow('2026-10-04 05:30:00');
        app(StableSettlements::class)->record($this->stable, StableSettlement::PAYOUT, '10', '2026-10-02');
        $this->paidBooking(1);                              // October: another 14.250

        $october = $this->month();

        $this->assertSame(14250, $october->opening());
        $this->assertCount(1, $october->rows());
        $this->assertSame(14250 + 14250 - 10000, $october->closing());
        $this->assertSame('We owe the stable OMR 18.500.', app(StableStatementExport::class)->balanceSentence($october->closing()));
    }

    public function test_the_statement_downloads_as_pdf_and_csv(): void
    {
        $this->paidBooking(2);
        app(StableSettlements::class)->record($this->stable, StableSettlement::PAYOUT, '5', '2026-10-04', 'cash', 'CASH-1');
        $export = app(StableStatementExport::class);

        $pdf = $export->pdf($this->month(), 'ar');
        $this->assertStringStartsWith('%PDF', $pdf);

        $out = fopen('php://memory', 'w+');
        $export->csv($out, $this->month(), 'en');
        rewind($out);
        $csv = stream_get_contents($out);

        $this->assertStringContainsString('"Balance carried forward",23.500', $csv);
        $this->assertStringContainsString('"Online, through us"', $csv);
        $this->assertStringContainsString('CASH-1', $csv);

        // the PDF view itself, in English
        $html = $export->html($this->month(), 'en');
        $this->assertStringContainsString('Stable statement', $html);
        $this->assertStringContainsString('We owe the stable OMR 23.500.', $html);
    }

    public function test_recording_a_payout_tells_the_owner(): void
    {
        Queue::fake();

        app(StableSettlements::class)->record($this->stable, StableSettlement::PAYOUT, '12.5', '2026-10-04', 'bank_transfer', 'TRX-9');

        Queue::assertPushed(SendStableEmail::class, fn (SendStableEmail $job) => $job->to === 'owner@example.com' && $job->type === 'stable_payout');
        $sms = NotificationLog::query()->where('type', 'stable_payout')->sole();
        $this->assertStringContainsString('We paid you OMR 12.500', $sms->message);
    }

    // ── screens ──────────────────────────────────────────────────────────────

    public function test_the_owner_sees_the_statement_and_the_month(): void
    {
        $this->paidBooking(2);

        $this->actingAs($this->owner)
            ->get('/stable/'.$this->stable->slug.'/statement')
            ->assertOk()
            ->assertSee('Stable statement')
            ->assertSee('We owe the stable OMR 28.500.')
            ->assertSee('Online, through us');

        Filament::setCurrentPanel(Filament::getPanel('stable'));
        Filament::setTenant($this->stable);
        Filament::bootCurrentPanel();

        Livewire::test(StableFinanceOverview::class)
            ->assertSee('Your share')
            ->assertSee('28.500')
            ->assertSee('We owe you this');

        // commission and share per booking on the bookings list
        $this->get('/stable/'.$this->stable->slug.'/stable-bookings')->assertOk()->assertSee('3.000')->assertSee('28.500');

        Livewire::test(Statement::class)->callAction('downloadPdf')->assertFileDownloaded();
    }

    public function test_the_admin_sees_balances_and_records_a_payout(): void
    {
        Queue::fake();
        $this->paidBooking(2);
        $other = $this->makeStable(null, ['slug' => 'no-bookings-yet']);

        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(StableBalances::class)
            ->assertOk()
            ->assertSee('Desert Riders')
            ->assertSee('we owe the stable')
            ->assertCanNotSeeTableRecords([$other])
            ->callTableAction('payout', $this->stable, ['amount' => '28.5', 'paid_on' => '2026-10-04', 'method' => 'bank_transfer', 'reference' => 'TRX-1'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(0, StableStatement::balance($this->stable));
        $this->assertSame(1, StableSettlement::query()->count());

        Livewire::withQueryParams(['stable' => $this->stable->id])
            ->test(StableStatementReport::class)
            ->assertOk()
            ->assertSee('Nothing is owed either way.')
            ->assertSee('TRX-1');
    }
}
