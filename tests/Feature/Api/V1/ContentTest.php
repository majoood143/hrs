<?php

namespace Tests\Feature\Api\V1;

use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

/**
 * Events, the blog and the video library.
 */
class ContentTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
    }

    public function test_events_list_upcoming_ones_in_date_order_by_default(): void
    {
        $racing = $this->row('event_categories', ['en_name' => 'Racing', 'ar_name' => 'سباقات', 'color' => '#c00000', 'order' => 1]);
        $past = $this->event(['date' => now()->subDay()->toDateString()]);
        $later = $this->event(['date' => now()->addDays(3)->toDateString(), 'category_id' => $racing, 'link' => '/racing']);
        $today = $this->event(['date' => now()->toDateString(), 'start_time' => '18:30:00', 'link' => 'javascript:alert(1)']);

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$today, $later])
            ->assertJsonPath('data.0.start_time', '18:30')
            ->assertJsonPath('data.0.link', null)
            ->assertJsonPath('data.1.link', url('/racing'))
            ->assertJsonPath('data.1.is_external_link', false)
            ->assertJsonPath('data.1.category', ['id' => $racing, 'name' => 'Racing', 'color' => '#c00000']);

        $this->assertSame([$past, $today], $this->getJson('/api/v1/events?from='.now()->subWeek()->toDateString().'&to='.now()->toDateString())->json('data.*.id'));
        $this->assertSame([$later], $this->getJson("/api/v1/events?category_id={$racing}")->json('data.*.id'));
        $this->getJson('/api/v1/events?from=tomorrow')->assertUnprocessable();

        $this->getJson('/api/v1/events/categories', ['Accept-Language' => 'ar'])->assertJsonPath('data.0.name', 'سباقات');
        $this->getJson('/api/v1/events', ['Accept-Language' => 'ar'])->assertJsonPath('data.0.title', 'فعالية');
    }

    public function test_the_blog_lists_published_posts_and_filters_by_category(): void
    {
        $news = $this->row('cms_categories', ['name' => $this->both('News', 'أخبار'), 'slug' => 'news']);
        $this->row('cms_categories', ['name' => $this->both('Empty', 'فارغة'), 'slug' => 'empty']);
        $older = $this->blogPost(['slug' => 'older', 'published_at' => now()->subDays(2), 'category_id' => $news]);
        $newer = $this->blogPost(['slug' => 'newer', 'published_at' => now()->subDay()]);
        $this->blogPost(['slug' => 'draft', 'status' => 'draft']);
        $this->blogPost(['slug' => 'scheduled', 'published_at' => now()->addDay()]);

        $this->getJson('/api/v1/blog/posts')
            ->assertOk()
            ->assertJsonPath('data.*.slug', ['newer', 'older'])
            ->assertJsonPath('data.1.category', ['id' => $news, 'slug' => 'news', 'name' => 'News'])
            ->assertJsonPath('data.0.image_url', null);

        $this->assertSame([$older], $this->getJson('/api/v1/blog/posts?category=news')->json('data.*.id'));
        $this->getJson('/api/v1/blog/posts?category=nope')->assertNotFound();
        $this->getJson('/api/v1/blog/posts/draft')->assertNotFound();
        $this->assertNotNull($newer);

        $this->getJson('/api/v1/blog/categories', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data', [['id' => $news, 'slug' => 'news', 'name' => 'أخبار', 'posts_count' => 1]]);
    }

    public function test_a_post_comes_as_native_blocks_in_the_requested_language(): void
    {
        $this->blogPost([
            'slug' => 'guide',
            'content' => json_encode([
                ['type' => 'heading', 'data' => ['level' => 'h2', 'text' => ['en' => 'Feeding', 'ar' => 'التغذية']]],
                ['type' => 'rich_text', 'data' => ['content' => [
                    'en' => '<p>Hay <strong>first</strong>.</p><ul><li>Water</li><li>Salt</li></ul><h3>Treats</h3><p>Apples &amp; carrots.<br>In moderation.</p><script>alert(1)</script>',
                    'ar' => '<p>التبن أولًا.</p>',
                ]]],
                ['type' => 'image', 'data' => ['image' => 'cms/hay.jpg', 'caption' => ['en' => 'Good hay', 'ar' => 'تبن جيد']]],
                ['type' => 'video', 'data' => ['url' => 'https://youtu.be/dQw4w9WgXcQ']],
                ['type' => 'form', 'data' => []],
                ['type' => 'race_widget', 'data' => []],
                ['type' => 'divider', 'data' => []],
                ['type' => 'cta', 'data' => ['heading' => ['en' => 'Find a stable'], 'button_text' => ['en' => 'Browse'], 'button_url' => '/stables']],
                ['type' => 'cta', 'data' => ['heading' => ['en' => 'Bad'], 'button_url' => 'javascript:alert(1)']],
            ]),
        ]);

        $blocks = $this->getJson('/api/v1/blog/posts/guide')->assertOk()
            ->assertJsonPath('data.website_url', route('blog.show', 'guide').'?lang=en')
            ->assertJsonPath('data.share_url', route('blog.show', 'guide'))
            ->json('data.blocks');

        $this->assertSame([
            ['type' => 'heading', 'text' => 'Feeding'],
            ['type' => 'paragraph', 'text' => 'Hay first.'],
            ['type' => 'list_item', 'text' => 'Water'],
            ['type' => 'list_item', 'text' => 'Salt'],
            ['type' => 'heading', 'text' => 'Treats'],
            ['type' => 'paragraph', 'text' => "Apples & carrots.\nIn moderation."],
            ['type' => 'image', 'url' => url('/storage/cms/hay.jpg'), 'alt' => null, 'caption' => 'Good hay'],
            ['type' => 'video', 'youtube_id' => 'dQw4w9WgXcQ', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg', 'caption' => null],
            ['type' => 'unsupported'],
            ['type' => 'divider'],
            ['type' => 'cta', 'heading' => 'Find a stable', 'text' => null, 'button_label' => 'Browse', 'button_url' => url('/stables')],
            ['type' => 'cta', 'heading' => 'Bad', 'text' => null, 'button_label' => null, 'button_url' => null],
        ], $blocks);

        $this->getJson('/api/v1/blog/posts/guide', ['Accept-Language' => 'ar'])
            ->assertJsonPath('data.title', 'مقال')
            ->assertJsonPath('data.blocks.0.text', 'التغذية')
            ->assertJsonPath('data.blocks.1', ['type' => 'paragraph', 'text' => 'التبن أولًا.']);
    }

    public function test_the_video_library_shows_active_folders_and_videos(): void
    {
        $root = $this->row('video_folders', ['name' => $this->both('Training', 'تدريب'), 'slug' => 'training', 'order' => 1]);
        $child = $this->row('video_folders', ['name' => $this->both('Jumping', 'قفز'), 'slug' => 'jumping', 'parent_id' => $root]);
        $this->row('video_folders', ['name' => $this->both('Hidden', 'مخفي'), 'slug' => 'hidden', 'is_active' => false]);
        $this->row('videos', ['folder_id' => $root, 'title' => $this->both('Lunging', 'الترويض'), 'slug' => 'lunging', 'youtube_url' => 'https://www.youtube.com/watch?v=abcdefghijk', 'description' => $this->both('<p>Step <em>one</em>.</p>', '<p>الخطوة الأولى.</p>')]);
        $this->row('videos', ['folder_id' => $child, 'title' => $this->both('Grid work', 'تمارين'), 'slug' => 'grid']);
        $this->row('videos', ['folder_id' => $root, 'title' => $this->both('Off', 'متوقف'), 'slug' => 'off', 'is_active' => false]);

        $this->getJson('/api/v1/videos/folders')
            ->assertOk()
            ->assertJsonPath('data', [[
                'id' => $root, 'slug' => 'training', 'name' => 'Training',
                'image_url' => 'https://img.youtube.com/vi/abcdefghijk/hqdefault.jpg', 'videos_count' => 2,
            ]]);

        $this->getJson('/api/v1/videos/folders/jumping', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.folder.name', 'قفز')
            ->assertJsonPath('data.parent.slug', 'training')
            ->assertJsonPath('data.children', [])
            ->assertJsonPath('data.videos.0.title', 'تمارين');

        $this->getJson('/api/v1/videos/folders/training')
            ->assertJsonPath('data.parent', null)
            ->assertJsonPath('data.children.0.slug', 'jumping')
            ->assertJsonPath('data.videos.*.slug', ['lunging']);

        $this->getJson('/api/v1/videos/lunging')
            ->assertOk()
            ->assertJsonPath('data.youtube_url', 'https://www.youtube.com/watch?v=abcdefghijk')
            ->assertJsonPath('data.share_url', route('video-library.show', 'lunging'))
            ->assertJsonPath('data.summary', 'Step one.')
            ->assertJsonPath('data.description', [['type' => 'paragraph', 'text' => 'Step one.']])
            ->assertJsonPath('data.folder', ['slug' => 'training', 'name' => 'Training']);

        $this->getJson('/api/v1/videos/off')->assertNotFound();
        $this->getJson('/api/v1/videos/folders/hidden')->assertNotFound();
    }

    public function test_a_folders_videos_follow_the_admins_order_then_newest_first(): void
    {
        $meeting = $this->row('video_folders', ['name' => $this->both('10th Meeting', 'السباق العاشر'), 'slug' => '10th-meeting']);
        // a race added later still sits in its place
        $this->row('videos', ['folder_id' => $meeting, 'title' => $this->both('race 2', 'الشوط الثاني'), 'slug' => 'race-2', 'order' => 1, 'created_at' => now()]);
        $this->row('videos', ['folder_id' => $meeting, 'title' => $this->both('race 1', 'الشوط الأول'), 'slug' => 'race-1', 'order' => 0, 'created_at' => now()->subWeek()]);
        $this->row('videos', ['folder_id' => $meeting, 'title' => $this->both('race 3', 'الشوط الثالث'), 'slug' => 'race-3', 'order' => 2, 'created_at' => now()->subWeek()]);
        $this->row('videos', ['folder_id' => $meeting, 'title' => $this->both('Older talk', 'قديم'), 'slug' => 'older', 'order' => 5, 'created_at' => now()->subYear()]);
        $this->row('videos', ['folder_id' => $meeting, 'title' => $this->both('Newer talk', 'جديد'), 'slug' => 'newer', 'order' => 5, 'created_at' => now()->subMonth()]);

        $this->getJson('/api/v1/videos/folders/10th-meeting')
            ->assertJsonPath('data.videos.*.slug', ['race-1', 'race-2', 'race-3', 'newer', 'older']);
    }

    private function event(array $values): int
    {
        return $this->row('events', $values + [
            'title' => $this->both('Event', 'فعالية'), 'start_time' => '09:00:00', 'end_time' => '12:00:00',
        ]);
    }

    private function blogPost(array $values): int
    {
        return $this->row('cms_posts', $values + [
            'title' => $this->both('Post', 'مقال'), 'excerpt' => $this->both('Short', 'قصير'),
            'status' => 'published', 'published_at' => now()->subHour(),
        ]);
    }
}
