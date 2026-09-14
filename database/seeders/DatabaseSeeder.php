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

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(HorseWebsiteSeeder::class);
        $this->call(CountrySeeder::class);
        $this->call(ColorSeeder::class);
        $this->call(TypeSeeder::class);
        $this->call(GenderSeeder::class);
        $this->call(RegionSeeder::class);
        $this->call(CitySeeder::class);
        $this->call(StatusSeeder::class);
        $this->call(StableServiceSeeder::class);
        $this->call(ClinicServiceSeeder::class);
        $this->call(CenterServiceSeeder::class);
        $this->call(ShopServiceSeeder::class);
        $this->call(CmsCategorySeeder::class);
        $this->call(MediaLibrarySeeder::class);
        $this->call(EventCategorySeeder::class);
    }
}
