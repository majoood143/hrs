<?php

namespace App\Models;

use App\Models\Concerns\LogsStableActivity;
use App\Services\Stables\BookingUnavailable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Something a customer adds to a booked session (photos, helmet hire, transport…), priced per booking
 * or per rider. Run by the stable's owner; admins switch the module (General Settings → Modules) and
 * each stable (admin → the stable). Its price becomes part of the booking order's price.
 */
class StableExtra extends Model
{
    use LogsStableActivity;
    use SoftDeletes;

    public const SETTING = 'module_extras_enabled';

    public const PER_BOOKING = 'per_booking';

    public const PER_RIDER = 'per_rider';

    protected $fillable = [
        'stable_id', 'en_name', 'ar_name', 'en_description', 'ar_description', 'photo',
        'price', 'pricing', 'max_quantity', 'is_active', 'sort',
    ];

    protected $attributes = [
        'pricing' => self::PER_BOOKING,
        'max_quantity' => 1,
        'is_active' => true,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:3',
            'max_quantity' => 'integer',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public static function enabled(): bool
    {
        return (bool) SiteSetting::get(self::SETTING, false);
    }

    /** Whether this stable's customers can add extras now. */
    public static function openFor(Stable $stable): bool
    {
        return self::enabled() && $stable->extras_enabled;
    }

    /** The active extras a booking of this service can take (none while switched off). */
    public static function forOffering(StableOffering $offering): Collection
    {
        if (! self::openFor($offering->stable)) {
            return new Collection;
        }

        return self::query()
            ->where('stable_id', $offering->stable_id)
            ->active()
            ->where(fn (Builder $q) => $q->whereDoesntHave('offerings')->orWhereHas('offerings', fn (Builder $o) => $o->whereKey($offering->getKey())))
            ->orderBy('sort')->orderBy('id')
            ->get();
    }

    /**
     * The extras chosen for a booking of this service (`[extra id => quantity]`, zeros dropped), each
     * checked: offered with this service now, and no more than its maximum.
     *
     * @return list<array{extra: self, quantity: int, amount: int}> amount in baisa
     *
     * @throws BookingUnavailable
     */
    public static function lines(StableOffering $offering, int $riders, mixed $chosen): array
    {
        $chosen = collect(is_array($chosen) ? $chosen : [])
            ->mapWithKeys(fn ($quantity, $id) => [(int) $id => (int) $quantity])
            ->filter(fn (int $quantity) => $quantity > 0);

        if ($chosen->isEmpty()) {
            return [];
        }

        $offered = self::forOffering($offering)->keyBy('id');
        $lines = [];

        foreach ($chosen as $id => $quantity) {
            $extra = $offered->get($id) ?? throw new BookingUnavailable(__('stable_extras.errors.unavailable'));

            if ($quantity > $extra->max_quantity) {
                throw new BookingUnavailable(__('stable_extras.errors.too_many', ['max' => $extra->max_quantity, 'name' => $extra->name]));
            }

            $lines[] = ['extra' => $extra, 'quantity' => $quantity, 'amount' => $extra->amountBaisa($quantity, $riders)];
        }

        return $lines;
    }

    /** @param  list<array{amount: int}>  $lines */
    public static function totalBaisa(array $lines): int
    {
        return array_sum(array_column($lines, 'amount'));
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function offerings(): BelongsToMany
    {
        return $this->belongsToMany(StableOffering::class, 'stable_extra_offering');
    }

    public function bookingLines(): HasMany
    {
        return $this->hasMany(StableBookingExtra::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' && filled($this->ar_name) ? $this->ar_name : (string) $this->en_name;
    }

    public function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar' && filled($this->ar_description) ? $this->ar_description : $this->en_description;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    public function priceBaisa(): int
    {
        return (int) round((float) $this->price * 1000);
    }

    /** What `$quantity` of it costs a booking of `$riders`, in baisa. */
    public function amountBaisa(int $quantity, int $riders): int
    {
        return $this->priceBaisa() * $quantity * ($this->pricing === self::PER_RIDER ? max(1, $riders) : 1);
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['en_name', 'ar_name', 'en_description', 'ar_description', 'photo', 'price', 'pricing', 'max_quantity', 'is_active'];
    }
}
