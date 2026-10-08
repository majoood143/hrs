<?php

return [
    'navigation' => [
        'label' => 'User',
        'plural' => 'Users',
    ],

    'sections' => [
        'account' => 'Account',
        'access' => 'Access',
        'password' => 'Password',
        'identity' => 'Identity and address',
    ],

    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'phone_helper' => 'Saved as digits with the country code; an 8-digit number is taken as Omani.',
        'locale' => 'Language',
        'locale_helper' => 'The language emails and SMS messages to this user are sent in.',
        'type' => 'Account type',
        'type_helper' => 'Stable owners can only open the stable panel (/stable), never the admin panel.',
        'roles' => 'Roles',
        'roles_helper' => 'What this user may see and do in the admin panel. A user without a role sees nothing there.',
        'email_verified_at' => 'Email verified at',
        'phone_verified_at' => 'Phone verified at',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'password_helper' => 'Leave empty to keep the current password.',
        'civiled_id' => 'Civil ID',
        'cr_number' => 'CR number',
        'country' => 'Country',
        'region' => 'Region',
        'city' => 'City',
        'postal_code' => 'Postal code',
        'stables' => 'Stables',
        'verified' => 'Email verified',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],

    'types' => [
        'owner' => 'Horse owner',
        'stable_owner' => 'Stable owner',
        'trainer' => 'Trainer',
        'trainer_assistant' => 'Trainer assistant',
        'veterinarian' => 'Veterinarian',
        'farrier' => 'Farrier',
        'jockey' => 'Jockey',
        'groom' => 'Groom',
        'admin' => 'Admin',
    ],

    'tabs' => [
        'all' => 'All',
        'staff' => 'With a role',
    ],

    'actions' => [
        'send_welcome' => 'Send welcome email',
        'send_welcome_description' => 'A welcome email goes to every user shown on this page of the table.',
    ],
];
