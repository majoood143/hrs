<?php

namespace Tests\Feature;

use App\Models\Video;
use App\Models\VideoFolder;
use Database\Seeders\RhrcRaceVideosSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The race replays seeded into the video library: Flat Races → season → meeting → "race N - …". */
class RhrcRaceVideosSeederTest extends TestCase
{
    private int $root;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('video_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->json('name');
            $table->string('slug')->unique();
            $table->date('date')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id');
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('slug')->unique();
            $table->string('youtube_url');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
        });

        $this->root = $this->folder('flat-races', null, 'Flat Races', 'سباقات الخيل');
    }

    public function test_it_fills_seasons_and_meetings_in_the_hand_made_structure(): void
    {
        $season = $this->folder('season-20192020', $this->root, '2019/2020 Season', 'موسم سباقات 2019/2020', false);
        $tenth = $this->folder('10th-meeting-20192020', $season, '10th Meeting', 'السباق العاشر');
        $this->folder('20th-meeting-20192020', $season, '20th Meeting', 'السباق العشرون');
        // entered by hand without the source's race 1: each video labelled one race low
        $this->video($tenth, 'race-1-10th-meeting-20192020', 'BBBBBBBBBBB', 0);
        $this->video($tenth, 'race-2-10th-meeting-20192020', 'CCCCCCCCCCC', 1);
        // a race the source has no video for, found by hand
        $this->video($tenth, 'race-4-10th-meeting-20192020', 'HANDMADE000', 3);

        $this->seedRaces([[
            'slug' => 'season-20192020', 'name' => ['en' => '2019/2020 Season', 'ar' => 'موسم سباقات 2019/2020'],
            'meetings' => [
                $this->meeting('10th-meeting-20192020', '10th Meeting', 'السباق العاشر', '2020-01-09', ['AAAAAAAAAAA', 'BBBBBBBBBBB', 'CCCCCCCCCCC', 'DDDDDDDDDDD']),
                $this->meeting('royal-meeting-20192020', 'Royal Meeting', 'السباق السلطاني', '2020-01-20', ['EEEEEEEEEEE'], 'STREAM00000'),
            ],
        ]]);

        $this->assertSame(
            ['race-1' => 'AAAAAAAAAAA', 'race-2' => 'BBBBBBBBBBB', 'race-3' => 'CCCCCCCCCCC', 'race-4' => 'HANDMADE000'],
            $this->races('10th-meeting-20192020'),
        );
        $this->assertSame([0, 1, 2, 3], Video::where('folder_id', $tenth)->orderBy('order')->pluck('order')->all());
        $this->assertSame(
            ['en' => 'race 2 - 10th Meeting', 'ar' => 'الشوط الثاني - السباق العاشر'],
            Video::where('slug', 'race-2-10th-meeting-20192020')->first()->getTranslations('title'),
        );

        $royal = VideoFolder::where('slug', 'royal-meeting-20192020')->first();
        $this->assertSame($season, $royal->parent_id);
        $this->assertSame('2020-01-20', $royal->date->toDateString());
        $this->assertSame(['race-1' => 'EEEEEEEEEEE', 'full-race-day' => 'STREAM00000'], $this->races('royal-meeting-20192020'));
        $this->assertSame('البث الكامل - السباق السلطاني', Video::where('slug', 'full-race-day-royal-meeting-20192020')->first()->getTranslation('title', 'ar'));

        // the newest race day first; an empty meeting is hidden, the season shown
        $this->assertSame(['royal-meeting-20192020', '10th-meeting-20192020'], VideoFolder::where('parent_id', $season)->active()->ordered()->pluck('slug')->all());
        $this->assertTrue(VideoFolder::find($season)->is_active);
    }

    public function test_misplaced_and_duplicated_videos_are_put_right(): void
    {
        $older = $this->folder('season-20202021', $this->root, '2020/2021 Season', 'موسم سباقات 2020/2021');
        $newer = $this->folder('season-20212022', $this->root, '2021/2022 Season', 'موسم سباقات 2021/2022');
        $wrong = $this->folder('6th-meeting-20202021', $older, '6th Meeting', 'السباق السادس');
        $right = $this->folder('6th-meeting-20212022', $newer, '6th Meeting', 'السباق السادس');
        // the next season's videos entered in this one, plus a copy of one of them twice
        $this->video($wrong, 'race-1-6th-meeting-20202021', 'NEXTSEASON1', 0);
        $this->video($wrong, 'race-2-6th-meeting-20202021', 'NEXTSEASON1', 1);
        // a folder created with other naming
        $odd = $this->folder('race-1-20262027', $this->folder('season-20262027', $this->root, '2026/2027 Season', 'موسم سباقات 2026/2027'), 'Race 1 2026/2027', 'السباق الأول 2026/2027');
        $this->video($odd, 'race-1-1st-meeting-rahba-17102026', 'NEWSEASON01', 4);

        $this->seedRaces([
            ['slug' => 'season-20202021', 'name' => ['en' => '2020/2021 Season', 'ar' => 'موسم سباقات 2020/2021'], 'meetings' => [
                $this->meeting('6th-meeting-20202021', '6th Meeting', 'السباق السادس', '2020-12-03', ['THISSEASON1']),
            ]],
            ['slug' => 'season-20212022', 'name' => ['en' => '2021/2022 Season', 'ar' => 'موسم سباقات 2021/2022'], 'meetings' => [
                $this->meeting('6th-meeting-20212022', '6th Meeting', 'السباق السادس', '2021-12-02', ['NEXTSEASON1']),
            ]],
            ['slug' => 'season-20262027', 'name' => ['en' => '2026/2027 Season', 'ar' => 'موسم سباقات 2026/2027'], 'meetings' => [
                $this->meeting('1st-meeting-20262027', '1st Meeting', 'السباق الأول', '2026-10-17', ['NEWSEASON01']),
            ]],
        ], ['race-1-20262027' => '1st-meeting-20262027']);

        $this->assertSame(['race-1' => 'THISSEASON1'], $this->races('6th-meeting-20202021'));
        $this->assertSame(['race-1' => 'NEXTSEASON1'], $this->races('6th-meeting-20212022'));
        $this->assertSame(1, Video::where('folder_id', $right)->count());

        $this->assertSame($odd, VideoFolder::where('slug', '1st-meeting-20262027')->value('id'));
        $this->assertSame('1st Meeting', VideoFolder::find($odd)->getTranslation('name', 'en'));
        $this->assertSame(['race-1' => 'NEWSEASON01'], $this->races('1st-meeting-20262027'));
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $data = [[
            'slug' => 'season-20232024', 'name' => ['en' => '2023/2024 Season', 'ar' => 'موسم سباقات 2023/2024'],
            'meetings' => [$this->meeting('1st-meeting-20232024', '1st Meeting', 'السباق الأول', '2023-10-13', ['AAAAAAAAAAA', 'BBBBBBBBBBB'], 'STREAM00000')],
        ]];

        $this->seedRaces($data);
        $before = [VideoFolder::orderBy('id')->get()->toArray(), Video::orderBy('id')->get()->toArray()];
        $this->seedRaces($data);

        $this->assertEquals($before, [VideoFolder::orderBy('id')->get()->toArray(), Video::orderBy('id')->get()->toArray()]);
    }

    public function test_the_shipped_data_seeds_cleanly(): void
    {
        $data = json_decode(file_get_contents(database_path('seeders/data/rhrc-race-videos.json')), true);
        $meetings = collect($data['seasons'])->flatMap(fn (array $season) => $season['meetings']);
        $races = $meetings->flatMap(fn (array $meeting) => $meeting['races']);

        $this->assertSame($meetings->count(), $meetings->pluck('slug')->unique()->count());
        $this->assertSame($races->count(), $races->pluck('youtube_id')->unique()->count());
        $this->assertTrue($races->every(fn (array $race) => preg_match('/^[\w-]{11}$/', $race['youtube_id']) === 1));

        (new RhrcRaceVideosSeeder)->run();

        $this->assertSame($races->count() + $meetings->whereNotNull('day_stream')->count(), Video::count());
    }

    private function folder(string $slug, ?int $parent, string $en, string $ar, bool $active = true): int
    {
        return DB::table('video_folders')->insertGetId([
            'parent_id' => $parent, 'slug' => $slug, 'name' => json_encode(['en' => $en, 'ar' => $ar]), 'is_active' => $active,
        ]);
    }

    private function video(int $folder, string $slug, string $youtubeId, int $order): void
    {
        DB::table('videos')->insert([
            'folder_id' => $folder, 'slug' => $slug, 'title' => json_encode(['en' => $slug]),
            'youtube_url' => "youtube.com/watch?v={$youtubeId}", 'order' => $order, 'created_at' => now()->subMonth(),
        ]);
    }

    /** @param  list<string>  $videos  one per race, race 1 first */
    private function meeting(string $slug, string $en, string $ar, string $date, array $videos, ?string $stream = null): array
    {
        return [
            'slug' => $slug, 'name' => ['en' => $en, 'ar' => $ar], 'date' => $date, 'day_stream' => $stream,
            'races' => array_map(fn (int $i) => ['number' => $i + 1, 'youtube_id' => $videos[$i]], array_keys($videos)),
        ];
    }

    private function seedRaces(array $seasons, array $renamed = []): void
    {
        $file = tempnam(sys_get_temp_dir(), 'rhrc');
        file_put_contents($file, json_encode(['root' => 'flat-races', 'seasons' => $seasons, 'renamed_folders' => (object) $renamed]));

        $seeder = new RhrcRaceVideosSeeder;
        $seeder->dataFile = $file;
        $seeder->run();

        unlink($file);
    }

    /** @return array<string, string> "race-N" / "full-race-day" => YouTube id */
    private function races(string $folder): array
    {
        return VideoFolder::where('slug', $folder)->first()->videos()->orderBy('order')->get()
            ->mapWithKeys(fn (Video $video) => [str_replace('-'.$folder, '', $video->slug) => $video->youtube_id])
            ->all();
    }
}
