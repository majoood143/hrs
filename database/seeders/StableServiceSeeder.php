<?php

namespace Database\Seeders;

use App\Models\StableService;
use Illuminate\Database\Seeder;

class StableServiceSeeder extends Seeder
{
    /**
     * Seed the services a stable can offer.
     */
    public function run(): void
    {
        $services = [
            ['en_name' => 'Boarding', 'ar_name' => 'إيواء'],
            ['en_name' => 'Feeding & Nutrition', 'ar_name' => 'التغذية'],
            ['en_name' => 'Grooming', 'ar_name' => 'العناية والتجميل'],
            ['en_name' => 'Training', 'ar_name' => 'التدريب'],
            ['en_name' => 'Riding Lessons', 'ar_name' => 'دروس ركوب الخيل'],
            ['en_name' => 'Farrier Services', 'ar_name' => 'خدمات البيطرة (الحدادة)'],
            ['en_name' => 'Veterinary Care', 'ar_name' => 'الرعاية البيطرية'],
            ['en_name' => 'Exercise & Turnout', 'ar_name' => 'التمارين والإخراج للمراعي'],
            ['en_name' => 'Breeding Services', 'ar_name' => 'خدمات التربية والتزاوج'],
            ['en_name' => 'Transportation', 'ar_name' => 'نقل الخيول'],
            ['en_name' => 'Arena Rental', 'ar_name' => 'تأجير الحلبة'],
            ['en_name' => 'Quarantine', 'ar_name' => 'الحجر الصحي'],
        ];

        foreach ($services as $service) {
            StableService::query()->updateOrCreate(
                ['en_name' => $service['en_name']],
                $service
            );
        }
    }
}
