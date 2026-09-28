<?php

// WhatsApp texts sent to the site's admins. Every message goes out in all site languages at
// once (App\Services\WhatsApp\BilingualMessage), so keep lang/ar/whatsapp.php in step.
return [
    'new_post' => [
        'heading' => '🆕 New post on :site',
        'type' => 'Section: :type',
        'contact' => '📞 Contact: :contact',
        'view' => '🔗 On the website: :link',
        'admin' => '🛠 In the admin: :link',
    ],

    'new_stable' => [
        'heading' => '🐎 New stable registered on :site, waiting for approval',
        'place' => '📍 :city, :region',
        'owner' => '👤 Owner: :name (:phone)',
        'admin' => '🛠 Review and approve: :link',
    ],

    'post_types' => [
        'transfer_post' => 'Transfer board',
        'horse_sale_post' => 'Horses for sale',
        'tool_sale_post' => 'Tools for sale',
        'farrier' => 'Farriers',
        'stable_registration' => 'New stable registrations',
    ],

    'transfer_kinds' => [
        'offer' => 'Offering transport',
        'request' => 'Looking for transport',
    ],

    'fields' => [
        'name' => 'Name: :value',
        'kind' => 'Post: :value',
        'route' => 'From :from to :to',
        'date' => 'Date: :value',
        'capacity' => 'Capacity: :value',
        'category' => 'Category: :value',
        'price' => 'Price: :value',
        'negotiable' => '(negotiable)',
    ],

    'test' => '✅ WhatsApp test from :site: admin alerts are working.',
];
