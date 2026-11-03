<?php

namespace Tests\Feature\Settings;

use App\Models\SiteSetting;
use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

class AppStoreBadgesTest extends TestCase
{
    use PreparesMobileApi;

    public function test_only_stores_with_a_link_and_a_badge_are_listed(): void
    {
        $this->prepareMobileApi([
            'store_app_store_url' => 'https://apps.apple.com/app/id1',
            'store_app_store_badge' => 'branding/app-store.png',
            'store_play_store_url' => 'https://play.google.com/store/apps/details?id=x',
            'store_huawei_badge' => 'branding/huawei.png',
        ]);

        $this->assertSame([[
            'name' => 'App Store',
            'url' => 'https://apps.apple.com/app/id1',
            'image_url' => url('/storage/branding/app-store.png'),
        ]], SiteSetting::appStoreBadges());
    }
}
