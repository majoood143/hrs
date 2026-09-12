<?php

namespace Database\Seeders;

use App\Models\ClinicService;
use Illuminate\Database\Seeder;

class ClinicServiceSeeder extends Seeder
{
    /**
     * Seed the services a veterinary clinic or pharmacy can offer.
     */
    public function run(): void
    {
        $services = [
            ['en_name' => 'General Checkup', 'ar_name' => 'الفحص العام', 'applies_to' => 'clinic'],
            ['en_name' => 'Vaccination', 'ar_name' => 'التطعيم', 'applies_to' => 'clinic'],
            ['en_name' => 'Dental Care', 'ar_name' => 'رعاية الأسنان', 'applies_to' => 'clinic'],
            ['en_name' => 'Surgery', 'ar_name' => 'الجراحة', 'applies_to' => 'clinic'],
            ['en_name' => 'X-Ray & Imaging', 'ar_name' => 'الأشعة والتصوير', 'applies_to' => 'clinic'],
            ['en_name' => 'Laboratory Testing', 'ar_name' => 'الفحوصات المخبرية', 'applies_to' => 'both'],
            ['en_name' => 'Deworming', 'ar_name' => 'التخلص من الديدان', 'applies_to' => 'clinic'],
            ['en_name' => 'Reproduction & Breeding', 'ar_name' => 'التكاثر والتربية', 'applies_to' => 'clinic'],
            ['en_name' => 'Emergency Care', 'ar_name' => 'الرعاية الطارئة', 'applies_to' => 'both'],
            ['en_name' => 'Lameness Examination', 'ar_name' => 'فحص العرج', 'applies_to' => 'clinic'],
            ['en_name' => 'Microchipping', 'ar_name' => 'زرع الشريحة الإلكترونية', 'applies_to' => 'clinic'],
            ['en_name' => 'Nutrition Consultation', 'ar_name' => 'استشارات التغذية', 'applies_to' => 'both'],
            ['en_name' => 'Medication Dispensing', 'ar_name' => 'صرف الأدوية', 'applies_to' => 'pharmacy'],
            ['en_name' => 'Home Delivery', 'ar_name' => 'التوصيل المنزلي', 'applies_to' => 'pharmacy'],
            ['en_name' => 'Supplements & Nutrition Products', 'ar_name' => 'المكملات ومنتجات التغذية', 'applies_to' => 'pharmacy'],
            ['en_name' => 'Prescription Compounding', 'ar_name' => 'تحضير الوصفات الطبية', 'applies_to' => 'pharmacy'],
        ];

        foreach ($services as $service) {
            ClinicService::query()->updateOrCreate(
                ['en_name' => $service['en_name']],
                $service
            );
        }
    }
}
