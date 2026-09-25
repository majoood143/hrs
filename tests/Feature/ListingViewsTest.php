<?php

namespace Tests\Feature;

use App\Models\CmsPost;
use App\Models\Event;
use App\Models\Farrier;
use App\Models\Video;
use App\Models\VideoFolder;
use App\Support\ViewCounter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\PreparesSiteLayout;
use Tests\TestCase;

class ListingViewsTest extends TestCase
{
    use PreparesSiteLayout;

    private const BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Safari/605.1.15';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSiteLayout();

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('en_name')->nullable();
            $table->string('ar_name')->nullable();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('en_name')->nullable();
            $table->string('ar_name')->nullable();
            $table->timestamps();
        });

        Schema::create('farriers', function (Blueprint $table) {
            $table->id();
            $table->string('en_name');
            $table->string('ar_name')->nullable();
            $table->string('specialty')->nullable();
            $table->unsignedInteger('years_experience')->nullable();
            $table->foreignId('country_id')->nullable();
            $table->foreignId('region_id')->nullable();
            $table->foreignId('city_id')->nullable();
            $table->decimal('price', 10, 3)->nullable();
            $table->string('cover_photo')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('description')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('category_id')->nullable();
            $table->string('link')->nullable();
            $table->timestamps();
        });

        Schema::create('video_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->json('name');
            $table->string('slug');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id');
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('slug');
            $table->string('youtube_url')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        (require database_path('migrations/2026_09_30_000001_add_views_count_to_listings.php'))->up();
        (require database_path('migrations/2026_09_30_000002_add_views_count_to_videos.php'))->up();
    }

    public function test_a_listing_page_shows_its_post_date_and_counts_one_view_per_session(): void
    {
        $farrier = $this->farrier(['created_at' => '2026-03-05 10:00:00', 'updated_at' => '2026-03-05 10:00:00']);

        $this->withHeader('User-Agent', self::BROWSER)
            ->get(route('farriers.show', $farrier))
            ->assertOk()
            ->assertSee('5 March 2026')
            ->assertSee('1 view');

        $this->withHeader('User-Agent', self::BROWSER)
            ->get(route('farriers.show', $farrier))
            ->assertOk()
            ->assertSee('1 view');

        $farrier->refresh();
        $this->assertSame(1, (int) $farrier->views_count);
        $this->assertSame('2026-03-05 10:00:00', $farrier->updated_at->format('Y-m-d H:i:s'), 'a view is not an edit');
    }

    public function test_the_arabic_page_uses_arabic_plurals(): void
    {
        $farrier = $this->farrier(['views_count' => 1]);

        $this->withHeader('User-Agent', self::BROWSER)
            ->get(route('farriers.show', $farrier).'?lang=ar')
            ->assertOk()
            ->assertSee('مشاهدتان')
            ->assertSee('نُشر في');
    }

    public function test_bots_are_not_counted(): void
    {
        $farrier = $this->farrier();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
            ->get(route('farriers.show', $farrier))
            ->assertOk()
            ->assertSee('No views yet');

        $this->assertSame(0, (int) $farrier->refresh()->views_count);
        $this->assertTrue(ViewCounter::isBot(null));
    }

    public function test_the_calendar_records_an_event_view_once_per_session(): void
    {
        $event = Event::create([
            'title' => ['en' => 'Spring cup', 'ar' => 'كأس الربيع'],
            'date' => '2026-10-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $this->withHeader('User-Agent', self::BROWSER)
            ->postJson(route('events.view', $event))
            ->assertOk()
            ->assertJson(['views' => 1, 'label' => '1 view']);

        $this->withHeader('User-Agent', self::BROWSER)
            ->postJson(route('events.view', $event))
            ->assertJson(['views' => 1]);

        $this->assertSame(1, (int) DB::table('events')->value('views_count'));
    }

    public function test_a_video_page_shows_and_counts_its_views(): void
    {
        $folder = VideoFolder::create(['name' => ['en' => 'Training', 'ar' => 'تدريب'], 'slug' => 'training', 'is_active' => true]);
        $video = Video::create([
            'folder_id' => $folder->id,
            'title' => ['en' => 'Lunging basics', 'ar' => 'أساسيات الترويض'],
            'slug' => 'lunging-basics',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'is_active' => true,
        ]);

        $this->withHeader('User-Agent', self::BROWSER)
            ->get(route('video-library.show', $video->slug))
            ->assertOk()
            ->assertSee('1 view');

        $this->assertSame(1, (int) $video->refresh()->views_count);
    }

    public function test_a_blog_post_shows_and_counts_its_views(): void
    {
        Schema::create('cms_posts', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('slug');
            $table->json('excerpt')->nullable();
            $table->json('content')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('show_title')->default(true);
            $table->text('custom_css')->nullable();
            $table->text('custom_head_scripts')->nullable();
            $table->text('custom_body_scripts')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->timestamps();
        });
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->string('collection_name');
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();
        });
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->string('type')->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();
        });
        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id');
            $table->morphs('taggable');
        });
        (require database_path('migrations/2026_09_30_000004_add_views_count_to_cms_posts.php'))->up();

        $post = CmsPost::create([
            'title' => ['en' => 'Summer care', 'ar' => 'العناية صيفًا'],
            'slug' => 'summer-care',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        foreach (range(1, 2) as $visit) { // a second visit in the same session is not counted
            $this->withHeader('User-Agent', self::BROWSER)
                ->get(url('/blog/'.$post->slug))
                ->assertOk()
                ->assertSee('1 view');
        }

        $this->assertSame(1, (int) $post->refresh()->views_count);
    }

    private function farrier(array $attributes = []): Farrier
    {
        $countryId = DB::table('countries')->insertGetId(['en_name' => 'Oman', 'ar_name' => 'عُمان']);
        $cityId = DB::table('cities')->insertGetId(['en_name' => 'Muscat', 'ar_name' => 'مسقط']);

        $farrier = new Farrier;
        $farrier->forceFill(array_merge([
            'en_name' => 'Salim the farrier',
            'ar_name' => 'سالم',
            'country_id' => $countryId,
            'city_id' => $cityId,
            'status' => 'active',
        ], $attributes))->save();

        return $farrier;
    }
}
