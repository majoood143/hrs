<?php

namespace Database\Seeders;

use App\Models\Video;
use App\Models\VideoFolder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The Royal Horse Racing Club's race replays in the video library: Flat Races → "2019/2020 Season" →
 * "10th Meeting" → "race 1 - 10th Meeting", … (the structure the first seasons were entered by hand in).
 *
 * The data file (`data/rhrc-race-videos.json`) was built from the club's results site (iframe.rhrc.om:
 * every race day and the replay it links to) and, where that has no link, the club's YouTube channel
 * (titles carry the race, the meeting and the date). Safe to run again: folders are found by slug,
 * and a video the data places elsewhere is moved there (or dropped when that folder already has it).
 * Videos the data does not know (found by hand) are left alone, and never replaced.
 *
 * php artisan db:seed --class=RhrcRaceVideosSeeder --force
 */
class RhrcRaceVideosSeeder extends Seeder
{
    private const RACE_ORDINALS_AR = [
        1 => 'الأول', 2 => 'الثاني', 3 => 'الثالث', 4 => 'الرابع', 5 => 'الخامس', 6 => 'السادس',
        7 => 'السابع', 8 => 'الثامن', 9 => 'التاسع', 10 => 'العاشر', 11 => 'الحادي عشر', 12 => 'الثاني عشر',
    ];

    /** The data to seed (a test points it at its own). */
    public string $dataFile = __DIR__.'/data/rhrc-race-videos.json';

    /** @var array<string, int> what happened, for the summary line */
    private array $counts = ['folders created' => 0, 'videos created' => 0, 'videos moved' => 0, 'videos fixed' => 0, 'duplicates removed' => 0, 'empty folders hidden' => 0];

    public function run(): void
    {
        $data = json_decode(file_get_contents($this->dataFile), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($data) {
            $root = VideoFolder::firstOrCreate(
                ['slug' => $data['root']],
                ['name' => ['en' => 'Flat Races', 'ar' => 'سباقات الخيل'], 'is_active' => true],
            );

            foreach ($data['renamed_folders'] as $old => $new) {
                if (! VideoFolder::where('slug', $new)->exists()) {
                    VideoFolder::where('slug', $old)->update(['slug' => $new]);
                }
            }

            $seasons = [];
            $meetings = [];

            foreach ($data['seasons'] as $season) {
                $seasonFolder = $this->folder($season['slug'], $root, $season['name']);
                $seasons[] = $seasonFolder;

                // newest race day first, like the seasons under Flat Races
                $count = count($season['meetings']);

                foreach ($season['meetings'] as $i => $meeting) {
                    $folder = $this->folder($meeting['slug'], $seasonFolder, $meeting['name']);
                    $folder->fill(['name' => $meeting['name'], 'date' => $meeting['date'], 'order' => $count - $i])->save();
                    $meetings[] = [$folder, $meeting];
                }
            }

            $this->placeKnownVideos($meetings);

            foreach ($meetings as [$folder, $meeting]) {
                $this->addMissingVideos($folder, $meeting);
            }

            $this->hideEmptyFolders($seasons);
        });

        $this->command?->info(collect($this->counts)->map(fn (int $n, string $what) => "{$n} {$what}")->implode(', '));
    }

    /** @param  array{en: string, ar: string}  $name */
    private function folder(string $slug, VideoFolder $parent, array $name): VideoFolder
    {
        $folder = VideoFolder::where('slug', $slug)->first();

        if ($folder === null) {
            $this->counts['folders created']++;

            return VideoFolder::create(['parent_id' => $parent->id, 'slug' => $slug, 'name' => $name, 'is_active' => true, 'order' => 0]);
        }

        if ($folder->parent_id !== $parent->id) {
            $folder->update(['parent_id' => $parent->id]);
        }

        return $folder;
    }

