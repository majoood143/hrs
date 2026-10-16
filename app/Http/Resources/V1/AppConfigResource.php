<?php

namespace App\Http\Resources\V1;

use App\Models\SiteSetting;
use App\Services\Auth\CustomerOtpService;
use App\Services\Sms\SmsManager;
use App\Support\MobileApp\MobileImages;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * General Settings as the app needs them: name, logos, theme, currency, contact, social links, terms.
 * The app caches this and themes itself from it, so an admin change reaches it without a store release.
 */
class AppConfigResource extends JsonResource
{
    public function __construct()
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        $branding = SiteSetting::branding();
        $fonts = config('fonts', []);
        $ar = app()->getLocale() === 'ar';

        return [
            'site_name' => SiteSetting::siteName(),
            /** @var string|null */
            'site_logo_url' => SiteSetting::siteLogoUrl(),
            /**
             * Square logo for the app icon and splash (General Settings "App logo").
             *
             * @var string|null
             */
            'app_logo_url' => SiteSetting::appLogoUrl(),
            /** The public website's home page. */
            'website_url' => url('/'),
            'branding' => [
                /** @example #05602b */
                'primary_color' => (string) $branding['primary'],
                'secondary_color' => (string) $branding['secondary'],
                'accent_color' => (string) $branding['accent'],
                'button_color' => (string) $branding['button_color'],
                'button_text_color' => (string) $branding['button_text_color'],
                'heading_font' => [
                    /** @example fraunces */
                    'key' => (string) $branding['heading_font'],
                    /** Google Fonts family name. */
                    'family' => (string) ($fonts[$branding['heading_font']]['label'] ?? $branding['heading_font']),
                ],
                'body_font' => [
                    'key' => (string) $branding['body_font'],
                    'family' => (string) ($fonts[$branding['body_font']]['label'] ?? $branding['body_font']),
                ],
            ],
            'currency' => CurrencyResource::current(),
            'contact' => [
                'email' => self::text('contact_email'),
                'phone' => self::text('contact_phone'),
            ],
            'social' => [
                'facebook_url' => self::text('social_facebook_url'),
                'instagram_url' => self::text('social_instagram_url'),
                'x_url' => self::text('social_x_url'),
                'youtube_url' => self::text('social_youtube_url'),
                'tiktok_url' => self::text('social_tiktok_url'),
                'linkedin_url' => self::text('social_linkedin_url'),
                'whatsapp_url' => self::text('social_whatsapp_url'),
            ],
            /** Terms page in the request's language (falls back to the other language's link). */
            'terms_url' => self::text($ar ? 'terms_url_ar' : 'terms_url_en') ?? self::text($ar ? 'terms_url_en' : 'terms_url_ar'),
            /** Privacy policy page in the request's language (falls back to the other language's link). */
            'privacy_url' => self::text($ar ? 'privacy_url_ar' : 'privacy_url_en') ?? self::text($ar ? 'privacy_url_en' : 'privacy_url_ar'),
            /** How verification codes (for posting) can be sent right now; neither = posting is unavailable. */
            'verification' => [
                'whatsapp' => (bool) rescue(fn () => app(CustomerOtpService::class)->whatsappAvailable(), false, false),
                'sms' => (bool) rescue(fn () => app(SmsManager::class)->canSend(), false, false),
            ],
            /**
             * Photos of the app's Services screen (admin → Mobile app → Services screen); null = the app's built-in photo.
             *
             * @var array{bookings: string|null, stables: string|null, racing: string|null}
             */
            'services_images' => MobileImages::urls(),
            /** The language this response is in (from Accept-Language). */
            'locale' => app()->getLocale(),
            'default_locale' => (string) config('languages.default', 'en'),
            'locales' => collect(config('languages.available', []))
                ->map(fn (array $language, string $code) => [
                    /** @example ar */
                    'code' => $code,
                    'name' => (string) ($language['name'] ?? $code),
                    'native' => (string) ($language['native'] ?? $code),
                    /** @var 'ltr'|'rtl' */
                    'dir' => (string) ($language['dir'] ?? 'ltr'),
                ])
                ->values()
                ->all(),
        ];
    }

    private static function text(string $key): ?string
    {
        $value = trim((string) SiteSetting::get($key, ''));

        return $value !== '' ? $value : null;
    }
}
