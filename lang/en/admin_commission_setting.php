<?php

return [
    'navigation' => ['label' => 'Commission', 'plural' => 'Commissions'],
    'notice' => 'A commission is taken out of the client\'s share of an order (the service price), not added to what the customer pays. It is optional: with no rule, only the service fee is due to us. Existing orders keep the commission they were created with. If part of the client\'s share is refunded, the commission shrinks with it.',
    'fields' => [
        'type' => 'Commission type',
        'value_helper' => 'A percentage of the service price (e.g. 10) or a fixed amount in the site currency (e.g. 1.000). A fixed commission never exceeds the price.',
    ],
    'columns' => ['rate' => 'Commission'],
];
