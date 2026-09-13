<?php

use App\Models\CmsPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CmsPage::firstOrCreate(
            ['slug' => 'centers'],
            [
                'title' => ['en' => 'Centers', 'ar' => 'المراكز'],
                'meta_title' => ['en' => 'Centers', 'ar' => 'المراكز'],
                'meta_description' => [
                    'en' => 'Discover equestrian centers offering training, breeding, boarding, and rehabilitation services near you.',
                    'ar' => 'اكتشف مراكز الفروسية التي تقدم خدمات التدريب والتربية والإيواء وإعادة التأهيل بالقرب منك.',
                ],
                'status' => 'published',
                'published_at' => now(),
                'is_homepage' => false,
                'is_system' => true,
                'show_title' => false,
                'content' => [],
            ]
        );
    }

    public function down(): void
    {
        CmsPage::query()->where('slug', 'centers')->where('is_system', true)->delete();
    }
};
