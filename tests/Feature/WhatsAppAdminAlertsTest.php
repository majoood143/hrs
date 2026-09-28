<?php

namespace Tests\Feature;

use App\Events\PublicPostSubmitted;
use App\Filament\Pages\Settings\GeneralSettings;
use App\Jobs\SendWhatsAppMessage;
use App\Models\City;
use App\Models\HorseSalePost;
use App\Models\NotificationLog;
use App\Models\SiteSetting;
use App\Models\TransferPost;
use App\Services\WhatsApp\BilingualMessage;
use App\Services\WhatsApp\NewPostAlert;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Support\WhatsAppSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\PreparesOrderSite;
use Tests\TestCase;
use WallaceMartinss\FilamentEvolution\Models\WhatsappInstance;
use WallaceMartinss\FilamentEvolution\Services\EvolutionClient;

class WhatsAppAdminAlertsTest extends TestCase
{
    use PreparesOrderSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSiteLayout();

        foreach ([
            '2026_09_23_000002_create_notification_logs_table',
            '2026_09_26_140451_create_whatsapp_instances_table',
        ] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }

        config([
            'filament-evolution.api.base_url' => 'https://evo.test',
            'filament-evolution.api.api_key' => 'test-key',
        ]);
        app()->forgetInstance(EvolutionClient::class);
    }

    private function alertsOn(array $overrides = []): void
    {
        $this->seedSiteSettings(array_merge([
            'site_name_en' => 'HRS',
            'site_name_ar' => 'اتش ار اس',
            'whatsapp.enabled' => true,
            'whatsapp.recipients' => [
                ['name' => 'Majid', 'phone' => '9123 4567'],
                ['name' => 'Office', 'phone' => '+968 9876 5432'],
                ['name' => 'Same again', 'phone' => '0096891234567'],
            ],
        ], $overrides));
    }

    private function horsePost(): HorseSalePost
    {
        return (new HorseSalePost)->forceFill([
            'id' => 7,
            'en_name' => 'Desert Wind',
            'ar_name' => 'ريح الصحراء',
            'price' => '1500.000',
            'price_negotiable' => true,
            'contact_number' => '9111 2222',
            'status' => 'active',
        ]);
    }

    // ── Which posts send an alert, and to whom ────────────────────────────────

    public function test_a_new_post_queues_one_alert_per_admin_number(): void
    {
        Queue::fake();
        $this->alertsOn();

        PublicPostSubmitted::dispatch($this->horsePost());

        // three rows, but the first and third are the same number
        Queue::assertPushed(SendWhatsAppMessage::class, 2);
        Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job) => $job->to === '96891234567' && $job->type === 'new_horse_sale_post');
        Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job) => $job->to === '96898765432');
    }

    public function test_nothing_is_sent_while_the_alerts_are_off(): void
    {
        Queue::fake();
        $this->alertsOn(['whatsapp.enabled' => false]);

        PublicPostSubmitted::dispatch($this->horsePost());

        Queue::assertNothingPushed();
    }

    public function test_a_post_type_can_be_switched_off_on_its_own(): void
    {
        Queue::fake();
        $this->alertsOn(['whatsapp.alerts' => ['horse_sale_post' => false, 'transfer_post' => true]]);

        PublicPostSubmitted::dispatch($this->horsePost());

        Queue::assertNothingPushed();
    }

    public function test_nothing_is_sent_without_admin_numbers(): void
    {
        Queue::fake();
        $this->alertsOn(['whatsapp.recipients' => []]);

        PublicPostSubmitted::dispatch($this->horsePost());

        Queue::assertNothingPushed();
    }

    // ── The message ──────────────────────────────────────────────────────────

    public function test_the_message_is_in_english_then_arabic_with_both_links(): void
    {
        $this->alertsOn();
        app()->setLocale('ar');

        $message = NewPostAlert::message($this->horsePost());
        [$en, $ar] = explode(BilingualMessage::DIVIDER, $message);

        $this->assertStringContainsString('New post on HRS', $en);
        $this->assertStringContainsString('Horses for sale', $en);
        $this->assertStringContainsString('Desert Wind', $en);
        $this->assertStringContainsString('(negotiable)', $en);
        $this->assertStringContainsString('1,500.000', $en);
        $this->assertStringContainsString('wa.me/96891112222', $en);

        $this->assertStringContainsString('إعلان جديد على', $ar);
        $this->assertStringContainsString('خيول للبيع', $ar);
        $this->assertStringContainsString('ريح الصحراء', $ar);

        $this->assertStringContainsString(route('horses-for-sale.show', 7), $en);
        $this->assertStringContainsString(route('horses-for-sale.show', 7), $ar);
        $this->assertStringContainsString('/admin/horse-sale-posts/7', $message);

        // the viewer's language is put back
        $this->assertSame('ar', app()->getLocale());
    }

    public function test_a_transfer_post_names_its_cities_in_each_language(): void
    {
        $this->alertsOn();

        $post = (new TransferPost)->forceFill([
            'id' => 3, 'type' => 'offer', 'capacity' => 2, 'price' => '40.000',
            'transfer_date' => '2026-10-01', 'contact_number' => '91112222', 'status' => 'active',
        ]);
        $post->setRelation('fromCity', new City(['en_name' => 'Muscat', 'ar_name' => 'مسقط']));
        $post->setRelation('toCity', new City(['en_name' => 'Sohar', 'ar_name' => 'صحار']));

        [$en, $ar] = explode(BilingualMessage::DIVIDER, NewPostAlert::message($post));

        $this->assertStringContainsString('From Muscat to Sohar', $en);
        $this->assertStringContainsString('Offering transport', $en);
        $this->assertStringContainsString('2026-10-01', $en);
        $this->assertStringContainsString('من مسقط إلى صحار', $ar);
        $this->assertStringContainsString(route('transfer-board.show', 3), $en);
    }

    // ── Sending through Evolution API ────────────────────────────────────────

    public function test_it_sends_through_the_chosen_instance_without_the_plugins_brazilian_prefix(): void
    {
        $this->alertsOn();
        WhatsappInstance::create(['name' => 'first-open', 'number' => '96890000001', 'status' => 'open']);
        $chosen = WhatsappInstance::create(['name' => 'hrs-alerts', 'number' => '96890000002', 'status' => 'open']);
        $this->alertsOn(['whatsapp.instance_id' => $chosen->id]);

        Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'MSG-1']], 201)]);

        $log = app(WhatsAppNotifier::class)->send('9123 4567', 'Hello', 'test');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://evo.test/message/sendText/hrs-alerts'
            && $request->header('apikey') === ['test-key']
            // an 11-digit Omani number: the plugin's own formatter would have made it 5596891234567
            && $request['number'] === '96891234567'
            && $request['text'] === 'Hello');

        $this->assertSame('sent', $log->status);
        $this->assertSame('whatsapp', $log->channel);
        $this->assertSame('MSG-1', $log->provider_reference);
    }

    public function test_without_a_chosen_instance_the_first_connected_one_is_used(): void
    {
        $this->alertsOn();
        WhatsappInstance::create(['name' => 'offline', 'number' => '96890000001', 'status' => 'close']);
        WhatsappInstance::create(['name' => 'online', 'number' => '96890000002', 'status' => 'open']);
        Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'x']], 201)]);

        app(WhatsAppNotifier::class)->send('91234567', 'Hi', 'test');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/message/sendText/online'));
    }

    public function test_an_api_error_is_logged_not_thrown(): void
    {
        $this->alertsOn();
        WhatsappInstance::create(['name' => 'online', 'number' => '96890000002', 'status' => 'open']);
        Http::fake(['evo.test/*' => Http::response(['message' => 'instance not connected'], 400)]);

        $log = app(WhatsAppNotifier::class)->send('91234567', 'Hi', 'new_farrier');

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('instance not connected', $log->error);
        $this->assertSame(1, NotificationLog::where('channel', 'whatsapp')->count());
    }

    public function test_nothing_is_called_when_the_api_or_an_instance_is_missing(): void
    {
        $this->alertsOn();
        Http::fake();

        $noInstance = app(WhatsAppNotifier::class)->send('91234567', 'Hi', 'test');

        config(['filament-evolution.api.base_url' => '']);
        app()->forgetInstance(EvolutionClient::class);
        WhatsappInstance::create(['name' => 'online', 'number' => '96890000002', 'status' => 'open']);
        $noApi = app(WhatsAppNotifier::class)->send('91234567', 'Hi', 'test');

        Http::assertNothingSent();
        $this->assertSame('failed', $noInstance->status);
        $this->assertStringContainsString('No WhatsApp instance', $noInstance->error);
        $this->assertStringContainsString('EVOLUTION_URL', $noApi->error);
    }

    public function test_the_queued_job_sends_and_logs(): void
    {
        $this->alertsOn();
        WhatsappInstance::create(['name' => 'online', 'number' => '96890000002', 'status' => 'open']);
        Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'x']], 201)]);

        PublicPostSubmitted::dispatch($this->horsePost());

        // the test queue driver is sync: both admin numbers were messaged
        Http::assertSentCount(2);
        $this->assertSame(2, NotificationLog::where(['channel' => 'whatsapp', 'type' => 'new_horse_sale_post', 'status' => 'sent'])->count());
    }

    // ── General Settings → WhatsApp ──────────────────────────────────────────

    private function admin(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type')->default('text');
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->string('managed_by')->nullable();
            $table->timestamps();
        });
        SiteSetting::clearCache();
    }

    public function test_the_settings_page_shows_the_whatsapp_tab_with_every_post_type_on(): void
    {
        $this->admin();
        $instance = WhatsappInstance::create(['name' => 'hrs-alerts', 'number' => '96890000002', 'status' => 'open']);

        Livewire::test(GeneralSettings::class)
            ->assertSuccessful()
            ->assertSee(__('admin_whatsapp.sections.alerts'))
            ->assertSee('hrs-alerts')
            ->assertFormSet([
                'whatsapp.enabled' => false,
                'whatsapp.alerts.horse_sale_post' => true,
                'whatsapp.alerts.farrier' => true,
            ]);
    }

    public function test_saving_stores_the_numbers_and_switches(): void
    {
        $this->admin();
        $instance = WhatsappInstance::create(['name' => 'hrs-alerts', 'number' => '96890000002', 'status' => 'open']);

        Livewire::test(GeneralSettings::class)
            ->fillForm([
                'site_name_ar' => 'اتش ار اس',
                'whatsapp.enabled' => true,
                'whatsapp.instance_id' => $instance->id,
                'whatsapp.recipients' => [['name' => 'Majid', 'phone' => '9123 4567']],
                'whatsapp.alerts.tool_sale_post' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        SiteSetting::resetMemo();
        $this->assertTrue(WhatsAppSettings::enabled());
        $this->assertSame($instance->id, WhatsAppSettings::instanceId());
        $this->assertSame(['96891234567'], WhatsAppSettings::recipients());
        $this->assertFalse(WhatsAppSettings::alertEnabled('tool_sale_post'));
        $this->assertTrue(WhatsAppSettings::alertEnabled('horse_sale_post'));
    }

    public function test_a_number_that_is_too_short_is_refused(): void
    {
        $this->admin();

        Livewire::test(GeneralSettings::class)
            ->fillForm(['site_name_ar' => 'اتش ار اس', 'whatsapp.recipients' => [['name' => 'x', 'phone' => '123']]])
            ->call('save')
            ->assertHasFormErrors()
            ->assertHasNoFormErrors(['site_name_ar']);

        $this->assertNull(SiteSetting::query()->where('key', 'whatsapp.recipients')->value('value'));
    }

    public function test_the_test_button_sends_a_text_and_logs_it(): void
    {
        $this->admin();
        WhatsappInstance::create(['name' => 'hrs-alerts', 'number' => '96890000002', 'status' => 'open']);
        Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'x']], 201)]);

        Livewire::test(GeneralSettings::class)
            ->callAction(TestAction::make('sendWhatsAppTest')->schemaComponent('whatsappTestActions'), ['phone' => '91234567'])
            ->assertNotified(__('admin_whatsapp.notifications.test_sent'));

        Http::assertSent(fn (Request $request) => $request['number'] === '96891234567');
        $this->assertSame(1, NotificationLog::where(['channel' => 'whatsapp', 'type' => 'test', 'status' => 'sent'])->count());
    }
}
