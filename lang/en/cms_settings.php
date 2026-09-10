<?php

return [
    'title' => 'CMS Settings',

    'tabs' => [
        'general' => 'General',
        'seo' => 'SEO',
    ],

    'sections' => [
        'defaults' => 'Blog Defaults',
        'defaults_desc' => 'Fallback values used for blog posts that do not set their own.',
        'seo' => 'Search Engines & Feeds',
    ],

    'fields' => [
        'default_meta_description' => 'Default Blog Meta Description',
        'default_meta_description_helper' => 'Used for blog posts that do not set their own meta description.',
        'default_og_image' => 'Default Blog Social Share Image',
        'default_og_image_helper' => 'Used for blog posts that do not set their own social share image.',
        'posts_per_page' => 'Posts per Page',
        'sitemap_enabled' => 'Enable Sitemap',
        'sitemap_enabled_helper' => 'Publish an XML sitemap of pages and posts at /sitemap.xml.',
        'rss_enabled' => 'Enable RSS Feed',
        'rss_enabled_helper' => 'Publish an RSS feed of blog posts.',
        'robots_block_all' => 'Block All Search Engines',
        'robots_block_all_helper' => 'Add a site-wide noindex directive. Use only for staging/maintenance.',
    ],

    'notifications' => [
        'updated' => 'CMS settings updated successfully.',
    ],

    'actions' => [
        'save' => 'Save Settings',
    ],
];
