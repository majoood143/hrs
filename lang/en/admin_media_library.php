<?php

return [
    'navigation' => [
        'label' => 'Media Library',
        'plural' => 'Media Libraries',
    ],
    'open_files' => 'Open Files',
    'fields' => [
        'en_name' => 'Name (English)',
        'ar_name' => 'Name (Arabic)',
        'files' => 'Files',
        'created_at' => 'Created',
    ],
    'delete' => [
        'warning' => '{0} This library is empty.|{1} Its 1 file will be deleted too, and its link will stop working.|[2,*] Its :count files will be deleted too, and their links will stop working.',
        'bulk_warning' => 'The files in these libraries will be deleted too, and their links will stop working.',
    ],
    'access' => [
        'no_delete' => 'You do not have permission to delete files here.',
    ],
    'upload' => [
        'done' => '{1} 1 file uploaded to ":folder".|[2,*] :count files uploaded to ":folder".',
    ],
    'share' => [
        'region' => 'Selected file link',
        'link' => 'Public link',
        'copy' => 'Copy link',
        'copy_many' => 'Copy :count links',
        'copied' => 'Link copied',
        'copied_short' => 'Copied',
        'copied_many' => 'Links copied',
        'copy_failed' => 'Could not copy. Select the link and copy it manually.',
        'share' => 'Share',
        'open' => 'Open',
        'open_link' => 'Open link',
        'sheet_title' => 'Share file',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'more' => 'More',
        'qr' => 'QR code',
        'qr_hint' => 'Scan to open the file, or print it on a poster or form.',
        'qr_download' => 'Download QR code',
        'close' => 'Close',
        'selected_many' => ':count files selected',
        'public_notice' => 'Public: anyone with this link can open the file.',
        'no_link' => 'This file is not stored publicly, so it has no shareable link.',
    ],
];
