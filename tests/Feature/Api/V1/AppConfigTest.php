<?php

namespace Tests\Feature\Api\V1;

use App\Models\SiteSetting;
use App\Support\MobileApp\MobileImages;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

class AppConfigTest extends TestCase
{
    use PreparesMobileApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi([
            'site_name_en' => 'Muhra',
            'site_name_ar' => 'مهرة',
            'app_logo' => 'branding/app.png',
            'primary_color' => '#112233',
            'heading_font' => 'cairo',
            'currency_code' => 'OMR',
            'currency_symbol' => 'ر.ع.',
            'currency_icon' => 'branding/rial.svg',
            'contact_email' => 'hello@example.com',
            'social_instagram_url' => 'https://instagram.com/muhra',
            'terms_url_en' => 'https://example.com/terms',
            'terms_url_ar' => 'https://example.com/ar/terms',
            'privacy_url_en' => 'https://example.com/privacy',
            'mobile.images' => json_encode(['stables' => 'mobile-app/services/stables.jpg']),
        ]);
    }

    public function test_it_returns_general_settings_in_english_by_default(): void
    {
        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('data.site_name', 'Muhra')
            ->assertJsonPath('data.app_logo_url', url('/storage/branding/app.png'))
            ->assertJsonPath('data.site_logo_url', null)
            ->assertJsonPath('data.branding.primary_color', '#112233')
            ->assertJsonPath('data.branding.button_color', '#112233')
            ->assertJsonPath('data.branding.heading_font', ['key' => 'cairo', 'family' => 'Cairo'])
            ->assertJsonPath('data.branding.body_font.key', 'inter')
            ->assertJsonPath('data.currency', [
                'code' => 'OMR',
                'symbol' => 'ر.ع.',
                'icon_url' => url('/storage/branding/rial.svg'),
            ])
            ->assertJsonPath('data.contact', ['email' => 'hello@example.com', 'phone' => null])
            ->assertJsonPath('data.social.instagram_url', 'https://instagram.com/muhra')
            ->assertJsonPath('data.social.x_url', null)
            ->assertJsonPath('data.terms_url', 'https://example.com/terms')
            ->assertJsonPath('data.privacy_url', 'https://example.com/privacy')
            ->assertJsonPath('data.services_images', [
                'bookings' => null,
                'stables' => url('/storage/mobile-app/services/stables.jpg'),
                'racing' => null,
            ])
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.locales.1', ['code' => 'ar', 'name' => 'Arabic', 'native' => 'عربي', 'dir' => 'rtl']);
    }

    public function test_accept_language_picks_the_language_of_every_text(): void
    {
        $this->getJson('/api/v1/app-config', ['Accept-Language' => 'ar-OM,ar;q=0.9,en;q=0.8'])
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('data.site_name', 'مهرة')
            ->assertJsonPath('data.terms_url', 'https://example.com/ar/terms')
            ->assertJsonPath('data.privacy_url', 'https://example.com/privacy')
            ->assertJsonPath('data.locale', 'ar');

        // A language the site does not have falls through to the next one asked for, then the default.
        $this->getJson('/api/v1/app-config', ['Accept-Language' => 'fr-FR,ar;q=0.5'])->assertJsonPath('data.locale', 'ar');
        $this->getJson('/api/v1/app-config', ['Accept-Language' => 'fr'])->assertJsonPath('data.locale', 'en');
    }

    public function test_services_photos_are_saved_and_a_replaced_one_is_deleted(): void
    {
        Storage::fake('public');
        Cache::forget('site_settings.all');
        SiteSetting::resetMemo();
        if (! Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('type')->default('text');
                $table->text('value')->nullable();
                $table->string('description')->nullable();
                $table->string('managed_by')->nullable();
                $table->timestamps();
            });
        }

        Storage::disk('public')->put('mobile-app/services/old.jpg', 'old');
        Storage::disk('public')->put('mobile-app/services/new.jpg', 'new');
        Storage::disk('public')->put('mobile-app/services/racing.jpg', 'racing');

        // FileUpload hands over a list while it holds an upload, a plain path otherwise.
        MobileImages::save(['stables' => 'mobile-app/services/old.jpg', 'racing' => ['mobile-app/services/racing.jpg']]);
        $this->assertSame(['bookings' => null, 'stables' => 'mobile-app/services/old.jpg', 'racing' => 'mobile-app/services/racing.jpg'], MobileImages::settings());

        MobileImages::save(['stables' => 'mobile-app/services/new.jpg', 'racing' => null]);
        $this->assertSame(['bookings' => null, 'stables' => 'mobile-app/services/new.jpg', 'racing' => null], MobileImages::settings());
        Storage::disk('public')->assertMissing('mobile-app/services/old.jpg');
        Storage::disk('public')->assertMissing('mobile-app/services/racing.jpg');
        Storage::disk('public')->assertExists('mobile-app/services/new.jpg');

        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertJsonPath('data.services_images.stables', Storage::disk('public')->url('mobile-app/services/new.jpg'))
            ->assertJsonPath('data.services_images.racing', null);
    }

    public function test_it_is_public_and_rate_limited_without_a_session(): void
    {
        $response = $this->get('/api/v1/app-config')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '120');

        $this->assertStringContainsString('Accept-Language', (string) $response->headers->get('Vary'));
        $this->assertEmpty($response->headers->getCookies(), 'the API never starts a session');
    }

    public function test_errors_are_json_even_without_an_accept_header(): void
    {
        $this->get('/api/v1/nothing-here')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }
}
