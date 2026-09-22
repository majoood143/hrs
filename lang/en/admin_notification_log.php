<?php

return [
    'navigation' => ['label' => 'Notification', 'plural' => 'Notification Log'],
    'columns' => [
        'created_at' => 'Date',
        'channel' => 'Channel',
        'type' => 'Type',
        'recipient' => 'Recipient',
        'status' => 'Status',
        'order' => 'Order',
        'error' => 'Error',
    ],
    'statuses' => ['sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped'],
    'empty' => 'No messages have been sent yet',
];
