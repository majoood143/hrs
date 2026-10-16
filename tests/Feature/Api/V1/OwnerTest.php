<?php

namespace Tests\Feature\Api\V1;

use App\Enums\StableBookingStatus;
use App\Models\BookingSlot;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StableOffering;
use App\Models\User;
use App\Services\Stables\SlotGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * The app's "My stable" for stable owners: sign-in (phone code or email + password) with a token
 * that opens the owner endpoints only, today's dashboard, the bookings and their actions, closing
 * times and days, and the income. "Now" is Sunday 2026-10-04 09:30 in Muscat; the stable opens
 * Tuesdays at 10:00 (4 places, 15.000 a rider); a 5% fee.
 */
class OwnerTest extends TestCase
{
    use PreparesStableBookings;

    private User $owner;

    private Stable $stable;

    private StableOffering $offering;

    private BookingSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo']);
        (require database_path('migrations/2026_09_11_145142_create_personal_access_tokens_table.php'))->up();
        Carbon::setTestNow('2026-10-04 05:30:00');

        $this->owner = $this->makeOwner();
        $this->stable = $this->makeStable($this->owner, ['slug' => 'desert-riders']);
        $this->offering = $this->makeOffering($this->stable, ['max_riders' => 3]);
        $this->makeFee('percentage', 5.0);
        app(SlotGenerator::class)->sync($this->makeSchedule($this->offering, [2], ['10:00']));
        $this->slot = BookingSlot::query()->where('date', '2026-10-06')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** The next request with this token; guards remember their user between requests in a test. */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function ownerToken(): string
    {
        return $this->postJson('/api/v1/owner/auth/login', ['email' => 'owner@example.com', 'password' => 'secret-password'])->assertOk()->json('token');
    }

    private function customerBooks(string $phone = '92223333', int $riders = 1): string
    {
        $this->stable->forceFill(['booking_settings' => ['payment_options' => ['at_stable']]])->save();
        $this->app['auth']->forgetGuards();
        $code = $this->postJson('/api/v1/auth/request', ['phone' => $phone, 'channel' => 'sms'])->json('demo_code');
        $token = $this->postJson('/api/v1/auth/verify', ['phone' => $phone, 'code' => $code])->json('token');

        return $this->as($token)->postJson("/api/v1/bookings/slots/{$this->slot->id}", [
            'name' => 'Aisha',
            'payment_option' => 'at_stable',
            'riders' => array_map(fn (int $i) => ['name' => "Rider {$i}", 'age' => 12, 'level' => 'beginner'], range(1, $riders)),
        ])->assertCreated()->json('data.reference');
    }

    // ── sign-in ──────────────────────────────────────────────────────────────

