<?php

namespace Tests\Feature;

use App\Enums\StableBookingStatus;
use App\Filament\Stable\Pages\CheckIn;
use App\Mail\StableBookingMail;
use App\Models\BookingSlot;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StableOffering;
use App\Models\User;
use App\Services\Stables\BookingTicketPdf;
use App\Services\Stables\SlotGenerator;
use App\Services\WhatsApp\BilingualMessage;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;
use WallaceMartinss\FilamentEvolution\Models\WhatsappInstance;
use WallaceMartinss\FilamentEvolution\Services\EvolutionClient;

/**
 * A confirmed booking's ticket: the PDF with its check-in QR code (email attachment, WhatsApp file,
 * signed download), the page the code opens, and checking the customer in from it (stable panel
 * and the app's owner API). "Now" is Sunday 2026-10-04 in Muscat; the session is Tuesday 10:00.
 */
class StableBookingTicketTest extends TestCase
{
    use PreparesStableBookings;

    private User $owner;

    private Stable $stable;

    private StableOffering $offering;

    private BookingSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', 'whatsapp.enabled' => true]);
        (require database_path('migrations/2026_09_11_145142_create_personal_access_tokens_table.php'))->up();
        foreach (['2026_09_26_140451_create_whatsapp_instances_table', '2026_09_26_140452_create_whatsapp_messages_table'] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }
        config(['filament-evolution.api.base_url' => 'https://evo.test', 'filament-evolution.api.api_key' => 'test-key']);
        app()->forgetInstance(EvolutionClient::class);
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->owner = $this->makeOwner();
        $this->stable = $this->makeStable($this->owner, ['slug' => 'desert-riders']);
        $this->stable->forceFill(['booking_settings' => ['payment_options' => ['at_stable']]])->save();
        $this->offering = $this->makeOffering($this->stable);
        app(SlotGenerator::class)->sync($this->makeSchedule($this->offering, [2], ['10:00']));
        $this->slot = BookingSlot::query()->where('date', '2026-10-06')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    /** A customer books from the app, paying at the stable (confirmed at once); returns [reference, token]. */
    private function customerBooks(string $language = 'en'): array
    {
        $this->app['auth']->forgetGuards();
        $code = $this->postJson('/api/v1/auth/request', ['phone' => '92223333', 'channel' => 'sms'])->json('demo_code');
        $token = $this->postJson('/api/v1/auth/verify', ['phone' => '92223333', 'code' => $code])->json('token');
        $reference = $this->as($token)->withHeaders(['Accept-Language' => $language])->postJson("/api/v1/bookings/slots/{$this->slot->id}", [
            'name' => 'Aisha',
            'email' => 'aisha@example.com',
            'payment_option' => 'at_stable',
            'riders' => [['name' => 'Rider 1', 'age' => 12, 'level' => 'beginner']],
        ])->assertCreated()->json('data.reference');

        return [$reference, $token];
    }

    private function booking(string $reference): StableBooking
    {
        return StableBooking::query()->where('reference', $reference)->sole();
    }

    public function test_the_ticket_is_a_pdf_with_the_check_in_code_in_both_languages(): void
    {
        [$reference] = $this->customerBooks('ar');
        $tickets = app(BookingTicketPdf::class);
        $booking = $this->booking($reference);

        $this->assertSame('ar', $tickets->locale($booking));
        $html = $tickets->html($booking);
        $this->assertStringContainsString('code="'.url('/check-in/'.$reference).'"', $html);
        $this->assertStringContainsString('تذكرة الحجز', $html);
        $this->assertStringContainsString('Booking ticket', $tickets->html($booking, 'en'));

        foreach (['en', 'ar'] as $locale) {
            $this->assertStringStartsWith('%PDF', $tickets->render($booking, $locale));
        }
    }

    public function test_the_download_link_is_signed_and_only_for_confirmed_bookings(): void
    {
        [$reference, $token] = $this->customerBooks();
        $url = $this->as($token)->getJson("/api/v1/account/bookings/{$reference}")->assertOk()->json('data.ticket_url');

        $this->assertNotNull($url);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/bookings/ticket/'.$reference)->assertForbidden();

        $this->booking($reference)->forceFill(['status' => StableBookingStatus::Cancelled])->save();
        $this->get($url)->assertNotFound();
        $this->as($token)->getJson("/api/v1/account/bookings/{$reference}")->assertJsonPath('data.ticket_url', null);
    }

    public function test_the_confirmation_email_carries_the_ticket_and_the_stables_does_not(): void
    {
        Mail::fake();
        [$reference] = $this->customerBooks();

        Mail::assertSent(StableBookingMail::class, fn (StableBookingMail $mail) => $mail->hasTo('aisha@example.com') && count($mail->attachments()) === 1
            && $mail->attachments()[0]->as === "ticket-{$reference}.pdf");
        Mail::assertSent(StableBookingMail::class, fn (StableBookingMail $mail) => $mail->audience === 'stable' && $mail->attachments() === []);
    }

