<?php

namespace App\Services\Orders;

use App\Models\OrderDocument;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\Storage;

/** Attaches, replaces and removes the result documents of an order (files are on the private disk). */
class OrderDocumentService
{
    /** Attach a file the upload field has already put on the private disk. */
    public function attach(ServiceOrder $order, string $title, string $path, ?string $originalName = null, ?int $userId = null): OrderDocument
    {
        $document = $order->documents()->create(['title' => $title, 'uploaded_by' => $userId] + $this->fileAttributes($path, $originalName));

        // the customer sees that something was added, not who added it
        $order->recordEvent('document_added', __('orders.events.document_added'), ['title' => $title], userId: $userId);

        return $document;
    }

    /**
     * Swap a document's file for a new one (and optionally its title). The old file is deleted once the
     * new one is recorded; the customer's download link is the same, so it now gives the new file.
     */
    public function replace(OrderDocument $document, string $path, ?string $originalName = null, ?string $title = null, ?int $userId = null): OrderDocument
    {
        $old = $document->path;

        $document->forceFill(['title' => filled($title) ? $title : $document->title, 'uploaded_by' => $userId] + $this->fileAttributes($path, $originalName))->save();

        if ($old !== $path) {
            $this->deleteFile($document->order, $old);
        }

        $document->order->recordEvent('document_replaced', __('orders.events.document_replaced'), ['title' => $document->title], public: false, userId: $userId);

        return $document;
    }

    /** Remove a document and its file. */
    public function delete(OrderDocument $document, ?int $userId = null): void
    {
        $order = $document->order;
        $title = $document->title;
        $path = $document->path;

        $document->delete();
        $this->deleteFile($order, $path);

        $order->recordEvent('document_removed', __('orders.events.document_removed'), ['title' => $title], public: false, userId: $userId);
    }

    /** @return array{path: string, original_name: ?string, mime: ?string, size: int} */
    private function fileAttributes(string $path, ?string $originalName): array
    {
        $disk = Storage::disk(OrderDocument::DISK);
        $exists = $disk->exists($path);

        return [
            'path' => $path,
            'original_name' => $originalName,
            'mime' => $exists ? $disk->mimeType($path) : null,
            'size' => $exists ? $disk->size($path) : 0,
        ];
    }

    /** Only ever deletes inside this order's own document folder, whatever the stored path says. */
    private function deleteFile(ServiceOrder $order, string $path): void
    {
        if (str_starts_with($path, OrderDocument::DIRECTORY.'/'.$order->getKey().'/') && ! str_contains($path, '..')) {
            Storage::disk(OrderDocument::DISK)->delete($path);
        }
    }
}
