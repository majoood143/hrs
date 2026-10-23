<?php

namespace Tests\Feature;

use App\Filament\Pages\RaceResultsWhatsApp;
use App\Jobs\SendWhatsAppMessage;
use App\Models\RaceResultAlert;
use App\Models\SiteSetting;
use App\Services\Racing\RaceResultAlerts;
use App\Services\WhatsApp\BilingualMessage;
use App\Services\WhatsApp\RaceResultMessage;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\FakesRacingSource;
use Tests\TestCase;

/** Race results to the admins by WhatsApp: the message, the automatic check and the admin page. */
class RaceResultAlertsTest extends TestCase
{
    use FakesRacingSource;

    /** A race day in the 25/26 season (fixture calendar_days_2526.json). */
    private const DAY = '2026-04-18';

    /** Whether race 9177's replay is on YouTube yet. */
    private bool $replay = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRacingSite();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        (require database_path('migrations/2026_10_22_000001_create_race_result_alerts_table.php'))->up();

        // the race day lists two races of the 9178 meeting (the fixtures' full one): 9178 with its
        // replay, 9177 with results but its replay only once $this->replay is set; 9176 has not run
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            // the results page's meeting without its replay links: they come from the race-day list here
            if (($q['page'] ?? null) === 'RaceResults' && isset($q['racefile'])) {
                $html = $this->fixture(($q['lang'] ?? 'en') === 'ar' ? 'meeting_results_9178_ar.html' : 'meeting_results_9178.html');

                return Http::response(preg_replace('/<a[^>]*videoLink[^>]*>.*?<\/a>/s', '', $html));
            }

            if (($q['method'] ?? null) !== 'getRaceDetailsWeb' || ($q['dateFrom'] ?? null) !== self::DAY || ($q['dateTo'] ?? null) !== self::DAY) {
                return null;
            }

            $link = fn (string $page, int $id) => "<a class='raceLink' href='http://x/?page={$page}&racefile={$id}'><img></a>";
            $video = fn (?string $url) => $url ? "<a class='videoLink' href='{$url}'><img></a>" : "<img src='img/icon_racevideo_disabled_16.png'>";
            $row = fn (int $id, string $name, ?string $url, bool $results, string $time) => [
                $id, $name, 1600.0, $video($url), '', $link('RaceEntry', $id), $link('RaceCard', $id),
                $results ? $link('RaceResults', $id) : '<img>', 'Final Meeting', '14:00', $time,
            ];

