<?php

return [
    'navigation' => [
        'label' => 'Horse',
        'plural' => 'Horses',
    ],

    'tabs' => [
        'horse' => 'Horse information',
        'location' => 'Location',
        'owner' => 'Owner',
    ],

    'fields' => [
        'name' => 'Name',
        'en_name' => 'Name (English)',
        'en_name_helper' => 'Used on the QR code and the certificate.',
        'ar_name' => 'Name (Arabic)',
        'microchip' => 'Microchip number',
        'passport_number' => 'Passport number',
        'registration_number' => 'Registration number',
        'dob' => 'Date of birth',
        'age' => 'Age',
        'years' => 'years',
        'type' => 'Type',
        'gender' => 'Gender',
        'color' => 'Colour',
        'breed' => 'Breed',
        'dam' => 'Dam (mother)',
        'sire' => 'Sire (father)',
        'country' => 'Country',
        'region' => 'Region',
        'city' => 'City',
        'owner' => 'Owner',
        'owner_helper' => 'The owner can manage the horse from their account.',
        'owner_name' => 'Full name',
        'email' => 'Email',
        'password' => 'Password',
        'mobile' => 'Mobile',
        'unassigned' => 'Not set',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
    ],

    'description' => 'Microchip: :microchip · Passport: :passport',

    'actions' => [
        'print_qr' => 'Download QR code',
        'change_owner' => 'Change owner',
        'change_owner_heading' => 'Transfer ownership: :name',
        'current_owner' => 'Current owner',
        'new_owner' => 'New owner',
        'owner_changed' => 'Owner changed',
        'move' => 'Move (export)',
        'current_location' => 'Current location',
        'new_location' => 'New location',
        'moved' => 'Horse location updated',
    ],
];
