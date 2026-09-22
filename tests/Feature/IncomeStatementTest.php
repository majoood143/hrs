<?php

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Services\Orders\CreateServiceOrder;
use App\Services\Orders\OrderMoney;
use App\Services\Orders\OrderPricing;
use App\Services\Reports\IncomeStatement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Tests\Concerns\PreparesOrderSite;
use Tests\TestCase;

class IncomeStatementTest extends TestCase
{
    use PreparesOrderSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareOrderSite();
        Carbon::setTestNow('2026-09-24 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** A paid order, paid on the given day. Money comes from the rules that exist when it is created. */
    private function paid(string $paidAt = '2026-09-24 10:00:00', float $price = 10.0, string $gateway = 'thawani', ?Service $service = null, float $refunded = 0): ServiceOrder
    {
        $order = app(CreateServiceOrder::class)->handle($service ?? $this->makeService($price), ['name' => 'Ali', 'phone' => '96891234567']);

        $order->forceFill([
            'payment_status' => $refunded > 0 && $refunded >= $order->clientShare() ? PaymentStatus::Refunded : PaymentStatus::Paid,
            'payment_method' => PaymentGateway::from($gateway),
            'paid_at' => $paidAt,
            'refunded_amount' => $refunded,
        ])->save();

        return $order->refresh();
    }

    private function statement(string $from = '2026-09-01', string $to = '2026-09-30', ?int $serviceId = null, ?string $gateway = null): IncomeStatement
    {
        return new IncomeStatement(CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay(), $serviceId, $gateway);
    }

    // ── Commission on an order ───────────────────────────────────────────────

    public function test_a_commission_comes_out_of_the_clients_share_and_is_not_added_to_the_total(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);

        $order = app(CreateServiceOrder::class)->handle($this->makeService(10), []);

        $this->assertSame('11.025', $order->total, 'the customer pays the same with or without a commission');
        $this->assertSame('1.000', $order->commission_amount);
        $this->assertSame('percentage', $order->commission_type);
        $this->assertSame('10.000', $order->commission_value);
        $this->assertNotNull($order->commission_setting_id);
    }

    public function test_no_rule_means_no_commission(): void
    {
        $order = app(CreateServiceOrder::class)->handle($this->makeService(10), []);

        $this->assertSame('0.000', $order->commission_amount);
        $this->assertNull($order->commission_setting_id);
        $this->assertNull($order->commission_type);
    }

    public function test_a_fixed_commission_never_exceeds_the_price(): void
    {
        $this->makeCommission('fixed', 3);

        $this->assertSame(3000, app(OrderPricing::class)->quote($this->makeService(10))->commission);
        $this->assertSame(2000, app(OrderPricing::class)->quote($this->makeService(2))->commission, 'capped at the 2.000 price');
    }

    public function test_the_most_specific_commission_wins(): void
    {
        $service = $this->makeService(10);
        $global = $this->makeCommission('percentage', 1);
        $forService = $this->makeCommission('percentage', 2, ['service_id' => $service->id]);
        $forForm = $this->makeCommission('percentage', 3, ['form_id' => 7]);
        $pricing = app(OrderPricing::class);

        $this->assertSame($forForm->id, $pricing->quote($service, 7)->commissionSetting->id);
        $this->assertSame($forService->id, $pricing->quote($service, 8)->commissionSetting->id);
        $this->assertSame($global->id, $pricing->quote($this->makeService(10, 'Other'))->commissionSetting->id);
    }

    public function test_inactive_and_out_of_date_commissions_are_ignored(): void
    {
        $this->makeCommission('percentage', 9, extra: ['is_active' => false]);
        $this->makeCommission('percentage', 8, extra: ['effective_to' => '2026-01-01']);
        $this->makeCommission('percentage', 7, extra: ['effective_from' => '2999-01-01']);

        $this->assertNull(app(OrderPricing::class)->quote($this->makeService(10))->commissionSetting);
    }

