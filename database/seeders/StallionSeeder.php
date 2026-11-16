<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Stallion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The 49 stallions of the 2025 catalogue "Arabian stallions in Oman available for breeding" (horse owners
 * and breeders association), with their photos where the catalogue has a real one. Transcribed by hand
 * into `data/stallions-2025.json`: names, sire and dam in both languages, the 3-generation pedigree in
 * Latin, the race record, the wilayat and phone. The catalogue's family notes are not copied.
 *
 * Safe to run again (local and production): a stallion already there (by English name) is left as the
 * admin has it, and a photo is only added where there is none.
 *
 * php artisan db:seed --class=StallionSeeder --force
 */
class StallionSeeder extends Seeder
{
    /** The data to seed (a test points it at its own). */
    public string $dataFile = __DIR__.'/data/stallions-2025.json';

    public function run(): void
    {
        $rows = json_decode(file_get_contents($this->dataFile), true, flags: JSON_THROW_ON_ERROR);
        $photos = dirname($this->dataFile).'/stallions';
        $created = 0;

        foreach ($rows as $row) {
            $city = $row['city'] ? City::query()->where('en_name', $row['city'])->value('id') : null;
            $photo = $row['photo'];
            unset($row['city'], $row['photo']);

            $stallion = Stallion::query()->firstOrCreate(['en_name' => $row['en_name']], $row + ['city_id' => $city]);
            $created += (int) $stallion->wasRecentlyCreated;

            if ($photo && ! $stallion->cover_photo && is_file("{$photos}/{$photo}")) {
                Storage::disk('public')->put("stallions/{$photo}", file_get_contents("{$photos}/{$photo}"));
                $stallion->update(['cover_photo' => "stallions/{$photo}"]);
            }
        }

        $this->command?->info("Stallions: {$created} added, ".(count($rows) - $created).' already there.');
    }
}
