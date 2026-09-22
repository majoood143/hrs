<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'type', 'value', 'description', 'managed_by'];

    /**
     * The whole table, read once per request. `get()` is called dozens of times rendering a
     * single page (the site layout alone calls it ~20 times); without this, every one of those
     * still round-trips to the cache *store* (Cache::rememberForever still calls the store's
     * get() first, which is a real query with the database cache driver — the documented
     * default) even though the value never changes within a request.
     */
    private static ?Collection $memo = null;

    protected static function cached(): Collection
    {
        return static::$memo ??= Cache::rememberForever('site_settings.all', fn () => static::query()->get()->keyBy('key'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::cached()->get($key);

        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($setting->value) ? $setting->value + 0 : $default,
            default => $setting->value,
        };
    }

    public static function set(string $key, mixed $value, string $type = 'text', ?string $description = null, ?string $managedBy = null): static
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            array_filter([
                'type' => $type,
                'value' => is_bool($value) ? ($value ? 'true' : 'false') : $value,
                'description' => $description,
                'managed_by' => $managedBy,
            ], fn ($v) => $v !== null)
        );

        static::clearCache();

        return $setting;
    }

    public static function clearCache(): void
    {
        static::$memo = null;
        Cache::forget('site_settings.all');
    }

    /**
     * Drop only the in-process copy, leaving the shared cache store entry alone: called once per
     * request/boot (AppServiceProvider) so a static property that would otherwise outlive a
     * request under Octane, queue workers or the test suite never serves another request's or
     * another test's settings.
     */
    public static function resetMemo(): void
    {
        static::$memo = null;
    }

    public static function siteName(): string
    {
        $key = app()->getLocale() === 'ar' ? 'site_name_ar' : 'site_name_en';

        return (string) static::get($key, config('app.name', 'HRS'));
    }

    public static function footerText(): string
    {
        $key = app()->getLocale() === 'ar' ? 'footer_text_ar' : 'footer_text_en';

        return (string) static::get($key, '');
    }

    public static function siteLogoUrl(): ?string
    {
        $path = static::get('site_logo');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function appLogoUrl(): ?string
    {
        $path = static::get('app_logo');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function faviconUrl(): ?string
    {
        $path = static::get('favicon');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Configured currency, with sane fallbacks so every consumer (Filament
     * resources, blade views, JS) reads from a single, already-defaulted place.
     *
     * @return array{code: string, symbol: string, icon_url: ?string}
     */
    public static function currency(): array
    {
        $code = (string) static::get('currency_code', 'OMR');
        $iconPath = static::get('currency_icon');

        return [
            'code' => $code,
            'symbol' => (string) static::get('currency_symbol', $code),
            'icon_url' => $iconPath ? Storage::disk('public')->url($iconPath) : null,
        ];
    }

    public static function formatCurrency(float|int|string $amount, int $decimals = 2): string
    {
        return static::currency()['symbol'].' '.number_format((float) $amount, $decimals);
    }

    /**
     * Same as formatCurrency(), but as HTML: the amount next to the uploaded currency icon (SVG)
     * instead of the symbol text, when one is uploaded. Returned as an HtmlString so Blade (`{{ }}`)
     * and Filament (Stat/TextColumn) render it unescaped instead of printing the raw markup.
     */
    public static function formatCurrencyHtml(float|int|string $amount, int $decimals = 2): HtmlString
    {
        $currency = static::currency();
        $formatted = number_format((float) $amount, $decimals);

        if ($currency['icon_url']) {
            return new HtmlString(
                '<img src="'.e($currency['icon_url']).'" alt="'.e($currency['code']).'" class="inline-block h-3.5 w-3.5 object-contain align-middle">'
                .' '.e($formatted)
            );
        }

        return new HtmlString(e($currency['symbol']).' '.e($formatted));
    }

    /**
     * Public-website theme values, with sane fallbacks so every consumer
     * (layout, CSS) reads from a single, already-defaulted place.
     *
     * @return array{primary: string, secondary: string, accent: string, button_color: string, button_text_color: string, heading_font: string, body_font: string}
     */
    public static function branding(): array
    {
        $primary = static::get('primary_color', '#05602b');
        $secondary = static::get('secondary_color', '#0da74c');
        $accent = static::get('accent_color', '#0ea5e9');
        $buttonColor = static::get('button_color', '');
        $fonts = config('fonts', []);
        $headingFont = static::get('heading_font', 'fraunces');
        $bodyFont = static::get('body_font', 'inter');

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'accent' => $accent,
            'button_color' => $buttonColor !== '' ? $buttonColor : $primary,
            'button_text_color' => static::get('button_text_color', '') ?: '#ffffff',
            'heading_font' => array_key_exists($headingFont, $fonts) ? $headingFont : 'fraunces',
            'body_font' => array_key_exists($bodyFont, $fonts) ? $bodyFont : 'inter',
        ];
    }
}