    public function test_a_free_service_has_no_commission_and_editing_a_rule_never_changes_an_old_order(): void
    {
        $rule = $this->makeCommission('percentage', 10);

        $free = app(CreateServiceOrder::class)->handle($this->makeService(0), []);
        $order = app(CreateServiceOrder::class)->handle($this->makeService(10), []);
        $rule->update(['commission_value' => 50]);

        $this->assertSame('0.000', $free->commission_amount);
        $this->assertSame('1.000', $order->refresh()->commission_amount);
        $this->assertSame('10.000', $order->commission_value);
    }

    public function test_a_refund_shrinks_the_commission_but_never_the_fee(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $order = $this->paid(refunded: 4);

        // client's share 10.500, 4.000 refunded: 6.500 of it is left, so 6.500 / 10.500 of the 1.000 commission
        $this->assertSame(0.619, $order->earnedCommission());
        $this->assertSame(0.525, $order->feeShare());
        $this->assertSame(1.144, $order->dueToUs());
    }

    public function test_the_commission_arithmetic_at_its_edges(): void
    {
        $this->assertSame(1000, OrderMoney::earnedCommission(1000, 10500, 0));
        $this->assertSame(619, OrderMoney::earnedCommission(1000, 10500, 4000));
        $this->assertSame(0, OrderMoney::earnedCommission(1000, 10500, 10500), 'fully refunded');
        $this->assertSame(0, OrderMoney::earnedCommission(1000, 10500, 99999), 'over-refunded');
        $this->assertSame(0, OrderMoney::earnedCommission(0, 10500, 0));
        $this->assertSame(0, OrderMoney::earnedCommission(1000, 0, 0));
        $this->assertSame(1000, OrderMoney::earnedCommission(1000, 10500, -5), 'a negative refund is nonsense, ignored');
    }

    // ── The statement ────────────────────────────────────────────────────────

    public function test_the_fee_is_what_is_due_to_us_and_the_rest_stays_with_the_client(): void
    {
        $this->makeFee('percentage', 5);
        $this->paid();

        $totals = $this->statement()->totals();

        $this->assertSame(1, $totals['orders']);
        $this->assertSame(11025, $totals['collected']);
        $this->assertSame(10000, $totals['price']);
        $this->assertSame(500, $totals['vat_on_price']);
        $this->assertSame(10500, $totals['client_gross']);
        $this->assertSame(500, $totals['fee']);
        $this->assertSame(25, $totals['vat_on_fee']);
        $this->assertSame(0, $totals['commission']);
        $this->assertSame(525, $totals['due_to_us']);
        $this->assertSame(10500, $totals['client_keeps']);
        $this->assertSame(0, $totals['refunded']);
    }

    public function test_a_commission_is_a_second_line_of_what_is_due(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $this->paid();

        $totals = $this->statement()->totals();

        $this->assertSame(11025, $totals['collected'], 'the customer still paid 11.025');
        $this->assertSame(1000, $totals['commission']);
        $this->assertSame(1525, $totals['due_to_us']);
        $this->assertSame(9500, $totals['client_keeps']);
    }

    public function test_a_refund_lowers_what_the_client_keeps_and_the_commission_but_not_the_fee(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $this->paid(refunded: 4);

        $totals = $this->statement()->totals();

        $this->assertSame(4000, $totals['refunded']);
        $this->assertSame(500, $totals['fee']);
        $this->assertSame(619, $totals['commission']);
        $this->assertSame(1144, $totals['due_to_us']);
        $this->assertSame(11025 - 4000 - 1144, $totals['client_keeps']);
    }

    public function test_a_fully_refunded_order_still_owes_us_its_fee(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $order = $this->paid(refunded: 10.5);

        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $totals = $this->statement()->totals();

        $this->assertSame(1, $totals['orders'], 'a refunded order stays in the statement');
        $this->assertSame(525, $totals['due_to_us'], 'the fee and its VAT are kept, the commission is gone');
        $this->assertSame(0, $totals['commission']);
        $this->assertSame(0, $totals['client_keeps']);
    }

