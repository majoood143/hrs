<?php

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Enums\StableBookingStatus;
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
use App\Services\Orders\OrderReceiptPdf;
use App\Services\Orders\OrderRefundService;
use App\Services\Payments\OrderPaymentService;
use App\Services\Reports\ClosedBooks;
use App\Services\Reports\IncomeStatement;
use App\Services\Reports\StableLedgerRow;
use App\Services\Reports\StableStatement;
use App\Services\Reports\StableStatementExport;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\CreateStableBooking;
use App\Services\Stables\SlotGenerator;
use App\Services\Stables\StableBookingActions;
use App\Services\Stables\StableSettlements;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
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
        Queue::fake();
        $booking = $this->paidBooking(2);
        // the whole client share (31.500) went back to the customer: nothing left to share, the fee stays ours
        app(OrderRefundService::class)->record($booking->order, 31500);

        $row = StableLedgerRow::fromOrder($booking->order->refresh());

        $this->assertSame([31500, 0, 0, 0], [$row->refunded, $row->commission, $row->stableShare(), $row->net()]);
        $this->assertSame(1575, $row->collected());
    }

    public function test_a_paid_booking_called_off_is_held_back_until_its_refund_is_recorded(): void
    {
        Queue::fake();
        $booking = $this->paidBooking(1);
        $this->assertSame(14250, StableStatement::payable($this->stable));

        // the money goes back to the customer: not ours to pay out to the stable meanwhile
        app(StableBookingActions::class)->cancelByStable($booking, 'Sick horse');
        $this->assertSame(14250, StableStatement::heldForRefund($this->stable));
        $this->assertSame(0, StableStatement::payable($this->stable));
        $this->assertStringContainsString('14.250 held until a cancelled booking is refunded', StableStatement::balanceLabel($this->stable));

        // 10.000 refunded: the rest is the stable's again, less the commission it still earns
        app(OrderRefundService::class)->record($booking->order->refresh(), 10000);
        $this->assertSame(0, StableStatement::heldForRefund($this->stable));
        $this->assertSame(5750 - 548, StableStatement::payable($this->stable));
    }

    public function test_the_fee_goes_back_too_when_the_customer_did_not_call_it_off(): void
    {
        Queue::fake();
        $booking = $this->paidBooking(1);
        $order = $booking->order;

        // the customer's own cancellation: price and its VAT only
        $order->forceFill(['status' => 'cancelled', 'cancellation_source' => 'customer'])->save();
        $this->assertSame(15.75, $order->refresh()->refundableAmount());

        // the stable's: everything they paid, our fee included
        $order->forceFill(['cancellation_source' => 'stable'])->save();
        $this->assertSame(16.538, $order->refresh()->refundableAmount());

        app(OrderRefundService::class)->record($order, 16538);
        $row = StableLedgerRow::fromOrder($order->refresh()->load('refunds'));
        $this->assertSame([0, 0, 0], [$row->stableShare(), $row->ourShare(), $row->collected()]);
        $this->assertSame(0, StableStatement::balance($this->stable));
        $this->assertSame(0, IncomeStatement::forPeriod('this_month')->totals()['due_to_us']);
    }

    public function test_a_refund_is_dated_the_day_it_was_recorded_and_leaves_the_past_month_alone(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-09-20 05:30:00');
        $booking = $this->paidBooking(1);

        Carbon::setTestNow('2026-10-04 05:30:00');
        app(OrderRefundService::class)->record($booking->order->refresh(), 10000);

        $september = StableStatement::forPeriod($this->stable->refresh(), 'custom', '2026-09-01', '2026-09-30');
        $this->assertSame([14250, 1], [$september->closing(), $september->totals()['bookings']]);

        // October: the refund only, 10.000 back and 0.952 of the commission with it
        $october = $this->month();
        $this->assertSame([14250, -9048, 0], [$october->opening(), $october->totals()['net'], $october->totals()['bookings']]);
        $this->assertTrue($october->rows()->sole()->isRefund());
        $this->assertSame(5202, $october->closing());

        $this->assertSame(2288, IncomeStatement::forPeriod('custom', '2026-09-01', '2026-09-30')->totals()['due_to_us']);
        $income = IncomeStatement::forPeriod('this_month')->totals();
        $this->assertSame([0, 10000, -952], [$income['orders'], $income['refunded'], $income['due_to_us']]);
    }

    public function test_a_closed_month_takes_no_backdated_settlement_or_attendance_change(): void
    {
        $books = app(ClosedBooks::class);

        try {
            $books->close('2026-10');
            $this->fail('The current month was closed.');
        } catch (InvalidArgumentException) {
        }

        $books->close('2026-09');
        $this->assertSame('2026-09-30', ClosedBooks::through());

        try {
            app(StableSettlements::class)->record($this->stable, StableSettlement::PAYOUT, '5', '2026-09-30');
            $this->fail('A payout was dated in a closed month.');
        } catch (InvalidArgumentException) {
        }
        app(StableSettlements::class)->record($this->stable, StableSettlement::PAYOUT, '5', '2026-10-01');

        // a pay-at-the-stable session attended in October, then October closed: it stays attended
        $late = BookingSlot::query()->where('date', '2026-10-27')->sole();
        $booking = app(CreateStableBooking::class)->handle($late, [['name' => 'Omar']], ['name' => 'Aisha', 'phone' => '91234567'], 'at_stable');
        Carbon::setTestNow('2026-10-27 08:00:00');
        app(StableBookingActions::class)->markAttended($booking);

        Carbon::setTestNow('2026-11-01 08:00:00');
        $books->close('2026-10');
        $this->expectException(BookingUnavailable::class);
        app(StableBookingActions::class)->markNoShow($booking);
    }

    public function test_gateway_charges_are_fixed_on_the_payment_and_stable_collected_fees_are_reported(): void
    {
        $this->stableSiteSettings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', 'vat.enabled' => true, 'vat.rate' => '5',
            'payments.gateway_costs' => json_encode(['thawani' => ['percent' => 2.5, 'fixed' => 0.1]])]);

        $booking = $this->booking(1);
        app(OrderPaymentService::class)->applyPaid($booking->order, PaymentGateway::Thawani, 'T-1');
        $this->assertSame('0.513', $booking->order->refresh()->gateway_cost);   // 2.5% of 16.538 + 0.100

        $atStable = $this->booking(1, 'at_stable');
        Carbon::setTestNow('2026-10-06 08:00:00');
        app(StableBookingActions::class)->markAttended($atStable);

        $income = IncomeStatement::forPeriod('this_month');
        $this->assertSame([2288, 513, 1775], [$income->totals()['due_to_us'], $income->totals()['gateway_cost'], $income->totals()['net_to_us']]);
        $this->assertSame(2288, $income->dueFromStables());
    }

    public function test_attendance_is_final_after_the_window_and_unmarked_sessions_are_billed(): void
    {
        $marked = $this->booking(1, 'at_stable');
        $unmarked = $this->booking(1, 'at_stable');
        $actions = app(StableBookingActions::class);

        Carbon::setTestNow('2026-10-06 08:00:00');
        $actions->markAttended($marked);

        // the last day of the window: still open, and the sweep leaves it to the stable
        Carbon::setTestNow('2026-10-13 08:00:00');
        $this->artisan('stables:close-attendance')->assertSuccessful();
        $this->assertSame(StableBookingStatus::Confirmed, $unmarked->refresh()->status);

        Carbon::setTestNow('2026-10-14 08:00:00');
        try {
            $actions->markNoShow($marked);   // would wipe what the stable owes us
            $this->fail('A closed session was changed.');
        } catch (BookingUnavailable) {
            $this->assertSame(StableBookingStatus::Completed, $marked->refresh()->status);
        }

        $this->artisan('stables:close-attendance')->assertSuccessful();
        $this->assertSame(StableBookingStatus::Completed, $unmarked->refresh()->status);
        $this->assertSame(0, StableStatement::unmarkedAtStable($this->stable));
        $this->assertSame(2 * 2288, $this->month()->totals()['owed_by_stable']);
    }

    public function test_vat_on_the_price_is_the_stables_and_only_when_it_is_registered(): void
    {
        // an unregistered stable: no VAT on its session; our fee still carries ours
        $this->stable->forceFill(['vat_registered' => false, 'vat_number' => null])->save();
        $order = $this->booking(1)->order;
        $this->assertSame(['0.000', '0.038', '15.788', null], [$order->vat_on_price, $order->vat_on_fee, $order->total, $order->stable_vat_number]);

        // a registered stable while we are not: VAT on its price only, under its number
        $this->stableSiteSettings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', 'vat.enabled' => false, 'vat.rate' => '5']);
        $this->stable->forceFill(['vat_registered' => true, 'vat_number' => 'OM1100000099'])->save();
        $order = $this->booking(1)->order;
        $this->assertSame(['0.750', '0.000', '16.500', 'OM1100000099'], [$order->vat_on_price, $order->vat_on_fee, $order->total, $order->stable_vat_number]);
    }

    public function test_each_refund_is_a_numbered_credit_note_on_the_tax_invoice(): void
    {
        Queue::fake();
        $order = $this->paidBooking(1)->order;

        $first = app(OrderRefundService::class)->record($order, 5000);
        $second = app(OrderRefundService::class)->record($order->refresh(), 2000);
        $this->assertSame(['CN-2026-000001', 'CN-2026-000002'], [$first->credit_note_number, $second->credit_note_number]);

        $html = app(OrderReceiptPdf::class)->html($order->refresh(), 'en');
        $this->assertStringContainsString('Tax invoice and receipt', $html);
        $this->assertStringContainsString('Session supplied by', $html);
        $this->assertStringContainsString('OM1100000001', $html);
        $this->assertStringContainsString('CN-2026-000002', $html);
        $this->assertStringStartsWith('%PDF', app(OrderReceiptPdf::class)->render($order, 'ar'));
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

    public function test_the_months_rows_come_by_their_payment_day_without_the_older_history(): void
    {
        Carbon::setTestNow('2026-09-20 05:30:00');
        $this->paidBooking(1);                              // booked and paid in September
        $late = $this->booking(1);                          // booked in September…

        Carbon::setTestNow('2026-10-04 05:30:00');
        app(OrderPaymentService::class)->applyPaid($late->order, PaymentGateway::Demo, 'D-late');   // …paid in October

        // rows() first, as the dashboard asks: the narrowed query, not the whole history
        $this->assertSame([$late->order->id], $this->month()->rows()->pluck('orderId')->all());
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

    public function test_the_admin_closes_and_reopens_a_month_from_the_balances_page(): void
    {
        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(StableBalances::class)
            ->assertActionHidden('reopenMonth')
            ->callAction('closeMonth', ['month' => '2026-09', 'note' => 'September audited'])
            ->assertHasNoActionErrors()
            ->assertSee('Books closed through 2026-09-30')
            ->assertActionVisible('reopenMonth');

        $this->assertSame('2026-09-30', ClosedBooks::through());

        Livewire::test(StableBalances::class)->callAction('reopenMonth');
        $this->assertSame('2026-08-31', ClosedBooks::through());
    }
}
