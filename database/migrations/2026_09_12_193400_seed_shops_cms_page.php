<?php

use App\Models\CmsPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CmsPage::firstOrCreate(
            ['slug' => 'shops'],
            [
                'title' => ['en' => 'Shops', 'ar' => 'المتاجر'],
                'meta_title' => ['en' => 'Shops', 'ar' => 'المتاجر'],
                'meta_description' => [
                    'en' => 'Browse tack, feed, equipment, and saddlery shops for everything your horse needs.',
                    'ar' => 'تصفح متاجر السروج والأعلاف والمعدات والسراجة لكل ما يحتاجه حصانك.',
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
        CmsPage::query()->where('slug', 'shops')->where('is_system', true)->delete();
    }
};
