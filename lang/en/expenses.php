<?php

return [
    'label' => 'Expense',
    'plural' => 'Expenses',
    'ours' => 'Ours',
    'empty_heading' => 'No expenses yet',
    'stable_empty_hint' => 'Record what the stable spends (feed, vet, farrier, rent…) to see your profit after expenses on the Statement.',
    'closed_month' => 'That month is closed: pick a day in an open month.',
    'horse_hint' => 'Optional: a school horse this cost was for, to see what each horse costs.',
    'receipt_invalid' => 'The receipt must be a JPG, PNG, WebP or PDF file of up to 5 MB.',
    'total' => 'Total expenses',
    'profit' => 'Profit after expenses',
    'our_income' => 'Our income (after gateway charges, with what stables owe us)',
    'net_after_expenses' => 'Net after expenses',
    'none_in_period' => 'No expenses in this period',
    'widget_hint' => 'This month, after :spent of expenses',

    'sections' => [
        'profit' => 'Expenses and profit',
    ],

    'fields' => [
        'category' => 'Category',
        'spent_on' => 'Date',
        'amount' => 'Amount',
        'horse' => 'Horse',
        'vendor' => 'Paid to',
        'note' => 'Note',
        'receipt' => 'Receipt',
        'stable' => 'Stable',
    ],

    'filters' => [
        'whose' => 'Whose',
        'stables' => 'Stables\' own',
    ],

    'categories' => [
        'label' => 'Expense category',
        'plural' => 'Expense categories',
        'en_name' => 'Name (English)',
        'ar_name' => 'Name (Arabic)',
        'scope' => 'Offered to',
        'scopes' => [
            'both' => 'Us and the stables',
            'platform' => 'Us only',
            'stable' => 'Stables only',
        ],
        'sort' => 'Order',
        'is_active' => 'Active',
    ],
];
