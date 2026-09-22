<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\OrderDocument;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the result documents of an order. The files are on the private disk, so they are only ever
 * reached here: by their own customer (signed in), or by an admin through a signed link. Always a
 * download, never rendered in the browser.
 */
class OrderDocumentController extends Controller
{
    /** The customer's own copy: /account/orders/{order}/documents/{document}, signed-in customers only. */
    public function customer(string $order, int $document): StreamedResponse
    {
        $order = auth('customer')->user()->orders()->where('order_number', $order)->firstOrFail();

        return $this->send($order->documents()->findOrFail($document));
    }

    /** An admin's copy, through a signed link, for people who may see orders. */
    public function admin(int $document): StreamedResponse
    {
        abort_unless(Gate::allows('viewAny', ServiceOrder::class), 403);

        return $this->send(OrderDocument::query()->findOrFail($document));
    }

    private function send(OrderDocument $document): StreamedResponse
    {
        abort_unless($document->exists(), 404);

        return Storage::disk(OrderDocument::DISK)->download($document->path, $document->downloadName());
    }
}
