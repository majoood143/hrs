<?php

namespace Database\Seeders;

use App\Models\CmsCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CmsCategorySeeder extends Seeder
{
    /**
     * Seed the blog/CMS post categories.
     */
    public function run(): void
    {
        $categories = [
            ['en' => 'News', 'ar' => 'أخبار'],
            ['en' => 'Events', 'ar' => 'فعاليات'],
            ['en' => 'Racing', 'ar' => 'السباقات'],
            ['en' => 'Breeding', 'ar' => 'التربية والتزاوج'],
            ['en' => 'Training Tips', 'ar' => 'نصائح التدريب'],
            ['en' => 'Health & Veterinary', 'ar' => 'الصحة والرعاية البيطرية'],
            ['en' => 'Nutrition', 'ar' => 'التغذية'],
            ['en' => 'Stable Management', 'ar' => 'إدارة الإسطبل'],
            ['en' => 'Announcements', 'ar' => 'إعلانات'],
        ];

        foreach ($categories as $order => $category) {
            $slug = Str::slug($category['en']);

            CmsCategory::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => ['en' => $category['en'], 'ar' => $category['ar']],
                    'order' => $order,
                ]
            );
        }
    }
}