    public function test_an_owner_signs_in_with_a_phone_code(): void
    {
        $code = $this->postJson('/api/v1/owner/auth/request', ['phone' => '91234567', 'channel' => 'sms'])->assertOk()->json('demo_code');

        $this->postJson('/api/v1/owner/auth/verify', ['phone' => '91234567', 'code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');

        $answer = $this->postJson('/api/v1/owner/auth/verify', ['phone' => '91234567', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('owner.email', 'owner@example.com')
            ->assertJsonPath('owner.stables.0.slug', 'desert-riders')
            ->assertJsonPath('owner.stables.0.role', 'owner')
            ->assertJsonPath('owner.stables.0.accepts_bookings', true);

        $this->as($answer->json('token'))->getJson('/api/v1/owner/me')->assertOk()->assertJsonPath('data.stables.0.approval.value', 'approved');
        $this->as($answer->json('token'))->postJson('/api/v1/owner/auth/logout')->assertOk();
        $this->as($answer->json('token'))->getJson('/api/v1/owner/me')->assertUnauthorized();
    }

    public function test_a_phone_without_a_stable_account_is_told_so(): void
    {
        $code = $this->postJson('/api/v1/owner/auth/request', ['phone' => '95556666', 'channel' => 'sms'])->json('demo_code');

        $this->postJson('/api/v1/owner/auth/verify', ['phone' => '95556666', 'code' => $code])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_an_owner_signs_in_with_email_and_password_and_wrong_tries_are_limited(): void
    {
        $this->ownerToken();

        foreach (range(1, 5) as $try) {
            $this->postJson('/api/v1/owner/auth/login', ['email' => 'owner@example.com', 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        $this->postJson('/api/v1/owner/auth/login', ['email' => 'owner@example.com', 'password' => 'secret-password'])->assertStatus(429);

        // only stable owners: an admin's own password is refused here
        $this->makeAdminUser();
        $this->postJson('/api/v1/owner/auth/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])->assertUnprocessable();
    }

    public function test_an_owner_token_opens_the_owner_endpoints_only(): void
    {
        Route::middleware('auth:sanctum')->get('/api/test-admin-only', fn () => 'admin');
        $token = $this->ownerToken();

        $this->as($token)->getJson('/api/test-admin-only')->assertUnauthorized();
        $this->as($token)->getJson('/api/v1/account')->assertUnauthorized();
        $this->as($token)->getJson('/api/v1/owner/stables/desert-riders')->assertOk();

        // a customer's token and an admin's token are not an owner's
        $this->app['auth']->forgetGuards();
        $code = $this->postJson('/api/v1/auth/request', ['phone' => '94445555', 'channel' => 'sms'])->json('demo_code');
        $customer = $this->postJson('/api/v1/auth/verify', ['phone' => '94445555', 'code' => $code])->json('token');
        $this->as($customer)->getJson('/api/v1/owner/me')->assertUnauthorized();

        $admin = $this->makeAdminUser();
        $this->as($admin->createToken('x')->plainTextToken)->getJson('/api/v1/owner/me')->assertForbidden()->assertJsonPath('code', 'not_stable_owner');
    }

    public function test_another_owners_stable_is_not_found(): void
    {
        $other = $this->makeStable($this->makeOwner('other@example.com', '96899990000'), ['slug' => 'other-stable']);
        $token = $this->ownerToken();

        $this->as($token)->getJson("/api/v1/owner/stables/{$other->slug}")->assertNotFound();
        $this->as($token)->getJson("/api/v1/owner/stables/{$other->slug}/bookings")->assertNotFound();
    }

    // ── the day, the bookings ────────────────────────────────────────────────

    public function test_the_dashboard_shows_today_and_what_needs_marking(): void
    {
        $reference = $this->customerBooks(riders: 2);
        Carbon::setTestNow('2026-10-06 07:30:00'); // Tuesday 11:30 in Muscat: the 10:00 session took place

        $this->as($this->ownerToken())->getJson('/api/v1/owner/stables/desert-riders')
            ->assertOk()
            ->assertJsonPath('today', '2026-10-06')
            ->assertJsonPath('today_bookings.0.reference', $reference)
            ->assertJsonPath('today_bookings.0.customer.phone', '96892223333')
            ->assertJsonPath('today_bookings.0.riders_data.1.name', 'Rider 2')
            ->assertJsonPath('today_bookings.0.can_mark_attended', true)
            ->assertJsonPath('today_riders', 2)
            ->assertJsonPath('to_mark', 1)
            ->assertJsonPath('unmarked_at_stable', 0)
            ->assertJsonPath('balance.direction', 'settled');
    }

    public function test_bookings_are_listed_marked_and_called_off(): void
    {
        $reference = $this->customerBooks();
        $second = $this->customerBooks('93334444');
        $token = $this->ownerToken();
        $base = '/api/v1/owner/stables/desert-riders/bookings';

        $this->as($token)->getJson($base)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.can_mark_attended', false);
        $this->as($token)->getJson("{$base}?date=2026-10-06")->assertOk()->assertJsonCount(2, 'data');
        $this->as($token)->getJson("{$base}?q=93334444")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', $second);

        // not before the session's day
        $this->as($token)->postJson("{$base}/{$reference}/attended")->assertStatus(409)->assertJsonPath('code', 'booking_unavailable');

        $this->as($token)->postJson("{$base}/{$second}/cancel", [])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->as($token)->postJson("{$base}/{$second}/cancel", ['reason' => 'The arena is flooded'])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled')
            ->assertJsonPath('data.cancelled_by', 'stable')
            ->assertJsonPath('data.can_cancel', false);
        $this->as($token)->postJson("{$base}/{$second}/cancel", ['reason' => 'Again'])->assertStatus(409);

        Carbon::setTestNow('2026-10-06 07:30:00');
        $this->as($token)->getJson("{$base}?when=to_mark")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', $reference);

        $this->as($token)->postJson("{$base}/{$reference}/no-show")->assertOk()->assertJsonPath('data.status.value', 'no_show');
        $this->as($token)->postJson("{$base}/{$reference}/attended")->assertOk()->assertJsonPath('data.status.value', 'completed');
        $this->assertSame(StableBookingStatus::Completed, StableBooking::query()->where('reference', $reference)->value('status'));
        $this->as($token)->getJson("{$base}?when=to_mark")->assertOk()->assertJsonCount(0, 'data');
        $this->as($token)->getJson("{$base}?when=past")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_list_of_bookings_reads_the_places_left_with_its_slots(): void
    {
        $this->customerBooks();
        $this->customerBooks('93334444');
        $token = $this->ownerToken();

        DB::enableQueryLog();
        $this->as($token)->getJson('/api/v1/owner/stables/desert-riders/bookings')->assertOk()->assertJsonPath('data.0.slot.places_left', 2);
        $perBooking = collect(DB::getQueryLog())->filter(fn (array $q) => str_starts_with($q['query'], 'select sum('));

        $this->assertCount(0, $perBooking, 'one places-left query per booking');
    }

    public function test_income_counts_an_attended_pay_at_the_stable_booking(): void
    {
        $reference = $this->customerBooks();
        $token = $this->ownerToken();
        Carbon::setTestNow('2026-10-07 07:30:00'); // the day after the session, still unmarked

        $this->as($token)->getJson('/api/v1/owner/stables/desert-riders/income')->assertOk()->assertJsonPath('totals.bookings', 0)->assertJsonPath('unmarked_at_stable', 1);

        $share = $this->as($token)->postJson("/api/v1/owner/stables/desert-riders/bookings/{$reference}/attended")->assertOk()->json('data.stable_share.amount');

        $this->as($token)->getJson('/api/v1/owner/stables/desert-riders/income?period=this_month')
            ->assertOk()
            ->assertJsonPath('period.from', '2026-10-01')
            ->assertJsonPath('totals.bookings', 1)
            ->assertJsonPath('totals.stable_share.amount', $share)
            ->assertJsonPath('rows.0.reference', $reference)
            ->assertJsonPath('rows.0.kind.value', 'at_stable')
            ->assertJsonPath('balance.direction', 'you_owe')
            ->assertJsonPath('unmarked_at_stable', 0);

        $this->as($token)->getJson('/api/v1/owner/stables/desert-riders/income?period=forever')->assertUnprocessable();
    }

    // ── times and days off ───────────────────────────────────────────────────

    public function test_a_time_is_closed_and_reopened(): void
    {
        $token = $this->ownerToken();
        $base = '/api/v1/owner/stables/desert-riders/slots';

        $this->as($token)->getJson("{$base}?date=2026-10-06")
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->slot->id)
            ->assertJsonPath('data.0.places_left', 4)
            ->assertJsonPath('days.2.date', '2026-10-06')
            ->assertJsonPath('days.2.open', 1)
            ->assertJsonPath('offerings.0.name', 'Beginner lesson')
            ->assertJsonCount(14, 'days');

        $this->as($token)->postJson("{$base}/{$this->slot->id}/close")->assertOk()->assertJsonPath('data.is_open', false);
        $this->assertFalse($this->slot->refresh()->is_open);
        $this->as($token)->postJson("{$base}/{$this->slot->id}/open")->assertOk()->assertJsonPath('data.is_open', true);

        Carbon::setTestNow('2026-10-06 07:30:00');
        $this->as($token)->postJson("{$base}/{$this->slot->id}/close")->assertStatus(409)->assertJsonPath('code', 'slot_started');
    }

    public function test_closed_days_take_down_free_times_and_keep_booked_ones(): void
    {
        $next = BookingSlot::query()->where('date', '2026-10-13')->sole();
        $this->customerBooks();
        $token = $this->ownerToken();
        $base = '/api/v1/owner/stables/desert-riders/closures';

        $this->as($token)->postJson($base, ['starts_on' => '2026-10-01', 'ends_on' => '2026-10-02'])->assertUnprocessable()->assertJsonValidationErrors('starts_on');

        $id = $this->as($token)->postJson($base, ['starts_on' => '2026-10-05', 'ends_on' => '2026-10-13', 'reason' => 'Rain'])
            ->assertCreated()
            ->assertJsonPath('removed', 1)
            ->assertJsonPath('kept', 1)
            ->assertJsonPath('data.reason', 'Rain')
            ->json('data.id');

        $this->assertNull(BookingSlot::query()->find($next->id));
        $this->assertFalse($this->slot->refresh()->is_open);
        $this->as($token)->getJson($base)->assertOk()->assertJsonPath('data.0.id', $id);

        $this->as($token)->deleteJson("{$base}/{$id}")->assertOk();
        $this->assertTrue(BookingSlot::query()->where('date', '2026-10-13')->exists());
        $this->as($token)->getJson($base)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_changes_are_refused_while_the_feature_is_off(): void
    {
        $token = $this->ownerToken();
        $this->seedSiteSettings(['enabled_gateways' => ['demo'], 'sms.driver' => 'demo', 'mobile.features' => ['stable_owner' => ['enabled' => false]]]);

        $this->as($token)->postJson("/api/v1/owner/stables/desert-riders/slots/{$this->slot->id}/close")->assertForbidden()->assertJsonPath('code', 'feature_disabled');
        $this->postJson('/api/v1/owner/auth/login', ['email' => 'owner@example.com', 'password' => 'secret-password'])->assertForbidden();
        $this->as($token)->getJson('/api/v1/owner/stables/desert-riders')->assertOk();
    }
}
