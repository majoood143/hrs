<?php

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Filament\Pages\IncomeReport;
use App\Filament\Resources\CommissionSettingResource\Pages\CreateCommissionSetting;
use App\Filament\Resources\CommissionSettingResource\Pages\EditCommissionSetting;
use App\Filament\Resources\CommissionSettingResource\Pages\ListCommissionSettings;
use App\Filament\Resources\ServiceOrderResource\Pages\ViewServiceOrder;
use App\Filament\Widgets\IncomeChart;
use App\Filament\Widgets\IncomeOverview;
use App\Models\CommissionSetting;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Services\Orders\CreateServiceOrder;
use App\Services\Reports\IncomeStatement;
use App\Services\Reports\StatementCsv;
use App\Services\Reports\StatementPdf;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Packstub\FormBuilder\Models\Form;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

/** The income report page, its downloads, the dashboard widgets and the commission screen. */
class IncomeReportPagesTest extends TestCase
{
    use MakesOrderForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareFormSite();
        Carbon::setTestNow('2026-09-24 12:00:00');

        Gate::before(fn () => true);
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): Authenticatable
    {
        return new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];
        };
    }

    private function paid(string $paidAt = '2026-09-24 10:00:00', float $price = 10.0, string $gateway = 'thawani', ?Service $service = null, float $refunded = 0): ServiceOrder
    {
        $order = app(CreateServiceOrder::class)->handle($service ?? $this->makeService($price), ['name' => 'Ali', 'phone' => '96891234567']);
        $order->forceFill(['payment_status' => PaymentStatus::Paid, 'payment_method' => PaymentGateway::from($gateway), 'paid_at' => $paidAt, 'refunded_amount' => $refunded])->save();

        return $order->refresh();
    }

    private function statement(string $from = '2026-09-01', string $to = '2026-09-30'): IncomeStatement
    {
        return new IncomeStatement(CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay());
    }

    // ── The page ─────────────────────────────────────────────────────────────

    public function test_the_page_shows_what_was_collected_and_what_is_due_to_us(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $this->paid();

        Livewire::test(IncomeReport::class)
            ->assertSuccessful()
            ->assertSee(__('admin_income_report.title'))
            ->assertSee('OMR 11.025')   // collected
            ->assertSee('OMR 1.525')    // due to us: fee 0.500 + VAT 0.025 + commission 1.000
            ->assertSee('OMR 9.500')    // stays with the client
            ->assertSee('Horse passport')
            ->assertSee('Thawani')
            ->assertSee('2026-09-01');
    }

    public function test_changing_the_filters_changes_the_figures(): void
    {
        // no fee rule: a 10 OMR passport collects 10.500 (with VAT), a 20 OMR vaccine 21.000
        $passport = $this->makeService(10, 'Passport');
        $vaccine = $this->makeService(20, 'Vaccine');
        $this->paid('2026-09-10 10:00:00', service: $passport);
        $this->paid('2026-08-10 10:00:00', service: $vaccine, gateway: 'nbo');

        $page = Livewire::test(IncomeReport::class);
        $page->assertSee('OMR 10.500')->assertDontSee('OMR 21.000');

        $page->fillForm(['period' => 'last_month'])->assertSee('OMR 21.000')->assertDontSee('OMR 10.500');
        $page->fillForm(['period' => 'custom', 'date_from' => '2026-08-01', 'date_to' => '2026-09-30'])->assertSee('OMR 10.500')->assertSee('OMR 21.000')->assertSee('OMR 31.500');
        $page->fillForm(['service_id' => $vaccine->id])->assertSee('OMR 21.000')->assertDontSee('OMR 10.500')->assertDontSee('OMR 31.500');
        $page->fillForm(['service_id' => null, 'gateway' => 'nbo'])->assertSee('OMR 21.000')->assertDontSee('OMR 10.500');
        $page->fillForm(['gateway' => 'thawani'])->assertSee('OMR 10.500')->assertDontSee('OMR 21.000');
    }

    public function test_an_empty_period_says_so(): void
    {
        Livewire::test(IncomeReport::class)
            ->fillForm(['period' => 'last_month'])
            ->assertSee(__('admin_income_report.empty'))
            ->assertDontSee(__('admin_income_report.sections.breakdown'));
    }

    public function test_the_page_is_in_the_payments_menu_and_has_its_own_route(): void
    {
        $this->assertSame(__('admin_navigation.payments'), IncomeReport::getNavigationGroup());
        $this->assertStringContainsString('income-report', IncomeReport::getUrl());
    }

    // ── Downloads ────────────────────────────────────────────────────────────

    public function test_the_statement_downloads_as_pdf_and_csv_named_after_the_period(): void
    {
        $this->paid();

        Livewire::test(IncomeReport::class)
            ->callAction('downloadPdf')
            ->assertFileDownloaded('income-statement-2026-09-01_2026-09-30.pdf');

        Livewire::test(IncomeReport::class)
            ->callAction('downloadCsv')
            ->assertFileDownloaded('income-statement-2026-09-01_2026-09-30.csv');
    }

    public function test_the_csv_has_the_summary_the_groupings_and_every_order(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $order = $this->paid();

        $stream = fopen('php://memory', 'w+');
        app(StatementCsv::class)->write($stream, $this->statement(), 'en');
        rewind($stream);
        $csv = stream_get_contents($stream);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'a byte-order mark so a spreadsheet reads UTF-8');
        $rows = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
        $find = fn (string $label) => collect($rows)->first(fn ($row) => ($row[0] ?? null) === $label);

        $this->assertSame(__('statement.title'), $rows[0][0]);
        $this->assertSame('2026-09-01 → 2026-09-30', $find(__('statement.period'))[1]);
        $this->assertSame('11.025', $find(__('statement.collected'))[1]);
        $this->assertSame('0.025', $find(__('statement.vat_on_fees'))[1]);
        $this->assertSame('1.000', $find(__('statement.commission'))[1]);
        $this->assertSame('1.525', $find(__('statement.due_to_us'))[1]);
        $this->assertSame('9.500', $find(__('statement.client_keeps'))[1]);
        $this->assertSame('Horse passport', $find('Horse passport')[0]);
        $orderRow = collect($rows)->first(fn ($row) => ($row[1] ?? null) === $order->order_number);
        $this->assertSame(['2026-09-24 10:00', $order->order_number, '11.025', '0.500', '0.025', '1.000', '0.000', '1.525'], $orderRow);
    }

    public function test_the_csv_follows_the_chosen_language_and_names_the_filters(): void
    {
        $service = $this->makeService(10, 'Passport');
        $this->paid(service: $service);

        $stream = fopen('php://memory', 'w+');
        app(StatementCsv::class)->write($stream, new IncomeStatement(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30')->endOfDay(), $service->id, 'thawani'), 'ar');
        rewind($stream);
        $csv = stream_get_contents($stream);

        $this->assertStringContainsString(__('statement.title', [], 'ar'), $csv);
        $this->assertStringContainsString(__('statement.due_to_us', [], 'ar'), $csv);
        $this->assertStringContainsString('جواز حصان', $csv, 'the service filter, in Arabic');
        $this->assertStringContainsString('Thawani', $csv);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_the_pdf_is_a_real_document_in_both_languages_with_cairo_only(): void
    {
        $this->makeFee('percentage', 5);
        $this->paid();
        $pdfs = app(StatementPdf::class);
        $outputs = [];

        foreach (['en', 'ar'] as $locale) {
            $pdf = $pdfs->render($this->statement(), $locale);

            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertGreaterThan(5000, strlen($pdf));
            preg_match_all('#/BaseFont\s*/[A-Z]+\+([A-Za-z-]+)#', $pdf, $m);
            $fonts = array_values(array_unique($m[1]));
            sort($fonts);
            $this->assertSame(['Cairo-Bold', 'Cairo-Regular'], $fonts, $locale);
            $outputs[$locale] = $pdf;
        }

        $this->assertNotSame($outputs['en'], $outputs['ar']);
    }

    public function test_the_pdf_says_what_the_client_owes_and_how_it_was_worked_out(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $this->seedSiteSettings(['vat.registration_number' => 'OM1100123456', 'enabled_gateways' => ['demo']]);
        $order = $this->paid();

        $html = app(StatementPdf::class)->html($this->statement(), 'en');

        foreach (['OMR 11.025', 'OMR 1.525', 'OMR 9.500', 'OMR 1.000', $order->order_number, 'Horse passport', 'OM1100123456', '2026-09-01', e(__('statement.note'))] as $expected) {
            $this->assertStringContainsString($expected, $html, $expected);
        }
        $this->assertStringContainsString('dir="ltr"', $html);
        $this->assertStringContainsString('dir="rtl"', app(StatementPdf::class)->html($this->statement(), 'ar'));
        $this->assertStringContainsString(__('statement.title', [], 'ar'), app(StatementPdf::class)->html($this->statement(), 'ar'));
    }

    public function test_an_empty_statement_still_produces_a_pdf(): void
    {
        $pdf = app(StatementPdf::class)->render($this->statement('2027-01-01', '2027-01-31'), 'en');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString(__('statement.empty'), app(StatementPdf::class)->html($this->statement('2027-01-01', '2027-01-31'), 'en'));
    }

    public function test_a_long_statement_still_renders(): void
    {
        $this->makeFee('percentage', 5);
        $service = $this->makeService(10);

        foreach (range(1, 60) as $i) {
            $this->paid('2026-09-'.str_pad((string) (1 + $i % 28), 2, '0', STR_PAD_LEFT).' 10:00:00', service: $service);
        }

        $pdf = app(StatementPdf::class)->render($this->statement(), 'en');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf), 'it runs over more than one page');
    }

    // ── Widgets ──────────────────────────────────────────────────────────────

    public function test_the_overview_shows_this_months_income(): void
    {
        $this->makeFee('percentage', 5);
        $this->paid('2026-09-24 09:00:00');
        $this->paid('2026-09-10 09:00:00');
        $this->paid('2026-08-20 09:00:00');

        Livewire::test(IncomeOverview::class)
            ->assertSuccessful()
            ->assertSee('OMR 22.050')   // collected this month
            ->assertSee('OMR 1.050')    // due to us (2 x 0.525)
            ->assertSee('OMR 21.000')   // stays with the client
            ->assertSee(__('admin_income_report.widgets.orders', ['count' => 2, 'today' => 'OMR 11.025']));
    }

    public function test_the_chart_has_a_bar_per_day_and_follows_the_filter(): void
    {
        $this->makeFee('percentage', 5);
        $this->paid('2026-09-24 09:00:00');
        $this->paid('2026-09-22 09:00:00');

        $data = fn (string $filter) => (function () use ($filter) {
            $this->filter = $filter;

            return $this->getData();
        })->call(Livewire::test(IncomeChart::class)->instance());

        $thirty = $data('30');
        $this->assertCount(30, $thirty['labels']);
        $this->assertCount(30, $thirty['datasets'][0]['data']);
        $this->assertSame(0.525, $thirty['datasets'][0]['data'][29], 'today: due to us');
        $this->assertSame(10.5, $thirty['datasets'][1]['data'][29], 'today: stays with the client');
        $this->assertSame(0.525, $thirty['datasets'][0]['data'][27], 'the 22nd');
        $this->assertSame(0, $thirty['datasets'][0]['data'][28], 'a day with nothing');
        $this->assertSame('Sep 24', $thirty['labels'][29]);
        $this->assertCount(7, $data('7')['labels']);
        $this->assertCount(90, $data('90')['labels']);
        $this->assertCount(30, $data('nonsense')['labels'], 'an unknown filter falls back to 30 days');
    }

    public function test_the_widgets_are_only_for_those_who_may_see_orders(): void
    {
        $this->assertTrue(IncomeOverview::canView());
        $this->assertTrue(IncomeChart::canView());

        // a user with no permissions: the "allow everything" gate of these tests is not in play
        $this->refreshApplication();
        $this->prepareFormSite();
        $this->actingAs($this->admin());

        $this->assertFalse(IncomeOverview::canView());
        $this->assertFalse(IncomeChart::canView());
    }

    // ── Commission settings ──────────────────────────────────────────────────

    public function test_a_commission_is_created_in_both_languages_and_edited_back(): void
    {
        $service = $this->makeService();
        $form = Form::create(['name' => ['en' => 'Passport form', 'ar' => 'نموذج الجواز'], 'slug' => 'passport']);

        Livewire::test(CreateCommissionSetting::class)
            ->fillForm([
                'name' => ['en' => 'Platform commission', 'ar' => 'عمولة المنصة'],
                'commission_type' => 'percentage',
                'commission_value' => '7.5',
                'service_id' => $service->id,
                'form_id' => $form->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rule = CommissionSetting::firstOrFail();
        $this->assertSame(['en' => 'Platform commission', 'ar' => 'عمولة المنصة'], $rule->getTranslations('name'));
        $this->assertSame('7.5%', $rule->formatted_value);
        $this->assertSame(__('admin_service_fee_setting.scope.form', ['id' => $form->id]), $rule->scope_name);

        Livewire::test(EditCommissionSetting::class, ['record' => $rule->getKey()])
            ->assertFormSet(['name.en' => 'Platform commission', 'commission_value' => '7.500'])
            ->fillForm(['commission_value' => '12'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('12.000', $rule->refresh()->commission_value);
    }

    public function test_a_commission_needs_a_name_and_a_value(): void
    {
        Livewire::test(CreateCommissionSetting::class)
            ->fillForm(['name' => ['en' => ''], 'commission_value' => ''])
            ->call('create')
            ->assertHasFormErrors(['name.en' => 'required', 'commission_value' => 'required']);
    }

    public function test_the_commission_list_shows_the_rate_and_where_it_applies(): void
    {
        $service = $this->makeService(10, 'Vaccination');
        $global = $this->makeCommission('percentage', 5);
        $fixed = $this->makeCommission('fixed', 0.5, ['service_id' => $service->id]);

        Livewire::test(ListCommissionSettings::class)
            ->assertCanSeeTableRecords([$global, $fixed])
            ->assertSee(__('admin_service_fee_setting.scope.global'))
            ->assertSee('Vaccination')
            ->assertSee('5%')
            ->assertSee('0.500 OMR');
    }

    public function test_the_order_page_shows_the_commission_and_what_is_due_to_us(): void
    {
        $this->makeFee('percentage', 5);
        $this->makeCommission('percentage', 10);
        $order = $this->paid();

        Livewire::test(ViewServiceOrder::class, ['record' => $order->getKey()])
            ->assertSee(__('admin_service_order.fields.commission'))
            ->assertSee('OMR 1.000')
            ->assertSee(__('admin_service_order.fields.due_to_us'))
            ->assertSee('OMR 1.525');

        $plain = $this->paid(service: $this->makeService(10, 'No commission'));
        $plain->update(['commission_amount' => 0]);
        Livewire::test(ViewServiceOrder::class, ['record' => $plain->getKey()])
            ->assertDontSee(__('admin_service_order.fields.commission'));
    }
}
