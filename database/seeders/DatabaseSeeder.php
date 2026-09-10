<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

<<<<<<< HEAD
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call(AdminSeeder::class);
=======
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
<<<<<<< HEAD
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
=======

        $this->call(HorseWebsiteSeeder::class);
        $this->call(ColorSeeder::class);
        $this->call(GenderSeeder::class);
        $this->call(RegionSeeder::class);
        $this->call(CitySeeder::class);
        $this->call(StatusSeeder::class);
        $this->call(StableServiceSeeder::class);
        $this->call(ClinicServiceSeeder::class);
        $this->call(CmsCategorySeeder::class);
>>>>>>> bbd33618 (Add transfer board creation and listing views)
    }
}
