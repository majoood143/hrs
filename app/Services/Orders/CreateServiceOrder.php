<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\ServiceOrderReceived;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Support\FormOrderSettings;
use App\Support\PhoneNumber;
use Packstub\FormBuilder\FormBuilder;

/**
 * Creates an order for a service with its money snapshot. A free one is received straight
 * away; a paid one waits in "pending payment" until a gateway confirms it.
 */
class CreateServiceOrder
{
    public function __construct(private readonly OrderPricing $pricing) {}

    /**
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, locale?: ?string, customer_id?: ?int}  $customer
     */
    public function handle(Service $service, array $customer = [], ?int $formId = null, ?int $submissionId = null): ServiceOrder
    {
        $quote = $this->pricing->quote($service, $formId);
        $formModel = FormBuilder::formModel();
        $form = $formId ? $formModel::find($formId) : null;

        $order = ServiceOrder::create([
            'form_id' => $formId,
            // A snapshot, the same way the money and (when review starts) the stages are:
            // editing the form's "requires a document" setting later must never change what an
            // existing order was scoped to require.
            'requires_document' => $form ? FormOrderSettings::for($form)->requiresDocument() : false,
            'submission_id' => $submissionId,
            'service_id' => $service->getKey(),
            'customer_id' => $customer['customer_id'] ?? null,
            'customer_name' => $customer['name'] ?? null,
            'customer_email' => $customer['email'] ?? null,
            'customer_phone' => PhoneNumber::normalize($customer['phone'] ?? null),
            'locale' => $customer['locale'] ?? app()->getLocale(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'status' => $quote->isFree() ? OrderStatus::New : OrderStatus::PendingPayment,
            'payment_status' => $quote->isFree() ? PaymentStatus::Free : PaymentStatus::Pending,
            'paid_at' => null,
        ] + $quote->toOrderAttributes());

        $order->recordEvent('created', __('orders.events.created'));

        if ($quote->isFree()) {
            ServiceOrderReceived::dispatch($order);
        }

        return $order;
    }
}
