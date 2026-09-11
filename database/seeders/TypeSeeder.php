<?php

namespace Database\Seeders;

use App\Models\Type;
use Illuminate\Database\Seeder;

class TypeSeeder extends Seeder
{
    /**
     * Seed the horse types table.
     */
    public function run(): void
    {
        $types = [
            ['en_name' => 'Purebred Arabian', 'ar_name' => 'عربي أصيل'],
            ['en_name' => 'Thoroughbred (TB)', 'ar_name' => 'مهجن أصيل'],
            ['en_name' => 'RC', 'ar_name' => 'مهجن'],
        ];

        foreach ($types as $type) {
            Type::query()->updateOrCreate(
                ['en_name' => $type['en_name']],
                $type
            );
        }
    }
}
