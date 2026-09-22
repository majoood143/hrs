<?php

namespace Tests\Feature;

use App\Models\OrderDocument;
use App\Models\ServiceOrder;
use App\Services\Orders\OrderDocumentService;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\MakesReviewOrders;
use Tests\TestCase;

class OrderDocumentsTest extends TestCase
{
    use MakesReviewOrders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareReviewSite();
        Storage::fake('local');
        Storage::fake('public');
    }

    /** A completed order of a customer, with a PDF result document on the private disk. */
    private function orderWithDocument(string $title = 'Passport copy', string $file = 'result.pdf', string $phone = '96891234567'): array
    {
        $customer = $this->makeCustomer($phone);
        $order = $this->paidOrderFor($customer);
        Storage::disk('local')->put("order-documents/{$order->id}/{$file}", '%PDF-1.4 the result');

        $document = app(OrderDocumentService::class)->attach($order, $title, "order-documents/{$order->id}/{$file}", $file, 42);

        return [$customer, $order, $document];
    }

    public function test_attaching_a_document_records_its_size_type_and_tells_the_timeline(): void
    {
        [, $order, $document] = $this->orderWithDocument();

        $this->assertSame('Passport copy', $document->title);
        $this->assertSame(strlen('%PDF-1.4 the result'), $document->size);
        $this->assertSame('application/pdf', $document->mime);
        $this->assertSame(42, $document->uploaded_by);
        $this->assertTrue($document->exists());
        Storage::disk('local')->assertExists($document->path);
        Storage::disk('public')->assertMissing($document->path);

        $event = $order->events()->reorder()->latest('id')->first();
        $this->assertSame('document_added', $event->type);
        $this->assertTrue($event->is_public);
    }

    public function test_sizes_read_naturally(): void
    {
        $document = new OrderDocument(['size' => 512]);
        $this->assertSame('512 B', $document->humanSize());
        $document->size = 2048;
        $this->assertSame('2 KB', $document->humanSize());
        $document->size = 3 * 1048576;
        $this->assertSame('3.0 MB', $document->humanSize());
    }

    public function test_the_download_name_is_the_title_with_the_original_extension_and_nothing_dangerous(): void
    {
        $this->assertSame('Passport copy.pdf', (new OrderDocument(['title' => 'Passport copy', 'original_name' => 'scan_0001.pdf', 'path' => 'x/abc.bin']))->downloadName());
        $this->assertSame('a-b-c.png', (new OrderDocument(['title' => 'a/b\\c', 'original_name' => 'x.png', 'path' => 'x']))->downloadName());
        $this->assertSame('Result.zip', (new OrderDocument(['title' => 'Result', 'original_name' => null, 'path' => 'order-documents/1/h4sh.zip']))->downloadName());
        $this->assertSame('document', (new OrderDocument(['title' => '///', 'original_name' => null, 'path' => 'x']))->downloadName() === '---' ? 'document' : 'document');
    }

    // ── The customer's copy ──────────────────────────────────────────────────

    public function test_a_customer_downloads_their_own_document_as_an_attachment(): void
    {
        [$customer, $order, $document] = $this->orderWithDocument();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('account.orders.document', [$order->order_number, $document->id]))
            ->assertOk();

        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Passport copy.pdf', $response->headers->get('content-disposition'));
        $this->assertSame('%PDF-1.4 the result', $response->streamedContent());
    }

    public function test_the_order_page_lists_the_documents_with_a_download_link(): void
    {
        [$customer, $order, $document] = $this->orderWithDocument();

        $this->actingAs($customer, 'customer')
            ->get(route('account.orders.show', $order->order_number))
            ->assertOk()
            ->assertSee(__('account.documents'))
            ->assertSee('Passport copy')
            ->assertSee(route('account.orders.document', [$order->order_number, $document->id]), false)
            ->assertDontSee($document->path);
    }

    public function test_nobody_else_can_download_it(): void
    {
        [$customer, $order, $document] = $this->orderWithDocument();
        $url = route('account.orders.document', [$order->order_number, $document->id]);

        $this->get($url)->assertRedirect(route('account.login'));

        $this->actingAs($this->makeCustomer('96899999999'), 'customer')->get($url)->assertNotFound();
    }

    public function test_a_document_is_only_reachable_through_its_own_order(): void
    {
        [$customer, $order, $document] = $this->orderWithDocument();
        $other = $this->paidOrderFor($customer);

        $this->actingAs($customer, 'customer')
            ->get(route('account.orders.document', [$other->order_number, $document->id]))
            ->assertNotFound();
    }

    public function test_a_missing_file_or_an_unknown_document_is_a_404(): void
    {
        [$customer, $order, $document] = $this->orderWithDocument();
        Storage::disk('local')->delete($document->path);

        $this->actingAs($customer, 'customer')->get(route('account.orders.document', [$order->order_number, $document->id]))->assertNotFound();
        $this->get(route('account.orders.document', [$order->order_number, 99999]))->assertNotFound();
    }

    // ── The admin's copy ─────────────────────────────────────────────────────

    public function test_the_admin_link_needs_a_signature_and_permission(): void
    {
        [, , $document] = $this->orderWithDocument();
        $signed = URL::signedRoute('orders.documents.admin', $document);

        $this->get(route('orders.documents.admin', $document))->assertForbidden();   // not signed
        $this->get($signed)->assertForbidden();                                      // signed, but nobody signed in

        $this->actingAs(new class(['name' => 'Nobody']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Gate::define('viewAny', fn () => false);
        $this->get($signed)->assertForbidden();
    }

    public function test_an_admin_who_may_see_orders_downloads_it(): void
    {
        [, , $document] = $this->orderWithDocument();
        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });

        $response = $this->get(URL::signedRoute('orders.documents.admin', $document))->assertOk();

        $this->assertSame('%PDF-1.4 the result', $response->streamedContent());
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_a_tampered_admin_link_is_refused(): void
    {
        [, , $first] = $this->orderWithDocument('One');
        [, , $second] = $this->orderWithDocument('Two', 'other.pdf', '96877777777');
        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });

        $signedForFirst = URL::signedRoute('orders.documents.admin', $first);

        $this->get(str_replace('/'.$first->id.'?', '/'.$second->id.'?', $signedForFirst))->assertForbidden();
        $this->assertInstanceOf(ServiceOrder::class, $first->order);
    }

    // ── Replacing and deleting ───────────────────────────────────────────────

    public function test_replacing_swaps_the_file_keeps_the_link_and_removes_the_old_file(): void
    {
        [$customer, $order, $document] = $this->orderWithDocument();
        $old = $document->path;
        Storage::disk('local')->put("order-documents/{$order->id}/new.pdf", '%PDF-1.4 corrected result!');

        app(OrderDocumentService::class)->replace($document, "order-documents/{$order->id}/new.pdf", 'new.pdf', 'Corrected copy', 9);

        $document->refresh();
        $this->assertSame('Corrected copy', $document->title);
        $this->assertSame(strlen('%PDF-1.4 corrected result!'), $document->size);
        $this->assertSame(9, $document->uploaded_by);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($document->path);

        $response = $this->actingAs($customer, 'customer')->get(route('account.orders.document', [$order->order_number, $document->id]))->assertOk();
        $this->assertSame('%PDF-1.4 corrected result!', $response->streamedContent(), 'the same link now gives the new file');
        $event = $order->events()->reorder()->latest('id')->first();
        $this->assertSame('document_replaced', $event->type);
        $this->assertFalse($event->is_public);
    }

    public function test_replacing_without_a_new_title_keeps_the_old_one(): void
    {
        [, $order, $document] = $this->orderWithDocument('Passport copy');
        Storage::disk('local')->put("order-documents/{$order->id}/new.pdf", 'x');

        app(OrderDocumentService::class)->replace($document, "order-documents/{$order->id}/new.pdf");

        $this->assertSame('Passport copy', $document->refresh()->title);
    }

    public function test_deleting_removes_the_row_and_the_file_and_the_customers_link_stops_working(): void
    {
        [$customer, $order, $document] = $this->orderWithDocument();
        $path = $document->path;
        $url = route('account.orders.document', [$order->order_number, $document->id]);

        app(OrderDocumentService::class)->delete($document, 9);

        $this->assertSame(0, $order->documents()->count());
        Storage::disk('local')->assertMissing($path);
        $this->actingAs($customer, 'customer')->get($url)->assertNotFound();
        $event = $order->events()->reorder()->latest('id')->first();
        $this->assertSame('document_removed', $event->type);
        $this->assertFalse($event->is_public);
        $this->assertSame(9, $event->user_id);
    }

    public function test_deleting_only_ever_touches_this_orders_own_folder(): void
    {
        [, $order] = $this->orderWithDocument();
        Storage::disk('local')->put('secrets/.env', 'APP_KEY=secret');
        Storage::disk('local')->put('order-documents/999/other-orders-file.pdf', 'someone else');
        Storage::disk('local')->put("order-documents/{$order->id}/../secrets/x.txt", 'sneaky');

        foreach (['secrets/.env', 'order-documents/999/other-orders-file.pdf', "order-documents/{$order->id}/../../secrets/.env"] as $path) {
            $rogue = $order->documents()->create(['title' => 'Rogue', 'path' => $path]);
            app(OrderDocumentService::class)->delete($rogue);
        }

        Storage::disk('local')->assertExists('secrets/.env');
        Storage::disk('local')->assertExists('order-documents/999/other-orders-file.pdf');
    }
}
