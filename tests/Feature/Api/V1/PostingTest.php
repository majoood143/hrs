<?php

namespace Tests\Feature\Api\V1;

use App\Events\PublicPostSubmitted;
use App\Models\HorseSalePost;
use App\Models\ToolSalePost;
use App\Models\TransferPost;
use App\Support\Api\PostingLimit;
use App\Support\MobileApp\MobilePosting;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;
use WallaceMartinss\FilamentEvolution\Models\WhatsappInstance;
use WallaceMartinss\FilamentEvolution\Services\EvolutionClient;

/**
 * Posting from the app: a phone verified by a code (WhatsApp first, SMS as the backup) instead of
 * the website's captcha, then the website's own post creation.
 */
class PostingTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    private const PHONE = '99887766';

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareDirectoryTables();

        foreach ([
            '2026_09_23_000001_create_customers_tables',
            '2026_09_23_000002_create_notification_logs_table',
            '2026_09_26_140451_create_whatsapp_instances_table',
            // after the directory tables: it changes the farriers' and transfers' status too
            '2026_10_13_000001_add_pending_status_to_public_posts',
        ] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }

        config(['filament-evolution.api.base_url' => 'https://evo.test', 'filament-evolution.api.api_key' => 'test-key']);
        app()->forgetInstance(EvolutionClient::class);

        $this->settings();
        $this->ids = $this->seedLocations();
        $this->ids['type'] = DB::table('types')->insertGetId(['en_name' => 'Arabian', 'ar_name' => 'عربي']);
        $this->ids['gender'] = DB::table('genders')->insertGetId(['en_name' => 'Mare', 'ar_name' => 'فرس']);

        Storage::fake('public');
        Event::fake([PublicPostSubmitted::class]);
    }

    // ── Codes ─────────────────────────────────────────────────────────────────

    public function test_the_code_goes_by_whatsapp_in_both_languages_and_never_into_the_log(): void
    {
        WhatsappInstance::create(['name' => 'muhra', 'number' => '96890000001', 'status' => 'open']);
        Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'MSG-1']], 201)]);

        $this->postJson('/api/v1/verification/request', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJson(['channel' => 'whatsapp', 'resend_in' => 60, 'expires_in' => 300, 'demo_code' => null]);

        Http::assertSent(function (Request $request) {
            $text = (string) $request['text'];

            return $request->url() === 'https://evo.test/message/sendText/muhra'
                && str_contains($text, 'your verification code is')
                && str_contains($text, 'رمز التحقق الخاص بك')
                && preg_match('/\b\d{6}\b/', $text) === 1;
        });

        $log = DB::table('notification_logs')->where('channel', 'whatsapp')->sole();
        $this->assertSame('otp', $log->type);
        $this->assertNull($log->message, 'a code never lands in the log');
    }

    public function test_when_whatsapp_cannot_send_the_code_goes_by_sms(): void
    {
        WhatsappInstance::create(['name' => 'muhra', 'number' => '96890000001', 'status' => 'open']);
        Http::fake(['evo.test/*' => Http::response(['message' => 'instance not connected'], 400)]);

        $this->postJson('/api/v1/verification/request', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJsonPath('channel', 'sms')
            ->assertJsonPath('demo_code', fn ($code) => preg_match('/^\d{6}$/', (string) $code) === 1);

        $this->assertSame(['whatsapp' => 'failed', 'sms' => 'sent'], DB::table('notification_logs')->orderBy('id')->pluck('status', 'channel')->all());
    }

    public function test_sms_is_used_when_asked_for_when_no_instance_is_connected_or_when_whatsapp_codes_are_off(): void
    {
        Http::fake();

        // No instance connected.
        $this->postJson('/api/v1/verification/request', ['phone' => '91111111'])->assertOk()->assertJsonPath('channel', 'sms');

        WhatsappInstance::create(['name' => 'muhra', 'number' => '96890000001', 'status' => 'open']);
        $this->postJson('/api/v1/verification/request', ['phone' => '92222222', 'channel' => 'sms'])->assertOk()->assertJsonPath('channel', 'sms');

        $this->settings(['whatsapp.codes' => false]);
        $this->postJson('/api/v1/verification/request', ['phone' => '93333333'])->assertOk()->assertJsonPath('channel', 'sms');

        Http::assertNothingSent();
    }

    public function test_asking_again_too_soon_a_bad_phone_and_no_way_to_send_are_refused(): void
    {
        $this->postJson('/api/v1/verification/request', ['phone' => self::PHONE])->assertOk();
        $this->postJson('/api/v1/verification/request', ['phone' => self::PHONE])
            ->assertStatus(429)
            ->assertJsonPath('retry_after', fn ($seconds) => $seconds > 0 && $seconds <= 60);

        $this->postJson('/api/v1/verification/request', ['phone' => '12'])->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->settings(['sms.driver' => '']);
        $this->postJson('/api/v1/verification/request', ['phone' => '95555555'])->assertStatus(503);
    }

    public function test_a_right_code_gives_a_posting_token_and_a_wrong_one_does_not(): void
    {
        $code = $this->requestCode();

        $this->postJson('/api/v1/verification/verify', ['phone' => self::PHONE, 'code' => $code === '000000' ? '111111' : '000000'], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', __('api.verification.wrong', [], 'ar'));

        $this->postJson('/api/v1/verification/verify', ['phone' => '+968 9988 7766', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('phone', '+96899887766')
            ->assertJsonPath('posting_token', fn ($token) => is_string($token) && strlen($token) > 40);

        // A code works once.
        $this->postJson('/api/v1/verification/verify', ['phone' => self::PHONE, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', __('api.verification.expired', [], 'en'));
    }

    public function test_app_config_says_which_channels_can_send_codes(): void
    {
        $this->getJson('/api/v1/app-config')->assertJsonPath('data.verification', ['whatsapp' => false, 'sms' => true]);

        WhatsappInstance::create(['name' => 'muhra', 'number' => '96890000001', 'status' => 'open']);
        $this->getJson('/api/v1/app-config')->assertJsonPath('data.verification', ['whatsapp' => true, 'sms' => true]);
    }

    // ── Posting ───────────────────────────────────────────────────────────────

    public function test_a_verified_phone_posts_a_horse_with_its_photos_like_the_website(): void
    {
        $token = $this->postingToken();

        $this->post('/api/v1/horses-for-sale', [
            'posting_token' => $token,
            'en_name' => 'Desert Wind',
            'ar_name' => 'ريح الصحراء',
            'type_id' => $this->ids['type'],
            'gender_id' => $this->ids['gender'],
            'city_id' => $this->ids['seeb'],
            'price' => '1500',
            'price_negotiable' => '1',
            'cover_photo' => UploadedFile::fake()->image('cover.jpg'),
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            'passport_document' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
            // Ignored: the contact number is the verified phone.
            'contact_number' => '91234567',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Desert Wind')
            ->assertJsonPath('data.contact_number', '+96899887766')
            ->assertJsonPath('data.price_negotiable', true)
            ->assertJsonPath('data.region.name', 'Muscat')
            ->assertJsonCount(3, 'data.image_urls')
            ->assertJsonPath('meta.pending_review', false);

        $post = HorseSalePost::sole();
        $this->assertSame('active', $post->status);
        $this->assertSame((int) $this->ids['oman'], (int) $post->country_id);
        Storage::disk('public')->assertExists($post->cover_photo);
        Storage::disk('public')->assertExists($post->passport_document);
        Event::assertDispatched(PublicPostSubmitted::class, fn ($event) => $event->post->is($post));
    }

    public function test_tools_farriers_and_transfers_can_be_posted_too(): void
    {
        $token = $this->postingToken();

        $this->post('/api/v1/tools-for-sale', [
            'posting_token' => $token, 'en_name' => 'Saddle', 'ar_name' => 'سرج', 'category' => 'saddlery_tack', 'condition' => 'used',
            'city_id' => $this->ids['seeb'], 'price' => '120', 'cover_photo' => UploadedFile::fake()->image('saddle.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.category.value', 'saddlery_tack');

        $this->post('/api/v1/farriers', [
            'posting_token' => $token, 'en_name' => 'Ali', 'ar_name' => 'علي', 'city_id' => $this->ids['seeb'], 'price' => '15',
            'years_experience' => 12, 'cover_photo' => UploadedFile::fake()->image('ali.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.years_experience', 12);

        $this->postJson('/api/v1/transfer-board', [
            'posting_token' => $token, 'type' => 'request', 'from_city_id' => $this->ids['seeb'], 'to_city_id' => $this->ids['bawshar'],
            'capacity' => 2, 'transfer_date' => now()->addDays(3)->toDateString(), 'price' => '99',
        ])->assertCreated()->assertJsonPath('data.price', null)->assertJsonPath('data.to.city.name', 'Bawshar');

        $this->assertSame('+96899887766', TransferPost::sole()->contact_number);
        Event::assertDispatchedTimes(PublicPostSubmitted::class, 3);
    }

    public function test_posting_needs_a_valid_unexpired_token(): void
    {
        $fields = ['type' => 'offer', 'from_city_id' => $this->ids['seeb'], 'to_city_id' => $this->ids['bawshar'], 'capacity' => 2, 'transfer_date' => now()->addDay()->toDateString(), 'price' => '50'];

        $this->postJson('/api/v1/transfer-board', $fields)->assertUnauthorized()->assertJsonPath('code', 'verification_required');
        $this->postJson('/api/v1/transfer-board', $fields + ['posting_token' => 'forged'])->assertUnauthorized();

        $token = $this->postingToken();
        $this->travel(13)->hours();
        $this->postJson('/api/v1/transfer-board', $fields + ['posting_token' => $token])->assertUnauthorized();

        $this->assertSame(0, TransferPost::count());
    }

    public function test_the_fields_are_checked_like_the_website_in_the_visitors_language(): void
    {
        $this->postJson('/api/v1/transfer-board', [
            'posting_token' => $this->postingToken(),
            'type' => 'offer', 'from_city_id' => $this->ids['seeb'], 'to_city_id' => $this->ids['seeb'],
            'capacity' => 0, 'transfer_date' => now()->subDay()->toDateString(),
        ], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from_city_id', 'capacity', 'transfer_date', 'price']);

        $this->assertSame(0, TransferPost::count());
    }

    public function test_one_phone_may_post_five_times_a_day(): void
    {
        $token = $this->postingToken();
        $fields = ['posting_token' => $token, 'type' => 'request', 'from_city_id' => $this->ids['seeb'], 'to_city_id' => $this->ids['bawshar'], 'capacity' => 1, 'transfer_date' => now()->addDay()->toDateString()];

        foreach (range(1, PostingLimit::perDay()) as $ignored) {
            $this->postJson('/api/v1/transfer-board', $fields)->assertCreated();
        }

        $this->postJson('/api/v1/transfer-board', $fields)->assertStatus(429)->assertJsonPath('code', 'posting_limit');

        $this->travel(25)->hours();
        $this->postJson('/api/v1/transfer-board', ['posting_token' => $this->postingToken('91112222')] + $fields)->assertCreated();
    }

    public function test_while_app_posts_are_reviewed_a_post_waits_hidden_for_an_admin(): void
    {
        $this->settings(['mobile.posting' => json_encode(['review' => true, 'daily_limit' => 5])]);
        $token = $this->postingToken();

        $id = $this->post('/api/v1/tools-for-sale', [
            'posting_token' => $token, 'en_name' => 'Saddle', 'ar_name' => 'سرج', 'category' => 'saddlery_tack', 'condition' => 'used',
            'city_id' => $this->ids['seeb'], 'price' => '120', 'cover_photo' => UploadedFile::fake()->image('saddle.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('meta.pending_review', true)
            ->json('data.id');

        $this->postJson('/api/v1/transfer-board', [
            'posting_token' => $token, 'type' => 'request', 'from_city_id' => $this->ids['seeb'], 'to_city_id' => $this->ids['bawshar'],
            'capacity' => 2, 'transfer_date' => now()->addDays(3)->toDateString(),
        ])->assertCreated()->assertJsonPath('meta.pending_review', true);

        $this->assertSame('pending', ToolSalePost::sole()->status);
        $this->assertSame('pending', TransferPost::sole()->status);
        $this->getJson("/api/v1/tools-for-sale/{$id}")->assertNotFound();
        $this->getJson('/api/v1/tools-for-sale')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/transfer-board')->assertOk()->assertJsonCount(0, 'data');
        Event::assertDispatchedTimes(PublicPostSubmitted::class, 2);

        ToolSalePost::sole()->update(['status' => 'active']);
        $this->getJson("/api/v1/tools-for-sale/{$id}")->assertOk();
    }

    public function test_the_daily_limit_comes_from_the_admin_and_counts_pending_posts(): void
    {
        $this->settings(['mobile.posting' => json_encode(['review' => true, 'daily_limit' => 2])]);
        $fields = ['posting_token' => $this->postingToken(), 'type' => 'request', 'from_city_id' => $this->ids['seeb'], 'to_city_id' => $this->ids['bawshar'], 'capacity' => 1, 'transfer_date' => now()->addDay()->toDateString()];

        $this->postJson('/api/v1/transfer-board', $fields)->assertCreated();
        $this->postJson('/api/v1/transfer-board', $fields)->assertCreated();
        $this->postJson('/api/v1/transfer-board', $fields)->assertStatus(429)->assertJsonPath('code', 'posting_limit');

        $this->assertSame(2, PostingLimit::perDay());
        $this->assertSame(2, TransferPost::where('status', 'pending')->count());
    }

    public function test_a_broken_or_missing_posting_setting_means_no_review_and_five_a_day(): void
    {
        $this->assertSame(['review' => false, 'daily_limit' => 5], MobilePosting::settings());

        $this->settings(['mobile.posting' => json_encode(['review' => 'yes', 'daily_limit' => 500])]);
        $this->assertSame(['review' => true, 'daily_limit' => 5], MobilePosting::settings());

        $this->settings(['mobile.posting' => 'not json']);
        $this->assertSame('active', MobilePosting::newPostStatus());
    }

    private function settings(array $overrides = []): void
    {
        $this->seedApiSettings($overrides + ['site_name_en' => 'Muhra', 'site_name_ar' => 'مهرة', 'sms.driver' => 'demo', 'whatsapp.codes' => true]);
    }

    /** Asks for a code by SMS (the demo driver hands it back) and returns it. */
    private function requestCode(string $phone = self::PHONE): string
    {
        return (string) $this->postJson('/api/v1/verification/request', ['phone' => $phone, 'channel' => 'sms'])->assertOk()->json('demo_code');
    }

    private function postingToken(string $phone = self::PHONE): string
    {
        return (string) $this->postJson('/api/v1/verification/verify', ['phone' => $phone, 'code' => $this->requestCode($phone)])->assertOk()->json('posting_token');
    }
}
