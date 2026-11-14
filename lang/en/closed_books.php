<?php

return [
    'close' => 'Close a month',
    'close_hint' => 'Closes this month and every one before it. Settlements can no longer be dated in it or removed, and attendance it counted can no longer change. Payments and refunds are always dated the day they are recorded.',
    'reopen' => 'Reopen :month',
    'reopen_hint' => 'Only for a correction: statements already sent for this month may change.',
    'month' => 'Month',
    'note' => 'Note',
    'closed_through' => 'Books closed through :date',
    'closed' => 'Month closed.',
    'reopened' => 'Month reopened.',
    'errors' => [
        'not_ended' => 'Only a month that has ended can be closed.',
        'already_closed' => 'That month is already closed.',
        'nothing_closed' => 'No month is closed.',
        'closed_date' => 'That day is in a closed month (closed through :date).',
        'closed_settlement' => 'This settlement is in a closed month and cannot be removed. Record a correcting one instead.',
        'closed_attendance' => 'This session was counted in a closed month and can no longer change.',
    ],
];
