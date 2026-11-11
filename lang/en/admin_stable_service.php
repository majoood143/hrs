<?php

return [
    'navigation' => [
        'label' => 'Stable Service',
        'plural' => 'Stable Services',
    ],
    'fields' => [
        'is_bookable' => 'Bookable',
        'offerings_count' => 'Offerings',
    ],
    'hints' => [
        'is_bookable' => 'Stables that tick this service can sell it by the slot (stable panel → Services → Type), and it becomes a booking search filter.',
    ],
    'errors' => [
        'bookable_in_use' => 'Stables have active offerings under this service. They must switch them off or change their type first.',
    ],
];