    /**
     * Every video already in these meetings whose YouTube id the data places: one copy is kept, in its
     * meeting, named and ordered as its race; other copies go.
     *
     * @param  list<array{VideoFolder, array<string, mixed>}>  $meetings
     */
    private function placeKnownVideos(array $meetings): void
    {
        /** @var array<string, array{VideoFolder, int, array<string, mixed>}> $places YouTube id => [meeting, race number (0 = the day's stream), meeting data] */
        $places = [];

        foreach ($meetings as [$folder, $meeting]) {
            foreach ($meeting['races'] as $race) {
                $places[$race['youtube_id']] = [$folder, $race['number'], $meeting];
            }

            if ($meeting['day_stream']) {
                $places[$meeting['day_stream']] = [$folder, 0, $meeting];
            }
        }

        $folderIds = array_map(fn (array $m) => $m[0]->id, $meetings);
        $rows = Video::whereIn('folder_id', $folderIds)->orderBy('id')->get()
            ->groupBy(fn (Video $video) => $video->youtube_id)
            ->filter(fn ($copies, string $youtubeId) => isset($places[$youtubeId]));

        /** @var list<array{Video, string, string}> $renames video, its race's slug, its current slug */
        $renames = [];

        foreach ($rows as $youtubeId => $copies) {
            [$folder, $number, $meeting] = $places[$youtubeId];
            $slug = $this->slug($folder, $number);

            // keep the copy already in place: right slug, then right meeting, then the oldest
            $keep = $copies->sortBy(fn (Video $v) => [$v->slug === $slug ? 0 : 1, $v->folder_id === $folder->id ? 0 : 1, $v->id])->first();

            foreach ($copies as $copy) {
                if ($copy->isNot($keep)) {
                    $copy->delete();
                    $this->counts['duplicates removed']++;
                }
            }

            if ($keep->folder_id !== $folder->id) {
                $this->counts['videos moved']++;
            } elseif ($keep->slug !== $slug || $keep->order !== $this->order($number, $meeting)) {
                $this->counts['videos fixed']++;
            }

            $keep->fill(['folder_id' => $folder->id, 'order' => $this->order($number, $meeting), 'title' => $this->title($folder, $number)]);

            // races labelled one off (a meeting entered without its race 1) hold each other's slugs:
            // park them all first, then hand out the right ones
            if ($keep->slug !== $slug) {
                $renames[] = [$keep, $slug, $keep->slug];
                $keep->slug = "renaming-{$keep->id}";
            }

            $keep->save();
        }

        foreach ($renames as [$video, $slug, $current]) {
            // a hand-added video may already hold this race's slug: then this one keeps its own
            $video->update(['slug' => Video::where('slug', $slug)->exists() ? $current : $slug]);
        }
    }

    /** @param  array<string, mixed>  $meeting */
    private function addMissingVideos(VideoFolder $folder, array $meeting): void
    {
        $present = $folder->videos()->get()->map(fn (Video $v) => $v->youtube_id)->filter()->all();
        $now = now();

        $wanted = array_map(fn (array $race) => [$race['number'], $race['youtube_id']], $meeting['races']);

        if ($meeting['day_stream']) {
            $wanted[] = [0, $meeting['day_stream']];
        }

        foreach ($wanted as [$number, $youtubeId]) {
            $slug = $this->slug($folder, $number);

            // already here, or this race was added by hand with another video
            if (in_array($youtubeId, $present, true) || Video::where('slug', $slug)->exists()) {
                continue;
            }

            $video = new Video([
                'folder_id' => $folder->id,
                'title' => $this->title($folder, $number),
                'slug' => $slug,
                'youtube_url' => 'https://www.youtube.com/watch?v='.$youtubeId,
                'order' => $this->order($number, $meeting),
                'is_active' => true,
            ]);
            $video->created_at = $video->updated_at = $now;
            $video->save();

            $this->counts['videos created']++;
        }
    }

    /**
     * Meetings with nothing to watch (abandoned days, numbers a season never reached) are hidden, and
     * a season is shown once it has any.
     *
     * @param  list<VideoFolder>  $seasons
     */
    private function hideEmptyFolders(array $seasons): void
    {
        foreach ($seasons as $season) {
            foreach ($season->children()->get() as $meeting) {
                if ($meeting->is_active && $meeting->activeVideosCount() === 0) {
                    $meeting->update(['is_active' => false]);
                    $this->counts['empty folders hidden']++;
                }
            }

            $season->update(['is_active' => $season->activeVideosCount() > 0]);
        }
    }

    private function slug(VideoFolder $folder, int $number): string
    {
        return ($number === 0 ? 'full-race-day' : "race-{$number}").'-'.$folder->slug;
    }

    /** @return array{en: string, ar: string} */
    private function title(VideoFolder $folder, int $number): array
    {
        $en = $folder->getTranslation('name', 'en');
        $ar = $folder->getTranslation('name', 'ar');

        return $number === 0
            ? ['en' => "Full race day - {$en}", 'ar' => "البث الكامل - {$ar}"]
            : ['en' => "race {$number} - {$en}", 'ar' => 'الشوط '.self::RACE_ORDINALS_AR[$number]." - {$ar}"];
    }

    /** Race 1 = 0, …; the day's full stream after the races. @param  array<string, mixed>  $meeting */
    private function order(int $number, array $meeting): int
    {
        return $number === 0 ? count($meeting['races']) : $number - 1;
    }
}
