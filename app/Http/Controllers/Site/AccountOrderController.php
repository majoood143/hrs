<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Services\Orders\OrderDetailsPdf;
use App\Services\Orders\OrderReceiptPdf;
use App\Services\Stables\BookingUnavailable;
use App\Services\Stables\StableBookingActions;
use App\Services\Stables\StableReviews;
use App\Support\OrderAnswers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** A signed-in customer's own orders. Another customer's order number is simply not found. */
class AccountOrderController extends Controller
{
    public function index(): View
    {
        return view('site.account.orders', [
            'customer' => $this->customer(),
            'orders' => $this->customer()->orders()->with('service')->paginate(10),
            'seoTitle' => __('account.orders_title'),
            'noindex' => true,
        ]);
    }

    public function show(string $order): View
    {
        $order = $this->find($order);

        $order->loadMissing(['stages', 'refunds', 'documents']);

        return view('site.account.order', [
            'customer' => $this->customer(),
            'order' => $order,
            'answers' => OrderAnswers::for($order),
            'events' => $order->events()->where('is_public', true)->get(),
            'hasReceipt' => app(OrderReceiptPdf::class)->available($order),
            'seoTitle' => __('orders.status_title', ['number' => $order->order_number]),
            'noindex' => true,
        ]);
    }

    /** The customer calls off their own booking, within the stable's cancellation window. */
    public function cancelBooking(string $order, StableBookingActions $actions): RedirectResponse
    {
        $booking = $this->find($order)->stableBooking;
        abort_unless($booking, 404);

        try {
            $actions->cancelByCustomer($booking);
        } catch (BookingUnavailable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('stable_bookings.card.cancelled'));
    }

    /** The customer rates a session they attended. */
    public function review(Request $request, string $order, StableReviews $reviews): RedirectResponse
    {
        $booking = $this->find($order)->stableBooking;
        abort_unless($booking, 404);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $reviews->submit($booking, $this->customer(), (int) $data['rating'], $data['comment'] ?? null);
        } catch (BookingUnavailable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('stable_reviews.thanks'));
    }

    public function receipt(Request $request, string $order, OrderReceiptPdf $receipts): Response
    {
        $order = $this->find($order);

        abort_unless($receipts->available($order), 404);

        return response($receipts->render($order, $request->query('lang')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$receipts->filename($order).'"',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** The whole order page as a PDF, for any order (paid or not). */
    public function details(Request $request, string $order, OrderDetailsPdf $details): Response
    {
        $order = $this->find($order);

        return response($details->render($order, $request->query('lang')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$details->filename($order).'"',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function customer(): Customer
    {
        return Auth::guard('customer')->user();
    }

    private function find(string $number): ServiceOrder
    {
        return $this->customer()->orders()->with(['service', 'submission.form'])->where('order_number', $number)->firstOrFail();
    }
}
