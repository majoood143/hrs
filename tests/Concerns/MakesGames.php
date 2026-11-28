<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\GameCampaign;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/** The customer site plus the games tables (real migrations), and a live campaign on the Al Rahba Sprint. */
trait MakesGames
{
    use PreparesCustomerSite;

    protected Carbon $opensAt;

    protected function prepareGames(): void
    {
        $this->prepareCustomerSite();
        (require database_path('migrations/2026_11_04_000001_create_mobile_devices_table.php'))->up();
        (require database_path('migrations/2026_11_07_000001_add_enabled_to_mobile_devices.php'))->up();
        (require database_path('migrations/2026_11_28_000002_track_uninstalled_mobile_devices.php'))->up();
        (require database_path('migrations/2026_11_28_000001_create_game_campaigns_tables.php'))->up();
        // a prize with a discount makes a promo code (which may name a stable)
        (require database_path('migrations/2026_09_08_010100_create_stables_table.php'))->up();
        (require database_path('migrations/2026_11_29_000001_create_promo_codes_table.php'))->up();
        (require database_path('migrations/2026_11_29_000002_add_real_races_to_games.php'))->up();

        $this->opensAt = Carbon::parse('2027-03-01 06:00:00'); // 10:00 in Muscat
        $this->travelTo($this->opensAt->copy()->addHour());
    }

    /** @param  array<string, mixed>  $attributes */
    protected function campaign(array $attributes = []): GameCampaign
    {
        return GameCampaign::query()->create($attributes + [
            'slug' => 'ramadan-race',
            'en_name' => 'Ramadan Race Challenge',
            'ar_name' => 'تحدي سباق رمضان',
            'status' => 'published',
            'starts_at' => $this->opensAt,
            'ends_at' => $this->opensAt->copy()->addDays(10),
            'race_id' => 'rahba-sprint',
            'difficulty' => 'normal',
            'attempts_per_day' => 3,
        ]);
    }

    protected function player(string $phone = '96891234567', string $name = 'Salim Al Harthy'): Customer
    {
        return $this->makeCustomer($phone, ['name' => $name]);
    }

    /** A believable finish of the 1000 m sprint (about 66 s; the best a player ever rode it is 65.7 s). */
    protected function finishData(array $overrides = []): array
    {
        return $overrides + ['position' => 1, 'field_size' => 11, 'race_time_ms' => 66_400, 'top_speed_kmh' => 68.2];
    }

    /** Start a race as $customer, let $seconds of real time pass, then send the finish. */
    protected function race(Customer $customer, GameCampaign $campaign, array $finish = [], int $seconds = 75): TestResponse
    {
        $uuid = $this->actingAs($customer, 'customer')->postJson(route('games.start', $campaign->slug), ['horse_style' => 'paceSetter'])->assertOk()->json('attempt');
        $this->travel($seconds)->seconds();

        return $this->actingAs($customer, 'customer')->postJson(route('games.finish', [$campaign->slug, $uuid]), $this->finishData($finish));
    }
}
