<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Services\Orders\OrderReceiptPdf;
use App\Support\OrderAnswers;
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

    private function customer(): Customer
    {
        return Auth::guard('customer')->user();
    }

    private function find(string $number): ServiceOrder
    {
        return $this->customer()->orders()->with(['service', 'submission.form'])->where('order_number', $number)->firstOrFail();
    }
}
