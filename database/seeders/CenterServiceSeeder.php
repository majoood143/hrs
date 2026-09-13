<?php

namespace Database\Seeders;

use App\Models\CenterService;
use Illuminate\Database\Seeder;

class CenterServiceSeeder extends Seeder
{
    /**
     * Seed the services an equestrian center can offer.
     */
    public function run(): void
    {
        $services = [
            ['en_name' => 'Riding Lessons', 'ar_name' => 'دروس ركوب الخيل', 'applies_to' => 'training'],
            ['en_name' => 'Show Jumping Training', 'ar_name' => 'تدريب القفز', 'applies_to' => 'training'],
            ['en_name' => 'Dressage Training', 'ar_name' => 'تدريب الترويض', 'applies_to' => 'training'],
            ['en_name' => 'Endurance Training', 'ar_name' => 'تدريب التحمل', 'applies_to' => 'training'],
            ['en_name' => 'Stud Services', 'ar_name' => 'خدمات التلقيح', 'applies_to' => 'breeding'],
            ['en_name' => 'Foaling Management', 'ar_name' => 'إدارة الولادة', 'applies_to' => 'breeding'],
            ['en_name' => 'Genetic Testing', 'ar_name' => 'الفحص الوراثي', 'applies_to' => 'breeding'],
            ['en_name' => 'Daily Boarding', 'ar_name' => 'الإيواء اليومي', 'applies_to' => 'boarding'],
            ['en_name' => 'Paddock Turnout', 'ar_name' => 'المراعي الخارجية', 'applies_to' => 'boarding'],
            ['en_name' => 'Feeding & Nutrition Plans', 'ar_name' => 'خطط التغذية', 'applies_to' => 'all'],
            ['en_name' => 'Physiotherapy', 'ar_name' => 'العلاج الطبيعي', 'applies_to' => 'rehabilitation'],
            ['en_name' => 'Hydrotherapy', 'ar_name' => 'العلاج المائي', 'applies_to' => 'rehabilitation'],
            ['en_name' => 'Post-Injury Recovery Programs', 'ar_name' => 'برامج التعافي بعد الإصابة', 'applies_to' => 'rehabilitation'],
            ['en_name' => 'Grooming Services', 'ar_name' => 'خدمات العناية', 'applies_to' => 'all'],
        ];

        foreach ($services as $service) {
            CenterService::query()->updateOrCreate(
                ['en_name' => $service['en_name']],
                $service
            );
        }
    }
}
