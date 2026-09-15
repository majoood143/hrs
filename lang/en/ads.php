<?php

return [
    'navigation' => [
        'label' => 'Ad',
        'plural' => 'Ads',
    ],
    'fields' => [
        'zone' => 'Zone',
        'customer_name' => 'Customer Name',
        'customer_name_helper' => 'Internal reference only — who rented this slot. Not shown on the site.',
        'media_type' => 'Media Type',
        'media_type_image' => 'Image',
        'media_type_video' => 'Video',
        'image' => 'Image',
        'video' => 'Video',
        'video_helper' => 'MP4, WebM or MOV, up to 20MB. Plays muted and looping in the slider.',
        'alt_text' => 'Alt Text',
        'target_url' => 'Click-through URL',
        'target_url_helper' => 'Where visitors go when they click the ad. Leave blank for a non-clickable ad.',
        'starts_at' => 'Starts At',
        'ends_at' => 'Ends At',
        'order' => 'Order',
        'is_active' => 'Active',
        'status' => 'Status',
    ],
    'statuses' => [
        'scheduled' => 'Scheduled',
        'active' => 'Active',
        'expired' => 'Expired',
        'inactive' => 'Inactive',
    ],
    'filters' => [
        'zone' => 'Zone',
        'is_active' => 'Active',
    ],
    'empty_state' => [
        'heading' => 'No ads yet',
        'description' => 'Create an ad zone first, then add rented ads to it.',
    ],
];
