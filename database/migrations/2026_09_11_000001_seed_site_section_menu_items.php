<?php

use App\Models\CmsMenu;
use App\Models\CmsMenuItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $header = CmsMenu::query()->where('location', 'header')->first();

        if ($header) {
            $sections = [
                ['route_name' => 'stables.index', 'label' => ['en' => 'Stables', 'ar' => 'الإسطبلات'], 'order' => 10],
                ['route_name' => 'clinics.index', 'label' => ['en' => 'Clinics', 'ar' => 'العيادات'], 'order' => 11],
                ['route_name' => 'horses-for-sale.index', 'label' => ['en' => 'Horses for Sale', 'ar' => 'خيول للبيع'], 'order' => 12],
                ['route_name' => 'farriers.index', 'label' => ['en' => 'Farriers', 'ar' => 'البياطرة'], 'order' => 13],
                ['route_name' => 'tools-for-sale.index', 'label' => ['en' => 'Tools for Sale', 'ar' => 'أدوات للبيع'], 'order' => 14],
                ['route_name' => 'transfer-board.index', 'label' => ['en' => 'Find a Transfer', 'ar' => 'ابحث عن نقلة'], 'order' => 15, 'is_button' => true],
            ];

            foreach ($sections as $section) {
                $exists = CmsMenuItem::query()
                    ->where('menu_id', $header->id)
                    ->where('route_name', $section['route_name'])
                    ->exists();

                if (! $exists) {
                    CmsMenuItem::create($section + ['menu_id' => $header->id, 'target' => '_self']);
                }
            }
        }

        CmsMenuItem::query()
            ->where('url', '/transportation')
            ->update(['url' => null, 'route_name' => 'transfer-board.index']);
    }

    public function down(): void
    {
        CmsMenuItem::query()
            ->whereIn('route_name', [
                'stables.index',
                'clinics.index',
                'horses-for-sale.index',
                'farriers.index',
                'tools-for-sale.index',
                'transfer-board.index',
            ])
            ->delete();
    }
};
