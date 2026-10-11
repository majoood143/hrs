<?php

namespace Tests\Feature\Api\V1;

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
