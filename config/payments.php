<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pending payment hold
    |--------------------------------------------------------------------------
    |
    | Minutes an order waits for its payment before "orders:expire-pending"
    | gives up on it. Before it does, the order's gateway session is checked
    | one last time, so a customer who is still on the hosted page is not
    | cut off.
    |
    */

    'pending_ttl_minutes' => (int) env('PAYMENT_PENDING_TTL', 30),

    // A Thawani checkout session stays payable for 24 hours after it was created, long
    // after the hold above has expired, so recently expired orders are re-checked.
    'late_payment_window_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | VAT defaults
    |--------------------------------------------------------------------------
    |
    | Used until an admin saves the Pricing section of the Payment Gateways
    | settings page (site settings "vat.enabled" and "vat.rate").
    | VAT is charged on the service price and on the service fee.
    |
    */

    'vat' => [
        'enabled' => true,
        'rate' => 5.0,
    ],

];
