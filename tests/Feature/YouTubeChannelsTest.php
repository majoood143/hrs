<?php

namespace Tests\Feature;

use App\Filament\Resources\VideoResource\Pages\CreateVideo;
use App\Filament\Resources\YouTubeChannelResource\Pages\CreateYouTubeChannel;
use App\Filament\Resources\YouTubeChannelResource\Pages\EditYouTubeChannel;
use App\Filament\Resources\YouTubeChannelResource\Pages\ListYouTubeChannels;
use App\Filament\Resources\YouTubeChannelVideoResource\Pages\ListYouTubeChannelVideos;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Video;
use App\Models\YouTubeChannel;
use App\Models\YouTubeChannelVideo;
use App\Services\WhatsApp\BilingualMessage;
use App\Services\YouTube\ChannelWatcher;
use App\Services\YouTube\YouTubeClient;
use App\Support\WhatsAppSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\PreparesOrderSite;
use Tests\TestCase;

/** Watched YouTube channels: feed → inbox → WhatsApp alert → "Add to library". */
class YouTubeChannelsTest extends TestCase
{
    use PreparesOrderSite;

    private const CHANNEL = 'UCabcdefghijklmnopqrstuv';

    private const KEY = 'test-youtube-key';

    private int $folderId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSiteLayout();
        $this->withoutVite();
        Http::preventStrayRequests();

