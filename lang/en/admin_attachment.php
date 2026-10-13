<?php

return [
    'navigation' => [
        'label' => 'Attachment',
        'plural' => 'Attachments',
    ],
    'fields' => [
        'name' => 'Name',
        'description' => 'Description',
        'file' => 'File',
        'file_helper' => 'PDF or image (JPEG, PNG, WebP), up to 10 MB. Stored privately: only admins can open it.',
        'type' => 'Type',
        'horse' => 'Horse',
        'uploaded_by' => 'Uploaded by',
        'is_visible_to_client' => 'Visible to the owner',
        'is_active' => 'Active',
        'is_approved' => 'Approved',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
        'open' => 'Open',
    ],
    'types' => [
        'id_card' => 'ID card',
        'cr_card' => 'Commercial registration',
        'passport' => 'Passport',
        'front_side' => 'Front side photo',
        'left_side' => 'Left side photo',
        'right_side' => 'Right side photo',
        'back_side' => 'Back side photo',
        'DNA' => 'DNA report',
        'other' => 'Other',
    ],
];
