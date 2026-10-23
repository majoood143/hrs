<?php

// The Muhra mobile app (repo ../hrs-mobile). What the admin controls (features, versions, maintenance,
// announcements) is in the admin "Mobile app" screens; these are the fixed facts about the store listings.
return [
    // Android application id, as in the app's app.json (the iOS bundle id is om.muhra.app). The Play
    // Store link and assetlinks.json are built from it.
    'android_package' => env('MOBILE_ANDROID_PACKAGE', 'om.muhra.android'),

    // Searched for on the App Store until the admin enters the app's App Store id.
    'store_search_term' => env('MOBILE_STORE_SEARCH_TERM', 'Muhra'),
];
