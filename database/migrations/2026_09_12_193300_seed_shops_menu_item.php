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
            $exists = CmsMenuItem::query()
                ->where('menu_id', $header->id)
                ->where('route_name', 'shops.index')
                ->exists();

            if (! $exists) {
                // Shift the items that come after Centers to make room for Shops at order 13.
                CmsMenuItem::query()
                    ->where('menu_id', $header->id)
                    ->whereIn('route_name', [
                        'horses-for-sale.index',
                        'farriers.index',
                        'tools-for-sale.index',
                        'transfer-board.index',
                    ])
                    ->increment('order');

                CmsMenuItem::create([
                    'menu_id' => $header->id,
                    'route_name' => 'shops.index',
                    'label' => ['en' => 'Shops', 'ar' => 'المتاجر'],
                    'order' => 13,
                    'target' => '_self',
                ]);
            }
        }
    }

    public function down(): void
    {
        $header = CmsMenu::query()->where('location', 'header')->first();

        CmsMenuItem::query()
            ->where('route_name', 'shops.index')
            ->delete();

        if ($header) {
            CmsMenuItem::query()
                ->where('menu_id', $header->id)
                ->whereIn('route_name', [
                    'horses-for-sale.index',
                    'farriers.index',
                    'tools-for-sale.index',
                    'transfer-board.index',
                ])
                ->decrement('order');
        }
    }
};
