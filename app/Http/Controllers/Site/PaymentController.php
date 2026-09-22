<?php

namespace App\Http\Controllers\Site;

use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGatewaySession;
use App\Models\ServiceOrder;
use App\Services\Payments\Gateways\CcAvenueGateway;
use App\Services\Payments\Gateways\NboGateway;
use App\Services\Payments\Gateways\ThawaniGateway;
use App\Services\Payments\OrderPaymentService;
use App\Services\Payments\PaymentGateways;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Sends a customer to a payment gateway and settles the order when they come back.
 *
 * Whatever a callback claims, an order is only marked paid after the gateway itself has
 * confirmed it (Thawani: the session is fetched again) or, for the gateways that answer with
 * an unsigned or encrypted form, after the amount matches the order's total.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly OrderPaymentService $payments,
    ) {}

    // ── Choosing a gateway ───────────────────────────────────────────────────

    public function show(string $order): View|RedirectResponse
    {
        $order = $this->findOrder($order);

        if (! $order->isPayable()) {
            return redirect()->route('orders.show', $order->order_number);
        }

        return view('site.payments.checkout', [
            'order' => $order->loadMissing('service'),
            'gateways' => $this->gateways->available(),
            'seoTitle' => __('payments.checkout_title'),
            'noindex' => true,
        ]);
    }

    public function begin(Request $request, string $order): View|RedirectResponse
    {
        $order = $this->findOrder($order);

        if (! $order->isPayable()) {
            return redirect()->route('orders.show', $order->order_number);
        }

        $gateway = $this->gateways->find((string) $request->input('gateway'));

        if (! $gateway) {
            return back()->with('error', __('payments.choose_gateway'));
        }

        // Thawani mints a brand new session every time initiate() runs, overwriting the order's
        // only payment_session_id column; a double-click, a back button or two tabs would
        // otherwise orphan an earlier session that later turns out to have been paid. Ask
        // Thawani about it first, since reconcile() is always safe to call and idempotent.
        if ($gateway instanceof ThawaniGateway && $order->payment_session_id && $order->payment_method === PaymentGateway::Thawani) {
            try {
                $gateway->reconcile($order);
            } catch (Throwable $e) {
                Log::warning('Thawani pre-check before re-initiating failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);
            }

            $order->refresh();

            if ($order->isPaid()) {
                return $this->toOrder($order);
            }

            if (! $order->isPayable()) {
                return redirect()->route('orders.show', $order->order_number);
            }
        }

        try {
            $redirect = $gateway->initiate($order);
        } catch (Throwable $e) {
            Log::error('Payment could not be started', ['order' => $order->order_number, 'gateway' => $gateway->gateway()->value, 'error' => $e->getMessage()]);

            return back()->with('error', __('payments.could_not_start'));
        }

        $order->recordEvent('payment_started', null, ['gateway' => $gateway->gateway()->value], public: false);

        return $redirect->post
            ? view('payments.redirect-post', ['redirect' => $redirect])
            : redirect()->away($redirect->url);
    }

    // ── Thawani ──────────────────────────────────────────────────────────────

    /** The customer is sent back here after paying (or after closing the hosted page). */
    public function thawaniReturn(Request $request, ThawaniGateway $thawani): RedirectResponse
    {
        $order = $this->orderFromReference($request);

        if (! $order) {
            return redirect('/')->with('error', __('payments.order_not_found'));
        }

        if ($order->isPaid()) {
            return $this->toOrder($order);
        }

        try {
            $status = $thawani->reconcile($order);
        } catch (Throwable $e) {
            Log::error('Thawani return: verification failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);

            return $this->toPayment($order, __('payments.verify_failed'));
        }

        if ($order->refresh()->isPaid()) {
            return $this->toOrder($order);
        }

        // Paid, but the order could not be marked paid (amount mismatch): say so plainly, since
        // "try again" would send the customer off to pay a second time.
        if ($status === 'paid' || $status === 'amount_mismatch') {
            return redirect()->route('orders.show', $order->order_number)->with('error', __('payments.paid_but_unconfirmed'));
        }

        // Unpaid: a failed card attempt, or the customer backed out. The session stays payable and
        // the expiry sweep decides when to give up on it.
        $this->payments->markFailed($order, PaymentGateway::Thawani, $status);

        return $this->toPayment($order, $status === 'cancelled' ? __('payments.cancelled') : __('payments.not_completed'));
    }

    public function thawaniCancel(Request $request, ThawaniGateway $thawani): RedirectResponse
    {
        $order = $this->orderFromReference($request);

        if (! $order) {
            return redirect('/')->with('error', __('payments.order_not_found'));
        }

        if ($order->isPaid()) {
            return $this->toOrder($order);
        }

        PaymentGatewayLog::log($order, 'thawani', 'cancel', ['session_id' => $order->payment_session_id], ['reason' => 'user_cancelled']);

        // Reaching the cancel URL is only a redirect, and the hosted session stays payable: ask
        // Thawani before giving up. If it turns out to be paid, reconcile() settles the order.
        try {
            $thawani->reconcile($order);
        } catch (Throwable $e) {
            Log::warning('Thawani cancel: session check failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);
        }

        if ($order->refresh()->isPaid()) {
            return $this->toOrder($order);
        }

        $this->payments->markFailed($order, PaymentGateway::Thawani, 'user_cancelled');

        return $this->toPayment($order, __('payments.cancelled'));
    }

    /** Thawani's server-to-server notification. The session is re-fetched, so a forged event changes nothing. */
    public function thawaniWebhook(Request $request, ThawaniGateway $thawani): JsonResponse
    {
        $payload = $request->getContent();

        if (! $thawani->verifyWebhookSignature($payload, (string) $request->header('thawani-signature', ''))) {
            Log::warning('Thawani webhook: invalid signature');

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $data = json_decode($payload, true) ?: [];
        $sessionId = $data['data']['session_id'] ?? null;

        // The order's *current* payment_session_id may since have moved on to a newer session
        // (see PaymentController::begin()); a late webhook for a superseded one is still found
        // through the full history so its payment is never silently lost.
        $order = $sessionId
            ? ServiceOrder::query()->where('payment_session_id', $sessionId)->first()
                ?? PaymentGatewaySession::findOrder('thawani', $sessionId)
            : null;

        PaymentGatewayLog::log($order, 'thawani', 'webhook', $data, ['event_type' => $data['event_type'] ?? null, 'session_id' => $sessionId]);

        if ($order && ! $order->isPaid()) {
            try {
                $thawani->reconcile($order, $sessionId);
            } catch (Throwable $e) {
                Log::error('Thawani webhook: resync failed', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
            }
        }

        return response()->json(['message' => 'ok']);
    }

    // ── NBO ──────────────────────────────────────────────────────────────────

    public function nboCallback(Request $request, NboGateway $nbo): RedirectResponse
    {
        $input = $nbo->normalizedInput($request);

        $paymentId = $nbo->pick($input, 'paymentid');
        $error = $nbo->pick($input, 'error');
        $errorText = $nbo->pick($input, 'errortext');
        // No 'raw' body here: it bypasses the array-based redaction in PaymentGatewayLog and
        // NBO's callback shape is only partly documented, so an unparsed body could carry a
        // card number straight into the log. `all()` already captures every parsed field.
        $loggable = ['content_type' => $request->header('Content-Type'), 'all' => $request->all()];

        // NBO mints a fresh PaymentID on every initiate() call; a callback for a superseded one
        // (see PaymentController::begin()) is still resolved through the full session history.
        $order = $paymentId
            ? ServiceOrder::query()->where('payment_session_id', $paymentId)->first()
                ?? PaymentGatewaySession::findOrder('nbo', $paymentId)
            : null;

        if ($order?->isPaid()) {
            return $this->toOrder($order);
        }

        // An explicit error from NBO: cancelled, declined card, gateway validation...
        if ($error && $error !== '0') {
            $errorText ??= __('payments.not_completed');
            PaymentGatewayLog::log($order, 'nbo', 'callback', $loggable, ['error' => $error, 'errorText' => $errorText]);

            if (! $order) {
                return redirect('/')->with('error', $errorText);
            }

            $this->payments->markFailed($order, PaymentGateway::Nbo, $error);

            return $this->toPayment($order, $errorText);
        }

        if (! $paymentId || ! $order) {
            Log::warning('NBO callback: no order for this payment id', ['payment_id' => $paymentId]);

            return redirect('/')->with('error', __('payments.order_not_found'));
        }

        try {
            $data = $nbo->resolveTranData($input);
        } catch (Throwable $e) {
            Log::error('NBO callback: decryption error', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);
            PaymentGatewayLog::log($order, 'nbo', 'callback', $loggable, ['error' => $e->getMessage()]);

            return $this->toPayment($order, __('payments.verify_failed'));
        }

        if ($data === null) {
            PaymentGatewayLog::log($order, 'nbo', 'callback', $loggable, ['error' => 'no_transaction_data']);
            $this->payments->markFailed($order, PaymentGateway::Nbo, 'no_transaction_data');

            return $this->toPayment($order, __('payments.verify_failed'));
        }

        PaymentGatewayLog::log($order, 'nbo', 'callback', $loggable, $data);

        $result = $data['result'] ?? null;

        // The callback is unsigned, so never trust CAPTURED / APPROVED unless the amount matches.
        $amountMatches = isset($data['amt']) && (int) round((float) $data['amt'] * 1000) === $order->totalBaisa();

        if (in_array($result, ['CAPTURED', 'APPROVED'], true) && $amountMatches) {
            $this->payments->applyPaid($order, PaymentGateway::Nbo, $paymentId);

            return $this->toOrder($order->refresh());
        }

        if (in_array($result, ['CAPTURED', 'APPROVED'], true)) {
            Log::warning('NBO callback: amount mismatch, treated as failed', ['order' => $order->order_number, 'expected' => $order->total, 'received' => $data['amt'] ?? null]);
        }

        $this->payments->markFailed($order, PaymentGateway::Nbo, (string) $result);

        return $this->toPayment($order, __('payments.not_completed'));
    }

    // ── CCAvenue ─────────────────────────────────────────────────────────────

    /**
     * CCAvenue posts one encrypted "encResp" field after the customer completes, cancels or
     * fails (redirect_url and cancel_url are both this route). Decrypted, it is a query string.
     */
    public function ccavenueCallback(Request $request, CcAvenueGateway $ccavenue): RedirectResponse
    {
        $encResp = $request->input('encResp');
        $loggable = ['content_type' => $request->header('Content-Type'), 'all' => $request->all()];

        if (! $encResp || ! is_string($encResp)) {
            Log::warning('CCAvenue callback: missing encResp');

            return redirect('/')->with('error', __('payments.verify_failed'));
        }

        try {
            parse_str($ccavenue->decrypt($encResp), $data);
        } catch (Throwable $e) {
            Log::error('CCAvenue callback: decryption error', ['error' => $e->getMessage()]);
            PaymentGatewayLog::log(null, 'ccavenue', 'callback', $loggable, ['error' => $e->getMessage()]);

            return redirect('/')->with('error', __('payments.verify_failed'));
        }

        $orderId = $data['order_id'] ?? null;
        $status = strtolower((string) ($data['order_status'] ?? ''));
        $order = $orderId
            ? ServiceOrder::query()->where('payment_session_id', $orderId)->first()
                ?? PaymentGatewaySession::findOrder('ccavenue', $orderId)
            : null;

        PaymentGatewayLog::log($order, 'ccavenue', 'callback', $loggable, $data);

        if (! $order) {
            Log::warning('CCAvenue callback: no order for order_id', ['order_id' => $orderId]);

            return redirect('/')->with('error', __('payments.order_not_found'));
        }

        if ($order->isPaid()) {
            return $this->toOrder($order);
        }

        $amountMatches = isset($data['amount']) && (int) round((float) $data['amount'] * 1000) === $order->totalBaisa();

        if ($status === 'success' && $amountMatches) {
            $this->payments->applyPaid($order, PaymentGateway::CcAvenue, $data['tracking_id'] ?? $data['bank_ref_no'] ?? null);

            return $this->toOrder($order->refresh());
        }

        if ($status === 'success') {
            Log::warning('CCAvenue callback: amount mismatch, treated as failed', ['order' => $order->order_number, 'expected' => $order->total, 'received' => $data['amount'] ?? null]);
        }

        $this->payments->markFailed($order, PaymentGateway::CcAvenue, $status);

        return $this->toPayment($order, $data['failure_message'] ?? $data['status_message'] ?? __('payments.not_completed'));
    }

    // ── Demo ─────────────────────────────────────────────────────────────────

    public function demo(string $order): View|RedirectResponse
    {
        $order = $this->findDemoOrder($order);

        if (! $order->isPayable()) {
            return redirect()->route('orders.show', $order->order_number);
        }

        return view('site.payments.demo', ['order' => $order->loadMissing('service'), 'seoTitle' => __('payments.demo_title'), 'noindex' => true]);
    }

    public function demoDecide(Request $request, string $order): RedirectResponse
    {
        $order = $this->findDemoOrder($order);

        if (! $order->isPayable()) {
            return redirect()->route('orders.show', $order->order_number);
        }

        $approved = $request->input('decision') === 'approve';

        PaymentGatewayLog::log($order, 'demo', 'callback', ['decision' => $request->input('decision')], ['status' => $approved ? 'approved' : 'declined']);

        if (! $approved) {
            $this->payments->markFailed($order, PaymentGateway::Demo, 'declined');

            return $this->toPayment($order, __('payments.not_completed'));
        }

        $this->payments->applyPaid($order, PaymentGateway::Demo, $order->payment_session_id);

        return $this->toOrder($order->refresh());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function findOrder(string $number): ServiceOrder
    {
        return ServiceOrder::query()->where('order_number', $number)->firstOrFail();
    }

    /** The demo gateway only exists while an admin has it ticked. */
    private function findDemoOrder(string $number): ServiceOrder
    {
        abort_unless(in_array(PaymentGateway::Demo, $this->gateways->selected(), true), 404);

        return $this->findOrder($number);
    }

    private function orderFromReference(Request $request): ?ServiceOrder
    {
        $reference = $request->query('reference');

        return is_string($reference) && $reference !== ''
            ? ServiceOrder::query()->where('order_number', $reference)->first()
            : null;
    }

    private function toOrder(ServiceOrder $order): RedirectResponse
    {
        return redirect()->route('orders.show', $order->order_number);
    }

    private function toPayment(ServiceOrder $order, string $error): RedirectResponse
    {
        return redirect()->route('payment.start', $order->order_number)->with('error', $error);
    }
}
