<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * The order's status page. Reachable with the order number alone, so it shows no personal
     * data: the customer's own account page (phone sign-in) carries the full detail.
     */
    public function show(string $order): View|RedirectResponse
    {
        $order = ServiceOrder::query()->with('service')->where('order_number', $order)->firstOrFail();

        // its owner, signed in, gets the full page (answers, receipt...) instead of this public one
        $customer = Auth::guard('customer')->user();

        if ($customer && $order->customer_id === $customer->getKey()) {
            return redirect()->route('account.orders.show', $order->order_number);
        }

        return view('site.orders.show', [
            'order' => $order,
            'events' => $order->events()->where('is_public', true)->get(),
            'seoTitle' => __('orders.status_title', ['number' => $order->order_number]),
            'noindex' => true,
        ]);
    }
}
