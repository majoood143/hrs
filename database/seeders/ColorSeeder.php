<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    /**
     * Seed the horse coat colors table.
     */
    public function run(): void
    {
        $colors = [
            ['en_name' => 'Bay', 'ar_name' => 'كميت', 'hex_code' => '#6F4E37'],
            ['en_name' => 'Black', 'ar_name' => 'أدهم', 'hex_code' => '#1C1C1C'],
            ['en_name' => 'Chestnut', 'ar_name' => 'أشقر', 'hex_code' => '#954535'],
            ['en_name' => 'Grey', 'ar_name' => 'أشهب', 'hex_code' => '#B0B0B0'],
            ['en_name' => 'White', 'ar_name' => 'أبيض', 'hex_code' => '#F5F5F5'],
            ['en_name' => 'Palomino', 'ar_name' => 'أصفر ذهبي', 'hex_code' => '#DEB887'],
            ['en_name' => 'Dun', 'ar_name' => 'أصهب', 'hex_code' => '#C19A6B'],
            ['en_name' => 'Roan', 'ar_name' => 'أزرق مروّن', 'hex_code' => '#8B8589'],
            ['en_name' => 'Pinto', 'ar_name' => 'أبلق', 'hex_code' => '#A0522D'],
            ['en_name' => 'Brown', 'ar_name' => 'بني', 'hex_code' => '#5C4033'],
            ['en_name' => 'Buckskin', 'ar_name' => 'أصفر داكن', 'hex_code' => '#D2B48C'],
            ['en_name' => 'Cremello', 'ar_name' => 'كريمي', 'hex_code' => '#FAEBD7'],
        ];

        foreach ($colors as $color) {
            Color::query()->updateOrCreate(
                ['en_name' => $color['en_name']],
                $color
            );
        }
    }
}
