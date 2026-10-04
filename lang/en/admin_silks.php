<?php

return [
    'colors' => [
        'label' => 'Silk colour',
        'plural' => 'Silk colours',
    ],
    'patterns' => [
        'label' => 'Silk pattern',
        'plural' => 'Silk patterns',
    ],
    'fields' => [
        'en_name' => 'Name (English)',
        'ar_name' => 'Name (Arabic)',
        'key' => 'Key',
        'key_help' => 'Used in share links. Changing it makes old links fall back to a default.',
        'hex' => 'Colour',
        'is_active' => 'Shown in the designer',
        'sort' => 'Order',
        'area' => 'Part',
        'svg' => 'Pattern shapes (SVG)',
        'svg_help' => 'Shapes only (path, rect, circle, ellipse, polygon, polyline, line, g), drawn in a :width × :height box with 0,0 at the top left. They are painted in the visitor\'s pattern colour; use fill="none" stroke="currentColor" for lines. Anything outside the part\'s outline is cut off.',
        'preview' => 'Preview',
    ],
    'actions' => [
        'restore_defaults' => 'Add missing defaults',
        'restore_defaults_help' => 'Adds back any of the built-in items that were deleted. Nothing you changed is touched.',
        'restored' => '{0} Nothing was missing.|{1} 1 item added.|[2,*] :count items added.',
    ],
    'errors' => [
        'svg_invalid' => 'This is not valid SVG markup.',
        'svg_dropped' => 'These are not allowed: :items. Use plain shapes and geometry only.',
        'plain_reserved' => '"plain" is reserved: every part can always be plain.',
    ],
];
