<?php

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Filament\Resources\StableResource\Pages\ViewStable;
use App\Jobs\SendStableEmail;
use App\Jobs\SendWhatsAppMessage;
use App\Mail\StableSalesReportMail;
use App\Models\BookingSlot;
use App\Models\NotificationLog;
use App\Models\Stable;
use App\Services\Payments\OrderPaymentService;
use App\Services\Stables\CreateStableBooking;
use App\Services\Stables\SlotGenerator;
use App\Services\WhatsApp\BilingualMessage;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/** "Now" is Sunday 2026-10-04, 09:30 in Muscat; one rider paid through us: stable share 14.250 (see StableStatementTest). */
class StableSalesReportTest extends TestCase
{
    use PreparesStableBookings;

    private Stable $stable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', 'vat.enabled' => true, 'vat.rate' => '5', 'whatsapp.enabled' => true]);
        $this->withoutVite();
        Queue::fake();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->stable = $this->makeStable($this->makeOwner());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function report(array $settings): void
    {
        $this->stable->forceFill(['sales_report' => ['enabled' => true, 'channels' => ['email'], 'frequency' => 'daily', 'time' => '08:00', ...$settings]])->save();
    }

    private function runReports(): void
    {
        $this->artisan('stables:send-sales-reports')->assertSuccessful();
    }

    public function test_an_admin_sets_the_report_on_the_stable_page(): void
    {
        $this->actAsAdmin();
        $page = fn () => Livewire::test(ViewStable::class, ['record' => $this->stable->slug]);
        $saved = ['enabled' => true, 'channels' => ['email', 'whatsapp'], 'frequency' => 'weekly', 'weekday' => 'mon', 'time' => '07:00'];

        $page()->callAction('stableSalesReport', data: $saved)->assertHasNoActionErrors();
        $this->assertSame($saved, $this->stable->refresh()->sales_report);

        // switched on it needs a channel; switched off it keeps the rest for next time
        $page()->callAction('stableSalesReport', data: [...$saved, 'channels' => []])->assertHasActionErrors(['channels']);
        $page()->callAction('stableSalesReport', data: ['enabled' => false])->assertHasNoActionErrors();
        $this->assertSame([...$saved, 'enabled' => false], $this->stable->refresh()->sales_report);
    }

    public function test_a_stable_without_an_owner_has_no_report_to_set(): void
    {
        $this->stable->owners()->detach();

        $this->actAsAdmin();

        Livewire::test(ViewStable::class, ['record' => $this->stable->slug])->assertActionHidden('stableSalesReport');
    }

    private function actAsAdmin(): void
    {
        Gate::before(fn () => true);
        $this->actingAs($this->makeAdminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_switched_off_nothing_goes_out(): void
    {
        $this->report(['enabled' => false]);
        $this->runReports();

        Queue::assertNothingPushed();
    }

    public function test_daily_goes_once_a_day_after_its_time(): void
    {
        $this->report(['time' => '10:00']);
        $this->runReports();
        Queue::assertNotPushed(SendStableEmail::class);

        Carbon::setTestNow('2026-10-04 06:00:00');
        $this->runReports();
        $this->runReports();
        Queue::assertPushed(SendStableEmail::class, 1);
        $this->assertSame('2026-10-04', $this->stable->refresh()->sales_report_sent_on);

        Carbon::setTestNow('2026-10-05 06:00:00');
        $this->runReports();
        Queue::assertPushed(SendStableEmail::class, 2);
    }

    public function test_weekly_goes_only_on_its_weekday(): void
    {
        $this->report(['frequency' => 'weekly', 'weekday' => 'mon']);
        $this->runReports();
        Queue::assertNothingPushed();

        Carbon::setTestNow('2026-10-05 05:30:00');
        $this->runReports();
        Queue::assertPushed(SendStableEmail::class, fn (SendStableEmail $job) => $job->mail->period === '2026-09-28 – 2026-10-04');
    }

    public function test_a_stable_switched_off_gets_none(): void
    {
        $this->report([]);
        $this->stable->forceFill(['is_active' => false])->save();
        $this->runReports();

        Queue::assertNothingPushed();
    }

    public function test_each_channel_carries_the_periods_figures(): void
    {
        $offering = $this->makeOffering($this->stable, ['max_riders' => 3]);
        $this->makeFee('percentage', 5.0);
        app(SlotGenerator::class)->sync($this->makeSchedule($offering, [2], ['10:00'], ['capacity' => 20]));
        $slot = BookingSlot::query()->where('date', '2026-10-06')->sole();
        $booking = app(CreateStableBooking::class)->handle($slot, [['name' => 'Omar']], ['name' => 'Aisha', 'phone' => '91234567'], 'online');
        app(OrderPaymentService::class)->applyPaid($booking->order, PaymentGateway::Demo, 'D-1');

        $this->report(['channels' => ['email', 'sms', 'whatsapp']]);

        // the day before the payment: nothing sold
        $this->runReports();
        Queue::assertPushed(SendStableEmail::class, fn (SendStableEmail $job) => $job->mail->totals['bookings'] === 0);

        // the day after it: the sale, in all three
        Carbon::setTestNow('2026-10-05 05:30:00');
        $this->runReports();

        Queue::assertPushed(SendStableEmail::class, function (SendStableEmail $job) {
            return $job->mail instanceof StableSalesReportMail
                && $job->to === 'owner@example.com'
                && $job->mail->period === '2026-10-04'
                && $job->mail->totals['bookings'] === 1
                && $job->mail->totals['stable_share'] === 14250
                && $job->mail->balance === 14250;
        });

        Queue::assertPushed(SendWhatsAppMessage::class, function (SendWhatsAppMessage $job) {
            [$en, $ar] = explode(BilingualMessage::DIVIDER, $job->message);

            return str_contains($en, 'Sales report') && str_contains($en, '14.250')
                && str_contains($ar, 'تقرير مبيعات') && str_contains($ar, '14.250');
        });

        $this->assertTrue(NotificationLog::query()->where('channel', 'sms')->where('type', 'stable_sales_report')->where('message', 'like', '%14.250%')->exists());
    }

    public function test_the_email_renders_in_the_owners_language(): void
    {
        $mail = new StableSalesReportMail($this->stable, '2026-10-04', ['bookings' => 2, 'collected' => 33076, 'refunded' => 0, 'commission' => 3000, 'stable_share' => 28500], 28500, 'ar');

        $html = $mail->render();

        $this->assertStringContainsString('تقرير مبيعات', $html);
        $this->assertStringContainsString('28.500', $html);
    }
}
