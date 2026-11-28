<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A phone that receives push notifications (an Expo push token; see App\Services\Push\ExpoPush):
 * booking alerts to the stable owner and the customer signed in on it, and campaigns. A phone the
 * app was removed from keeps its row with `uninstalled_at` (counted in the admin) and is left out of
 * every query by the "installed" scope; registering the same token again brings it back.
 */
class MobileDevice extends Model
{
    /** What campaigns are about; a phone takes all of them unless it turned some off in the app. */
    public const TOPICS = ['news', 'offers', 'marketplace', 'services', 'events', 'racing'];

    protected $fillable = [
        'expo_token', 'platform', 'app_version', 'locale', 'topics', 'enabled',
        'user_id', 'owner_token_id', 'customer_id', 'customer_token_id', 'last_seen_at', 'uninstalled_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('installed', fn (Builder $query) => $query->whereNull('uninstalled_at'));
    }

    /** The register endpoints' query: finds a removed phone too, so its token is brought back, not duplicated. */
    public static function anyToken(): Builder
    {
        return static::query()->withoutGlobalScope('installed');
    }

    /** Apple/Google no longer know these phones (DeviceNotRegistered): the app was removed. */
    public static function markUninstalled(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        static::query()->whereKey($ids)->update(['uninstalled_at' => now()]);
        PushSavedSearch::query()->whereIn('mobile_device_id', $ids)->delete();
    }

    protected function casts(): array
    {
        return ['topics' => 'array', 'enabled' => 'boolean', 'last_seen_at' => 'datetime', 'uninstalled_at' => 'datetime'];
    }

    /** The phones these stable owners/staff are signed in on (signed out = the token is gone). */
    public function scopeOwnersSignedIn(Builder $query, array $userIds): Builder
    {
        return $query->whereIn('user_id', $userIds)->whereNotNull('owner_token_id');
    }

    /** The phones this customer is signed in on. */
    public function scopeCustomerSignedIn(Builder $query, ?int $customerId): Builder
    {
        return $query->where('customer_id', $customerId)->whereNotNull('customer_token_id');
    }

    /** The phones a customer or stable owner is signed in on with one of these numbers (typed any way: PhoneNumber). */
    public function scopeSignedInWithPhones(Builder $query, array $phones): Builder
    {
        $numbers = collect($phones)->flatMap(fn ($p) => [PhoneNumber::normalize((string) $p), PhoneNumber::toAscii((string) $p)])->filter()->unique()->values()->all();

        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $c) => $c->whereNotNull('customer_token_id')->whereIn('customer_id', Customer::query()->whereIn('phone', $numbers)->select('id')))
            ->orWhere(fn (Builder $o) => $o->whereNotNull('owner_token_id')->whereIn('user_id', User::query()->whereIn('phone', $numbers)->select('id'))));
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PushDelivery::class);
    }
}