    public function test_every_row_adds_up_whatever_the_amounts(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 7.5);

        foreach ([33.333, 0.1, 7.777, 120.0, 0.999, 1234.567] as $i => $price) {
            $this->paid('2026-09-'.str_pad((string) (10 + $i), 2, '0', STR_PAD_LEFT).' 09:00:00', $price, refunded: $i % 2 ? round($price / 3, 3) : 0);
        }

        foreach ($this->statement()->rows() as $row) {
            $this->assertSame($row->total, $row->clientKeeps() + $row->dueToUs() + $row->refunded, $row->orderNumber);
        }

        $totals = $this->statement()->totals();
        $this->assertSame($totals['collected'], $totals['client_keeps'] + $totals['due_to_us'] + $totals['refunded']);
        $this->assertSame($totals['collected'], $totals['price'] + $totals['vat_on_price'] + $totals['fee'] + $totals['vat_on_fee']);
    }

    // ── Which orders count ───────────────────────────────────────────────────

    public function test_orders_count_by_the_day_they_were_paid_both_ends_included(): void
    {
        $this->paid('2026-08-31 23:59:59');
        $first = $this->paid('2026-09-01 00:00:00');
        $last = $this->paid('2026-09-30 23:59:59');
        $this->paid('2026-10-01 00:00:00');

        $rows = $this->statement()->rows();

        $this->assertSame([$first->id, $last->id], $rows->pluck('orderId')->all());
    }

    public function test_unpaid_free_and_cancelled_orders_carry_no_money(): void
    {
        $this->paid();
        app(CreateServiceOrder::class)->handle($this->makeService(10), []);                       // pending payment
        app(CreateServiceOrder::class)->handle($this->makeService(0), []);                        // free
        $cancelled = app(CreateServiceOrder::class)->handle($this->makeService(10), []);
        $cancelled->update(['payment_status' => PaymentStatus::Cancelled, 'paid_at' => '2026-09-24 09:00:00']);
        $failed = app(CreateServiceOrder::class)->handle($this->makeService(10), []);
        $failed->update(['payment_status' => PaymentStatus::Failed]);

        $this->assertSame(1, $this->statement()->totals()['orders']);
    }

    public function test_the_statement_can_be_narrowed_to_a_service_or_a_gateway(): void
    {
        $passport = $this->makeService(10, 'Passport');
        $vaccine = $this->makeService(20, 'Vaccine');
        $this->paid(service: $passport, gateway: 'thawani');
        $this->paid(service: $vaccine, gateway: 'nbo');
        $this->paid(service: $vaccine, gateway: 'thawani');

        $this->assertSame(3, $this->statement()->totals()['orders']);
        $this->assertSame(2, $this->statement(serviceId: $vaccine->id)->totals()['orders']);
        $this->assertSame(2, $this->statement(gateway: 'thawani')->totals()['orders']);
        $this->assertSame(1, $this->statement(serviceId: $vaccine->id, gateway: 'nbo')->totals()['orders']);
        $this->assertSame(0, $this->statement(serviceId: 999)->totals()['orders']);
    }

    // ── Groupings ────────────────────────────────────────────────────────────

    public function test_every_day_of_the_period_is_there_and_the_days_add_up_to_the_total(): void
    {
        $this->makeFee('percentage', 5);
        $this->paid('2026-09-02 10:00:00');
        $this->paid('2026-09-02 15:00:00');
        $this->paid('2026-09-05 10:00:00');

        $days = $this->statement('2026-09-01', '2026-09-07')->byDay();

        $this->assertCount(7, $days);
        $this->assertSame(0, $days['2026-09-01']['collected']);
        $this->assertSame(2, $days['2026-09-02']['orders']);
        $this->assertSame(22050, $days['2026-09-02']['collected']);
        $this->assertSame(1050, $days['2026-09-02']['due_to_us']);
        $this->assertSame(1, $days['2026-09-05']['orders']);
        $this->assertSame($this->statement('2026-09-01', '2026-09-07')->totals()['collected'], $days->sum('collected'));
    }

    public function test_services_are_ranked_by_what_they_earn_us_and_named_in_the_current_language(): void
    {
        $this->makeFee('percentage', 5);
        $small = $this->makeService(10, 'Small service');
        $big = $this->makeService(100, 'Big service');
        $this->paid(service: $small);
        $this->paid(service: $big);
        $this->paid(service: $big);

        $groups = $this->statement()->byService();

        $this->assertSame(['Big service', 'Small service'], $groups->pluck('label')->all());
        $this->assertSame(2, $groups[0]['totals']['orders']);

        app()->setLocale('ar');
        $this->assertSame('جواز حصان', $this->statement()->byService()->first()['label'], 'the Arabic name');
    }

    public function test_gateways_are_ranked_by_what_they_collected(): void
    {
        $this->paid(gateway: 'nbo');
        $this->paid(price: 50, gateway: 'thawani');
        $this->paid(price: 50, gateway: 'thawani');

        $groups = $this->statement()->byGateway();

        $this->assertSame(['Thawani', 'NBO'], $groups->pluck('label')->all());
    }

    public function test_an_empty_period_is_all_zeros_not_an_error(): void
    {
        $statement = $this->statement('2027-01-01', '2027-01-31');

        $this->assertSame(0, $statement->totals()['orders']);
        $this->assertSame(0, $statement->totals()['due_to_us']);
        $this->assertCount(31, $statement->byDay());
        $this->assertCount(0, $statement->byService());
    }

    // ── Periods ──────────────────────────────────────────────────────────────

    public function test_the_named_periods(): void
    {
        $today = Carbon::parse('2026-09-24 12:00:00'); // a Thursday

        $range = fn (string $period, ?string $from = null, ?string $to = null) => array_map(fn ($d) => $d->format('Y-m-d H:i:s'), IncomeStatement::period($period, $from, $to, $today));

        $this->assertSame(['2026-09-24 00:00:00', '2026-09-24 23:59:59'], $range('today'));
        $this->assertSame(['2026-09-21 00:00:00', '2026-09-27 23:59:59'], $range('this_week'));
        $this->assertSame(['2026-09-01 00:00:00', '2026-09-30 23:59:59'], $range('this_month'));
        $this->assertSame(['2026-08-01 00:00:00', '2026-08-31 23:59:59'], $range('last_month'));
        $this->assertSame(['2026-01-01 00:00:00', '2026-12-31 23:59:59'], $range('this_year'));
        $this->assertSame(['2026-09-05 00:00:00', '2026-09-12 23:59:59'], $range('custom', '2026-09-05', '2026-09-12'));
        $this->assertSame(['2026-09-01 00:00:00', '2026-09-30 23:59:59'], $range('nonsense'));
    }

    public function test_custom_dates_given_the_wrong_way_round_are_swapped_and_gaps_are_filled(): void
    {
        $today = Carbon::parse('2026-09-24 12:00:00');
        $range = fn (?string $from, ?string $to) => array_map(fn ($d) => $d->format('Y-m-d'), IncomeStatement::period('custom', $from, $to, $today));

        $this->assertSame(['2026-09-05', '2026-09-12'], $range('2026-09-12', '2026-09-05'));
        $this->assertSame(['2026-09-01', '2026-09-24'], $range(null, null), 'from the start of the month to today');
    }

    public function test_last_month_from_the_end_of_a_long_month_is_the_previous_month(): void
    {
        $range = fn (string $day) => array_map(fn ($d) => $d->format('Y-m-d'), IncomeStatement::period('last_month', null, null, Carbon::parse($day)));

        $this->assertSame(['2026-02-01', '2026-02-28'], $range('2026-03-31'));
        $this->assertSame(['2025-12-01', '2025-12-31'], $range('2026-01-15'));
    }
}
