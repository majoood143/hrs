<?php

namespace App\Listeners;

use App\Models\Service;
use App\Models\ServiceOrder;
use App\Services\Orders\CreateServiceOrder;
use App\Support\FormOrderSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Packstub\FormBuilder\Events\SubmissionReceived;

/**
 * A submission of a form that is linked to a service becomes an order, and the visitor is sent on:
 * to the payment page when there is something to pay, else to the order's page (a free service
 * is received straight away). A form with no service is left alone.
 */
class CreateOrderFromSubmission
{
    public function __construct(private readonly CreateServiceOrder $createOrder) {}

    public function handle(SubmissionReceived $event): void
    {
        $settings = FormOrderSettings::for($event->form);
        $service = $settings->service();

        if (! $service) {
            return;
        }

        $submission = $event->submission;

        // an order needs the submission it reviews, whatever "store submissions" says
        if (! $submission->exists) {
            $submission->save();
        }

        $customer = $settings->customer($submission);

        // A customer who is signed in and orders for their own (verified) number gets the order in their account
        // straight away; anyone else's orders are attached when that number signs in.
        $signedIn = Auth::guard('customer')->user();
        $customer['customer_id'] = $signedIn && $signedIn->phone === $customer['phone'] ? $signedIn->getKey() : null;

        $order = $this->findOrCreateOrder($service, $customer, $event->form->getKey(), $submission->getKey());

        $submission->forceFill(['meta' => [...($submission->meta ?? []), 'order_number' => $order->order_number]])->save();

        $event->extra['order_number'] = $order->order_number;
        $event->redirectTo($order->isPayable()
            ? route('payment.start', $order->order_number)
            : route('orders.show', $order->order_number));
    }

    /**
     * A submission becomes at most one order: the unique index on submission_id is what
     * actually guarantees that under concurrent/retried delivery of the same event, not this
     * check. If two requests both miss the initial lookup and both try to create one, the
     * loser's insert fails on that constraint and simply re-fetches the winner's order instead
     * of raising or creating a second one.
     *
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, locale?: ?string, customer_id?: ?int}  $customer
     */
    private function findOrCreateOrder(Service $service, array $customer, int $formId, int $submissionId): ServiceOrder
    {
        $existing = ServiceOrder::query()->where('submission_id', $submissionId)->first();

        if ($existing) {
            return $existing;
        }

        try {
            return $this->createOrder->handle($service, $customer, $formId, $submissionId);
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'submission_id')) {
                throw $e;
            }

            return ServiceOrder::query()->where('submission_id', $submissionId)->firstOrFail();
        }
    }
}
