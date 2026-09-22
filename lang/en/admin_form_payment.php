<?php

return [
    'tab' => 'Service & payment',

    'service' => [
        'heading' => 'Service',
        'description' => 'Link this form to a service to turn each submission into an order. The customer pays the service\'s price (plus the service fee and VAT) before the request reaches you; a free service is received straight away. Leave it empty for an ordinary form.',
        'label' => 'Service',
        'helper' => 'Manage services and their prices under Services. Submissions of a linked form are always stored.',
        'none' => 'None: an ordinary form',
        'inactive' => 'inactive',
        'free' => 'This service is free: no payment step.',
        'quote' => 'Customer pays :total (service :price + fee :fee + VAT :vat) at today\'s rates.',
    ],

    'customer' => [
        'heading' => 'Who is the customer?',
        'description' => 'Pick the form fields that hold the customer\'s details, used for the order, the receipt and the notifications. Add a Phone field (and an Email field) to the form first, then save it.',
        'name' => 'Name field',
        'phone' => 'Phone field',
        'phone_helper' => 'Required: it identifies the customer and receives the SMS.',
        'email' => 'Email field',
        'auto' => 'Detect automatically',
    ],

    'notifications' => [
        'heading' => 'Tell the customer',
        'description' => 'Sent when the order is received or paid (with the order number) and again when it is completed.',
        'email' => 'Send email notifications',
        'sms' => 'Send SMS notifications',
        'sms_helper' => 'Needs the SMS gateway to be set up.',
    ],

    'review' => [
        'heading' => 'Review before completing',
        'description' => 'Optional. Once an order is paid (or received, if free) it passes through these stages in order. Anyone holding a stage\'s role may approve or reject it; a rejection ends the request and refunds the price and its VAT (never the service fee). With no stages, an admin completes the order directly.',
        'stages' => 'Review stages',
        'stages_helper' => 'They run from top to bottom. The people who hold the role are emailed when it is their turn.',
        'add_stage' => 'Add a stage',
        'stage_name' => 'Stage name',
        'role' => 'Who reviews it (role)',
        'role_helper' => 'Roles are managed under Roles.',
        'requires_document' => 'A result document is required to complete',
        'requires_document_helper' => 'The order cannot be completed until a document has been uploaded for the customer.',
    ],
];
