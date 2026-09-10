<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Seed Oman's governorates (regions).
     */
    public function run(): void
    {
        $oman = Country::query()->where('en_name', 'Oman')->first();

        if (! $oman) {
            return;
        }

        $regions = [
            ['en_name' => 'Muscat', 'ar_name' => 'مسقط'],
            ['en_name' => 'Dhofar', 'ar_name' => 'ظفار'],
            ['en_name' => 'Musandam', 'ar_name' => 'مسندم'],
            ['en_name' => 'Al Buraimi', 'ar_name' => 'البريمي'],
            ['en_name' => 'Ad Dakhiliyah', 'ar_name' => 'الداخلية'],
            ['en_name' => 'Al Batinah North', 'ar_name' => 'شمال الباطنة'],
            ['en_name' => 'Al Batinah South', 'ar_name' => 'جنوب الباطنة'],
            ['en_name' => 'Al Sharqiyah North', 'ar_name' => 'شمال الشرقية'],
            ['en_name' => 'Al Sharqiyah South', 'ar_name' => 'جنوب الشرقية'],
            ['en_name' => 'Ad Dhahirah', 'ar_name' => 'الظاهرة'],
            ['en_name' => 'Al Wusta', 'ar_name' => 'الوسطى'],
        ];

        foreach ($regions as $region) {
            Region::query()->updateOrCreate(
                ['country_id' => $oman->id, 'en_name' => $region['en_name']],
                array_merge($region, ['country_id' => $oman->id])
            );
        }
    }
}
