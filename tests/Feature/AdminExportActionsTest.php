<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\ServiceOrderResource\Pages\ViewServiceOrder;
use App\Services\Exports\ExportCsv;
use App\Services\Exports\ExportDocument;
use App\Services\Exports\ExportValue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

/** The "PDF" / "CSV" header buttons every resource's list and view page gets from HasExportActions. */
class AdminExportActionsTest extends TestCase
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

    private function downloaded(Testable $page): string
    {
        return base64_decode($page->effects['download']['content']);
    }

    public function test_list_pages_get_a_pdf_and_a_csv_button_per_language(): void
    {
        Livewire::test(ListCustomers::class)
            ->assertActionExists('exportPdfEn')
            ->assertActionExists('exportPdfAr')
            ->assertActionExists('exportCsvEn')
            ->assertActionExists('exportCsvAr');
    }

    public function test_the_list_csv_is_the_table_in_the_chosen_language(): void
    {
        $this->makeCustomer('96891234567', ['name' => 'Ali Al Balushi']);
        $this->makeCustomer('96899999999', ['name' => 'Second']);

        $page = Livewire::test(ListCustomers::class)->callAction('exportCsvEn');
        $page->assertFileDownloaded('customers-en-'.now()->format('Y-m-d').'.csv');
        $csv = $this->downloaded($page);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(__('admin_customer.columns.phone', [], 'en'), $csv);
        $this->assertStringContainsString('Ali Al Balushi', $csv);
        $this->assertStringContainsString('Second', $csv);

        // the admin panel is in English, the export is not
        $arabic = $this->downloaded(Livewire::test(ListCustomers::class)->callAction('exportCsvAr'));
        $this->assertStringContainsString(__('admin_customer.columns.phone', [], 'ar'), $arabic);
        $this->assertStringNotContainsString(__('admin_customer.columns.phone', [], 'en'), $arabic);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_the_list_export_follows_the_table_search(): void
    {
        $this->makeCustomer('96891234567', ['name' => 'Ali Al Balushi']);
        $this->makeCustomer('96899999999', ['name' => 'Second']);

        $csv = $this->downloaded(Livewire::test(ListCustomers::class)->searchTable('Ali')->callAction('exportCsvEn'));

        $this->assertStringContainsString('Ali Al Balushi', $csv);
        $this->assertStringNotContainsString('Second', $csv);
    }

    public function test_the_list_pdf_is_a_pdf_in_either_language(): void
    {
        $this->makeCustomer('96891234567', ['name' => 'Ali Al Balushi']);

        foreach (['en', 'ar'] as $locale) {
            $page = Livewire::test(ListCustomers::class)->callAction('exportPdf'.ucfirst($locale));
            $page->assertFileDownloaded('customers-'.$locale.'-'.now()->format('Y-m-d').'.pdf');
            $this->assertStringStartsWith('%PDF', $this->downloaded($page));
        }
    }

    public function test_a_view_page_exports_the_record_details(): void
    {
        $order = $this->paidOrderFor($this->makeCustomer());

        $page = Livewire::test(ViewServiceOrder::class, ['record' => $order->getKey()])->callAction('exportCsvEn');
        $page->assertFileDownloaded('service-order-'.$order->getKey().'-en-'.now()->format('Y-m-d').'.csv');
        $csv = $this->downloaded($page);

        $this->assertStringContainsString($order->order_number, $csv);

        $pdf = Livewire::test(ViewServiceOrder::class, ['record' => $order->getKey()])->callAction('exportPdfAr');
        $this->assertStringStartsWith('%PDF', $this->downloaded($pdf));
    }

    public function test_values_are_plain_text(): void
    {
        $this->assertSame('OMR 12.500', ExportValue::text(new HtmlString('<img src="x.svg" alt="OMR" class="h-3"> 12.500')));
        $this->assertSame("a\nb", ExportValue::text(new HtmlString('<p>a</p><p>b</p>')));
        $this->assertSame('x, y', ExportValue::text(['x', 'y']));
        $this->assertSame(__('admin_export.yes'), ExportValue::text(true));
    }

    public function test_the_csv_does_not_let_a_spreadsheet_run_formulas(): void
    {
        $document = new ExportDocument(ExportDocument::TABLE, 'T', null, [], ['A', 'B'], fn () => [['=HYPERLINK("x")', '-5']], 1);

        $out = fopen('php://memory', 'w+b');
        (new ExportCsv)->write($out, $document);
        rewind($out);
        $csv = stream_get_contents($out);

        $this->assertStringContainsString("\"'=HYPERLINK(\"\"x\"\")\",-5", $csv);
    }
}
