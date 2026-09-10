<?php

namespace Database\Seeders;

use App\Models\Gender;
use Illuminate\Database\Seeder;

class GenderSeeder extends Seeder
{
    /**
     * Seed the horse genders table.
     */
    public function run(): void
    {
        $genders = [
            ['en_name' => 'Stallion', 'ar_name' => 'حصان (فحل)'],
            ['en_name' => 'Mare', 'ar_name' => 'فرس'],
            ['en_name' => 'Gelding', 'ar_name' => 'حصان مخصي'],
            ['en_name' => 'Colt', 'ar_name' => 'مهر'],
            ['en_name' => 'Filly', 'ar_name' => 'مهرة'],
        ];

        foreach ($genders as $gender) {
            Gender::query()->updateOrCreate(
                ['en_name' => $gender['en_name']],
                $gender
            );
        }
    }
}
