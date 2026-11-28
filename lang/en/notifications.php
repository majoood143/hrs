<?php

return [
    // One SMS: keep it short. :url is the order's status page.
    'sms' => [
        'received_paid' => ':site: payment received. Order :number (:service). Track it: :url',
        'received_free' => ':site: your request was received. Order :number (:service). Track it: :url',
        'completed' => ':site: your order :number is completed. Details: :url',
        'rejected' => ':site: your request :number could not be approved. Details: :url',
        'refunded' => ':site: a refund of :amount for order :number was processed. Details: :url',
        'auction_approved' => ':site: you can now bid in :auction. :url',
        'auction_outbid' => ':site: you were outbid on lot :lot :horse (now :price). Bid again: :url',
        'auction_ending' => ':site: lot :lot :horse closes in :minutes min (now :price). :url',
        'auction_won' => ':site: you won lot :lot :horse for :price. Pay :total by :due: :url',
        'auction_invoice_due' => ':site: your invoice for lot :lot :horse (:total) is due :due. Pay: :url',
        'auction_application_approved' => ':site: your horse :horse is accepted for :auction. Follow it: :url',
        'auction_application_rejected' => ':site: we cannot take :horse into an auction for now: :reason :url',
        'auction_seller_sold' => ':site: your horse :horse (lot :lot) sold for :price. We will pay you :payout once the buyer pays. :url',
        'auction_seller_unsold' => ':site: your horse :horse (lot :lot) did not reach its reserve and was not sold. :url',
        'auction_seller_paid' => ':site: we paid you :payout for :horse (lot :lot). :url',
        'game_award' => ':site: congratulations :name! You finished #:rank in :campaign. Your prize: :prize. We will contact you to hand it over.',
        'game_award_code' => ':site: congratulations :name! You finished #:rank in :campaign. Your prize: :prize. Your promo code :code works for your number until :until when booking a stable session.',
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
