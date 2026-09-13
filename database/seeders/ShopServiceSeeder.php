<?php

namespace Database\Seeders;

use App\Models\ShopService;
use Illuminate\Database\Seeder;

class ShopServiceSeeder extends Seeder
{
    /**
     * Seed the product categories a horse shop can offer.
     */
    public function run(): void
    {
        $services = [
            ['en_name' => 'Saddles', 'ar_name' => 'السروج', 'applies_to' => 'saddlery'],
            ['en_name' => 'Bridles & Halters', 'ar_name' => 'اللجم والرسن', 'applies_to' => 'tack'],
            ['en_name' => 'Rugs & Blankets', 'ar_name' => 'الأغطية والبطانيات', 'applies_to' => 'tack'],
            ['en_name' => 'Grooming Supplies', 'ar_name' => 'مستلزمات العناية', 'applies_to' => 'tack'],
            ['en_name' => 'Horse Feed', 'ar_name' => 'أعلاف الخيول', 'applies_to' => 'feed_supplements'],
            ['en_name' => 'Vitamins & Supplements', 'ar_name' => 'الفيتامينات والمكملات', 'applies_to' => 'feed_supplements'],
            ['en_name' => 'Hay & Forage', 'ar_name' => 'التبن والأعلاف الخشنة', 'applies_to' => 'feed_supplements'],
            ['en_name' => 'Stable Equipment', 'ar_name' => 'معدات الإسطبل', 'applies_to' => 'equipment'],
            ['en_name' => 'Transport Equipment', 'ar_name' => 'معدات النقل', 'applies_to' => 'equipment'],
            ['en_name' => 'Riding Apparel', 'ar_name' => 'ملابس ركوب الخيل', 'applies_to' => 'all'],
            ['en_name' => 'Leatherwork & Repairs', 'ar_name' => 'أعمال الجلود والإصلاح', 'applies_to' => 'saddlery'],
            ['en_name' => 'Custom Saddle Fitting', 'ar_name' => 'تفصيل السروج حسب الطلب', 'applies_to' => 'saddlery'],
        ];

        foreach ($services as $service) {
            ShopService::query()->updateOrCreate(
                ['en_name' => $service['en_name']],
                $service
            );
        }
    }
}
