<?php

namespace Database\Seeders;

use App\Models\ClinicService;
use Illuminate\Database\Seeder;

class ClinicServiceSeeder extends Seeder
{
    /**
     * Seed the services a veterinary clinic can offer.
     */
    public function run(): void
    {
        $services = [
            ['en_name' => 'General Checkup', 'ar_name' => 'الفحص العام'],
            ['en_name' => 'Vaccination', 'ar_name' => 'التطعيم'],
            ['en_name' => 'Dental Care', 'ar_name' => 'رعاية الأسنان'],
            ['en_name' => 'Surgery', 'ar_name' => 'الجراحة'],
            ['en_name' => 'X-Ray & Imaging', 'ar_name' => 'الأشعة والتصوير'],
            ['en_name' => 'Laboratory Testing', 'ar_name' => 'الفحوصات المخبرية'],
            ['en_name' => 'Deworming', 'ar_name' => 'التخلص من الديدان'],
            ['en_name' => 'Reproduction & Breeding', 'ar_name' => 'التكاثر والتربية'],
            ['en_name' => 'Emergency Care', 'ar_name' => 'الرعاية الطارئة'],
            ['en_name' => 'Lameness Examination', 'ar_name' => 'فحص العرج'],
            ['en_name' => 'Microchipping', 'ar_name' => 'زرع الشريحة الإلكترونية'],
            ['en_name' => 'Nutrition Consultation', 'ar_name' => 'استشارات التغذية'],
        ];

        foreach ($services as $service) {
            ClinicService::query()->updateOrCreate(
                ['en_name' => $service['en_name']],
                $service
            );
        }
    }
}
