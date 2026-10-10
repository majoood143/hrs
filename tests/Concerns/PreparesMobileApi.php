<?php

namespace Tests\Concerns;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The mobile API (/api/v1) tests: site settings faked through the settings cache (no site_settings
 * table), plus the real lookup and sale-post migrations, which are portable.
 */
trait PreparesMobileApi
{
    private const MOBILE_API_MIGRATIONS = [
        '2025_08_11_194003_create_colors_table.php',
        '2026_09_08_000001_add_ar_name_to_colors_table.php',
        '2025_08_11_195346_create_genders_table.php',
        '2025_08_11_200246_create_types_table.php',
        '2025_08_13_201740_create_countries_table.php',
        '2026_09_07_121818_add_is_public_to_countries_table.php',
        '2026_09_13_000000_add_country_code_and_phone_code_to_countries_table.php',
        '2026_09_13_000001_add_currency_nationality_and_order_to_countries_table.php',
        '2025_08_13_202625_create_regions_table.php',
        '2025_08_24_155228_add_new_column_to_regions_table.php',
        '2025_08_24_182524_create_cities_table.php',
        '2026_09_08_000000_create_horse_sale_posts_table.php',
        '2026_09_12_100000_add_pedigree_and_gallery_to_horse_sale_posts_table.php',
        '2026_09_08_030000_create_tool_sale_posts_table.php',
        '2026_09_30_000001_add_views_count_to_listings.php',
        '2026_09_30_000003_add_price_negotiable_to_sale_posts.php',
    ];

    protected function prepareMobileApi(array $settings = []): void
    {
        Cache::flush();
        $this->seedApiSettings($settings);

        foreach (self::MOBILE_API_MIGRATIONS as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    /**
     * @param  array<string, mixed>  $settings  site setting key => value (booleans become "boolean" settings)
     */
    protected function seedApiSettings(array $settings): void
    {
        Cache::forever('site_settings.all', collect($settings)->map(fn ($value, $key) => (object) [
            'key' => $key,
            'type' => is_bool($value) ? 'boolean' : 'text',
            'value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
        ]));

        SiteSetting::resetMemo();
    }

    /**
     * Oman (public) → Muscat → Seeb and Bawshar, and a hidden country. Returns the ids by name.
     *
     * @return array<string, int>
     */
    protected function seedLocations(): array
    {
        $ids['oman'] = DB::table('countries')->insertGetId(['en_name' => 'Oman', 'ar_name' => 'عمان', 'country_code' => 'OM', 'is_public' => true]);
        $ids['hidden'] = DB::table('countries')->insertGetId(['en_name' => 'Hidden land', 'ar_name' => 'مخفية', 'is_public' => false]);
        $ids['muscat'] = DB::table('regions')->insertGetId(['country_id' => $ids['oman'], 'en_name' => 'Muscat', 'ar_name' => 'مسقط']);
        $ids['seeb'] = DB::table('cities')->insertGetId(['region_id' => $ids['muscat'], 'en_name' => 'Seeb', 'ar_name' => 'السيب']);
        $ids['bawshar'] = DB::table('cities')->insertGetId(['region_id' => $ids['muscat'], 'en_name' => 'Bawshar', 'ar_name' => 'بوشر']);

        return $ids;
    }
}
