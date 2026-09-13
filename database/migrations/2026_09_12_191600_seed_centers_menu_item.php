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
                ->where('route_name', 'centers.index')
                ->exists();

            if (! $exists) {
                // Shift the items that come after Clinics to make room for Centers at order 12.
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
                    'route_name' => 'centers.index',
                    'label' => ['en' => 'Centers', 'ar' => 'المراكز'],
                    'order' => 12,
                    'target' => '_self',
                ]);
            }
        }
    }

    public function down(): void
    {
        $header = CmsMenu::query()->where('location', 'header')->first();

        CmsMenuItem::query()
            ->where('route_name', 'centers.index')
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
