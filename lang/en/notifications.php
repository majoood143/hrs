<?php

return [
    // One SMS: keep it short. :url is the order's status page.
    'sms' => [
        'received_paid' => ':site: payment received. Order :number (:service). Track it: :url',
        'received_free' => ':site: your request was received. Order :number (:service). Track it: :url',
        'completed' => ':site: your order :number is completed. Details: :url',
        'rejected' => ':site: your request :number could not be approved. Details: :url',
        'refunded' => ':site: a refund of :amount for order :number was processed. The service fee is not refundable. Details: :url',
    ],

    'mail' => [
        'greeting' => 'Hello :name,',
        'greeting_guest' => 'Hello,',
        'track_button' => 'View my order',

        'received' => [
            'subject_paid' => 'Order :number received: payment confirmed (:site)',
            'subject_free' => 'Order :number received (:site)',
            'intro_paid' => 'Thank you. We have received your payment and your request for **:service**.',
            'intro_free' => 'Thank you. We have received your request for **:service**.',
            'receipt_attached' => 'Your receipt is attached.',
            'next' => 'Our team will review your request and we will let you know as soon as it is ready. Keep your order number: you can use it to follow your order, or sign in with your phone number to see all your orders.',
        ],

        'completed' => [
            'subject' => 'Your order :number is completed (:site)',
            'intro' => 'Good news: your request for **:service** has been completed.',
            'next' => 'You can see the details of your order at any time.',
        ],

        'reviewer' => [
            'subject' => 'New paid order :number: :service',
            'heading' => 'New paid order :number',
            'intro' => 'A customer has paid :total for **:service**. Their details and answers:',
            'button' => 'Open the order',
        ],

        'rejected' => [
            'subject' => 'Your request :number could not be approved (:site)',
            'intro' => 'We are sorry: we were not able to approve your request for **:service**.',
            'reason' => 'Reason',
            'refund' => 'You will be refunded :amount (the service price and its VAT). We will confirm when the refund has been made.',
        ],

        'refunded' => [
            'subject' => 'Your refund for order :number (:site)',
            'intro' => 'A refund of **:amount** for your request for **:service** has been processed.',
            'timing' => 'It can take a few days to appear in your account, depending on your bank.',
            'fee_kept' => 'The service fee (:amount, with VAT) is not refundable.',
        ],

        'stage' => [
            'subject' => 'Order :number is waiting for your review (:stage)',
            'heading' => 'Order :number is waiting for you',
            'intro' => 'The request for **:service** is at the stage **:stage**, which is yours to decide. What the customer wrote:',
            'button' => 'Review the order',
        ],
    ],
];
