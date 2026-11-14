<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use App\Models\Stable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** A stable's profile circle is never empty: its own picture, else the app logo, else the bundled copy of it. */
class StableProfilePhotoTest extends TestCase
{
    public function test_the_profile_photo_falls_back_to_the_app_logo_then_the_bundled_one(): void
    {
        $this->settings(['app_logo' => 'brand/app.png']);
        $this->assertSame(url('/storage/stables/logos/a.jpg'), (new Stable(['logo' => 'stables/logos/a.jpg']))->profile_photo_url);
        $this->assertSame(url('/storage/brand/app.png'), (new Stable)->profile_photo_url);

        $this->settings([]);
        $this->assertSame(asset('images/app-logo.jpg'), (new Stable)->profile_photo_url);
    }

    /** @param  array<string, string>  $settings */
    private function settings(array $settings): void
    {
        Cache::forever('site_settings.all', collect($settings)->map(fn ($value, $key) => (object) ['key' => $key, 'type' => 'text', 'value' => $value]));
        SiteSetting::resetMemo();
    }
}
