<?php

namespace App\Models;

use App\Enums\FeeType;
use App\Enums\StableApprovalStatus;
use App\Models\Concerns\LogsStableActivity;
use App\Support\PhoneNumber;
use App\Support\StableBookingSettings;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A stable in the directory. Admins can create one directly (it starts approved); an owner who
 * registers one from the /stable panel runs it there, and it waits for an admin to approve it and
 * set its commission before it shows on the site. It is the tenant of the stable owner panel.
 *
 * approval_status, the commission and booking_settings are deliberately not fillable: they change
 * only through StableApproval (admins) and the owner's settings page.
 */
class Stable extends Model implements HasAvatar, HasName
{
    use LogsStableActivity;

    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    protected $fillable = [
        'en_name',
        'ar_name',
        'slug',
        'country_id',
        'region_id',
        'city_id',
        'address',
        'map_link',
        'en_description',
        'ar_description',
        'cover_photo',
        'gallery',
        'opening_hours',
        'is_active',
        'phone',
        'email',
    ];

    protected $casts = [
        'gallery' => 'array',
        'opening_hours' => 'array',
        'is_active' => 'boolean',
        'approval_status' => StableApprovalStatus::class,
        'approved_at' => 'datetime',
        'commission_type' => FeeType::class,
        'commission_value' => 'decimal:3',
        'booking_settings' => 'array',
    ];

    protected $attributes = [
        'approval_status' => 'approved',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(StableService::class, 'stable_stable_service');
    }

    /** The users who run this stable (role "owner" or "staff" on the pivot). */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'stable_user')->withPivot('role')->withTimestamps();
    }

    public function owners(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'owner');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(StableOffering::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(StableSchedule::class);
    }

    public function closures(): HasMany
    {
        return $this->hasMany(StableClosure::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(BookingSlot::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(StableBooking::class);
    }

    public function trainers(): HasMany
    {
        return $this->hasMany(StableTrainer::class);
    }

    public function horses(): HasMany
    {
        return $this->hasMany(StableHorse::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(StablePackage::class);
    }

    public function packagePurchases(): HasMany
    {
        return $this->hasMany(StablePackagePurchase::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(StableReview::class);
    }

    /** @return array{average: ?float, count: int} over the visible reviews */
    public function rating(): array
    {
        $row = $this->reviews()->visible()->selectRaw('avg(rating) as average, count(*) as total')->first();

        return [
            'average' => $row && $row->total ? round((float) $row->average, 1) : null,
            'count' => (int) ($row->total ?? 0),
        ];
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(StableSettlement::class);
    }

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(StablePaymentAccount::class);
    }

    /**
     * The account online payments go to: the stable's own, when it chose to take payments itself
     * and an admin approved the keys of the gateway it chose; else null (our merchant account).
     */
    public function activePaymentAccount(): ?StablePaymentAccount
    {
        $settings = $this->bookingSettings();

        if ($settings->paymentMode() !== 'own' || ! $settings->paymentGateway()) {
            return null;
        }

        $account = $this->paymentAccounts()->where('gateway', $settings->paymentGateway())->first();

        return $account?->isApproved() && $account->isComplete() ? $account : null;
    }

    /** Where to email the stable about its bookings: its alert address, its own, or its owners'. */
    public function alertEmails(): array
    {
        $email = $this->bookingSettings()->alertEmail() ?? (filter_var($this->email, FILTER_VALIDATE_EMAIL) ? $this->email : null);

        return $email ? [$email] : $this->owners()->pluck('email')->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->values()->all();
    }

    /** The number SMS and WhatsApp alerts go to: its alert number, its own, or its first owner's. */
    public function alertPhone(): ?string
    {
        return $this->bookingSettings()->alertPhone()
            ?? PhoneNumber::normalize($this->phone)
            ?? PhoneNumber::normalize($this->owners()->value('phone'));
    }

    /** The language messages to the stable go out in: its first owner's. */
    public function ownerLocale(): string
    {
        return (string) ($this->owners()->value('locale') ?: config('languages.default', 'en'));
    }

    /** Shown on the site: switched on by the stable and approved by the admins. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('approval_status', StableApprovalStatus::Approved->value);
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('approval_status', StableApprovalStatus::Pending->value);
    }

    public function isApproved(): bool
    {
        return $this->approval_status === StableApprovalStatus::Approved;
    }

    public function hasCommission(): bool
    {
        return $this->commission_type !== null && $this->commission_value !== null;
    }

    /** Whether customers can book it: shown on the site and with a commission agreed. */
    public function acceptsBookings(): bool
    {
        return $this->is_active && $this->isApproved() && $this->hasCommission();
    }

    public function bookingSettings(): StableBookingSettings
    {
        return StableBookingSettings::from($this->booking_settings);
    }

    public function getFormattedCommissionAttribute(): ?string
    {
        if (! $this->hasCommission()) {
            return null;
        }

        return $this->commission_type === FeeType::Percentage
            ? rtrim(rtrim(number_format((float) $this->commission_value, 3), '0'), '.').'%'
            : SiteSetting::formatCurrency((float) $this->commission_value, 3);
    }

    public function getFilamentName(): string
    {
        return (string) $this->name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->cover_photo_url;
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }

    public function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->ar_description : $this->en_description;
    }

    public function getCoverPhotoUrlAttribute(): ?string
    {
        return $this->cover_photo ? Storage::disk('public')->url($this->cover_photo) : null;
    }

    public function getGalleryUrlsAttribute(): array
    {
        return collect($this->gallery ?? [])
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->all();
    }

    /** @return list<string> */
    protected function stableActivityAttributes(): array
    {
        return ['en_name', 'ar_name', 'slug', 'country_id', 'region_id', 'city_id', 'address', 'map_link', 'en_description', 'ar_description', 'cover_photo', 'gallery', 'opening_hours', 'is_active', 'phone', 'email', 'approval_status', 'commission_type', 'commission_value', 'booking_settings', 'rejection_reason'];
    }
}
