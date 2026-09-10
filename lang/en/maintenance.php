<?php

return [
    'title' => 'System Maintenance',
    'navigation_label' => 'Maintenance',

    'status' => [
        'up' => 'Site is live',
        'down' => 'Site is in maintenance mode',
        'since' => 'Enabled :time',
        'bypass_link' => 'Bypass link (share only with staff)',
    ],

    'confirm' => [
        'enable' => 'This will take the public website offline for all visitors except allowed IPs and the bypass link. Continue?',
        'disable' => 'This will bring the public website back online. Continue?',
    ],

    'actions' => [
        'enable' => 'Enable Maintenance Mode',
        'update' => 'Save & Update',
        'disable' => 'Disable Maintenance Mode',
    ],

    'sections' => [
        'message' => 'Maintenance Message',
        'message_desc' => 'Shown to visitors on the public website while maintenance mode is on.',
        'access' => 'Access Control',
        'access_desc' => 'Let specific people reach the site while it is down.',
        'behavior' => 'Behavior',
        'behavior_desc' => 'Fine-tune how browsers and clients handle the outage.',
    ],

    'fields' => [
        'message_en' => 'Message (English)',
        'message_ar' => 'Message (Arabic)',
        'secret' => 'Bypass Secret',
        'secret_helper' => 'Visiting /{secret} sets a cookie that lets that browser through, even while the site is down.',
        'allowed_ips' => 'Allowed IP Addresses',
        'allowed_ips_helper' => 'One per line (or comma-separated). These IPs skip the maintenance page entirely.',
        'retry' => 'Retry-After (seconds)',
        'retry_helper' => 'Suggested wait time sent to browsers and crawlers via the Retry-After header.',
        'refresh' => 'Auto-Refresh (seconds)',
        'refresh_helper' => 'If set, the maintenance page automatically reloads after this many seconds.',
    ],

    'notifications' => [
        'enabled' => 'Maintenance mode enabled.',
        'disabled' => 'Maintenance mode disabled — the site is live again.',
        'bypass_hint' => 'Bypass link: :url',
        'command_ran' => 'Ran `:command` successfully.',
    ],

    'system' => [
        'title' => 'System Information',
        'description' => 'Live environment snapshot',

        'groups' => [
            'application' => 'Application',
            'status' => 'Status',
            'infrastructure' => 'Infrastructure',
            'server' => 'Server',
        ],

        'laravel' => 'Laravel',
        'php' => 'PHP',
        'environment' => 'Environment',
        'timezone' => 'Timezone',

        'debug_mode' => 'Debug Mode',
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
        'maintenance' => 'Maintenance',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'queue' => 'Queue',
        'cache' => 'Cache',

        'db_driver' => 'DB Driver',
        'database' => 'Database',
        'session' => 'Session',
        'mail' => 'Mail',

        'os' => 'OS',
        'php_memory' => 'PHP Memory',
        'extensions' => 'Extensions',
        'extensions_loaded' => ':count loaded',
        'web_server' => 'Web Server',

        'log_file' => 'Log File',
        'cache_storage' => 'Cache Storage',
        'disk_usage' => 'Disk Usage',
        'disk_usage_detail' => ':free free / :total (:percent%)',

        'view_health_report' => 'View full health report',
        'view_backups' => 'Manage backups',
    ],

    'quick_actions' => [
        'title' => 'Quick Actions',
        'description' => 'Common housekeeping commands, run without leaving the admin panel.',
        'cache_clear' => 'Clear App Cache',
        'config_clear' => 'Clear Config Cache',
        'route_clear' => 'Clear Route Cache',
        'view_clear' => 'Clear View Cache',
        'optimize' => 'Optimize App',
        'queue_restart' => 'Restart Queue Workers',
        'storage_link' => 'Create Storage Link',
    ],

    'default_message_en' => "We're currently performing scheduled maintenance and will be back shortly. Thank you for your patience.",
    'default_message_ar' => 'نقوم حاليًا بأعمال صيانة مجدولة وسنعود قريبًا. نشكركم على تفهمكم.',
];
