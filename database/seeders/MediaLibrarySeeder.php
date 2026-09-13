<?php

namespace Database\Seeders;

use App\Models\MediaLibrary;
use Illuminate\Database\Seeder;

class MediaLibrarySeeder extends Seeder
{
    /**
     * Seed the default general-purpose media library.
     */
    public function run(): void
    {
        MediaLibrary::query()->firstOrCreate(
            ['en_name' => 'General Media'],
            ['ar_name' => 'الوسائط العامة']
        );
    }
}
