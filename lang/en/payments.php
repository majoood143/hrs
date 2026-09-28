<?php

return [
    'checkout_eyebrow' => 'Secure payment',
    'checkout_title' => 'Complete your payment',
    'pay_with' => 'Pay with',
    'pay_securely' => 'Continue to payment',
    'pay_button' => 'Pay with :gateway',
    'secure_note' => 'You will be taken to the payment provider\'s secure page. We never see or store your card details.',
    'no_gateways' => 'Online payment is not available right now. Please contact us with your order number.',
    'choose_gateway' => 'Please choose a payment method.',
    'could_not_start' => 'We could not start the payment. Please try again in a moment.',
    'order_not_found' => 'We could not find this order.',
    'verify_failed' => 'An error occurred while verifying your payment. Please contact us: do not pay again.',
    'paid_but_unconfirmed' => 'Your payment was received, but we could not confirm this order automatically. Please contact us with your order number: do not pay again.',
    'cancelled' => 'The payment was cancelled. You can try again.',
    'not_completed' => 'The payment was not completed. Please try again.',
    'redirecting' => 'Redirecting to the secure payment page…',
    'continue' => 'Continue',

    'demo_badge' => 'Demo payment',
    'demo_title' => 'Demo payment page',
    'demo_notice' => 'This is a demonstration gateway: no real payment is made. Approve to see the order confirmed, or decline to see a failed payment.',
    'demo_approve' => 'Approve payment',
    'demo_decline' => 'Decline payment',

    // checking a stable's own gateway keys (stable owner panel and the admin's review)
    'test' => [
        'incomplete' => 'Fill in every key first.',
        'unreachable' => 'The gateway could not be reached: :error',
        'rejected' => 'The gateway refused the key. Check it and whether test mode matches the key.',
        'gateway_error' => 'The gateway answered with an error (:status). Try again later.',
        'refused' => 'The gateway refused the details: :error',
        'thawani_ok' => 'Thawani accepted the secret key.',
        'nbo_ok' => 'NBO accepted the Tranportal ID, password and resource key.',
        'ccavenue_ok' => 'The keys are filled in and the working key is valid. CCAvenue confirms them on the first payment.',
    ],
];
