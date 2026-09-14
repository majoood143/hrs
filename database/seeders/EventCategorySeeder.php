<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use Illuminate\Database\Seeder;

class EventCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['en_name' => 'Meeting', 'ar_name' => 'اجتماع', 'color' => '#2f5c8a', 'order' => 0],
            ['en_name' => 'Holiday', 'ar_name' => 'عطلة', 'color' => '#9a6b1f', 'order' => 1],
            ['en_name' => 'Deadline', 'ar_name' => 'موعد نهائي', 'color' => '#a83e3e', 'order' => 2],
            ['en_name' => 'Social', 'ar_name' => 'اجتماعي', 'color' => '#6b4b8a', 'order' => 3],
            ['en_name' => 'Reminder', 'ar_name' => 'تذكير', 'color' => '#b8860f', 'order' => 4],
            ['en_name' => 'Other', 'ar_name' => 'أخرى', 'color' => '#5c6259', 'order' => 5],
        ];

        foreach ($categories as $category) {
            EventCategory::query()->updateOrCreate(
                ['en_name' => $category['en_name']],
                $category
            );
        }
    }
}
