<?php

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Filament\Pages\IncomeReport;
use App\Filament\Pages\Settings\PaymentGateways;
use App\Filament\Resources\ServiceOrderResource\Pages\ViewServiceOrder;
use App\Models\CommissionSetting;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use App\Services\Orders\CreateServiceOrder;
use App\Services\Orders\OrderPricing;
use App\Services\Reports\IncomeStatement;
use App\Services\Reports\StatementCsv;
use App\Services\Reports\StatementPdf;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\PreparesOrderSite;
use Tests\TestCase;

/** The switch that adds VAT to the commission we charge the client: off by default, a snapshot per order. */
class VatOnCommissionTest extends TestCase
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

    private function paid(float $refunded = 0): ServiceOrder
    {
        $order = app(CreateServiceOrder::class)->handle($this->makeService(10), ['name' => 'Ali', 'phone' => '96891234567']);
        $order->forceFill([
            'payment_status' => PaymentStatus::Paid, 'payment_method' => PaymentGateway::Thawani,
            'paid_at' => '2026-09-24 10:00:00', 'refunded_amount' => $refunded,
        ])->save();

        return $order->refresh();
    }

    private function statement(): IncomeStatement
    {
        return new IncomeStatement(CarbonImmutable::parse('2026-09-01')->startOfDay(), CarbonImmutable::parse('2026-09-30')->endOfDay());
    }

    private function switchOn(array $extra = []): void
    {
        $this->seedSiteSettings(['vat.on_commission' => true] + $extra);
    }

    // ── On the order ─────────────────────────────────────────────────────────

    public function test_it_is_off_by_default(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);

        $order = $this->paid();

        $this->assertSame('1.000', $order->commission_amount);
        $this->assertSame('0.000', $order->vat_on_commission);
        $this->assertSame(1.525, $order->dueToUs(), 'fee 0.500 + its VAT 0.025 + commission 1.000, nothing on the commission');
    }

    public function test_switched_on_the_vat_is_worked_out_on_the_commission_and_kept_off_the_customers_total(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $this->switchOn();

        $order = $this->paid();

        $this->assertSame('11.025', $order->total, 'the customer pays the same');
        $this->assertSame('1.000', $order->commission_amount);
        $this->assertSame('0.050', $order->vat_on_commission);
        $this->assertSame(0.05, $order->earnedVatOnCommission());
        $this->assertSame(1.575, $order->dueToUs(), 'fee 0.500 + its VAT 0.025 + commission 1.000 + its VAT 0.050');
    }

    public function test_it_needs_vat_to_be_on_and_a_commission_to_apply_to(): void
    {
        $this->makeFee('percentage', 5);
        $pricing = app(OrderPricing::class);

        $this->makeCommission('percentage', 10);
        $this->seedSiteSettings(['vat.on_commission' => true, 'vat.enabled' => false]);
        $this->assertSame(0, $pricing->quote($this->makeService(10))->vatOnCommission, 'VAT is switched off altogether');

        CommissionRuleCleaner::clear();
        $this->switchOn();
        $this->assertSame(0, $pricing->quote($this->makeService(10, 'Other'))->vatOnCommission, 'no commission, so nothing to add VAT to');
    }

    public function test_the_vat_on_a_fixed_commission_rounds_half_up_in_baisa(): void
    {
        $this->makeCommission('fixed', 0.75);
        $this->switchOn();

        $quote = app(OrderPricing::class)->quote($this->makeService(10));

        $this->assertSame(750, $quote->commission);
        $this->assertSame(38, $quote->vatOnCommission, '5% of 750 baisa is 37.5, rounded half up');
    }

    public function test_an_order_keeps_what_it_was_created_with_when_the_switch_is_flipped_later(): void
    {
        $this->makeCommission('percentage', 10);
        $before = $this->paid();
        $this->switchOn();
        $after = $this->paid();

        $this->assertSame('0.000', $before->refresh()->vat_on_commission);
        $this->assertSame('0.050', $after->vat_on_commission);
        $this->seedSiteSettings(['vat.on_commission' => false]);
        $this->assertSame('0.050', $after->refresh()->vat_on_commission, 'switching it off again does not rewrite it');
    }

    public function test_a_refund_shrinks_the_vat_on_the_commission_along_with_the_commission(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $this->switchOn();

        $order = $this->paid(refunded: 4);

        // 6.500 of the 10.500 client share is left: commission 1.000 -> 0.619, its VAT 0.050 -> 0.031
        $this->assertSame(0.619, $order->earnedCommission());
        $this->assertSame(0.031, $order->earnedVatOnCommission());
        $this->assertSame(0.525 + 0.619 + 0.031, $order->dueToUs());
    }

    // ── In the statement ─────────────────────────────────────────────────────

    public function test_the_statement_adds_it_to_what_is_due_and_the_columns_still_add_up(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $this->switchOn();
        $this->paid();
        $this->paid(refunded: 4);

        $totals = $this->statement()->totals();

        $this->assertSame(1000 + 619, $totals['commission']);
        $this->assertSame(50 + 31, $totals['vat_on_commission']);
        $this->assertSame(2 * 525 + 1619 + 81, $totals['due_to_us']);
        $this->assertSame($totals['collected'], $totals['client_keeps'] + $totals['due_to_us'] + $totals['refunded']);
        foreach ($this->statement()->rows() as $row) {
            $this->assertSame($row->total, $row->clientKeeps() + $row->dueToUs() + $row->refunded);
        }
        $this->assertSame(1050, IncomeStatement::commissionWithVat(['commission' => 1000, 'vat_on_commission' => 50]));
        $this->assertSame(1000, IncomeStatement::commissionWithVat(['commission' => 1000]), 'a group with no VAT key');
    }

    public function test_the_statement_shows_the_extra_line_only_when_there_is_something_in_it(): void
    {
        $this->makeCommission('percentage', 10);
        $this->paid();
        $csv = app(StatementCsv::class);

        $withoutLines = collect($csv->summaryLines($this->statement()->totals()))->pluck(0);
        $this->assertNotContains(__('statement.vat_on_commission'), $withoutLines);

        $this->switchOn();
        $this->paid();
        $lines = collect($csv->summaryLines($this->statement()->totals()))->pluck(0);

        $this->assertContains(__('statement.vat_on_commission'), $lines);
        $this->assertSame(
            $lines->search(__('statement.commission')) + 1,
            $lines->search(__('statement.vat_on_commission')),
            'right after the commission it belongs to',
        );
    }

    public function test_the_commission_column_then_carries_the_vat_and_is_labelled_so(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $this->switchOn();
        $order = $this->paid();

        $stream = fopen('php://memory', 'w+');
        app(StatementCsv::class)->write($stream, $this->statement(), 'en');
        rewind($stream);
        $rows = array_map('str_getcsv', explode("\n", trim(substr(stream_get_contents($stream), 3))));

        $header = collect($rows)->first(fn ($r) => ($r[0] ?? null) === __('statement.service'));
        $this->assertSame(__('statement.commission_and_vat'), $header[4]);
        $orderRow = collect($rows)->first(fn ($r) => ($r[1] ?? null) === $order->order_number);
        $this->assertSame(['2026-09-24 10:00', $order->order_number, '11.025', '0.500', '0.025', '1.050', '0.000', '1.575'], $orderRow);
        $this->assertSame('0.050', collect($rows)->first(fn ($r) => ($r[0] ?? null) === __('statement.vat_on_commission'))[1]);
        $this->assertSame('1.575', collect($rows)->first(fn ($r) => ($r[0] ?? null) === __('statement.due_to_us'))[1]);
    }

    public function test_the_pdf_shows_the_line_and_the_label(): void
    {
        $this->makeCommission('percentage', 10);
        $this->switchOn();
        $this->paid();

        $html = app(StatementPdf::class)->html($this->statement(), 'en');

        $this->assertStringContainsString(__('statement.vat_on_commission'), $html);
        $this->assertStringContainsString(__('statement.commission_and_vat'), $html);
        $this->assertStringContainsString('OMR 0.050', $html);
        $this->assertStringContainsString(__('statement.vat_on_commission', [], 'ar'), app(StatementPdf::class)->html($this->statement(), 'ar'));
        $this->assertStringStartsWith('%PDF-', app(StatementPdf::class)->render($this->statement(), 'en'));
    }

    public function test_without_it_the_statement_looks_exactly_as_before(): void
    {
        $this->makeCommission('percentage', 10);
        $this->paid();

        $html = app(StatementPdf::class)->html($this->statement(), 'en');

        $this->assertStringNotContainsString(__('statement.vat_on_commission'), $html);
        $this->assertStringNotContainsString(__('statement.commission_and_vat'), $html);
    }

    // ── In the admin ─────────────────────────────────────────────────────────

    private function admin(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_report_page_and_the_order_page_show_it(): void
    {
        $this->admin();
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $this->switchOn();
        $order = $this->paid();

        Livewire::test(IncomeReport::class)
            ->assertSee(__('statement.vat_on_commission'))
            ->assertSee(__('statement.commission_and_vat'))
            ->assertSee('OMR 1.575');

        Livewire::test(ViewServiceOrder::class, ['record' => $order->getKey()])
            ->assertSee(__('admin_service_order.fields.vat_on_commission'))
            ->assertSee('OMR 0.050')
            ->assertSee('OMR 1.575');
    }

    public function test_the_setting_is_saved_with_the_vat_settings_and_only_while_vat_is_on(): void
    {
        $this->admin();
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
            ->fillForm(['enabled_gateways' => ['demo'], 'vat' => ['enabled' => true, 'rate' => 5, 'on_commission' => true]])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('true', DB::table('site_settings')->where('key', 'vat.on_commission')->value('value'));

        Livewire::test(PaymentGateways::class)
            ->assertFormSet(['vat.on_commission' => true])
            ->fillForm(['vat' => ['enabled' => false, 'on_commission' => true]])
            ->call('save');
        $this->assertSame('false', DB::table('site_settings')->where('key', 'vat.on_commission')->value('value'), 'no VAT at all means none on the commission');
    }
}

/** Removes every commission rule (a test helper kept out of the class above so it reads plainly). */
final class CommissionRuleCleaner
{
    public static function clear(): void
    {
        CommissionSetting::query()->delete();
    }
}
