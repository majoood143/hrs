<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

class LookupsTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
    }

    public function test_it_lists_public_locations_and_the_filter_choices_in_the_requested_language(): void
    {
        $ids = $this->seedLocations();
        DB::table('types')->insert([['en_name' => 'Thoroughbred', 'ar_name' => 'أصيل'], ['en_name' => 'Arabian', 'ar_name' => 'عربي']]);
        DB::table('genders')->insert(['en_name' => 'Mare', 'ar_name' => 'فرس']);
        DB::table('colors')->insert(['en_name' => 'Bay', 'ar_name' => 'أحمر', 'hex_code' => '#8b4513']);

        $this->getJson('/api/v1/lookups')
            ->assertOk()
            ->assertJsonCount(1, 'data.countries')
            ->assertJsonPath('data.countries.0.id', $ids['oman'])
            ->assertJsonPath('data.countries.0.name', 'Oman')
            ->assertJsonPath('data.countries.0.code', 'OM')
            ->assertJsonPath('data.countries.0.regions.0.name', 'Muscat')
            ->assertJsonPath('data.countries.0.regions.0.cities', [
                ['id' => $ids['seeb'], 'name' => 'Seeb'],
                ['id' => $ids['bawshar'], 'name' => 'Bawshar'],
            ])
            ->assertJsonPath('data.types.0.name', 'Arabian')
            ->assertJsonPath('data.genders.0.name', 'Mare')
            ->assertJsonPath('data.colors.0', ['id' => 1, 'name' => 'Bay', 'hex' => '#8b4513'])
            ->assertJsonPath('data.tool_categories.0', ['value' => 'saddlery_tack', 'label' => __('tools-for-sale.categories.saddlery_tack', [], 'en')])
            ->assertJsonPath('data.tool_conditions.0.value', 'new');

        $this->getJson('/api/v1/lookups', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.countries.0.name', 'عمان')
            ->assertJsonPath('data.countries.0.regions.0.cities.0.name', 'السيب')
            ->assertJsonPath('data.colors.0.name', 'أحمر')
            ->assertJsonPath('data.tool_categories.0.label', __('tools-for-sale.categories.saddlery_tack', [], 'ar'));
    }
}
