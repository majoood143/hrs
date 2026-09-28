<?php

namespace App\Services\Stables;

use App\Enums\StableApprovalStatus;
use App\Events\StableRegistered;
use App\Models\Stable;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs up a stable owner with their first stable, or adds another stable to an owner. Either way
 * the stable starts pending (hidden from the site, no bookings) and the admins are told.
 */
class StableRegistration
{
    /**
     * @param  array{name: string, email: string, phone: string, password: string}  $owner  the phone already verified by SMS
     * @param  array<string, mixed>  $stable
     */
    public function register(array $owner, array $stable): User
    {
        [$user, $created] = DB::transaction(function () use ($owner, $stable): array {
            $user = User::create([
                'name' => $owner['name'],
                'email' => Str::lower(trim($owner['email'])),
                'phone' => PhoneNumber::normalize($owner['phone']),
                'password' => $owner['password'],
                'type' => User::TYPE_STABLE_OWNER,
                'locale' => app()->getLocale(),
            ]);
            $user->forceFill(['phone_verified_at' => now()])->save();

            return [$user, $this->createStable($user, $stable, notify: false)];
        });

        StableRegistered::dispatch($created, $user);

        return $user;
    }

    /** @param  array<string, mixed>  $data */
    public function createStable(User $owner, array $data, bool $notify = true): Stable
    {
        $stable = DB::transaction(function () use ($owner, $data): Stable {
            $stable = new Stable(collect($data)->only([
                'en_name', 'ar_name', 'country_id', 'region_id', 'city_id', 'address', 'map_link',
                'en_description', 'ar_description', 'phone', 'email',
            ])->all());

            $stable->slug = $this->uniqueSlug((string) $data['en_name']);
            $stable->is_active = true;
            $stable->phone = PhoneNumber::normalize($data['phone'] ?? null) ?? $owner->phone;
            $stable->email = filled($data['email'] ?? null) ? $data['email'] : $owner->email;
            $stable->forceFill(['approval_status' => StableApprovalStatus::Pending])->save();

            $stable->members()->attach($owner->getKey(), ['role' => 'owner']);

            return $stable;
        });

        if ($notify) {
            StableRegistered::dispatch($stable, $owner);
        }

        return $stable;
    }

    /** From the English name, like every slug here (never the Arabic one). */
    public function uniqueSlug(string $englishName): string
    {
        $base = Str::slug($englishName) ?: 'stable';
        $slug = $base;

        for ($i = 2; Stable::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