    public function test_whatsapp_sends_the_ticket_as_a_file_with_a_bilingual_caption(): void
    {
        WhatsappInstance::create(['name' => 'hrs', 'number' => '96890000002', 'status' => 'open']);
        Http::fake(['evo.test/*' => Http::response(['key' => ['id' => 'MSG-1']], 201)]);

        [$reference] = $this->customerBooks();

        Http::assertSent(function (Request $request) use ($reference) {
            if ($request->url() !== 'https://evo.test/message/sendMedia/hrs') {
                return false;
            }

            [$en, $ar] = explode(BilingualMessage::DIVIDER, $request['caption']);

            return $request['number'] === '96892223333'
                && $request['mediatype'] === 'document'
                && $request['fileName'] === "ticket-{$reference}.pdf"
                && str_starts_with(base64_decode($request['media']), '%PDF')
                && str_contains($en, 'Your ticket is attached') && str_contains($ar, 'التذكرة مرفقة');
        });
        $this->assertSame('sent', DB::table('notification_logs')->where('channel', 'whatsapp')->where('type', 'booking_confirmed')->value('status'));
        $this->assertSame($this->booking($reference)->service_order_id, DB::table('notification_logs')->where('channel', 'whatsapp')->where('type', 'booking_confirmed')->value('service_order_id'));
    }

    public function test_the_whatsapp_ticket_can_be_switched_off(): void
    {
        $this->stableSiteSettings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', 'whatsapp.enabled' => true, 'whatsapp.alerts' => ['booking_ticket' => false]]);
        Http::fake();

        $this->customerBooks();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'sendMedia'));
    }

    public function test_the_code_opens_the_check_in_for_the_stables_staff_and_a_plain_page_for_others(): void
    {
        [$reference] = $this->customerBooks();

        $this->get('/check-in/'.$reference)->assertOk()->assertSee($reference)->assertSee($this->stable->name)->assertDontSee('Aisha');
        $this->get('/check-in/BK-NOTHERE')->assertNotFound();

        $this->app['auth']->shouldUse('web');
        $this->actingAs($this->owner, 'web')->get('/check-in/'.strtolower($reference))
            ->assertRedirect(CheckIn::getUrl(['reference' => $reference], panel: 'stable', tenant: $this->stable));
    }

    public function test_the_stable_checks_the_customer_in_on_the_day(): void
    {
        [$reference] = $this->customerBooks();
        $this->actingAs($this->owner, 'web');
        $this->app['auth']->shouldUse('web');
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        Filament::setTenant($this->stable);

        // two days early: shown, not offered
        Livewire::test(CheckIn::class, ['reference' => $reference])
            ->assertSee('it can be checked in from that day')
            ->assertDontSee('Collect');

        Carbon::setTestNow('2026-10-06 05:50:00');

        Livewire::test(CheckIn::class)
            ->set('data.reference', url('/check-in/'.$reference))
            ->call('find')
            ->assertSet('reference', $reference)
            ->assertSee('Ready to check in')
            ->assertSee('Collect')
            ->call('checkIn')
            ->assertSee('Checked in: enjoy the session!');

        $this->assertSame(StableBookingStatus::Completed, $this->booking($reference)->status);

        Livewire::test(CheckIn::class, ['reference' => $reference])->assertSee('Already checked in');
        Livewire::test(CheckIn::class, ['reference' => 'BK-NOTHERE'])->assertSee('No booking BK-NOTHERE at this stable.');
    }

    public function test_another_of_the_owners_stables_is_offered_to_switch_to(): void
    {
        $other = $this->makeStable($this->owner, ['slug' => 'sea-riders', 'en_name' => 'Sea Riders']);
        [$reference] = $this->customerBooks();
        $this->actingAs($this->owner, 'web');
        $this->app['auth']->shouldUse('web');
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        Filament::setTenant($other);

        Livewire::test(CheckIn::class, ['reference' => $reference])->assertSee('Check in at '.$this->stable->name);
    }

    public function test_the_app_finds_a_scanned_ticket_among_the_owners_stables(): void
    {
        [$reference] = $this->customerBooks();
        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/v1/owner/auth/login', ['email' => 'owner@example.com', 'password' => 'secret-password'])->assertOk()->json('token');

        $this->as($token)->getJson('/api/v1/owner/check-in/'.$reference)
            ->assertOk()
            ->assertJsonPath('stable.slug', 'desert-riders')
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonPath('data.can_mark_attended', false);
        $this->as($token)->getJson('/api/v1/owner/check-in/BK-NOTHERE')->assertNotFound();

        $stranger = $this->makeOwner('other@example.com', '96899990000');
        $this->makeStable($stranger, ['slug' => 'elsewhere']);
        $this->app['auth']->forgetGuards();
        $theirs = $this->postJson('/api/v1/owner/auth/login', ['email' => 'other@example.com', 'password' => 'secret-password'])->assertOk()->json('token');
        $this->as($theirs)->getJson('/api/v1/owner/check-in/'.$reference)->assertNotFound();
    }
}
