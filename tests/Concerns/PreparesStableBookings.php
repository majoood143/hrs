<?php

namespace Tests\Concerns;

use App\Enums\FeeType;
use App\Enums\StableApprovalStatus;
use App\Models\BookingSlot;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Stable;
use App\Models\StableBooking;
use App\Models\StableOffering;
use App\Models\StableSchedule;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stable booking tables from their real (portable) migrations, on top of hand-made location,
 * users and the portable permission, customer (one-time codes) and notification log tables.
 */
trait PreparesStableBookings
{
    use PreparesOrderSite;

    protected function prepareStableBookings(array $settings = []): void
    {
        // the order tables too: a booking's money is a service order
        $this->prepareOrderSite($settings);

        // Seo::forSlug() looks for a CMS page with the section's slug
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('status');
            $table->timestamp('published_at')->nullable();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('en_name');
            $table->string('ar_name');
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id');
            $table->string('en_name');
            $table->string('ar_name');
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id');
            $table->string('en_name');
            $table->string('ar_name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->string('type')->default('owner');
            $table->string('phone')->nullable();
            $table->string('civiled_id')->nullable();
            $table->string('cr_number')->nullable();
            $table->string('postal_code')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        foreach ([
            '2025_08_24_201524_create_permission_tables',
            '2026_09_08_010000_create_stable_services_table',
            '2026_09_08_010100_create_stables_table',
            '2026_09_08_010200_create_stable_stable_service_table',
            '2026_09_23_000001_create_customers_tables',
            '2026_09_30_100001_add_stable_owners',
            '2026_09_30_100002_create_stable_booking_tables',
            '2026_09_30_100003_add_stable_booking_checkout',
            '2026_09_30_100005_create_stable_settlements_table',
            '2026_09_30_100006_create_stable_packages_tables',
            '2026_09_30_100007_create_stable_reviews_table',
            '2026_09_30_100008_add_reminded_at_to_stable_bookings',
        ] as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }
    }

    /** Site settings without a site_settings table (SiteSetting reads one cached collection). */
    protected function stableSiteSettings(array $settings): void
    {
        $this->seedSiteSettings($settings);
    }

    /** @return array{0: Country, 1: Region, 2: City} */
    protected function makeLocation(): array
    {
        $country = Country::query()->forceCreate(['en_name' => 'Oman', 'ar_name' => 'عُمان', 'is_public' => true]);
        $region = Region::query()->forceCreate(['country_id' => $country->id, 'en_name' => 'Muscat', 'ar_name' => 'مسقط']);
        $city = City::query()->forceCreate(['region_id' => $region->id, 'en_name' => 'Seeb', 'ar_name' => 'السيب']);

        return [$country, $region, $city];
    }

    protected function makeOwner(string $email = 'owner@example.com', string $phone = '96891234567'): User
    {
        $user = User::create([
            'name' => 'Salim Al Hinai',
            'email' => $email,
            'password' => 'secret-password',
            'phone' => $phone,
            'type' => User::TYPE_STABLE_OWNER,
            'locale' => 'en',
        ]);
        $user->forceFill(['phone_verified_at' => now()])->save();

        return $user;
    }

    protected function makeAdminUser(string $email = 'admin@example.com'): User
    {
        return User::create(['name' => 'Admin', 'email' => $email, 'password' => 'secret-password', 'type' => 'admin']);
    }

    protected function makeStable(?User $owner = null, array $attributes = [], StableApprovalStatus $status = StableApprovalStatus::Approved): Stable
    {
        $city = City::query()->first() ?? $this->makeLocation()[2];
        $region = $city->region;

        $stable = new Stable($attributes + [
            'en_name' => 'Desert Riders',
            'ar_name' => 'فرسان الصحراء',
            'country_id' => $region->country_id,
            'region_id' => $region->id,
            'city_id' => $city->id,
            'is_active' => true,
        ]);
        $stable->slug = $attributes['slug'] ?? 'desert-riders-'.(Stable::query()->count() + 1);
        $stable->forceFill(['approval_status' => $status])->save();

        if ($status === StableApprovalStatus::Approved) {
            $stable->forceFill(['commission_type' => FeeType::Percentage, 'commission_value' => 10])->save();
        }

        if ($owner) {
            $stable->members()->attach($owner->id, ['role' => 'owner']);
        }

        return $stable->refresh();
    }

    protected function makeOffering(Stable $stable, array $attributes = []): StableOffering
    {
        return StableOffering::create($attributes + [
            'stable_id' => $stable->id,
            'en_name' => 'Beginner lesson',
            'ar_name' => 'حصة للمبتدئين',
            'price' => 15,
            'duration_minutes' => 60,
            'capacity' => 4,
            'max_riders' => 2,
        ]);
    }

    /** @param  list<int>  $weekdays  Carbon day numbers (0 = Sunday) */
    protected function makeSchedule(StableOffering $offering, array $weekdays, array $times, array $attributes = []): StableSchedule
    {
        return StableSchedule::create($attributes + [
            'stable_id' => $offering->stable_id,
            'stable_offering_id' => $offering->id,
            'weekdays' => $weekdays,
            'start_times' => $times,
            'valid_from' => BookingSlot::today(),
        ]);
    }

    protected function book(BookingSlot $slot, int $riders, string $status = 'confirmed'): StableBooking
    {
        return StableBooking::create([
            'stable_id' => $slot->stable_id,
            'booking_slot_id' => $slot->id,
            'stable_offering_id' => $slot->stable_offering_id,
            'riders' => $riders,
            'status' => $status,
        ]);
    }
}