            return Http::response(json_encode(['TOTAL' => 1, 'RECORDS' => 3, 'PAGE' => 1, 'ROWS' => [
                $row(9178, 'Race 9178', 'https://youtu.be/AAAAAAAAAAA', true, '14:00'),
                $row(9177, 'Race 9177', $this->replay ? 'https://youtu.be/BBBBBBBBBBB' : null, true, '14:30'),
                $row(9176, 'Race 9176', null, false, '15:00'),
            ]]));
        });
        $this->fakeRacingSource();

        Carbon::setTestNow(Carbon::parse(self::DAY.' 16:00', 'Asia/Muscat'));
        $this->settings();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function settings(array $overrides = []): void
    {
        Cache::forever('site_settings.all', collect(array_merge([
            'site_name_en' => 'Muhra',
            'whatsapp.enabled' => true,
            'whatsapp.recipients' => [
                ['name' => 'Majid', 'phone' => '9123 4567'],
                ['name' => 'Office', 'phone' => '+968 9876 5432'],
            ],
        ], $overrides))->map(fn ($value, $key) => (object) [
            'key' => $key,
            'type' => is_bool($value) ? 'boolean' : 'text',
            'value' => is_bool($value) ? ($value ? 'true' : 'false') : (is_array($value) ? json_encode($value) : (string) $value),
        ]));
        SiteSetting::resetMemo();
    }

    /** The source's answers are cached; drop them (but not the settings) as if the cache had expired. */
    private function expireRacingCache(): void
    {
        $settings = Cache::get('site_settings.all');
        Cache::flush();
        Cache::forever('site_settings.all', $settings);
    }

    public function test_the_message_is_arabic_right_to_left_with_the_first_six_places_the_replay_and_the_results_page(): void
    {
        $message = app(RaceResultMessage::class)->build(9178, 'https://youtu.be/AAAAAAAAAAA')['message'];

        // Arabic only, as the admins asked
        $this->assertStringNotContainsString(BilingualMessage::DIVIDER, $message);
        $this->assertStringNotContainsString('Race results', $message);
        $this->assertStringContainsString('نتائج السباق', $message);

        $this->assertStringContainsString('🥇 1. *', $message);
        $this->assertStringContainsString('6️⃣ 6. *', $message);
        $this->assertStringNotContainsString(' 7. *', $message);
        $this->assertSame(6, substr_count($message, '🏇 الفارس: '));
        $this->assertSame(6, substr_count($message, '🧑‍🏫 المدرب: '));
        $this->assertSame(6, substr_count($message, '👤 المالك: '));
        $this->assertStringContainsString('youtu', $message);
        $this->assertStringContainsString(route('racing.meeting', ['page' => 'results', 'race' => 9178]), $message);

        // every line starts right to left, whatever its first character (emoji, number, Latin text, link)
        foreach (array_filter(explode("\n", $message), fn (string $line) => $line !== '') as $line) {
            $this->assertStringStartsWith(RaceResultMessage::RLM, $line);
        }
    }

    public function test_a_race_without_results_has_no_message(): void
    {
        $this->assertNull(app(RaceResultMessage::class)->build(10005));
    }

    public function test_the_automatic_check_sends_each_race_once_and_waits_for_the_replay(): void
    {
        Queue::fake();
        $alerts = app(RaceResultAlerts::class);

        // 9178 has its replay: sent to both numbers; 9177 waits for its replay; 9176 has not run
        $this->assertSame(['sent' => 1, 'waiting' => 1, 'videos' => 0], $alerts->checkToday());
        Queue::assertPushed(SendWhatsAppMessage::class, 2);
        Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job) => $job->to === '96891234567' && $job->type === 'race_result');
        $this->assertSame('auto', RaceResultAlert::firstWhere('race_id', 9178)->mode);
        $this->assertNull(RaceResultAlert::firstWhere('race_id', 9176));

        // the next run sends nothing again
        $this->assertSame(['sent' => 0, 'waiting' => 1, 'videos' => 0], $alerts->checkToday());
        Queue::assertPushed(SendWhatsAppMessage::class, 2);

        // no replay after the wait: 9177 goes without it
        Carbon::setTestNow(now()->addMinutes(config('racing.result_alert_video_wait') + 1));
        $this->assertSame(['sent' => 1, 'waiting' => 0, 'videos' => 0], $alerts->checkToday());
        Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job) => str_contains($job->message, __('whatsapp.race_result.no_replay', [], 'ar')));
        $this->assertNull(RaceResultAlert::firstWhere('race_id', 9177)->video_sent_at);

        // the replay comes later: one short follow-up, once
        $this->replay = true;
        $this->expireRacingCache();
        $this->assertSame(['sent' => 0, 'waiting' => 0, 'videos' => 1], $alerts->checkToday());
        Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job) => $job->type === 'race_result_video' && str_contains($job->message, 'https://youtu.be/BBBBBBBBBBB'));
        $this->assertSame(['sent' => 0, 'waiting' => 0, 'videos' => 0], $alerts->checkToday());
        Queue::assertPushed(SendWhatsAppMessage::class, 6);
    }

    public function test_nothing_is_sent_automatically_with_the_alert_off_or_on_other_days(): void
    {
        Queue::fake();

        $this->settings(['whatsapp.alerts' => ['race_result' => false]]);
        $this->artisan('racing:result-alerts')->assertSuccessful();

        $this->settings(['whatsapp.enabled' => false]);
        $this->artisan('racing:result-alerts')->assertSuccessful();

        $this->settings();
        Carbon::setTestNow(Carbon::parse('2026-04-19 16:00', 'Asia/Muscat'));
        $this->artisan('racing:result-alerts')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(0, RaceResultAlert::count());
    }

    public function test_the_admin_page_lists_the_day_and_sends_a_race_by_hand(): void
    {
        Queue::fake();
        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(RaceResultsWhatsApp::class)
            ->assertSee('Race 9178')
            ->assertSee('Race 9176')
            ->assertActionHidden(TestAction::make('send')->table('9176'))
            ->callAction(TestAction::make('send')->table('9178'), ['recipients' => ['96898765432']])
            ->assertHasNoActionErrors();

        Queue::assertPushed(SendWhatsAppMessage::class, 1);
        Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job) => $job->to === '96898765432');
        $this->assertSame('manual', RaceResultAlert::firstWhere('race_id', 9178)->mode);

        // "send all not sent" skips 9178 (sent) and 9176 (no results)
        $page->callAction('sendAll');
        Queue::assertPushed(SendWhatsAppMessage::class, 3);
        $this->assertNotNull(RaceResultAlert::firstWhere('race_id', 9177)->sent_at);
    }
}