        Schema::create('video_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->json('name');
            $table->string('slug');
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
            $table->string('slug');
            $table->string('youtube_url')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
        });

        (require database_path('migrations/2026_10_18_000001_create_youtube_channel_tables.php'))->up();

        $this->folderId = DB::table('video_folders')->insertGetId([
            'name' => json_encode(['en' => 'Races', 'ar' => 'السباقات']), 'slug' => 'races',
        ]);

        config(['services.youtube.key' => self::KEY]);
        $this->alertsOn();
    }

    private function alertsOn(array $overrides = []): void
    {
        $this->seedSiteSettings(array_merge([
            'site_name_en' => 'Muhra',
            'site_name_ar' => 'مهرة',
            'whatsapp.enabled' => true,
            'whatsapp.recipients' => [
                ['name' => 'Majid', 'phone' => '9123 4567'],
                ['name' => 'Office', 'phone' => '+968 9876 5432'],
            ],
        ], $overrides));
    }

    private function actAsAdmin(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** @param  list<array{0: string, 1: string, 2?: bool}>  $videos  [id, title, is short] */
    private static function feed(array $videos): string
    {
        $entries = collect($videos)->map(fn (array $v) => sprintf(
            '<entry><id>yt:video:%1$s</id><yt:videoId>%1$s</yt:videoId><title>%2$s</title>'
            .'<link rel="alternate" href="https://www.youtube.com/%3$s%1$s"/>'
            .'<published>2026-10-18T10:00:00+00:00</published>'
            .'<media:group><media:title>%2$s</media:title><media:thumbnail url="https://i1.ytimg.com/vi/%1$s/hqdefault.jpg" width="480" height="360"/>'
            .'<media:description>First line&#10;second line&#10;&#10;Second paragraph &amp; more</media:description></media:group></entry>',
            $v[0], htmlspecialchars($v[1], ENT_XML1), ($v[2] ?? false) ? 'shorts/' : 'watch?v=',
        ))->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?><feed xmlns:yt="http://www.youtube.com/xml/schemas/2015" xmlns:media="http://search.yahoo.com/mrss/" xmlns="http://www.w3.org/2005/Atom">'
            .'<title>Royal Cavalry</title>'.$entries.'</feed>';
    }

    /** @param  array<string, array<string, mixed>>  $items  id → overrides */
    private static function details(array $items): array
    {
        return ['items' => collect($items)->map(fn (array $item, string $id) => array_replace_recursive([
            'id' => $id,
            'snippet' => ['liveBroadcastContent' => 'none'],
            'contentDetails' => ['duration' => 'PT12M5S'],
            'status' => ['embeddable' => true],
        ], $item))->values()->all()];
    }

    private function fakeYouTube(string $feed, array $details = [], int $apiStatus = 200): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'www.youtube.com/feeds/*' => Http::response($feed, 200, ['Content-Type' => 'application/atom+xml']),
            'www.googleapis.com/youtube/v3/videos*' => $apiStatus === 200
                ? Http::response(self::details($details))
                : Http::response(['error' => ['code' => $apiStatus, 'errors' => [['reason' => 'quotaExceeded']]]], $apiStatus),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [[
                'id' => self::CHANNEL,
                'snippet' => ['title' => 'Royal Cavalry', 'customUrl' => '@royalcavalry', 'thumbnails' => ['default' => ['url' => 'https://yt3.example/c.jpg']]],
            ]]]),
        ]);
    }

    private function watchedChannel(array $attributes = []): YouTubeChannel
    {
        return YouTubeChannel::query()->forceCreate([
            'channel_id' => self::CHANNEL, 'handle' => '@royalcavalry', 'title' => 'Royal Cavalry',
            'video_folder_id' => $this->folderId, 'checked_at' => now()->subHour(), ...$attributes,
        ]);
    }

    private function storedVideo(string $id, array $attributes = []): YouTubeChannelVideo
    {
        return YouTubeChannelVideo::query()->forceCreate([
            'youtube_channel_id' => YouTubeChannel::query()->value('id'), 'youtube_id' => $id,
            'title' => 'Older video', 'kind' => 'video', 'status' => 'new', ...$attributes,
        ]);
    }

    // ── Adding a channel ─────────────────────────────────────────────────────

    public function test_adding_a_channel_by_its_link_records_its_recent_videos_without_alerting(): void
    {
        Queue::fake();
        $this->actAsAdmin();
        $this->fakeYouTube(self::feed([['vid00000001', 'Muscat races'], ['vid00000002', 'Training day']]), [
            'vid00000001' => [], 'vid00000002' => [],
        ]);

        Livewire::test(CreateYouTubeChannel::class)
            ->fillForm(['url' => 'https://www.youtube.com/@royalcavalry', 'video_folder_id' => $this->folderId])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $channel = YouTubeChannel::query()->sole();
        $this->assertSame(self::CHANNEL, $channel->channel_id);
        $this->assertSame('Royal Cavalry', $channel->title);
        $this->assertSame('@royalcavalry', $channel->handle);
        $this->assertNotNull($channel->checked_at);
        $this->assertSame(2, YouTubeChannelVideo::query()->where('status', 'new')->count());
        Queue::assertNothingPushed();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'channels') && $request['forHandle'] === '@royalcavalry');
    }

    public function test_without_an_api_key_a_handle_is_found_from_the_channel_page(): void
    {
        config(['services.youtube.key' => null]);
        Http::fake([
            'www.youtube.com/@royalcavalry' => Http::response('<html><link rel="canonical" href="https://www.youtube.com/channel/'.self::CHANNEL.'"></html>'),
            'www.youtube.com/feeds/*' => Http::response(self::feed([])),
        ]);

        $channel = app(YouTubeClient::class)->resolveChannel('@royalcavalry');

        $this->assertSame(self::CHANNEL, $channel['channel_id']);
        $this->assertSame('Royal Cavalry', $channel['title']);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'googleapis'));
    }

    public function test_an_unknown_or_already_watched_channel_is_refused(): void
    {
        $this->actAsAdmin();
        Http::fake(['www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => []])]);

        Livewire::test(CreateYouTubeChannel::class)
            ->fillForm(['url' => '@nobody-here'])
            ->call('create')
            ->assertHasFormErrors(['url']);

        $this->watchedChannel();
        $this->fakeYouTube(self::feed([]));

        Livewire::test(CreateYouTubeChannel::class)
            ->fillForm(['url' => 'https://www.youtube.com/channel/'.self::CHANNEL])
            ->call('create')
            ->assertHasFormErrors(['url']);

        $this->assertSame(1, YouTubeChannel::query()->count());
    }

    public function test_channel_links_are_understood_in_every_usual_form(): void
    {
        $this->assertSame(['id' => self::CHANNEL], YouTubeClient::parseChannelInput('https://www.youtube.com/channel/'.self::CHANNEL.'/videos'));
        $this->assertSame(['id' => self::CHANNEL], YouTubeClient::parseChannelInput(self::CHANNEL));
        $this->assertSame('@royalcavalry', YouTubeClient::parseChannelInput('https://m.youtube.com/@royalcavalry/streams')['handle']);
        $this->assertSame('@royalcavalry', YouTubeClient::parseChannelInput('royalcavalry')['handle']);
        $this->assertSame('https://www.youtube.com/user/oldname', YouTubeClient::parseChannelInput('youtube.com/user/oldname')['url']);
        $this->assertNull(YouTubeClient::parseChannelInput('https://example.com/'));

        $this->assertSame(3725, YouTubeClient::seconds('PT1H2M5S'));
        $this->assertNull(YouTubeClient::seconds('P0D'));
    }

    // ── New videos and the alert ─────────────────────────────────────────────

    public function test_new_videos_are_alerted_once_in_one_bilingual_message_per_channel(): void
    {
        Queue::fake();
        $channel = $this->watchedChannel();
        $this->storedVideo('vid00000001');
        $this->fakeYouTube(self::feed([['vid00000003', 'Final race'], ['vid00000002', 'Heats'], ['vid00000001', 'Older video']]), [
            'vid00000003' => [], 'vid00000002' => ['contentDetails' => ['duration' => 'PT1H2M5S']],
        ]);

        $result = app(ChannelWatcher::class)->check($channel);

        $this->assertSame(['checked' => true, 'found' => 2, 'alerted' => 2, 'error' => null], $result);
        Queue::assertPushed(SendWhatsAppMessage::class, 2);
        Queue::assertPushed(SendWhatsAppMessage::class, function (SendWhatsAppMessage $job) {
            [$en, $ar] = explode(BilingualMessage::DIVIDER, $job->message);

            return $job->type === 'new_youtube_video'
                && str_contains($en, 'New on YouTube: Royal Cavalry')
                && str_contains($en, 'Final race')
                && str_contains($en, 'Type: Video · 12:05')
                && str_contains($en, 'Type: Video · 1:02:05')
                && str_contains($en, 'https://youtu.be/vid00000003')
                && str_contains($en, '/admin/youtube-inbox')
                && ! str_contains($en, 'Older video')
                && str_contains($ar, 'جديد على يوتيوب')
                && str_contains($ar, 'النوع: فيديو');
        });
        $this->assertSame(2, YouTubeChannelVideo::query()->whereNotNull('notified_at')->count());

        // the next check finds nothing new
        Queue::fake();
        $this->assertSame(0, app(ChannelWatcher::class)->check($channel->fresh())['found']);
        Queue::assertNothingPushed();
    }

    public function test_shorts_are_skipped_unless_the_channel_keeps_them(): void
    {
        Queue::fake();
        $channel = $this->watchedChannel();
        $this->fakeYouTube(self::feed([['short000001', 'A short', true]]), ['short000001' => []]);

        app(ChannelWatcher::class)->check($channel);

        $this->assertSame('skipped', YouTubeChannelVideo::query()->sole()->status);
        Queue::assertNothingPushed();
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'googleapis'));

        $channel->update(['skip_shorts' => false]);
        $this->fakeYouTube(self::feed([['short000002', 'Another short', true]]), ['short000002' => ['contentDetails' => ['duration' => 'PT40S']]]);

        app(ChannelWatcher::class)->check($channel);

        $this->assertSame('new', YouTubeChannelVideo::query()->where('youtube_id', 'short000002')->value('status'));
        Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job) => str_contains($job->message, 'Type: Short · 0:40'));
    }

    public function test_scheduled_and_live_streams_say_so_and_are_refreshed_later(): void
    {
        Queue::fake();
        $channel = $this->watchedChannel();
        $this->fakeYouTube(self::feed([['live0000001', 'Race day live'], ['soon0000001', 'Cup final']]), [
            'live0000001' => ['snippet' => ['liveBroadcastContent' => 'live'], 'contentDetails' => ['duration' => 'P0D'], 'liveStreamingDetails' => ['actualStartTime' => '2026-10-18T09:00:00Z']],
            'soon0000001' => ['snippet' => ['liveBroadcastContent' => 'upcoming'], 'contentDetails' => ['duration' => 'P0D'], 'liveStreamingDetails' => ['scheduledStartTime' => '2026-10-20T14:00:00Z']],
        ]);

        app(ChannelWatcher::class)->check($channel);

        Queue::assertPushed(SendWhatsAppMessage::class, function (SendWhatsAppMessage $job) {
            [$en, $ar] = explode(BilingualMessage::DIVIDER, $job->message);

            // 14:00 UTC = 18:00 in Muscat
            return str_contains($en, 'Live now') && str_contains($en, 'starts 2026-10-20 18:00 (Muscat time)')
                && str_contains($ar, 'بث مباشر الآن') && str_contains($ar, '2026-10-20 18:00');
        });

        // the stream ended: the inbox shows it, without a second alert
        Queue::fake();
        $this->fakeYouTube(self::feed([['live0000001', 'Race day live']]), [
            'live0000001' => ['contentDetails' => ['duration' => 'PT2H'], 'liveStreamingDetails' => ['actualEndTime' => '2026-10-18T11:00:00Z']],
        ]);

        app(ChannelWatcher::class)->check($channel->fresh());

        $live = YouTubeChannelVideo::query()->where('youtube_id', 'live0000001')->sole();
        $this->assertSame('stream', $live->kind);
        $this->assertSame(7200, $live->duration_seconds);
        Queue::assertNothingPushed();
    }

    public function test_a_video_that_cannot_be_embedded_is_flagged(): void
    {
        Queue::fake();
        $this->fakeYouTube(self::feed([['noembed0001', 'Private rights']]), ['noembed0001' => ['status' => ['embeddable' => false]]]);

        app(ChannelWatcher::class)->check($this->watchedChannel());

        $this->assertFalse(YouTubeChannelVideo::query()->sole()->embeddable);
        Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job) => str_contains($job->message, 'cannot play in the video library')
            && str_contains($job->message, 'لا يمكن تشغيله في مكتبة الفيديو'));
    }

    public function test_an_api_failure_still_alerts_from_the_feed_and_never_stores_the_key(): void
    {
        Queue::fake();
        $channel = $this->watchedChannel();
        $this->fakeYouTube(self::feed([['vid00000009', 'Still found']]), apiStatus: 403);

        $result = app(ChannelWatcher::class)->check($channel);

        $this->assertSame(1, $result['alerted']);
        $this->assertSame('api_failed|quotaExceeded', $channel->fresh()->last_error);
        $this->assertSame('video', YouTubeChannelVideo::query()->sole()->kind);
        Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job) => str_contains($job->message, 'Still found'));
    }

    public function test_when_the_feed_is_down_the_uploads_playlist_stands_in(): void
    {
        Queue::fake();
        $channel = $this->watchedChannel();
        $item = fn (string $id, string $title, string $privacy = 'public') => [
            'snippet' => ['title' => $title, 'description' => 'About it', 'publishedAt' => '2026-10-18T10:05:00Z', 'thumbnails' => ['high' => ['url' => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg"]]],
            'contentDetails' => ['videoId' => $id, 'videoPublishedAt' => '2026-10-18T10:00:00Z'],
            'status' => ['privacyStatus' => $privacy],
        ];
        Http::fake([
            'www.youtube.com/feeds/*' => Http::response('', 404),
            'www.googleapis.com/youtube/v3/playlistItems*playlistId=UUSH*' => Http::response(['items' => [$item('short000003', 'A short')]]),
            'www.googleapis.com/youtube/v3/playlistItems*playlistId=UU*' => Http::response(['items' => [
                $item('vid00000010', 'From the API'), $item('short000003', 'A short'), $item('private0001', 'Private video', 'private'),
            ]]),
            'www.googleapis.com/youtube/v3/videos*' => Http::response(self::details(['vid00000010' => []])),
        ]);

        $result = app(ChannelWatcher::class)->check($channel);

        $this->assertTrue($result['checked']);
        $this->assertSame(['short000003' => 'skipped', 'vid00000010' => 'new'], YouTubeChannelVideo::query()->orderBy('youtube_id')->pluck('status', 'youtube_id')->all());
        $this->assertSame('About it', YouTubeChannelVideo::query()->where('youtube_id', 'vid00000010')->value('description'));
        Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job) => str_contains($job->message, 'From the API'));
    }

    public function test_a_channel_without_uploads_playlist_is_not_found(): void
    {
        $channel = $this->watchedChannel();
        Http::fake([
            'www.youtube.com/feeds/*' => Http::response('', 404),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response(['error' => ['code' => 404, 'errors' => [['reason' => 'playlistNotFound']]]], 404),
        ]);

        $this->assertFalse(app(ChannelWatcher::class)->check($channel)['checked']);
        $this->assertSame('not_found|', $channel->fresh()->last_error);
    }

    public function test_a_feed_that_cannot_be_read_is_recorded_on_the_channel(): void
    {
        config(['services.youtube.key' => null]);
        $channel = $this->watchedChannel();
        Http::fake(['www.youtube.com/feeds/*' => Http::response('', 500)]);

        $result = app(ChannelWatcher::class)->check($channel);

        $this->assertFalse($result['checked']);
        $this->assertSame('feed_failed|500', $channel->fresh()->last_error);
    }

    public function test_videos_are_kept_but_not_sent_while_the_alert_is_off(): void
    {
        Queue::fake();
        $this->alertsOn(['whatsapp.alerts' => ['youtube_video' => false]]);
        $this->fakeYouTube(self::feed([['vid00000004', 'Quiet one']]), ['vid00000004' => []]);

        app(ChannelWatcher::class)->check($this->watchedChannel());

        $this->assertSame('new', YouTubeChannelVideo::query()->sole()->status);
        $this->assertNull(YouTubeChannelVideo::query()->sole()->notified_at);
        Queue::assertNothingPushed();
        $this->assertContains('youtube_video', WhatsAppSettings::ALERTS);
    }

    public function test_a_video_already_in_the_library_is_marked_added_without_an_alert(): void
    {
        Queue::fake();
        $video = Video::query()->forceCreate([
            'folder_id' => $this->folderId, 'title' => ['en' => 'Pasted earlier'], 'slug' => 'pasted-earlier',
            'youtube_url' => 'https://youtu.be/vid00000005',
        ]);
        $this->fakeYouTube(self::feed([['vid00000005', 'Pasted earlier']]), ['vid00000005' => []]);

        app(ChannelWatcher::class)->check($this->watchedChannel());

        $row = YouTubeChannelVideo::query()->sole();
        $this->assertSame('added', $row->status);
        $this->assertSame($video->getKey(), $row->library_video_id);
        Queue::assertNothingPushed();
    }

    public function test_the_command_checks_only_the_channels_being_watched(): void
    {
        Queue::fake();
        $this->watchedChannel(['is_active' => false]);
        $this->fakeYouTube(self::feed([['vid00000006', 'Not looked at']]));

        $this->artisan('youtube:check')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, YouTubeChannelVideo::query()->count());
    }

    // ── The inbox and "Add to library" ───────────────────────────────────────

    public function test_add_to_library_fills_the_new_video_form_and_marks_the_entry_added(): void
    {
        $this->actAsAdmin();
        $this->watchedChannel();
        $entry = $this->storedVideo('vid00000007', [
            'title' => 'Sultan Cup 2026', 'description' => "Line one\nline two\n\nMore <b>here</b>",
        ]);

        Livewire::test(CreateVideo::class, ['youtubeInboxId' => (string) $entry->getKey()])
            ->assertSchemaStateSet([
                'folder_id' => $this->folderId,
                'title.en' => 'Sultan Cup 2026',
                'slug' => 'sultan-cup-2026',
                'youtube_url' => 'https://www.youtube.com/watch?v=vid00000007',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $video = Video::query()->sole();
        $this->assertSame('vid00000007', $video->youtube_id);
        $this->assertStringContainsString('<p>Line one<br>', (string) $video->getTranslation('description', 'en'));
        $this->assertStringNotContainsString('<b>here</b>', (string) $video->getTranslation('description', 'en'));
        $this->assertSame('added', $entry->fresh()->status);
        $this->assertSame($video->getKey(), $entry->fresh()->library_video_id);
    }

    public function test_an_arabic_title_goes_in_the_arabic_field(): void
    {
        $this->watchedChannel();
        $data = $this->storedVideo('vid00000008', ['title' => 'كأس السلطان'])->load('channel')->libraryFormData();

        $this->assertSame(['ar' => 'كأس السلطان'], $data['title']);
        $this->assertSame('vid00000008', $data['slug']);
    }

    public function test_the_channel_list_shows_problems_and_checks_on_request(): void
    {
        Queue::fake();
        $this->actAsAdmin();
        $channel = $this->watchedChannel(['last_error' => 'feed_failed|500']);
        $this->fakeYouTube(self::feed([['vid0000000c', 'Fresh one']]), ['vid0000000c' => []]);

        Livewire::test(ListYouTubeChannels::class)
            ->assertOk()
            ->assertSee('Royal Cavalry')
            ->assertSee('The channel feed answered with an error (500).')
            ->callAction(TestAction::make('check_now')->table($channel))
            ->assertNotified();

        $this->assertNull($channel->fresh()->last_error);
        $this->assertSame(1, YouTubeChannelVideo::query()->count());

        Livewire::test(EditYouTubeChannel::class, ['record' => $channel->getKey()])
            ->assertOk()
            ->assertSchemaStateSet(['channel_id' => self::CHANNEL])
            ->fillForm(['skip_shorts' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($channel->fresh()->skip_shorts);
    }

    public function test_the_inbox_lists_new_videos_and_can_ignore_them(): void
    {
        $this->actAsAdmin();
        $this->watchedChannel();
        $new = $this->storedVideo('vid0000000a', ['title' => 'Waiting video']);
        $this->storedVideo('vid0000000b', ['title' => 'Handled video', 'status' => 'ignored']);

        Livewire::test(ListYouTubeChannelVideos::class)
            ->assertOk()
            ->assertSee('Waiting video')
            ->assertDontSee('Handled video')
            ->callAction(TestAction::make('ignore')->table($new));

        $this->assertSame('ignored', $new->fresh()->status);
    }
}
